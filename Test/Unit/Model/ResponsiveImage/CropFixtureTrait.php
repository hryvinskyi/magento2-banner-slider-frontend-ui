<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\ResponsiveImage;

use Hryvinskyi\BannerSlider\Model\ResponsiveCrop;

/**
 * Builds responsive crops the way the crop repository returns them: crop columns plus the joined breakpoint
 */
trait CropFixtureTrait
{
    /**
     * Create a crop holding the given row data
     *
     * @param array<string, mixed> $data
     * @return ResponsiveCrop
     */
    private function crop(array $data): ResponsiveCrop
    {
        $crop = $this->getMockBuilder(ResponsiveCrop::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $crop->setData($data);

        return $crop;
    }

    /**
     * Create the desktop crop of the homepage slider
     *
     * @param array<string, mixed> $data
     * @return ResponsiveCrop
     */
    private function desktopCrop(array $data = []): ResponsiveCrop
    {
        return $this->crop($data + [
            'crop_id' => 6,
            'cropped_image' => 'banner/desktop.jpg',
            'sort_order' => 0,
            'media_query' => '(min-width: 768px)',
            'min_width' => '768',
            'target_width' => '1920',
            'target_height' => '294',
        ]);
    }

    /**
     * Create the mobile crop of the homepage slider
     *
     * @param array<string, mixed> $data
     * @return ResponsiveCrop
     */
    private function mobileCrop(array $data = []): ResponsiveCrop
    {
        return $this->crop($data + [
            'crop_id' => 7,
            'cropped_image' => 'banner/mobile.jpg',
            'sort_order' => 0,
            'media_query' => '(max-width: 767px)',
            'min_width' => '0',
            'target_width' => '892',
            'target_height' => '588',
        ]);
    }
}
