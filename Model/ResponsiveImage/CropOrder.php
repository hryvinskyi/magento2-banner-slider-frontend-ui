<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\ResponsiveImage;

use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\ResponsiveImage\CropOrderInterface;

/**
 * Orders crops by their breakpoint's starting viewport width, widest first
 *
 * Crops of one banner usually share a sort order, so the breakpoint decides; the crop's own sort order
 * and then its id break ties, which keeps the result independent of the order the database returns rows in.
 */
class CropOrder implements CropOrderInterface
{
    /**
     * @inheritDoc
     */
    public function sort(array $crops): array
    {
        $crops = array_values($crops);

        usort($crops, static function (ResponsiveCropInterface $a, ResponsiveCropInterface $b): int {
            return [
                CropBreakpoint::fromCrop($b)->getMinWidth(),
                $a->getSortOrder() ?? 0,
                $a->getCropId() ?? 0,
            ] <=> [
                CropBreakpoint::fromCrop($a)->getMinWidth(),
                $b->getSortOrder() ?? 0,
                $b->getCropId() ?? 0,
            ];
        });

        return $crops;
    }
}
