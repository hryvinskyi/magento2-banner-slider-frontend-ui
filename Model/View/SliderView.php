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
use Hryvinskyi\BannerSliderApi\Api\Value\PictureSource;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\HtmlAttributes;

/**
 * Everything the slider template renders: the container, its slides and the pause control.
 */
class SliderView
{
    /**
     * @var list<SlideView>
     */
    private readonly array $slides;

    /**
     * @var array<int, list<PictureSource>>
     */
    private readonly array $pictureSources;

    /**
     * @param SliderInterface $slider
     * @param string $domId Page-unique id of the container
     * @param HtmlAttributes $containerAttributes
     * @param list<SlideView> $slides
     * @param bool $autoplay Whether the slides advance on their own, which calls for a pause control
     * @param string $pauseLabel Visible label of the pause control
     * @param array<int,list<PictureSource>> $pictureSources Picture sources by banner id
     * @throws \InvalidArgumentException When there is no slide
     */
    public function __construct(
        private readonly SliderInterface $slider,
        private readonly string $domId,
        private readonly HtmlAttributes $containerAttributes,
        array $slides,
        private readonly bool $autoplay,
        private readonly string $pauseLabel,
        array $pictureSources = []
    ) {
        if ($slides === []) {
            throw new \InvalidArgumentException('A slider view needs at least one slide.');
        }

        $this->slides = $slides;
        $this->pictureSources = $pictureSources;
    }

    /**
     * The slider rendered
     *
     * @return SliderInterface
     */
    public function getSlider(): SliderInterface
    {
        return $this->slider;
    }

    /**
     * Page-unique id of the container
     *
     * @return string
     */
    public function getDomId(): string
    {
        return $this->domId;
    }

    /**
     * Attributes of the container element
     *
     * @return HtmlAttributes
     */
    public function getContainerAttributes(): HtmlAttributes
    {
        return $this->containerAttributes;
    }

    /**
     * The rendered slides, in order
     *
     * @return list<SlideView>
     */
    public function getSlides(): array
    {
        return $this->slides;
    }

    /**
     * The banners of the rendered slides, in order
     *
     * @return list<BannerInterface>
     */
    public function getBanners(): array
    {
        return array_map(fn (SlideView $slide): BannerInterface => $slide->getBanner(), $this->slides);
    }

    /**
     * Whether the slider plays on its own and needs a pause control
     *
     * @return bool
     */
    public function hasPauseControl(): bool
    {
        return $this->autoplay;
    }

    /**
     * Visible label of the pause control
     *
     * @return string
     */
    public function getPauseLabel(): string
    {
        return $this->pauseLabel;
    }

    /**
     * Picture sources of the banners by banner id
     *
     * @return array<int,list<PictureSource>>
     */
    public function getPictureSources(): array
    {
        return $this->pictureSources;
    }
}
