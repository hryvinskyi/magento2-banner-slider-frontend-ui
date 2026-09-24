<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\ResponsiveImage;

use Hryvinskyi\BannerSliderFrontendUi\Model\ResponsiveImage\CropOrder;
use Hryvinskyi\BannerSliderFrontendUi\Model\ResponsiveImage\PictureRenderer;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hryvinskyi\BannerSliderFrontendUi\Model\ResponsiveImage\PictureRenderer
 */
class PictureRendererTest extends TestCase
{
    use CropFixtureTrait;

    private const MEDIA_URL = 'https://shop.test/media/';

    /**
     * @var PictureRenderer
     */
    private PictureRenderer $renderer;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn(self::MEDIA_URL);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $this->renderer = new PictureRenderer($storeManager, new CropOrder());
    }

    /**
     * Each source reserves its own crop's size, and the fallback image is the widest crop,
     * even when the mobile crop is loaded first
     *
     * @return void
     */
    public function testEverySourceCarriesItsOwnSize(): void
    {
        $html = $this->renderer->render([$this->mobileCrop(), $this->desktopCrop()], 'Run Rate Orders', true);

        self::assertStringContainsString(
            '<source srcset="' . self::MEDIA_URL . 'banner/desktop.jpg" width="1920" height="294"'
            . ' media="(min-width: 768px)" />',
            $html
        );
        self::assertStringContainsString(
            '<source srcset="' . self::MEDIA_URL . 'banner/mobile.jpg" width="892" height="588"'
            . ' media="(max-width: 767px)" />',
            $html
        );
        self::assertStringContainsString(
            '<img class="banner-slider-image" src="' . self::MEDIA_URL . 'banner/desktop.jpg"'
            . ' width="1920" height="294" alt="Run Rate Orders" loading="lazy" />',
            $html
        );
        self::assertLessThan(
            strpos($html, 'banner/mobile.jpg'),
            strpos($html, 'banner/desktop.jpg'),
            'The desktop source must come before the mobile one.'
        );
    }

    /**
     * AVIF and WebP sources precede the original format of the same breakpoint
     *
     * @return void
     */
    public function testModernFormatsPrecedeTheOriginal(): void
    {
        $html = $this->renderer->render([
            $this->desktopCrop([
                'generate_avif' => 1,
                'avif_image' => 'banner/desktop.avif',
                'generate_webp' => 1,
                'webp_image' => 'banner/desktop.webp',
            ]),
        ], 'Banner');

        $avif = strpos($html, 'type="image/avif"');
        $webp = strpos($html, 'type="image/webp"');
        $original = strpos($html, 'srcset="' . self::MEDIA_URL . 'banner/desktop.jpg"');

        self::assertNotFalse($avif);
        self::assertNotFalse($webp);
        self::assertNotFalse($original);
        self::assertLessThan($webp, $avif);
        self::assertLessThan($original, $webp);
        self::assertStringNotContainsString('loading=', $html);
    }

    /**
     * A breakpoint without a known size renders its source without one
     *
     * @return void
     */
    public function testSourceWithoutKnownSizeHasNoDimensions(): void
    {
        $html = $this->renderer->render([$this->crop(['crop_id' => 1, 'cropped_image' => 'banner/any.jpg'])], 'A');

        self::assertStringNotContainsString('width=', $html);
        self::assertStringContainsString('media="(min-width: 0px)"', $html);
    }

    /**
     * Nothing is rendered when no crop has an image, so the caller can fall back to the banner image
     *
     * @return void
     */
    public function testRendersNothingWithoutCroppedImages(): void
    {
        self::assertSame('', $this->renderer->render([$this->desktopCrop(['cropped_image' => ''])], 'Banner'));
        self::assertSame('', $this->renderer->render([], 'Banner'));
    }
}
