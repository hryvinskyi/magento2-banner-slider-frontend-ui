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
 * Interface for collecting and merging attributes from all registered providers
 *
 * @api
 */
interface ElementAttributePoolInterface
{
    /**
     * Get merged container attributes from all providers
     *
     * @param SliderInterface $slider
     * @param array<BannerInterface> $banners
     * @return array<string, string|bool|int>
     */
    public function getContainerAttributes(SliderInterface $slider, array $banners): array;

    /**
     * Get merged slide attributes from all providers
     *
     * @param SliderInterface $slider
     * @param BannerInterface $banner
     * @return array<string, string|bool|int>
     */
    public function getSlideAttributes(SliderInterface $slider, BannerInterface $banner): array;

    /**
     * Get merged link attributes from all providers
     *
     * @param SliderInterface $slider
     * @param BannerInterface $banner
     * @return array<string, string|bool|int>
     */
    public function getLinkAttributes(SliderInterface $slider, BannerInterface $banner): array;
}
