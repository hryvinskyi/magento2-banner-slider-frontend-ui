<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Api\Value;

use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideLoading;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SlideLoading::class)]
class SlideLoadingTest extends TestCase
{
    /**
     * An eager, high-priority slide
     *
     * @return void
     */
    public function testEagerHighPriorityAttributes(): void
    {
        $loading = new SlideLoading(false, true);

        self::assertFalse($loading->isLazy());
        self::assertTrue($loading->isHighPriority());
        self::assertSame(
            ['loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async'],
            $loading->toImageAttributes()->toArray()
        );
        self::assertSame(['loading' => null], $loading->toFrameAttributes()->toArray());
    }

    /**
     * A lazy slide
     *
     * @return void
     */
    public function testLazyAttributes(): void
    {
        $loading = new SlideLoading(true, false);

        self::assertSame(
            ['loading' => 'lazy', 'fetchpriority' => null, 'decoding' => 'async'],
            $loading->toImageAttributes()->toArray()
        );
        self::assertSame(['loading' => 'lazy'], $loading->toFrameAttributes()->toArray());
    }

    /**
     * A lazy slide cannot be high priority
     *
     * @return void
     */
    public function testLazyHighPriorityIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SlideLoading(true, true);
    }
}
