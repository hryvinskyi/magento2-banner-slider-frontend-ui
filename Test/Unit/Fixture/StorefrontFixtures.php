<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture;

use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\AspectRatio;
use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointSpec;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFile;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Hryvinskyi\BannerSliderApi\Api\Value\PictureSource;
use Hryvinskyi\BannerSliderApi\Api\Value\ResponsiveItem;
use Hryvinskyi\BannerSliderApi\Api\Value\SlideEffect;
use Hryvinskyi\BannerSliderFrontendUi\Model\Head\EnabledBreakpointReader;
use Magento\Framework\Escaper;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Storefront read models built from the published contracts and value objects, and a predictable escaper.
 */
trait StorefrontFixtures
{
    /**
     * A slider read model
     *
     * @param array{id?: int, name?: string, effect?: SlideEffect, loop?: bool, lazy?: bool, autoplay?: bool,
     *     autoplayToggle?: bool, interval?: int, nav?: bool, dots?: bool, items?: list<ResponsiveItem>,
     *     preload?: int, css?: string|null} $data
     * @return SliderInterface&MockObject
     */
    private function slider(array $data = []): SliderInterface
    {
        $slider = $this->createMock(SliderInterface::class);
        $slider->method('getSliderId')->willReturn($data['id'] ?? 7);
        $slider->method('getName')->willReturn($data['name'] ?? 'Homepage');
        $slider->method('getEffect')->willReturn($data['effect'] ?? SlideEffect::SLIDE);
        $slider->method('isLoopEnabled')->willReturn($data['loop'] ?? false);
        $slider->method('isLazyLoadEnabled')->willReturn($data['lazy'] ?? true);
        $slider->method('isAutoPlayEnabled')->willReturn($data['autoplay'] ?? true);
        $slider->method('isAutoPlayToggleEnabled')->willReturn($data['autoplayToggle'] ?? true);
        $slider->method('getAutoPlayInterval')->willReturn($data['interval'] ?? 5000);
        $slider->method('isNavigationEnabled')->willReturn($data['nav'] ?? true);
        $slider->method('isPaginationEnabled')->willReturn($data['dots'] ?? true);
        $slider->method('isAutoWidthEnabled')->willReturn(false);
        $slider->method('isAutoHeightEnabled')->willReturn(false);
        $slider->method('getResponsiveItems')->willReturn($data['items'] ?? []);
        $slider->method('getPreloadBannersCount')->willReturn($data['preload'] ?? 1);
        $slider->method('getCustomCss')->willReturn($data['css'] ?? null);

        return $slider;
    }

    /**
     * A banner read model
     *
     * @param array{id?: int, name?: string, type?: BannerType, title?: string|null, image?: string|null,
     *     dimensions?: Dimensions|null, link?: string|null, newTab?: bool, content?: string|null,
     *     videoUrl?: string|null, videoPath?: string|null, background?: bool, ratio?: AspectRatio,
     *     preload?: bool} $data
     * @return BannerInterface&MockObject
     */
    private function banner(array $data = []): BannerInterface
    {
        $banner = $this->createMock(BannerInterface::class);
        $banner->method('getBannerId')->willReturn($data['id'] ?? 11);
        $banner->method('getType')->willReturn($data['type'] ?? BannerType::IMAGE);
        $banner->method('getTitle')->willReturn($data['title'] ?? null);
        $banner->method('getName')->willReturn($data['name'] ?? 'Internal banner name');
        $banner->method('getImage')->willReturn($data['image'] ?? null);
        $banner->method('getImageDimensions')->willReturn($data['dimensions'] ?? null);
        $banner->method('getLinkUrl')->willReturn($data['link'] ?? null);
        $banner->method('isOpenInNewTab')->willReturn($data['newTab'] ?? false);
        $banner->method('getContent')->willReturn($data['content'] ?? null);
        $banner->method('getVideoUrl')->willReturn($data['videoUrl'] ?? null);
        $banner->method('getVideoPath')->willReturn($data['videoPath'] ?? null);
        $banner->method('isVideoAsBackground')->willReturn($data['background'] ?? false);
        $banner->method('getVideoAspectRatio')->willReturn($data['ratio'] ?? new AspectRatio(16, 9));
        $banner->method('isPreloadEnabled')->willReturn($data['preload'] ?? false);

        return $banner;
    }

    /**
     * A picture source with a WebP variant and a JPEG original
     *
     * @param string $identifier
     * @param string $mediaQuery
     * @param int $width
     * @param int $height
     * @param int|null $minWidth Min width of the breakpoint; by default 768 for a crop wider than 1000, else 0
     * @return PictureSource
     */
    private function pictureSource(
        string $identifier,
        string $mediaQuery,
        int $width,
        int $height,
        ?int $minWidth = null
    ): PictureSource {
        $path = 'banner_slider/responsive/11/' . $identifier;

        return new PictureSource(
            new BreakpointSpec($identifier, $mediaQuery, $minWidth ?? ($width > 1000 ? 768 : 0), $width, null),
            new Dimensions($width, $height),
            [
                new ImageFile($path . '.webp', new ImageFormat('webp', 'image/webp', 'webp')),
                new ImageFile($path . '.jpg', new ImageFormat('jpeg', 'image/jpeg', 'jpg')),
            ]
        );
    }

    /**
     * A breakpoint reader whose every slider has the given enabled breakpoints
     *
     * @param list<BreakpointSpec> $breakpoints Widest first
     * @return EnabledBreakpointReader
     */
    private function breakpointReader(array $breakpoints = []): EnabledBreakpointReader
    {
        $stored = [];
        foreach ($breakpoints as $spec) {
            $breakpoint = $this->createMock(BreakpointInterface::class);
            $breakpoint->method('toSpec')->willReturn($spec);
            $stored[] = $breakpoint;
        }
        $repository = $this->createMock(BreakpointRepositoryInterface::class);
        $repository->method('getBySliderId')->willReturn($stored);

        return new EnabledBreakpointReader($repository);
    }

    /**
     * An escaper that escapes attribute values predictably and marks URL escaping
     *
     * @return Escaper&MockObject
     */
    private function escaper(): Escaper
    {
        $map = ['&' => '&amp;', '"' => '&quot;', "'" => '&#039;', '<' => '&lt;', '>' => '&gt;'];
        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtmlAttr')->willReturnCallback(fn (string $value): string => strtr($value, $map));
        $escaper->method('escapeUrl')->willReturnCallback(fn (string $value): string => 'url:' . strtr($value, $map));

        return $escaper;
    }
}
