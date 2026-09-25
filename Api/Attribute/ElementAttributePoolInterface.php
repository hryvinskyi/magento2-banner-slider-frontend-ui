<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Api\Attribute;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\HtmlAttributes;

/**
 * The final attributes of the slider's elements: the element's own attributes with every provider's merged on top.
 *
 * @api
 */
interface ElementAttributePoolInterface
{
    /**
     * Attributes of the slider container
     *
     * @param SliderInterface $slider
     * @param list<BannerInterface> $banners
     * @param HtmlAttributes $base The container's own attributes
     * @return HtmlAttributes
     * @throws \InvalidArgumentException When a provider returns an attribute name that is not allowed
     */
    public function getContainerAttributes(
        SliderInterface $slider,
        array $banners,
        HtmlAttributes $base
    ): HtmlAttributes;

    /**
     * Attributes of a slide
     *
     * @param SliderInterface $slider
     * @param BannerInterface $banner
     * @param HtmlAttributes $base The slide's own attributes
     * @return HtmlAttributes
     * @throws \InvalidArgumentException When a provider returns an attribute name that is not allowed
     */
    public function getSlideAttributes(
        SliderInterface $slider,
        BannerInterface $banner,
        HtmlAttributes $base
    ): HtmlAttributes;

    /**
     * Attributes of a slide's link
     *
     * @param SliderInterface $slider
     * @param BannerInterface $banner
     * @param HtmlAttributes $base The link's own attributes
     * @return HtmlAttributes
     * @throws \InvalidArgumentException When a provider returns an attribute name that is not allowed
     */
    public function getLinkAttributes(
        SliderInterface $slider,
        BannerInterface $banner,
        HtmlAttributes $base
    ): HtmlAttributes;
}
