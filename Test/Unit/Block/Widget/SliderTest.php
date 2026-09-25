<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Block\Widget;

use DateTimeImmutable;
use Hryvinskyi\BannerSliderApi\Api\Banner\VisibleBannersProviderInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Picture\PictureSourcesProviderInterface;
use Hryvinskyi\BannerSliderApi\Api\Slider\SliderLocatorInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\StorefrontContext;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\SlideRendererInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideContext;
use Hryvinskyi\BannerSliderFrontendUi\Block\Widget\Slider;
use Hryvinskyi\BannerSliderFrontendUi\Model\Attribute\ElementAttributePool;
use Hryvinskyi\BannerSliderFrontendUi\Model\Head\HeadAssetRegistrar;
use Hryvinskyi\BannerSliderFrontendUi\Model\StorefrontContextProvider;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\DomIdAllocator;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\FrontendAssets;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\JsonAttributeEncoder;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\SlideLoadingPolicy;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\SliderView;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\SliderViewBuilder;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\SplideConfigBuilder;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\StorefrontFixtures;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\TemplateRendererSpy;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Element\Template\Context;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(Slider::class)]
class SliderTest extends TestCase
{
    use StorefrontFixtures;

    /**
     * @var SliderLocatorInterface&MockObject
     */
    private MockObject $sliderLocator;

    /**
     * @var VisibleBannersProviderInterface&MockObject
     */
    private MockObject $visibleBannersProvider;

    /**
     * @var HeadAssetRegistrar&MockObject
     */
    private MockObject $headAssetRegistrar;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var TemplateRendererSpy
     */
    private TemplateRendererSpy $templates;

    /**
     * @var DomIdAllocator
     */
    private DomIdAllocator $domIdAllocator;

    /**
     * @var array<int, \Throwable> Banner id => exception its slide renderer throws
     */
    private array $failures = [];

    /**
     * @var StorefrontContext
     */
    private StorefrontContext $storefrontContext;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->sliderLocator = $this->createMock(SliderLocatorInterface::class);
        $this->visibleBannersProvider = $this->createMock(VisibleBannersProviderInterface::class);
        $this->headAssetRegistrar = $this->createMock(HeadAssetRegistrar::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->templates = new TemplateRendererSpy();
        $this->domIdAllocator = new DomIdAllocator();
        $this->storefrontContext = new StorefrontContext(1, 0, new DateTimeImmutable('2026-09-25 10:00:00'));
    }

    /**
     * The slider id argument accepts positive whole numbers only
     *
     * @param mixed $value
     * @param int|null $expected
     * @return void
     */
    #[TestWith([5, 5])]
    #[TestWith(['5', 5])]
    #[TestWith([' 12 ', 12])]
    #[TestWith([0, null])]
    #[TestWith(['0', null])]
    #[TestWith([-3, null])]
    #[TestWith(['5abc', null])]
    #[TestWith([null, null])]
    #[TestWith([5.0, null])]
    public function testSliderIdArgument(mixed $value, ?int $expected): void
    {
        self::assertSame($expected, $this->block(['slider_id' => $value])->getSliderIdArgument());
    }

    /**
     * The location argument becomes a location code; an invalid one is logged once and ignored
     *
     * @return void
     */
    public function testLocationArgument(): void
    {
        $location = $this->block(['location' => ' homepage_top '])->getLocationArgument();
        self::assertSame('homepage_top', $location?->getCode());
        self::assertNull($this->block(['location' => ''])->getLocationArgument());
        self::assertNull($this->block([])->getLocationArgument());

        $this->logger->expects(self::once())->method('warning');
        $block = $this->block(['location' => 'home page!']);
        self::assertNull($block->getLocationArgument());
        self::assertNull($block->getLocationArgument());
    }

    /**
     * Without a template argument the slider template is used; a given one is kept
     *
     * @return void
     */
    public function testDefaultTemplate(): void
    {
        self::assertSame(Slider::DEFAULT_TEMPLATE, $this->block([])->getTemplate());
        $block = $this->block(['template' => 'Theme_Module::own.phtml']);
        self::assertSame('Theme_Module::own.phtml', $block->getTemplate());
    }

    /**
     * No placement argument: nothing is looked up, the generic tag is used and nothing renders
     *
     * @return void
     */
    public function testNoArgumentRendersNothing(): void
    {
        $this->sliderLocator->expects(self::never())->method('findById');
        $this->sliderLocator->expects(self::never())->method('findByLocation');
        $block = $this->block([]);

        self::assertSame([SliderInterface::CACHE_TAG], $block->getIdentities());
        self::assertSame('', $block->toHtml());
        self::assertSame([], $this->templates->getRenders());
    }

