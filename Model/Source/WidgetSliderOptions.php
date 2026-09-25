<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Slider choices of the widget's optional slider field: an empty choice first, so a widget can leave the slider
 * unset and be placed by location instead, then every slider.
 */
class WidgetSliderOptions implements OptionSourceInterface
{
    /**
     * @param OptionSourceInterface $sliderOptions Every slider, valued by id (wired in `di.xml`)
     */
    public function __construct(
        private readonly OptionSourceInterface $sliderOptions
    ) {
    }

    /**
     * The empty choice, then every slider option
     *
     * @return list<array<mixed>>
     */
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('-- Use the location --')]];
        foreach ($this->sliderOptions->toOptionArray() as $option) {
            if (is_array($option)) {
                $options[] = $option;
            }
        }

        return $options;
    }
}
