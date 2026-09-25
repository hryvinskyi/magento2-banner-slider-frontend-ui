<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\View;

use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideLoading;

/**
 * Decides how each slide's media loads.
 *
 * The first slide is what the visitor sees first and usually the page's largest paint, so it always loads eagerly
 * with high priority, whatever the slider's settings. The slider's lazy-load setting applies to the slides after
 * it: lazy when on, eager (without priority) when off.
 */
class SlideLoadingPolicy
{
    /**
     * Loading of the slide at a position
     *
     * @param SliderInterface $slider
     * @param int $position Zero-based slide position
     * @return SlideLoading
     */
    public function forSlide(SliderInterface $slider, int $position): SlideLoading
    {
        if ($position === 0) {
            return new SlideLoading(false, true);
        }

        return new SlideLoading($slider->isLazyLoadEnabled(), false);
    }
}
