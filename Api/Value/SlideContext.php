<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Api\Value;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\PictureSource;

/**
 * Everything a slide renderer gets to render one banner as a slide.
 *
 * - `slider` and `banner`: the storefront read models, already checked for visibility and the active window;
 * - `position`: the zero-based index of the slide among the slides rendered so far (0 is the first visible slide);
 * - `pictureSources`: the banner's responsive sources, widest first; empty when it has no renderable crop;
 * - `loading`: how the slide's media should load, decided for its position.
 *
 * @api
 */
class SlideContext
{
    /**
     * @var list<PictureSource>
     */
    private readonly array $pictureSources;

    /**
     * @param SliderInterface $slider
     * @param BannerInterface $banner
     * @param int $position Zero or greater
     * @param list<PictureSource> $pictureSources Widest first
     * @param SlideLoading $loading
     * @throws \InvalidArgumentException When the position is negative
     */
    public function __construct(
        private readonly SliderInterface $slider,
        private readonly BannerInterface $banner,
        private readonly int $position,
        array $pictureSources,
        private readonly SlideLoading $loading
    ) {
        if ($position < 0) {
            throw new \InvalidArgumentException(
                sprintf('A slide position must be zero or greater, got %d.', $position)
            );
        }

        $this->pictureSources = $pictureSources;
    }

    /**
     * The slider the slide belongs to
     *
     * @return SliderInterface
     */
    public function getSlider(): SliderInterface
    {
        return $this->slider;
    }

    /**
     * The banner rendered as the slide
     *
     * @return BannerInterface
     */
    public function getBanner(): BannerInterface
    {
        return $this->banner;
    }

    /**
     * Zero-based position of the slide
     *
     * @return int
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Whether this is the first slide
     *
     * @return bool
     */
    public function isFirst(): bool
    {
        return $this->position === 0;
    }

    /**
     * The banner's responsive picture sources, widest first
     *
     * @return list<PictureSource>
     */
    public function getPictureSources(): array
    {
        return $this->pictureSources;
    }

    /**
     * How the slide's media loads
     *
     * @return SlideLoading
     */
    public function getLoading(): SlideLoading
    {
        return $this->loading;
    }
}
