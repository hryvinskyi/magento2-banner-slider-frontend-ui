<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\Head;

use Hryvinskyi\BannerSliderApi\Api\Media\MediaUrlResolverInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointSpec;
use Hryvinskyi\BannerSliderApi\Api\Value\PictureSource;
use Hryvinskyi\BannerSliderFrontendUi\Model\Head\PreloadLinkBuilder;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\StorefrontFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(PreloadLinkBuilder::class)]
class PreloadLinkBuilderTest extends TestCase
{
    use StorefrontFixtures;

    private const MEDIA = 'https://shop.test/media/';
    private const CROPS = self::MEDIA . 'banner_slider/responsive/11/';
    private const IMAGE = 'banner_slider/image/hero.png';

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var MediaUrlResolverInterface&MockObject
     */
    private MockObject $resolver;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->resolver = $this->createMock(MediaUrlResolverInterface::class);
        $this->resolver->method('getUrl')->willReturnCallback(function (string $path): string {
            if (str_contains($path, '..')) {
                throw new \InvalidArgumentException('unsafe');
            }

            return self::MEDIA . $path;
        });
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    /**
     * Crops for every breakpoint: one link per crop, restricted to the widths where the picture shows it, naming its
     * preferred image and type; only the first slide is high priority
     *
     * @return void
     */
    public function testCropsForEveryBreakpointAndOnlyTheFirstSlideIsHighPriority(): void
    {
        $crops = [$this->desktopCrop(), $this->mobileCrop()];
        $pictures = [1 => $crops, 2 => $crops];
        $banners = [$this->banner(['id' => 1, 'image' => self::IMAGE]), $this->banner(['id' => 2])];

        $links = $this->builder($this->twoBreakpoints())->build($this->slider(['preload' => 2]), $banners, $pictures);

        $desktop = [
            'rel' => 'preload',
            'as' => 'image',
            'href' => self::CROPS . 'desktop.webp',
            'type' => 'image/webp',
            'media' => '(min-width: 768px)',
        ];
        $mobile = [
            'rel' => 'preload',
            'as' => 'image',
            'href' => self::CROPS . 'mobile.webp',
            'type' => 'image/webp',
            'media' => '(max-width: 767px)',
        ];
        self::assertSame([
            $desktop + ['fetchpriority' => 'high'],
            $mobile + ['fetchpriority' => 'high'],
            $desktop,
            $mobile,
        ], $links);
    }

    /**
     * A banner cropped for phones only: the phone crop below the desktop breakpoint, the banner image from it on,
     * which is what the picture's `<img>` shows there
     *
     * @return void
     */
    public function testMobileCropOnlyPreloadsTheBannerImageForWiderScreens(): void
    {
        $banner = $this->banner(['id' => 36, 'image' => self::IMAGE]);

        $links = $this->builder($this->twoBreakpoints())
            ->build($this->slider(['preload' => 1]), [$banner], [36 => [$this->mobileCrop()]]);

        self::assertSame([
            [
                'rel' => 'preload',
                'as' => 'image',
                'href' => self::MEDIA . self::IMAGE,
                'media' => '(min-width: 768px)',
                'fetchpriority' => 'high',
            ],
            [
                'rel' => 'preload',
                'as' => 'image',
                'href' => self::CROPS . 'mobile.webp',
                'type' => 'image/webp',
                'media' => '(max-width: 767px)',
                'fetchpriority' => 'high',
            ],
        ], $links);
    }

    /**
     * A banner cropped for desktops only: the desktop crop from its breakpoint on, the banner image below it
     *
     * @return void
     */
    public function testDesktopCropOnlyPreloadsTheBannerImageForNarrowerScreens(): void
    {
        $banner = $this->banner(['id' => 1, 'image' => self::IMAGE]);

        $links = $this->builder($this->twoBreakpoints())
            ->build($this->slider(['preload' => 1]), [$banner], [1 => [$this->desktopCrop()]]);

        self::assertSame(
            [
                [self::CROPS . 'desktop.webp', 'image/webp', '(min-width: 768px)'],
                [self::MEDIA . self::IMAGE, null, '(max-width: 767px)'],
            ],
            $this->summary($links)
        );
    }

    /**
     * A banner without crops gets one link for its image with no media query: every range preloads the same image
     *
     * @return void
     */
    public function testNoCropsGiveOneLinkForEveryWidth(): void
    {
        $banner = $this->banner(['id' => 3, 'image' => self::IMAGE]);

        self::assertSame(
            [['rel' => 'preload', 'as' => 'image', 'href' => self::MEDIA . self::IMAGE, 'fetchpriority' => 'high']],
            $this->builder($this->twoBreakpoints())->build($this->slider(['preload' => 1]), [$banner], [3 => []])
        );
    }

    /**
     * A slider without enabled breakpoints preloads the banner image for every width
     *
     * @return void
     */
    public function testSliderWithoutBreakpoints(): void
    {
        $banner = $this->banner(['id' => 3, 'image' => self::IMAGE]);

        self::assertSame(
            [[self::MEDIA . self::IMAGE, null, null]],
            $this->summary($this->builder([])->build($this->slider(['preload' => 1]), [$banner], [3 => []]))
        );
    }

