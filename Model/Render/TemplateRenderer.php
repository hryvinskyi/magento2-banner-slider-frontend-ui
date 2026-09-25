<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\Render;

use Hryvinskyi\BannerSliderFrontendUi\Api\Render\TemplateRendererInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\LayoutInterface;

/**
 * Renders a template through an anonymous template block of the current layout, so theme overrides, the template
 * engine's `$escaper`/`$secureRenderer` and content security policy nonces all apply as for any block.
 */
class TemplateRenderer implements TemplateRendererInterface
{
    /**
     * @param LayoutInterface $layout
     */
    public function __construct(
        private readonly LayoutInterface $layout
    ) {
    }

    /**
     * @inheritDoc
     */
    public function render(string $template, array $variables = []): string
    {
        $block = $this->layout->createBlock(Template::class, '', ['data' => ['template' => $template]]);
        if (!$block instanceof Template) {
            return '';
        }

        foreach ($variables as $name => $value) {
            $block->assign($name, $value);
        }

        return $block->toHtml();
    }
}
