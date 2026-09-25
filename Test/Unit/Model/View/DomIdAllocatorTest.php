<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\View;

use Hryvinskyi\BannerSliderFrontendUi\Model\View\DomIdAllocator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DomIdAllocator::class)]
class DomIdAllocatorTest extends TestCase
{
    /**
     * The first render keeps the historical id; later renders of the same slider are suffixed
     *
     * @return void
     */
    public function testFirstRenderKeepsTheHistoricalIdAndLaterOnesAreSuffixed(): void
    {
        $allocator = new DomIdAllocator();

        self::assertSame('banner-slider-2', $allocator->allocate(2));
        self::assertSame('banner-slider-5', $allocator->allocate(5));
        self::assertSame('banner-slider-2-2', $allocator->allocate(2));
        self::assertSame('banner-slider-2-3', $allocator->allocate(2));
        self::assertSame('banner-slider-2', $allocator->getSliderClass(2));
    }

    /**
     * Counters start over after the request
     *
     * @return void
     */
    public function testResetStartsOver(): void
    {
        $allocator = new DomIdAllocator();
        $allocator->allocate(2);
        $allocator->_resetState();

        self::assertSame('banner-slider-2', $allocator->allocate(2));
    }
}
