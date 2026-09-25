<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\View;

use Hryvinskyi\BannerSliderFrontendUi\Model\View\SlideLoadingPolicy;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\StorefrontFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(SlideLoadingPolicy::class)]
class SlideLoadingPolicyTest extends TestCase
{
    use StorefrontFixtures;

    /**
     * The first slide is always eager and high priority; later slides follow the slider's lazy setting
     *
     * @param bool $sliderLazy
     * @param int $position
     * @param bool $lazy
     * @param bool $highPriority
     * @return void
     */
    #[TestWith([true, 0, false, true])]
    #[TestWith([false, 0, false, true])]
    #[TestWith([true, 1, true, false])]
    #[TestWith([false, 1, false, false])]
    #[TestWith([true, 5, true, false])]
    public function testLoading(bool $sliderLazy, int $position, bool $lazy, bool $highPriority): void
    {
        $loading = (new SlideLoadingPolicy())->forSlide($this->slider(['lazy' => $sliderLazy]), $position);

        self::assertSame($lazy, $loading->isLazy());
        self::assertSame($highPriority, $loading->isHighPriority());
    }
}