    /**
     * By id and not found: the requested slider's tag and the generic tag, so the page refreshes when it appears
     *
     * @return void
     */
    public function testIdentitiesByIdNotFound(): void
    {
        $this->sliderLocator->expects(self::once())->method('findById')->with(5, $this->storefrontContext)
            ->willReturn(null);
        $this->visibleBannersProvider->expects(self::never())->method('getForSlider');

        $block = $this->block(['slider_id' => '5', 'location' => 'ignored']);

        self::assertSame(
            [SliderInterface::CACHE_TAG . '_5', SliderInterface::CACHE_TAG],
            $block->getIdentities()
        );
        self::assertSame('', $block->toHtml());
    }

    /**
     * By id and found: the slider's tag and each banner's tag
     *
     * @return void
     */
    public function testIdentitiesByIdFound(): void
    {
        $this->sliderLocator->method('findById')->willReturn($this->slider(['id' => 5]));
        $this->visibleBannersProvider->method('getForSlider')->willReturn([
            $this->banner(['id' => 21]),
            $this->banner(['id' => 22]),
        ]);

        self::assertSame(
            [
                SliderInterface::CACHE_TAG . '_5',
                BannerInterface::CACHE_TAG . '_21',
                BannerInterface::CACHE_TAG . '_22',
            ],
            $this->block(['slider_id' => 5])->getIdentities()
        );
    }

    /**
     * By location: the location's tag, found or not
     *
     * @return void
     */
    public function testIdentitiesByLocation(): void
    {
        $this->sliderLocator->method('findByLocation')->willReturnCallback(
            fn (string $location): ?SliderInterface => $location === 'Home-Top' ? $this->slider(['id' => 3]) : null
        );
        $this->visibleBannersProvider->expects(self::once())->method('getForSlider')
            ->with(3, $this->storefrontContext->getNow())
            ->willReturn([$this->banner(['id' => 9])]);

        self::assertSame(
            [
                SliderInterface::LOCATION_CACHE_TAG . '_home_top',
                SliderInterface::CACHE_TAG . '_3',
                BannerInterface::CACHE_TAG . '_9',
            ],
            $this->block(['location' => 'Home-Top'])->getIdentities()
        );
        self::assertSame(
            [SliderInterface::LOCATION_CACHE_TAG . '_footer', SliderInterface::CACHE_TAG],
            $this->block(['location' => 'footer'])->getIdentities()
        );
    }

    /**
     * A failing lookup renders nothing and is logged
     *
     * @return void
     */
    public function testLookupFailureRendersNothing(): void
    {
        $this->sliderLocator->method('findById')->willThrowException(new NoSuchEntityException());
        $this->logger->expects(self::once())->method('error');

        $block = $this->block(['slider_id' => 5]);

        self::assertSame('', $block->toHtml());
        self::assertSame([SliderInterface::CACHE_TAG . '_5', SliderInterface::CACHE_TAG], $block->getIdentities());
    }

    /**
     * Head assets are registered before the template renders, and the bootstrap follows the container
     *
     * @return void
     */
    public function testRendersWithAssetsAndBootstrap(): void
    {
        $this->sliderLocator->method('findById')->willReturn($this->slider(['id' => 5]));
        $this->visibleBannersProvider->method('getForSlider')->willReturn([$this->banner(['id' => 21])]);
        $order = [];
        $this->headAssetRegistrar->expects(self::once())->method('register')
            ->willReturnCallback(function (SliderView $view) use (&$order): void {
                $order[] = 'assets:' . $view->getDomId();
            });
        $block = $this->block(['slider_id' => 5], '<div id="banner-slider-5">', $order);

        $html = $block->toHtml();

        self::assertSame('<div id="banner-slider-5">[' . Slider::BOOTSTRAP_TEMPLATE . ']', $html);
        self::assertSame(['assets:banner-slider-5', 'template'], $order);
        self::assertSame(Slider::BOOTSTRAP_TEMPLATE, $this->templates->getRenders()[0]['template']);
    }

    /**
     * Every render of a slider on a page gets its own id, keeps the historical class and its own bootstrap
     *
     * @return void
     */
    public function testSameSliderTwiceGetsTwoIdsAndTwoBootstraps(): void
    {
        $this->sliderLocator->method('findById')->willReturn($this->slider(['id' => 5]));
        $this->visibleBannersProvider->method('getForSlider')->willReturn([$this->banner(['id' => 21])]);

        $first = $this->block(['slider_id' => 5]);
        $second = $this->block(['slider_id' => 5]);
        $first->toHtml();
        $second->toHtml();

        $firstView = $first->getSliderView();
        $secondView = $second->getSliderView();
        self::assertInstanceOf(SliderView::class, $firstView);
        self::assertInstanceOf(SliderView::class, $secondView);
        self::assertSame('banner-slider-5', $firstView->getDomId());
        self::assertSame('banner-slider-5-2', $secondView->getDomId());
        self::assertSame('hbs-slider banner-slider-5', $secondView->getContainerAttributes()->get('class'));
        self::assertCount(2, $this->templates->getRenders());
    }

