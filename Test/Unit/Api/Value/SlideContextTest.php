<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Api\Value;

use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideContext;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideLoading;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\StorefrontFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SlideContext::class)]
class SlideContextTest extends TestCase
{
    use StorefrontFixtures;

    /**
     * The context exposes what it was built with
     *
     * @return void
     */
    public function testExposesItsParts(): void
    {
        $slider = $this->slider();
        $banner = $this->banner();
        $source = $this->pictureSource('desktop', '(min-width: 768px)', 1920, 600);
        $loading = new SlideLoading(false, true);

        $context = new SlideContext($slider, $banner, 0, [$source], $loading);

        self::assertSame($slider, $context->getSlider());
        self::assertSame($banner, $context->getBanner());
        self::assertSame(0, $context->getPosition());
        self::assertTrue($context->isFirst());
        self::assertSame([$source], $context->getPictureSources());
        self::assertSame($loading, $context->getLoading());
        self::assertFalse((new SlideContext($slider, $banner, 3, [], $loading))->isFirst());
    }

    /**
     * A negative position is rejected
     *
     * @return void
     */
    public function testNegativePositionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SlideContext($this->slider(), $this->banner(), -1, [], new SlideLoading(true, false));
    }
}
