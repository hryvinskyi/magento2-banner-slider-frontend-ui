<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Api\View;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Builds the view of one render of a slider, which the slider template reads.
 *
 * Each call is one render: the view gets a DOM id no earlier render on the page has, so the same slider placed twice
 * on a page yields two distinct containers. A banner that cannot be rendered is left out of the view (and logged);
 * the other slides still render.
 *
 * @api
 */
interface SliderViewBuilderInterface
{
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
    public function build(SliderInterface $slider, array $banners): ?SliderView;
}