    /**
     * A slide with an unsafe media path is left out and logged while the others render
     *
     * @return void
     */
    public function testBrokenSlideIsSkippedWhileOthersRender(): void
    {
        $this->sliderLocator->method('findById')->willReturn($this->slider(['id' => 5]));
        $this->visibleBannersProvider->method('getForSlider')->willReturn([
            $this->banner(['id' => 21]),
            $this->banner(['id' => 22]),
        ]);
        $this->failures = [21 => new \InvalidArgumentException('Unsafe media path "../x.jpg".')];
        $this->logger->expects(self::once())->method('warning')->with(
            self::anything(),
            self::callback(fn (array $context): bool => $context['banner_id'] === 21)
        );

        $block = $this->block(['slider_id' => 5]);
        $block->toHtml();

        $view = $block->getSliderView();
        self::assertInstanceOf(SliderView::class, $view);
        self::assertCount(1, $view->getSlides());
        self::assertSame(22, $view->getSlides()[0]->getBanner()->getBannerId());
        self::assertContains(BannerInterface::CACHE_TAG . '_21', $block->getIdentities());
    }

    /**
     * No banner renders: nothing, not even the bootstrap
     *
     * @return void
     */
    public function testNoRenderableSlideRendersNothing(): void
    {
        $this->sliderLocator->method('findById')->willReturn($this->slider(['id' => 5]));
        $this->visibleBannersProvider->method('getForSlider')->willReturn([$this->banner(['id' => 21])]);
        $this->failures = [21 => new \InvalidArgumentException('broken')];
        $this->headAssetRegistrar->expects(self::never())->method('register');

        self::assertSame('', $this->block(['slider_id' => 5])->toHtml());
        self::assertSame([], $this->templates->getRenders());
    }

    /**
     * A block with the given arguments; its template renders as the given HTML
     *
     * @param array<string,mixed> $arguments
     * @param string $templateHtml
     * @param list<string> $order Receives "template" when the template renders
     * @return Slider&MockObject
     */
    private function block(array $arguments, string $templateHtml = '<div>', array &$order = []): Slider
    {
        $context = $this->createMock(Context::class);
        $context->method('getEventManager')->willReturn($this->createMock(ManagerInterface::class));
        $context->method('getScopeConfig')->willReturn($this->createMock(ScopeConfigInterface::class));

        $storefrontContextProvider = $this->createMock(StorefrontContextProvider::class);
        $storefrontContextProvider->method('get')->willReturn($this->storefrontContext);

        $block = $this->getMockBuilder(Slider::class)
            ->setConstructorArgs([
                $context,
                $this->sliderLocator,
                $this->visibleBannersProvider,
                $storefrontContextProvider,
                $this->viewBuilder(),
                $this->headAssetRegistrar,
                $this->templates,
                $this->logger,
                $arguments,
            ])
            ->onlyMethods(['fetchView', 'getTemplateFile'])
            ->getMock();
        $block->method('getTemplateFile')->willReturn('/templates/slider.phtml');
        $block->method('fetchView')->willReturnCallback(function () use ($templateHtml, &$order): string {
            $order[] = 'template';

            return $templateHtml;
        });

        return $block;
    }

    /**
     * A view builder whose slide renderer fails for the banners listed in `failures`
     *
     * @return SliderViewBuilder
     */
    private function viewBuilder(): SliderViewBuilder
    {
        $pictureSourcesProvider = $this->createMock(PictureSourcesProviderInterface::class);
        $pictureSourcesProvider->method('getForBanners')->willReturn([]);
        $renderer = $this->createMock(SlideRendererInterface::class);
        $renderer->method('render')->willReturnCallback(function (SlideContext $context): string {
            $failure = $this->failures[(int)$context->getBanner()->getBannerId()] ?? null;
            if ($failure !== null) {
                throw $failure;
            }

            return '<img>';
        });

        return new SliderViewBuilder(
            $pictureSourcesProvider,
            $renderer,
            new SlideLoadingPolicy(),
            new ElementAttributePool(),
            new SplideConfigBuilder(400),
            new JsonAttributeEncoder(),
            new FrontendAssets($this->createMock(Repository::class)),
            $this->domIdAllocator,
            $this->logger
        );
    }
}
