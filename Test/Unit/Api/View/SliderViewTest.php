<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Api\View;

use Hryvinskyi\BannerSliderFrontendUi\Api\Value\HtmlAttributes;
use Hryvinskyi\BannerSliderFrontendUi\Api\View\SliderView;
use Hryvinskyi\BannerSliderFrontendUi\Api\View\SlideView;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\StorefrontFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(SliderView::class)]
#[CoversClass(SlideView::class)]
class SliderViewTest extends TestCase
{
    use StorefrontFixtures;

    /**
     * The view exposes what it was built with, and the banners of its slides in order
     *
     * @return void
     */
    public function testExposesItsParts(): void
    {
        $slider = $this->slider();
        $first = $this->banner(['id' => 21]);
        $second = $this->banner(['id' => 22]);
        $attributes = new HtmlAttributes(['class' => 'hbs-slider']);
        $slideAttributes = new HtmlAttributes(['class' => 'hbs-slide']);
        $slides = [new SlideView($first, $slideAttributes, '<img>'), new SlideView($second, new HtmlAttributes(), '')];
        $sources = [21 => [$this->pictureSource('desktop', '(min-width: 768px)', 1920, 600)]];

        $view = new SliderView($slider, 'banner-slider-7', $attributes, $slides, true, 'Pause', $sources);

        self::assertSame($slider, $view->getSlider());
        self::assertSame('banner-slider-7', $view->getDomId());
        self::assertSame($attributes, $view->getContainerAttributes());
        self::assertSame($slides, $view->getSlides());
        self::assertSame([$first, $second], $view->getBanners());
        self::assertTrue($view->hasPauseControl());
        self::assertSame('Pause', $view->getPauseLabel());
        self::assertSame($sources, $view->getPictureSources());
        self::assertSame($first, $slides[0]->getBanner());
        self::assertSame($slideAttributes, $slides[0]->getAttributes());
        self::assertSame('<img>', $slides[0]->getContentHtml());
    }

    /**
     * The pause control is whatever the builder decided; picture sources default to none
     *
     * @param bool $pauseControl
     * @return void
     */
    #[TestWith([true])]
    #[TestWith([false])]
    public function testPauseControlAndDefaultSources(bool $pauseControl): void
    {
        $view = new SliderView(
            $this->slider(),
            'banner-slider-7',
            new HtmlAttributes(),
            [new SlideView($this->banner(), new HtmlAttributes(), '<img>')],
            $pauseControl,
            'Pause'
        );

        self::assertSame($pauseControl, $view->hasPauseControl());
        self::assertSame([], $view->getPictureSources());
    }

    /**
     * A slider view without slides cannot exist
     *
     * @return void
     */
    public function testEmptySlidesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SliderView($this->slider(), 'banner-slider-7', new HtmlAttributes(), [], false, 'Pause');
    }
}