    /**
     * A banner without an image shows the widest crop's original where no crop applies, so that is preloaded there
     *
     * @return void
     */
    public function testBannerWithoutImageFallsBackToTheWidestCropsOriginal(): void
    {
        $links = $this->builder($this->twoBreakpoints())
            ->build($this->slider(['preload' => 1]), [$this->banner(['id' => 1])], [1 => [$this->mobileCrop()]]);

        self::assertSame(
            [
                [self::CROPS . 'mobile.jpg', 'image/jpeg', '(min-width: 768px)'],
                [self::CROPS . 'mobile.webp', 'image/webp', '(max-width: 767px)'],
            ],
            $this->summary($links)
        );
    }

    /**
     * A banner with neither an image nor crops preloads nothing
     *
     * @return void
     */
    public function testBannerWithNothingToShow(): void
    {
        self::assertSame(
            [],
            $this->builder($this->twoBreakpoints())
                ->build($this->slider(['preload' => 1]), [$this->banner(['id' => 1])], [1 => []])
        );
    }

    /**
     * Overlapping stored media queries do not preload two crops for one width: each link covers only the widths
     * where the picture shows its crop, from the breakpoint's min width up to the next wider breakpoint
     *
     * @return void
     */
    public function testOverlappingMediaQueriesPreloadOneCropPerWidth(): void
    {
        $breakpoints = [
            new BreakpointSpec('desktop', '(min-width: 768px)', 768, 1920, null),
            new BreakpointSpec('mobile', '(max-width: 768px)', 0, 892, null),
        ];
        $pictures = [1 => [$this->desktopCrop(), $this->mobileCrop()]];

        $links = $this->builder($breakpoints)
            ->build($this->slider(['preload' => 1]), [$this->banner(['id' => 1])], $pictures);

        self::assertSame(['(min-width: 768px)', '(max-width: 767px)'], array_column($links, 'media'));
    }

    /**
     * A middle breakpoint is bounded on both sides; ranges that preload the same image merge only when adjacent
     *
     * @return void
     */
    public function testRangesOfThreeBreakpointsMergeOnlyWhenAdjacent(): void
    {
        $banner = $this->banner(['id' => 1, 'image' => self::IMAGE]);
        $tablet = $this->pictureSource('tablet', '(min-width: 768px)', 1199, 500, 768);

        $links = $this->builder($this->threeBreakpoints())
            ->build($this->slider(['preload' => 1]), [$banner], [1 => [$tablet]]);

        self::assertSame(
            [
                [self::MEDIA . self::IMAGE, null, '(min-width: 1200px)'],
                [self::CROPS . 'tablet.webp', 'image/webp', '(min-width: 768px) and (max-width: 1199px)'],
                [self::MEDIA . self::IMAGE, null, '(max-width: 767px)'],
            ],
            $this->summary($links)
        );
    }

    /**
     * Adjacent ranges without a crop share one link
     *
     * @return void
     */
    public function testAdjacentRangesWithoutACropMerge(): void
    {
        $banner = $this->banner(['id' => 1, 'image' => self::IMAGE]);
        $desktop = $this->pictureSource('desktop', '(min-width: 1200px)', 1920, 600, 1200);

        $links = $this->builder($this->threeBreakpoints())
            ->build($this->slider(['preload' => 1]), [$banner], [1 => [$desktop]]);

        self::assertSame(
            [
                [self::CROPS . 'desktop.webp', 'image/webp', '(min-width: 1200px)'],
                [self::MEDIA . self::IMAGE, null, '(max-width: 1199px)'],
            ],
            $this->summary($links)
        );
    }

    /**
     * Widths below the narrowest breakpoint show the banner image
     *
     * @return void
     */
    public function testWidthsBelowTheNarrowestBreakpoint(): void
    {
        $banner = $this->banner(['id' => 1, 'image' => self::IMAGE]);

        $links = $this->builder([new BreakpointSpec('desktop', '(min-width: 768px)', 768, 1920, null)])
            ->build($this->slider(['preload' => 1]), [$banner], [1 => [$this->desktopCrop()]]);

        self::assertSame(
            [
                [self::CROPS . 'desktop.webp', 'image/webp', '(min-width: 768px)'],
                [self::MEDIA . self::IMAGE, null, '(max-width: 767px)'],
            ],
            $this->summary($links)
        );
    }

    /**
     * Breakpoints sharing a min width share one range, and the first of them with a crop supplies it
     *
     * @return void
     */
    public function testBreakpointsSharingAMinWidthShareOneRange(): void
    {
        $breakpoints = [
            new BreakpointSpec('first', '', 0, 1920, null),
            new BreakpointSpec('second', '(max-width: 767px)', 0, 892, null),
        ];
        $second = $this->pictureSource('second', '(max-width: 767px)', 892, 588, 0);

        $links = $this->builder($breakpoints)
            ->build($this->slider(['preload' => 1]), [$this->banner(['id' => 1])], [1 => [$second]]);

        self::assertSame([[self::CROPS . 'second.webp', 'image/webp', null]], $this->summary($links));
    }

