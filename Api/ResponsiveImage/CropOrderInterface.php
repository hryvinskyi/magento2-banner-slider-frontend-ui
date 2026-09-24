<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Api\ResponsiveImage;

use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;

/**
 * Orders a banner's responsive crops for rendering
 */
interface CropOrderInterface
{
    /**
     * Order crops from the widest breakpoint to the narrowest
     *
     * @param array<ResponsiveCropInterface> $crops
     * @return list<ResponsiveCropInterface>
     */
    public function sort(array $crops): array;
}
