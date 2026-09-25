<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\View;

use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Picture\PictureSourcesProviderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\SlideRendererInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideContext;
use Hryvinskyi\BannerSliderFrontendUi\Api\View\SliderView;
use Hryvinskyi\BannerSliderFrontendUi\Model\Attribute\ElementAttributePool;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\DomIdAllocator;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\FrontendAssets;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\JsonAttributeEncoder;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\SlideLoadingPolicy;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\SliderViewBuilder;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\SplideConfigBuilder;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\StorefrontFixtures;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Asset\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(SliderViewBuilder::class)]
class SliderViewBuilderTest extends TestCase
{
    use StorefrontFixtures;

    /**
     * @var PictureSourcesProviderInterface&MockObject
     */
    private MockObject $pictureSourcesProvider;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var list<SlideContext>
     */
    private array $contexts = [];

    /**
     * @var array<int, \Throwable|string> Banner id => exception to throw or HTML to return
     */
    private array $outcomes = [];

    /**
     * @var SliderViewBuilder
     */
    private SliderViewBuilder $builder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->pictureSourcesProvider = $this->createMock(PictureSourcesProviderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $renderer = $this->createMock(SlideRendererInterface::class);
        $renderer->method('render')->willReturnCallback(function (SlideContext $context): string {
            $this->contexts[] = $context;
            $outcome = $this->outcomes[(int)$context->getBanner()->getBannerId()] ?? '<img>';
            if ($outcome instanceof \Throwable) {
                throw $outcome;
            }

            return $outcome;
        });
        $repository = $this->createMock(Repository::class);
        $repository->method('getUrl')->willReturnCallback(fn (string $id): string => 'https://static.test/' . $id);

        $this->builder = new SliderViewBuilder(
            $this->pictureSourcesProvider,
            $renderer,
            new SlideLoadingPolicy(),
            new ElementAttributePool(),
            new SplideConfigBuilder(400),
            new JsonAttributeEncoder(),
            new FrontendAssets($repository, [], ['splide' => 'A::splide.js', 'slider' => 'B::slider.js']),
            new DomIdAllocator(),
            $this->logger
        );
    }