    /**
     * An unsafe banner image path does not matter when crops cover every width; it drops the banner's links when a
     * range needs it
     *
     * @return void
     */
    public function testUnsafeBannerImageMattersOnlyWhereItIsShown(): void
    {
        $banner = $this->banner(['id' => 1, 'image' => '../x.png']);
        $builder = $this->builder($this->twoBreakpoints());

        $covered = $builder->build(
            $this->slider(['preload' => 1]),
            [$banner],
            [1 => [$this->desktopCrop(), $this->mobileCrop()]]
        );
        self::assertCount(2, $covered);

        $this->logger->expects(self::once())->method('warning')
            ->with(self::anything(), self::callback(fn (array $context): bool => $context['banner_id'] === 1));
        self::assertSame(
            [],
            $builder->build($this->slider(['preload' => 1]), [$banner], [1 => [$this->mobileCrop()]])
        );
    }

    /**
     * Banners past the count are preloaded only when flagged; videos and custom content never are
     *
     * @return void
     */
    public function testSelection(): void
    {
        $banners = [
            $this->banner(['id' => 1, 'type' => BannerType::VIDEO, 'image' => 'a.jpg']),
            $this->banner(['id' => 2, 'image' => 'b.jpg']),
            $this->banner(['id' => 3, 'image' => 'c.jpg', 'preload' => true]),
            $this->banner(['id' => 4, 'type' => BannerType::CUSTOM, 'image' => 'd.jpg', 'preload' => true]),
        ];

        $links = $this->builder($this->twoBreakpoints())->build($this->slider(['preload' => 1]), $banners, []);

        self::assertSame([['rel' => 'preload', 'as' => 'image', 'href' => self::MEDIA . 'c.jpg']], $links);
    }

    /**
     * No count and no flags preload nothing
     *
     * @return void
     */
    public function testNothingToPreload(): void
    {
        $banner = $this->banner(['image' => 'a.jpg']);

        self::assertSame([], $this->builder([])->build($this->slider(['preload' => 0]), [$banner], []));
    }

    /**
     * A banner with an unsafe path is skipped and logged; the others keep their links
     *
     * @return void
     */
    public function testUnsafePathIsSkippedAndLogged(): void
    {
        $this->logger->expects(self::once())->method('warning')
            ->with(self::anything(), self::callback(fn (array $context): bool => $context['banner_id'] === 1));
        $banners = [$this->banner(['id' => 1, 'image' => '../x.jpg']), $this->banner(['id' => 2, 'image' => 'b.jpg'])];

        $links = $this->builder([])->build($this->slider(['preload' => 2]), $banners, []);

        self::assertSame([['rel' => 'preload', 'as' => 'image', 'href' => self::MEDIA . 'b.jpg']], $links);
    }

    /**
     * The builder for a slider with the given enabled breakpoints
     *
     * @param list<BreakpointSpec> $breakpoints Widest first
     * @return PreloadLinkBuilder
     */
    private function builder(array $breakpoints): PreloadLinkBuilder
    {
        return new PreloadLinkBuilder($this->resolver, $this->breakpointReader($breakpoints), $this->logger);
    }

    /**
     * Desktop from 768 px, mobile below
     *
     * @return list<BreakpointSpec>
     */
    private function twoBreakpoints(): array
    {
        return [
            new BreakpointSpec('desktop', '(min-width: 768px)', 768, 1920, 340),
            new BreakpointSpec('mobile', '(max-width: 767px)', 0, 892, 588),
        ];
    }

    /**
     * Desktop from 1200 px, tablet from 768 px, mobile below
     *
     * @return list<BreakpointSpec>
     */
    private function threeBreakpoints(): array
    {
        return [
            new BreakpointSpec('desktop', '(min-width: 1200px)', 1200, 1920, null),
            new BreakpointSpec('tablet', '(min-width: 768px)', 768, 1199, null),
            new BreakpointSpec('mobile', '(max-width: 767px)', 0, 767, null),
        ];
    }

    /**
     * The desktop crop
     *
     * @return PictureSource
     */
    private function desktopCrop(): PictureSource
    {
        return $this->pictureSource('desktop', '(min-width: 768px)', 1920, 340, 768);
    }

    /**
     * The mobile crop
     *
     * @return PictureSource
     */
    private function mobileCrop(): PictureSource
    {
        return $this->pictureSource('mobile', '(max-width: 767px)', 892, 588, 0);
    }

    /**
     * URL, type and media of each link
     *
     * @param list<array<string,string>> $links
     * @return list<array{0: string|null, 1: string|null, 2: string|null}>
     */
    private function summary(array $links): array
    {
        return array_map(
            fn (array $link): array => [$link['href'] ?? null, $link['type'] ?? null, $link['media'] ?? null],
            $links
        );
    }
}
