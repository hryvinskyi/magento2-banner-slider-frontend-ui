<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Api\Head;

use Hryvinskyi\BannerSliderFrontendUi\Api\View\SliderView;
use Magento\Framework\Exception\LocalizedException;

/**
 * Adds what a rendered slider needs to the page head (stylesheets, preload links of its leading images, its custom
 * CSS), before the slider's template renders.
 *
 * Several sliders on a page, or the same slider placed twice, add each shared element once.
 *
 * @api
 */
interface HeadAssetRegistrarInterface
{
    /**
     * Register the head elements of a rendered slider
     *
     * @param SliderView $view
     * @return void
     * @throws LocalizedException When a stylesheet URL cannot be resolved
     */
    public function register(SliderView $view): void;
}
