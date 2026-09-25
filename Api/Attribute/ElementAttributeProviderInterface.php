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

/**
 * Contributes HTML attributes to the slider container, its slides and the slide links.
 *
 * Providers are registered in the `providers` argument of the element attribute pool in `di.xml`. Values follow
 * the `HtmlAttributes` rules: a string or integer renders escaped, `true` renders a bare attribute, `false` or
 * `null` removes the attribute; classes are added to the element's own classes.
 *
 * @api
 */
interface ElementAttributeProviderInterface
{
    /**
     * Attributes for the slider container element
     *
     * @param SliderInterface $slider
     * @param list<BannerInterface> $banners
     * @return array<string,string|int|bool|null>
     */
    public function getContainerAttributes(SliderInterface $slider, array $banners): array;

    /**
     * Attributes for a slide element
     *
     * @param SliderInterface $slider
     * @param BannerInterface $banner
     * @return array<string,string|int|bool|null>
     */
    public function getSlideAttributes(SliderInterface $slider, BannerInterface $banner): array;

    /**
     * Attributes for a slide's link element
     *
     * @param SliderInterface $slider
     * @param BannerInterface $banner
     * @return array<string,string|int|bool|null>
     */
    public function getLinkAttributes(SliderInterface $slider, BannerInterface $banner): array;

    /**
     * Position among providers; lower applies first, so a higher one overrides it
     *
     * @return int
     */
    public function getSortOrder(): int;
}
