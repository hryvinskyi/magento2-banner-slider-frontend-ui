<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\Render;

use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\ContentFilterInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\SlideRendererInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\TemplateRendererInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideContext;

/**
 * Renders custom HTML banners: the banner content with its CMS directives processed.
 *
 * Content that filters to nothing (empty, failed, or nested too deeply) renders no slide at all.
 */
class CustomSlideRenderer implements SlideRendererInterface
{
    /**
     * @param ContentFilterInterface $contentFilter
     * @param TemplateRendererInterface $templateRenderer
     * @param string $template Template of the slide
     */
    public function __construct(
        private readonly ContentFilterInterface $contentFilter,
        private readonly TemplateRendererInterface $templateRenderer,
        private readonly string $template = 'Hryvinskyi_BannerSliderFrontendUi::slide/custom.phtml'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function supports(BannerType $type): bool
    {
        return $type === BannerType::CUSTOM;
    }

    /**
     * @inheritDoc
     */
    public function render(SlideContext $context): string
    {
        $content = $this->contentFilter->filter($context->getBanner()->getContent());
        if (trim($content) === '') {
            return '';
        }

        return $this->templateRenderer->render($this->template, ['contentHtml' => $content]);
    }
}