    /**
     * The container carries the historical class and id, the markers, the configuration and the asset URLs
     *
     * @return void
     */
    public function testContainerAttributes(): void
    {
        $source = $this->pictureSource('desktop', '(min-width: 768px)', 1920, 600);
        $this->pictureSourcesProvider->expects(self::once())->method('getForBanners')->with([1, 2])
            ->willReturn([1 => [$source], 2 => []]);

        $view = $this->build($this->slider(['id' => 7, 'name' => 'Hero']), [1, 2]);

        $attributes = $view->getContainerAttributes();
        self::assertSame('hbs-slider banner-slider-7', $attributes->get('class'));
        self::assertSame('banner-slider-7', $attributes->get('id'));
        self::assertSame('banner-slider-7', $view->getDomId());
        self::assertTrue($attributes->get('data-hbs-slider'));
        self::assertSame('region', $attributes->get('role'));
        self::assertSame('carousel', $attributes->get('aria-roledescription'));
        self::assertSame('Hero', $attributes->get('aria-label'));
        self::assertSame(
            '{"Hryvinskyi_BannerSliderFrontendUi/js/banner-slider-requirejs":{}}',
            $attributes->get('data-mage-init')
        );
        self::assertSame(
            ['splide' => 'https://static.test/A::splide.js', 'slider' => 'https://static.test/B::slider.js'],
            json_decode((string)$attributes->get('data-hbs-assets'), true, 512, JSON_THROW_ON_ERROR)
        );

        $config = json_decode((string)$attributes->get('data-hbs-config'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($config);
        self::assertSame(
            ['splide', 'pauseLabel', 'playLabel', 'videoPlayLabel', 'videoPauseLabel', 'hasVideo', 'backgroundVideo'],
            array_keys($config)
        );
        self::assertSame('Pause autoplay', $config['pauseLabel']);
        self::assertSame('Start autoplay', $config['playLabel']);
        self::assertFalse($config['hasVideo']);
        self::assertFalse($config['backgroundVideo']);
        self::assertIsArray($config['splide']);
        self::assertSame('slide', $config['splide']['type']);
        self::assertTrue($view->hasPauseControl());
        self::assertSame('Pause autoplay', $view->getPauseLabel());
        self::assertSame([1 => [$source], 2 => []], $view->getPictureSources());
        self::assertSame([$source], $this->contexts[0]->getPictureSources());
    }

    /**
     * Each slide carries its classes and banner id; the first slide is eager and the rest follow the slider
     *
     * @return void
     */
    public function testSlides(): void
    {
        $this->pictureSourcesProvider->method('getForBanners')->willReturn([]);

        $view = $this->build($this->slider(['lazy' => true]), [1, 2]);

        self::assertCount(2, $view->getSlides());
        self::assertSame(
            ['class' => 'splide__slide hbs-slide', 'data-hbs-banner-id' => 1],
            $view->getSlides()[0]->getAttributes()->toArray()
        );
        self::assertSame('<img>', $view->getSlides()[1]->getContentHtml());
        self::assertSame(0, $this->contexts[0]->getPosition());
        self::assertFalse($this->contexts[0]->getLoading()->isLazy());
        self::assertTrue($this->contexts[0]->getLoading()->isHighPriority());
        self::assertTrue($this->contexts[1]->getLoading()->isLazy());
    }

    /**
     * A banner that cannot be rendered is logged and left out; the next one becomes the eager first slide
     *
     * @return void
     */
    public function testBrokenSlideIsSkippedAndLogged(): void
    {
        $this->pictureSourcesProvider->method('getForBanners')->willReturn([]);
        $this->outcomes = [1 => new \InvalidArgumentException('unsafe media path'), 2 => new NoSuchEntityException()];
        $this->logger->expects(self::exactly(2))->method('warning');

        $view = $this->build($this->slider(), [1, 2, 3]);

        self::assertSame([3], array_map(fn ($banner): ?int => $banner->getBannerId(), $view->getBanners()));
        self::assertSame(0, $this->contexts[2]->getPosition());
        self::assertTrue($this->contexts[2]->getLoading()->isHighPriority());
        self::assertFalse($view->hasPauseControl(), 'A single slide does not play.');
    }

    /**
     * The pause control is rendered only for a slider that plays on its own (auto play on, more than one slide) and
     * shows its pause/play button; the button setting never changes whether the slides play
     *
     * @param bool $toggle
     * @param bool $autoplay
     * @param int $slideCount
     * @param bool $expected
     * @return void
     */
    #[TestWith([true, true, 2, true])]
    #[TestWith([false, true, 2, false])]
    #[TestWith([true, false, 2, false])]
    #[TestWith([false, false, 2, false])]
    #[TestWith([true, true, 1, false])]
    #[TestWith([false, true, 1, false])]
    #[TestWith([true, false, 1, false])]
    #[TestWith([false, false, 1, false])]
    public function testPauseControl(bool $toggle, bool $autoplay, int $slideCount, bool $expected): void
    {
        $this->pictureSourcesProvider->method('getForBanners')->willReturn([]);

        $view = $this->build(
            $this->slider(['autoplay' => $autoplay, 'autoplayToggle' => $toggle]),
            range(1, $slideCount)
        );

        self::assertSame($expected, $view->hasPauseControl());
        $config = json_decode(
            (string)$view->getContainerAttributes()->get('data-hbs-config'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertIsArray($config);
        self::assertIsArray($config['splide']);
        self::assertSame($autoplay && $slideCount > 1, $config['splide']['autoplay']);
    }

    /**
     * A slide whose renderer returns nothing is left out without a log entry
     *
     * @return void
     */
    public function testEmptySlideIsLeftOut(): void
    {
        $this->pictureSourcesProvider->method('getForBanners')->willReturn([]);
        $this->outcomes = [1 => '  '];
        $this->logger->expects(self::never())->method('warning');

        self::assertCount(1, $this->build($this->slider(), [1, 2])->getSlides());
    }

    /**
     * Nothing renders: no view
     *
     * @return void
     */
    public function testNoRenderedSlideGivesNoView(): void
    {
        $this->pictureSourcesProvider->method('getForBanners')->willReturn([]);
        $this->outcomes = [1 => ''];

        $banners = [$this->banner(['id' => 1])];

        self::assertNull($this->builder->build($this->slider(), $banners));
    }

    /**
     * The same slider twice on a page gets two ids but keeps its class
     *
     * @return void
     */
    public function testSecondRenderOfTheSameSliderIsSuffixed(): void
    {
        $this->pictureSourcesProvider->method('getForBanners')->willReturn([]);

        $first = $this->build($this->slider(['id' => 4]), [1]);
        $second = $this->build($this->slider(['id' => 4]), [1]);

        self::assertSame('banner-slider-4', $first->getDomId());
        self::assertSame('banner-slider-4-2', $second->getDomId());
        self::assertSame('hbs-slider banner-slider-4', $second->getContainerAttributes()->get('class'));
    }

    /**
     * Video slides are flagged for the scripts, and a background video is noted
     *
     * @return void
     */
    public function testVideoFlags(): void
    {
        $this->pictureSourcesProvider->method('getForBanners')->willReturn([]);
        $banners = [
            $this->banner(['id' => 1, 'type' => BannerType::VIDEO, 'background' => true]),
            $this->banner(['id' => 2]),
        ];

        $view = $this->builder->build($this->slider(['loop' => true]), $banners);

        self::assertInstanceOf(SliderView::class, $view);
        $config = json_decode(
            (string)$view->getContainerAttributes()->get('data-hbs-config'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertIsArray($config);
        self::assertTrue($config['hasVideo']);
        self::assertTrue($config['backgroundVideo']);
        self::assertIsArray($config['splide']);
        self::assertSame('slide', $config['splide']['type'], 'A slider with video never loops.');
    }

    /**
     * Build a view of image banners with the given ids
     *
     * @param SliderInterface $slider
     * @param list<int> $bannerIds
     * @return SliderView
     */
    private function build(SliderInterface $slider, array $bannerIds): SliderView
    {
        $banners = array_map(fn (int $id) => $this->banner(['id' => $id]), $bannerIds);
        $view = $this->builder->build($slider, $banners);
        self::assertInstanceOf(SliderView::class, $view);

        return $view;
    }
}
