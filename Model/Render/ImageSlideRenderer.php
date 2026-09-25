<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\Render;

use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Hryvinskyi\BannerSliderFrontendUi\Api\Attribute\ElementAttributePoolInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\ContentFilterInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\SlideRendererInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\TemplateRendererInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\HtmlAttributes;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideContext;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\ImageSlideView;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\PictureView;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\PictureViewBuilder;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Renders image banners: the responsive picture when the banner has renderable crops, otherwise its image. The
 * picture's `<img>`, shown where no crop's breakpoint applies, is the banner image.
 *
 * - The alternative text is the banner title; without a title the image is decorative (`alt=""`), never the
 *   banner's internal name.
 * - With a link URL the picture sits inside a link; a new-tab link gets `rel="noopener noreferrer"`. The URL's scheme
 *   was validated when the banner was saved, and it is escaped as a URL on output.
 * - A link always has an accessible name: with a title, the image's alternative text names it; without one the image
 *   is decorative, so the link is named by `aria-label`, the banner name (or a translated "Banner" when that is
 *   empty). An empty `alt` is only ever used where something else names the link.
 * - The banner content, filtered, is shown over the picture as a sibling after the link, so it never nests inside
 *   the link.
 */
class ImageSlideRenderer implements SlideRendererInterface
{
    /**
     * @param PictureViewBuilder $pictureViewBuilder
     * @param TemplateRendererInterface $templateRenderer
     * @param ContentFilterInterface $contentFilter
     * @param ElementAttributePoolInterface $attributePool
     * @param string $pictureTemplate Template of the picture or image
     * @param string $slideTemplate Template of the slide around the picture
     */
    public function __construct(
        private readonly PictureViewBuilder $pictureViewBuilder,
        private readonly TemplateRendererInterface $templateRenderer,
        private readonly ContentFilterInterface $contentFilter,
        private readonly ElementAttributePoolInterface $attributePool,
        private readonly string $pictureTemplate = 'Hryvinskyi_BannerSliderFrontendUi::slide/picture.phtml',
        private readonly string $slideTemplate = 'Hryvinskyi_BannerSliderFrontendUi::slide/image.phtml'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function supports(BannerType $type): bool
    {
        return $type === BannerType::IMAGE;
    }

    /**
     * @inheritDoc
     */
    public function render(SlideContext $context): string
    {
        $banner = $context->getBanner();

        return $this->templateRenderer->render($this->slideTemplate, [
            'view' => new ImageSlideView(
                $this->templateRenderer->render($this->pictureTemplate, ['view' => $this->picture($context)]),
                $this->link($context),
                $this->contentFilter->filter($banner->getContent())
            ),
        ]);
    }

    /**
     * The picture of the slide
     *
     * @param SlideContext $context
     * @return PictureView
     * @throws \InvalidArgumentException When the banner has nothing to show or a stored path is unsafe
     * @throws NoSuchEntityException When the current store cannot be resolved
     */
    private function picture(SlideContext $context): PictureView
    {
        $banner = $context->getBanner();
        $alt = trim((string)$banner->getTitle());
        $sources = $context->getPictureSources();
        if ($sources !== []) {
            return $this->pictureViewBuilder->fromSources(
                $sources,
                $banner->getImage(),
                $banner->getImageDimensions(),
                $alt,
                $context->getLoading()
            );
        }

        $image = $banner->getImage();
        if ($image === null || $image === '') {
            throw new \InvalidArgumentException(
                sprintf('Image banner %d has neither a responsive crop nor an image.', (int)$banner->getBannerId())
            );
        }

        return $this->pictureViewBuilder->fromImage(
            $image,
            $banner->getImageDimensions(),
            $alt,
            $context->getLoading()
        );
    }

    /**
     * Attributes of the link around the picture, or null when the banner has no link
     *
     * @param SlideContext $context
     * @return HtmlAttributes|null
     */
    private function link(SlideContext $context): ?HtmlAttributes
    {
        $banner = $context->getBanner();
        $url = trim((string)$banner->getLinkUrl());
        if ($url === '') {
            return null;
        }

        $title = trim((string)$banner->getTitle());
        $newTab = $banner->isOpenInNewTab();
        $base = new HtmlAttributes([
            'class' => 'hbs-slide__link',
            'href' => $url,
            'title' => $title !== '' ? $title : null,
            'aria-label' => $title !== '' ? null : $this->linkName($context),
            'target' => $newTab ? '_blank' : null,
            'rel' => $newTab ? 'noopener noreferrer' : null,
        ]);

        return $this->attributePool->getLinkAttributes($context->getSlider(), $banner, $base);
    }

    /**
     * Name of a link whose image is decorative: the banner name, or a generic label when it has none
     *
     * @param SlideContext $context
     * @return string
     */
    private function linkName(SlideContext $context): string
    {
        $name = trim($context->getBanner()->getName());

        return $name !== '' ? $name : (string)__('Banner');
    }
}
