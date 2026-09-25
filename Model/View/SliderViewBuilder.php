<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\View;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Picture\PictureSourcesProviderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\PictureSource;
use Hryvinskyi\BannerSliderFrontendUi\Api\Attribute\ElementAttributePoolInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\SlideRendererInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\HtmlAttributes;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideContext;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * Builds the view of one slider render from the slider and its visible banners.
 *
 * The picture sources of every banner are read in one call. Each banner is rendered by the slide renderer for its
 * type; a banner that cannot be rendered (an unsafe stored media path, a video source no provider understands, a
 * stored value that fails validation) is logged with its id and left out, and the remaining slides still render.
 * Slide positions, and so the eager first slide, count only the slides that rendered.
 *
 * The container carries:
 * - the classes `hbs-slider` and `banner-slider-{id}` and a page-unique id (see `DomIdAllocator`);
 * - `data-hbs-slider`, the marker scripts mount on;
 * - `data-hbs-config`: `{splide, pauseLabel, playLabel, videoPlayLabel, videoPauseLabel, hasVideo, backgroundVideo}`;
 * - `data-hbs-assets`: the script URLs a page without a module loader injects;
 * - `data-mage-init` for pages with a module loader;
 * - `role="region"`, `aria-roledescription` and `aria-label` (the slider name).
 */
class SliderViewBuilder
{
    private const MODULE_LOADER_COMPONENT = 'Hryvinskyi_BannerSliderFrontendUi/js/banner-slider-requirejs';

    /**
     * @param PictureSourcesProviderInterface $pictureSourcesProvider
     * @param SlideRendererInterface $slideRenderer
     * @param SlideLoadingPolicy $loadingPolicy
     * @param ElementAttributePoolInterface $attributePool
     * @param SplideConfigBuilder $splideConfigBuilder
     * @param JsonAttributeEncoder $jsonEncoder
     * @param FrontendAssets $frontendAssets
     * @param DomIdAllocator $domIdAllocator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PictureSourcesProviderInterface $pictureSourcesProvider,
        private readonly SlideRendererInterface $slideRenderer,
        private readonly SlideLoadingPolicy $loadingPolicy,
        private readonly ElementAttributePoolInterface $attributePool,
        private readonly SplideConfigBuilder $splideConfigBuilder,
        private readonly JsonAttributeEncoder $jsonEncoder,
        private readonly FrontendAssets $frontendAssets,
        private readonly DomIdAllocator $domIdAllocator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * The view of a slider with its banners, or null when none of the banners renders
     *
     * @param SliderInterface $slider
     * @param list<BannerInterface> $banners Visible banners, in display order
     * @return SliderView|null
     * @throws \InvalidArgumentException When an attribute provider returns a name that is not allowed
     * @throws LocalizedException When an asset URL cannot be resolved
     * @throws \JsonException When the configuration cannot be encoded
     */
    public function build(SliderInterface $slider, array $banners): ?SliderView
    {
        $pictures = $this->pictureSourcesProvider->getForBanners($this->bannerIds($banners));

        $slides = [];
        foreach ($banners as $banner) {
            $sources = $pictures[(int)$banner->getBannerId()] ?? [];
            $slide = $this->buildSlide($slider, $banner, count($slides), $sources);
            if ($slide !== null) {
                $slides[] = $slide;
            }
        }

        if ($slides === []) {
            return null;
        }

        $sliderId = (int)$slider->getSliderId();
        $domId = $this->domIdAllocator->allocate($sliderId);
        $config = $this->config($slider, $slides);
        $autoplay = ($config['splide']['autoplay'] ?? false) === true;
        $renderedBanners = array_map(fn (SlideView $slide): BannerInterface => $slide->getBanner(), $slides);

        $base = new HtmlAttributes([
            'class' => 'hbs-slider ' . $this->domIdAllocator->getSliderClass($sliderId),
            'id' => $domId,
            'data-hbs-slider' => true,
            'data-hbs-config' => $this->jsonEncoder->encode($config),
            'data-hbs-assets' => $this->jsonEncoder->encode($this->frontendAssets->getScriptUrls()),
            'data-mage-init' => $this->jsonEncoder->encode([self::MODULE_LOADER_COMPONENT => new \stdClass()]),
            'role' => 'region',
            'aria-roledescription' => (string)__('carousel'),
            'aria-label' => $slider->getName(),
        ]);

        return new SliderView(
            $slider,
            $domId,
            $this->attributePool->getContainerAttributes($slider, $renderedBanners, $base),
            $slides,
            $autoplay,
            (string)__('Pause autoplay'),
            $pictures
        );
    }

    /**
     * One slide, or null when the banner cannot be rendered
     *
     * @param SliderInterface $slider
     * @param BannerInterface $banner
     * @param int $position
     * @param list<PictureSource> $pictureSources
     * @return SlideView|null
     */
    private function buildSlide(
        SliderInterface $slider,
        BannerInterface $banner,
        int $position,
        array $pictureSources
    ): ?SlideView {
        try {
            $html = $this->slideRenderer->render(new SlideContext(
                $slider,
                $banner,
                $position,
                $pictureSources,
                $this->loadingPolicy->forSlide($slider, $position)
            ));
            if (trim($html) === '') {
                return null;
            }

            $base = new HtmlAttributes([
                'class' => 'splide__slide hbs-slide',
                'data-hbs-banner-id' => (int)$banner->getBannerId(),
            ]);

            return new SlideView($banner, $this->attributePool->getSlideAttributes($slider, $banner, $base), $html);
        } catch (\InvalidArgumentException | LocalizedException $e) {
            $this->logger->warning('Banner slider: a banner could not be rendered and was left out.', [
                'slider_id' => $slider->getSliderId(),
                'banner_id' => $banner->getBannerId(),
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Configuration read by the slider scripts
     *
     * @param SliderInterface $slider
     * @param non-empty-list<SlideView> $slides
     * @return array{splide: array<string,mixed>, pauseLabel: string, playLabel: string, videoPlayLabel: string,
     *     videoPauseLabel: string, hasVideo: bool, backgroundVideo: bool}
     */
    private function config(SliderInterface $slider, array $slides): array
    {
        $hasVideo = false;
        $backgroundVideo = false;
        foreach ($slides as $slide) {
            $banner = $slide->getBanner();
            if ($banner->getType()->requiresVideo()) {
                $hasVideo = true;
                $backgroundVideo = $backgroundVideo || $banner->isVideoAsBackground();
            }
        }

        return [
            'splide' => $this->splideConfigBuilder->build($slider, count($slides), $hasVideo),
            'pauseLabel' => (string)__('Pause autoplay'),
            'playLabel' => (string)__('Start autoplay'),
            'videoPlayLabel' => (string)__('Play background video'),
            'videoPauseLabel' => (string)__('Pause background video'),
            'hasVideo' => $hasVideo,
            'backgroundVideo' => $backgroundVideo,
        ];
    }

    /**
     * Ids of the banners
     *
     * @param list<BannerInterface> $banners
     * @return list<int>
     */
    private function bannerIds(array $banners): array
    {
        $ids = [];
        foreach ($banners as $banner) {
            $bannerId = $banner->getBannerId();
            if ($bannerId !== null) {
                $ids[] = $bannerId;
            }
        }

        return $ids;
    }
}
