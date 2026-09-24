<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\ResponsiveImage;

use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderFrontendUi\Model\ResponsiveImage\CropOrder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hryvinskyi\BannerSliderFrontendUi\Model\ResponsiveImage\CropOrder
 */
class CropOrderTest extends TestCase
{
    use CropFixtureTrait;

    /**
     * Crops sharing a sort order come out widest breakpoint first, whatever order they arrive in
     *
     * @return void
     */
    public function testWidestBreakpointComesFirstWhenSortOrdersTie(): void
    {
        $sorted = (new CropOrder())->sort(['mobile' => $this->mobileCrop(), 'desktop' => $this->desktopCrop()]);

        self::assertSame([6, 7], $this->cropIds($sorted));
    }

    /**
     * Crops at the same breakpoint follow their own sort order, then their id
     *
     * @return void
     */
    public function testSameBreakpointFollowsSortOrderThenId(): void
    {
        $sorted = (new CropOrder())->sort([
            $this->desktopCrop(['crop_id' => 3, 'sort_order' => 1]),
            $this->desktopCrop(['crop_id' => 9, 'sort_order' => 0]),
            $this->desktopCrop(['crop_id' => 2, 'sort_order' => 0]),
        ]);

        self::assertSame([2, 9, 3], $this->cropIds($sorted));
    }

    /**
     * Get the ids of the crops in order
     *
     * @param list<ResponsiveCropInterface> $crops
     * @return list<int|null>
     */
    private function cropIds(array $crops): array
    {
        return array_map(static fn (ResponsiveCropInterface $crop): ?int => $crop->getCropId(), $crops);
    }
}
