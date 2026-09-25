<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\View;

use Hryvinskyi\BannerSliderApi\Api\Value\ResponsiveItem;
use Hryvinskyi\BannerSliderApi\Api\Value\SlideEffect;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\SplideConfigBuilder;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\StorefrontFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(SplideConfigBuilder::class)]
class SplideConfigBuilderTest extends TestCase
{
    use StorefrontFixtures;

    /**
     * Type and rewind: fade and slide rewind so autoplay never stops at the last slide; loop does not need it;
     * a slider with video never loops
     *
     * @param string $effect
     * @param bool $loop
     * @param bool $hasVideo
     * @param string $type
     * @param bool $rewind
     * @return void
     */
    #[TestWith(['fade', true, false, 'fade', true])]
    #[TestWith(['fade', false, false, 'fade', true])]
    #[TestWith(['slide', true, false, 'loop', false])]
    #[TestWith(['slide', false, false, 'slide', true])]
    #[TestWith(['slide', true, true, 'slide', true])]
    public function testTypeAndRewind(string $effect, bool $loop, bool $hasVideo, string $type, bool $rewind): void
    {
        $slider = $this->slider(['effect' => SlideEffect::from($effect), 'loop' => $loop]);

        $config = (new SplideConfigBuilder(400))->build($slider, 3, $hasVideo);

        self::assertSame($type, $config['type']);
        self::assertSame($rewind, $config['rewind']);
    }

    /**
     * Responsive items become min-width breakpoints keyed by width, encoded as a JSON object
     *
     * @return void
     */
    public function testMinWidthBreakpoints(): void
    {
        $slider = $this->slider(['items' => [new ResponsiveItem(0, 1, null), new ResponsiveItem(768, 3, '12px')]]);

        $config = (new SplideConfigBuilder(400))->build($slider, 3, false);

        self::assertSame('min', $config['mediaQuery']);
        self::assertSame(
            '{"0":{"perPage":1},"768":{"perPage":3,"gap":"12px"}}',
            json_encode($config['breakpoints'], JSON_THROW_ON_ERROR)
        );
    }

    /**
     * A single breakpoint at width 0 and no breakpoints both stay JSON objects
     *
     * @return void
     */
    public function testBreakpointsAlwaysEncodeAsAnObject(): void
    {
        $builder = new SplideConfigBuilder(400);

        $single = $builder->build($this->slider(['items' => [new ResponsiveItem(0, 2, null)]]), 3, false);
        $none = $builder->build($this->slider(['items' => []]), 3, false);

        self::assertSame('{"0":{"perPage":2}}', json_encode($single['breakpoints'], JSON_THROW_ON_ERROR));
        self::assertSame('{}', json_encode($none['breakpoints'], JSON_THROW_ON_ERROR));
    }

    /**
     * Playback options follow the slider; the interval is always an integer; speed comes from configuration
     *
     * @return void
     */
    public function testPlaybackOptions(): void
    {
        $slider = $this->slider(['autoplay' => true, 'interval' => 7000, 'nav' => false, 'dots' => true]);

        $config = (new SplideConfigBuilder(650))->build($slider, 3, false);

        self::assertTrue($config['autoplay']);
        self::assertSame(7000, $config['interval']);
        self::assertFalse($config['arrows']);
        self::assertTrue($config['pagination']);
        self::assertSame(650, $config['speed']);
        self::assertFalse($config['lazyLoad']);
        self::assertTrue($config['pauseOnHover']);
        self::assertTrue($config['pauseOnFocus']);
        self::assertSame(1, $config['perPage']);
        self::assertSame('', $config['role']);
        self::assertArrayNotHasKey('label', $config);
    }

    /**
     * A single slide never plays, and shows neither arrows nor pagination
     *
     * @return void
     */
    public function testSingleSlideDoesNotMove(): void
    {
        $config = (new SplideConfigBuilder(400))->build($this->slider(), 1, false);

        self::assertFalse($config['autoplay']);
        self::assertFalse($config['arrows']);
        self::assertFalse($config['pagination']);
        self::assertSame(5000, $config['interval']);
    }

    /**
     * Every label Splide announces is present and translatable
     *
     * @return void
     */
    public function testLabels(): void
    {
        $config = (new SplideConfigBuilder(400))->build($this->slider(), 2, false);

        self::assertIsArray($config['i18n']);
        self::assertSame(
            ['prev', 'next', 'first', 'last', 'slideX', 'pageX', 'play', 'pause', 'carousel', 'select', 'slide',
                'slideLabel'],
            array_keys($config['i18n'])
        );
        self::assertSame('Go to slide %s', $config['i18n']['slideX']);
        self::assertSame('%s of %s', $config['i18n']['slideLabel']);
        self::assertSame('', $config['i18n']['carousel']);
    }
}
