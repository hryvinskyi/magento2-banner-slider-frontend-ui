<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\Head;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Media\MediaUrlResolverInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\HtmlAttributes;
use Hryvinskyi\BannerSliderFrontendUi\Model\Head\HeadAssetRegistrar;
use Hryvinskyi\BannerSliderFrontendUi\Model\Head\PreloadLinkBuilder;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\FrontendAssets;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\SliderView;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\SlideView;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\StorefrontFixtures;
use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;
use Magento\Framework\View\Asset\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(HeadAssetRegistrar::class)]
class HeadAssetRegistrarTest extends TestCase
{
    use StorefrontFixtures;

    /**
     * @var HeadTagManagerInterface&MockObject
     */
    private MockObject $headTagManager;

    /**
     * @var HeadAssetRegistrar
     */
    private HeadAssetRegistrar $registrar;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->headTagManager = $this->createMock(HeadTagManagerInterface::class);
        $this->headTagManager->method('generateElementKey')->willReturnCallback(
            fn (string $type, array $data): string => $type . ':' . json_encode($data, JSON_THROW_ON_ERROR)
        );
        $repository = $this->createMock(Repository::class);
        $repository->method('getUrl')->willReturnCallback(fn (string $id): string => 'https://static.test/' . $id);
        $resolver = $this->createMock(MediaUrlResolverInterface::class);
        $resolver->method('getUrl')->willReturnCallback(fn (string $path): string => 'https://media.test/' . $path);
        $this->registrar = new HeadAssetRegistrar(
            $this->headTagManager,
            new FrontendAssets($repository, ['splide' => 'A::a.css', 'slider' => 'B::b.css']),
            new PreloadLinkBuilder($resolver, $this->breakpointReader(), $this->createMock(LoggerInterface::class))
        );
    }

    /**
     * Stylesheets, the leading image's preload link and the custom CSS, each under a stable key
     *
     * @return void
     */
    public function testRegistersStylesheetsPreloadsAndCustomCss(): void
    {
        $slider = $this->slider(['id' => 7, 'css' => '.banner-slider-7 { color: red } </style><script>']);
        $banner = $this->banner(['id' => 11, 'image' => 'banner_slider/image/a.jpg']);

        $stylesheets = [];
        $this->headTagManager->expects(self::exactly(2))->method('addStylesheet')->willReturnCallback(
            function (string $href, array $attributes, ?string $key) use (&$stylesheets): HeadTagManagerInterface {
                $stylesheets[] = [$href, $key];

                return $this->headTagManager;
            }
        );
        $expectedLink = [
            'rel' => 'preload',
            'as' => 'image',
            'href' => 'https://media.test/banner_slider/image/a.jpg',
            'fetchpriority' => 'high',
        ];
        $this->headTagManager->expects(self::once())->method('addLink')
            ->with($expectedLink, 'link:' . json_encode(['attributes' => $expectedLink], JSON_THROW_ON_ERROR));
        $this->headTagManager->expects(self::once())->method('addInlineStyle')
            ->with('.banner-slider-7 { color: red } <\/style><script>', [], 'hryvinskyi_banner_slider_css_7');

        $this->registrar->register($this->view($slider, $banner));

        self::assertSame('https://static.test/A::a.css', $stylesheets[0][0]);
        self::assertSame(
            'link:' . json_encode(
                ['attributes' => ['rel' => 'stylesheet', 'href' => 'https://static.test/A::a.css']],
                JSON_THROW_ON_ERROR
            ),
            $stylesheets[0][1]
        );
        self::assertSame('https://static.test/B::b.css', $stylesheets[1][0]);
    }

    /**
     * No custom CSS, no inline style
     *
     * @return void
     */
    public function testNoCustomCss(): void
    {
        $this->headTagManager->expects(self::never())->method('addInlineStyle');

        $this->registrar->register($this->view($this->slider(['css' => '  ']), $this->banner()));
    }

    /**
     * A view with one slide of the banner
     *
     * @param SliderInterface $slider
     * @param BannerInterface $banner
     * @return SliderView
     */
    private function view(SliderInterface $slider, BannerInterface $banner): SliderView
    {
        return new SliderView(
            $slider,
            'banner-slider-7',
            new HtmlAttributes(),
            [new SlideView($banner, new HtmlAttributes(), '<img>')],
            false,
            'Pause',
            []
        );
    }
}
