<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\View;

use Hryvinskyi\BannerSliderApi\Api\Media\MediaUrlResolverInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointSpec;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFile;
use Hryvinskyi\BannerSliderApi\Api\Value\ImageFormat;
use Hryvinskyi\BannerSliderApi\Api\Value\PictureSource;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideLoading;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\PictureViewBuilder;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\StorefrontFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(PictureViewBuilder::class)]
class PictureViewBuilderTest extends TestCase
{
    use StorefrontFixtures;

    /**
     * @var PictureViewBuilder
     */
    private PictureViewBuilder $builder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $resolver = $this->createMock(MediaUrlResolverInterface::class);
        $resolver->method('getUrl')->willReturnCallback(
            fn (string $path): string => 'https://shop.test/media/' . $path
        );
        $this->builder = new PictureViewBuilder($resolver);
    }

    /**
     * One source per image, preferred format first, each with its own crop size; without a banner image the `<img>`
     * is the widest source's original with that size
     *
     * @return void
     */
    public function testSourcesCarryTheirOwnCropSize(): void
    {
        $desktop = $this->pictureSource('desktop', '(min-width: 768px)', 1920, 294);
        $mobile = $this->pictureSource('mobile', '(max-width: 767px)', 892, 588);

        $view = $this->builder->fromSources(
            [$desktop, $mobile],
            null,
            null,
            'Run Rate Orders',
            new SlideLoading(false, true)
        );

        $sources = array_map(fn ($source): array => $source->toArray(), $view->getSources());
        self::assertTrue($view->hasSources());
        self::assertSame([
            [
                'media' => '(min-width: 768px)',
                'srcset' => 'https://shop.test/media/banner_slider/responsive/11/desktop.webp',
                'type' => 'image/webp',
                'width' => 1920,
                'height' => 294,
            ],
            [
                'media' => '(min-width: 768px)',
                'srcset' => 'https://shop.test/media/banner_slider/responsive/11/desktop.jpg',
                'type' => 'image/jpeg',
                'width' => 1920,
                'height' => 294,
            ],
            [
                'media' => '(max-width: 767px)',
                'srcset' => 'https://shop.test/media/banner_slider/responsive/11/mobile.webp',
                'type' => 'image/webp',
                'width' => 892,
                'height' => 588,
            ],
            [
                'media' => '(max-width: 767px)',
                'srcset' => 'https://shop.test/media/banner_slider/responsive/11/mobile.jpg',
                'type' => 'image/jpeg',
                'width' => 892,
                'height' => 588,
            ],
        ], $sources);
        self::assertSame([
            'class' => 'hbs-slide__media',
            'src' => 'https://shop.test/media/banner_slider/responsive/11/desktop.jpg',
            'alt' => 'Run Rate Orders',
            'width' => 1920,
            'height' => 294,
            'loading' => 'eager',
            'fetchpriority' => 'high',
            'decoding' => 'async',
        ], $view->getImage()->toArray());
    }

    /**
     * A source whose breakpoint has no fixed height still carries the rendered size, and no media query means none
     *
     * @return void
     */
    public function testSizeComesFromTheSourceNotTheBreakpoint(): void
    {
        $format = new ImageFormat('png', 'image/png', 'png');
        $source = new PictureSource(
            new BreakpointSpec('all', '', 0, 1200, null),
            new Dimensions(1200, 437),
            [new ImageFile('banner_slider/responsive/11/all.png', $format)]
        );

        $view = $this->builder->fromSources([$source], '', null, '', new SlideLoading(true, false));

        self::assertSame(1200, $view->getSources()[0]->get('width'));
        self::assertSame(437, $view->getSources()[0]->get('height'));
        self::assertNull($view->getSources()[0]->get('media'));
        self::assertSame('', $view->getImage()->get('alt'));
        self::assertSame('lazy', $view->getImage()->get('loading'));
        self::assertNull($view->getImage()->get('fetchpriority'));
    }

    /**
     * Whichever breakpoints the crops cover, the `<img>` (shown where no source matches) is the banner image with
     * the banner's stored size; the sources are the crops', unchanged
     *
     * @param list<string> $crops Identifiers of the cropped breakpoints
     * @return void
     */
    #[TestWith([['mobile']])]
    #[TestWith([['desktop']])]
    #[TestWith([['desktop', 'mobile']])]
    public function testImageIsTheBannerImageWhateverTheCropsCover(array $crops): void
    {
        $available = [
            'desktop' => $this->pictureSource('desktop', '(min-width: 768px)', 1920, 340, 768),
            'mobile' => $this->pictureSource('mobile', '(max-width: 767px)', 892, 588, 0),
        ];
        $sources = array_map(fn (string $crop): PictureSource => $available[$crop], $crops);
        self::assertNotSame([], $sources);

        $view = $this->builder->fromSources(
            $sources,
            'banner_slider/image/networking.png',
            new Dimensions(1365, 209),
            'Networking',
            new SlideLoading(false, true)
        );

        self::assertCount(2 * count($crops), $view->getSources());
        self::assertSame(
            'https://shop.test/media/banner_slider/responsive/11/' . $crops[0] . '.webp',
            $view->getSources()[0]->get('srcset')
        );
        self::assertSame([
            'class' => 'hbs-slide__media',
            'src' => 'https://shop.test/media/banner_slider/image/networking.png',
            'alt' => 'Networking',
            'width' => 1365,
            'height' => 209,
            'loading' => 'eager',
            'fetchpriority' => 'high',
            'decoding' => 'async',
        ], $view->getImage()->toArray());
    }

    /**
     * A banner image of unknown size leaves width and height out of the `<img>`; an empty path counts as no image
     *
     * @return void
     */
    public function testBannerImageOfUnknownSizeAndEmptyPath(): void
    {
        $mobile = $this->pictureSource('mobile', '(max-width: 767px)', 892, 588, 0);
        $loading = new SlideLoading(true, false);

        $unsized = $this->builder->fromSources([$mobile], 'banner_slider/image/a.png', null, '', $loading);
        $empty = $this->builder->fromSources([$mobile], '', new Dimensions(10, 10), '', $loading);

        self::assertSame('https://shop.test/media/banner_slider/image/a.png', $unsized->getImage()->get('src'));
        self::assertNull($unsized->getImage()->get('width'));
        self::assertNull($unsized->getImage()->get('height'));
        self::assertSame(
            'https://shop.test/media/banner_slider/responsive/11/mobile.jpg',
            $empty->getImage()->get('src')
        );
        self::assertSame(892, $empty->getImage()->get('width'));
    }

    /**
     * A plain image uses the stored size and no sources; an unknown size leaves width and height out
     *
     * @return void
     */
    public function testPlainImage(): void
    {
        $loading = new SlideLoading(true, false);
        $sized = $this->builder->fromImage('banner_slider/image/a.jpg', new Dimensions(800, 400), 'A', $loading);
        $unsized = $this->builder->fromImage('banner_slider/image/b.jpg', null, 'B', $loading);

        self::assertFalse($sized->hasSources());
        self::assertSame('https://shop.test/media/banner_slider/image/a.jpg', $sized->getImage()->get('src'));
        self::assertSame(800, $sized->getImage()->get('width'));
        self::assertSame(400, $sized->getImage()->get('height'));
        self::assertNull($unsized->getImage()->get('width'));
        self::assertSame(' class="hbs-slide__media" src="url:https://shop.test/media/banner_slider/image/b.jpg"'
            . ' alt="B" loading="lazy" decoding="async"', $unsized->getImage()->render($this->escaper()));
    }

    /**
     * An image for other uses carries no class
     *
     * @return void
     */
    public function testImageWithoutClass(): void
    {
        $image = $this->builder->image('banner_slider/image/a.jpg', null, '', new SlideLoading(false, false));

        self::assertNull($image->get('class'));
        self::assertSame('eager', $image->get('loading'));
    }

    /**
     * An unsafe stored path fails instead of producing a URL
     *
     * @return void
     */
    public function testUnsafePathFails(): void
    {
        $resolver = $this->createMock(MediaUrlResolverInterface::class);
        $resolver->method('getUrl')->willThrowException(new \InvalidArgumentException('unsafe'));

        $this->expectException(\InvalidArgumentException::class);

        (new PictureViewBuilder($resolver))->fromImage('../etc/env.php', null, '', new SlideLoading(true, false));
    }
}
