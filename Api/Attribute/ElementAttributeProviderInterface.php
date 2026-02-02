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
 * Interface for providing custom HTML attributes to slider elements
 *
 * @api
 */
interface ElementAttributeProviderInterface
{
    /**
     * Get attributes for the slider container element
     *
     * @param SliderInterface $slider
     * @param array<BannerInterface> $banners
     * @return array<string, string|bool|int>
     */
    public function getContainerAttributes(SliderInterface $slider, array $banners): array;

    /**
     * Get attributes for individual slide elements
     *
     * @param SliderInterface $slider
     * @param BannerInterface $banner
     * @return array<string, string|bool|int>
     */
    public function getSlideAttributes(SliderInterface $slider, BannerInterface $banner): array;

    /**
     * Get attributes for banner link elements
     *
     * @param SliderInterface $slider
     * @param BannerInterface $banner
     * @return array<string, string|bool|int>
     */
    public function getLinkAttributes(SliderInterface $slider, BannerInterface $banner): array;

    /**
     * Get sort order for determining provider execution priority
     *
     * @return int
     */
    public function getSortOrder(): int;
}
