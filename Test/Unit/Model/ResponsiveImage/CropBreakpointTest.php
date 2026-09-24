<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\ResponsiveImage;

use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderFrontendUi\Model\ResponsiveImage\CropBreakpoint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hryvinskyi\BannerSliderFrontendUi\Model\ResponsiveImage\CropBreakpoint
 */
class CropBreakpointTest extends TestCase
{
    use CropFixtureTrait;

    /**
     * The joined breakpoint columns become the breakpoint
     *
     * @return void
     */
    public function testReadsTheJoinedBreakpoint(): void
    {
        $breakpoint = CropBreakpoint::fromCrop($this->mobileCrop());

        self::assertSame('(max-width: 767px)', $breakpoint->getMediaQuery());
        self::assertSame(0, $breakpoint->getMinWidth());
        self::assertSame(892, $breakpoint->getWidth());
        self::assertSame(588, $breakpoint->getHeight());
        self::assertTrue($breakpoint->hasDimensions());
    }

    /**
     * A crop loaded without its breakpoint matches every viewport and has no size
     *
     * @return void
     */
    public function testCropWithoutBreakpointMatchesEveryViewport(): void
    {
        $breakpoint = CropBreakpoint::fromCrop($this->crop(['crop_id' => 1, 'cropped_image' => 'a.jpg']));

        self::assertSame(CropBreakpoint::MATCH_ALL_MEDIA_QUERY, $breakpoint->getMediaQuery());
        self::assertSame(0, $breakpoint->getMinWidth());
        self::assertFalse($breakpoint->hasDimensions());
    }

    /**
     * A crop that is not a data object carries no breakpoint at all
     *
     * @return void
     */
    public function testCropContractAloneMatchesEveryViewport(): void
    {
        $breakpoint = CropBreakpoint::fromCrop($this->createMock(ResponsiveCropInterface::class));

        self::assertSame(CropBreakpoint::MATCH_ALL_MEDIA_QUERY, $breakpoint->getMediaQuery());
        self::assertFalse($breakpoint->hasDimensions());
    }

    /**
     * A known width without a height is not a usable size
     *
     * @return void
     */
    public function testWidthWithoutHeightHasNoDimensions(): void
    {
        self::assertFalse((new CropBreakpoint('(min-width: 768px)', 768, 1920, 0))->hasDimensions());
    }

    /**
     * An empty media query is refused
     *
     * @return void
     */
    public function testRejectsEmptyMediaQuery(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CropBreakpoint(' ', 0, 0, 0);
    }

    /**
     * A negative size is refused
     *
     * @return void
     */
    public function testRejectsNegativeSize(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CropBreakpoint('(min-width: 768px)', 768, -1, 294);
    }
}
