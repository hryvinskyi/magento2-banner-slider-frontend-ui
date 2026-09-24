<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\ResponsiveImage;

use Hryvinskyi\BannerSliderFrontendUi\Model\ResponsiveImage\CropOrder;
use Hryvinskyi\BannerSliderFrontendUi\Model\ResponsiveImage\PreloadLinkBuilder;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Hryvinskyi\BannerSliderFrontendUi\Model\ResponsiveImage\PreloadLinkBuilder
 */
class PreloadLinkBuilderTest extends TestCase
{
    use CropFixtureTrait;

    private const MEDIA_URL = 'https://shop.test/media/';

    /**
     * @var PreloadLinkBuilder
     */
    private PreloadLinkBuilder $builder;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn(self::MEDIA_URL);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $this->builder = new PreloadLinkBuilder($storeManager, new CropOrder());
    }

    /**
     * The original format covers every breakpoint, widest first, when no modern format exists
     *
     * @return void
     */
    public function testOriginalFormatCoversEveryBreakpoint(): void
    {
        self::assertSame(
            [[
                'rel' => 'preload',
                'as' => 'image',
                'href' => self::MEDIA_URL . 'banner/desktop.jpg',
                'imagesrcset' => self::MEDIA_URL . 'banner/desktop.jpg 1920w, '
                    . self::MEDIA_URL . 'banner/mobile.jpg 892w',
                'imagesizes' => '(min-width: 768px) 1920px, (max-width: 767px) 892px, 100vw',
            ]],
            $this->builder->build([$this->mobileCrop(), $this->desktopCrop()])
        );
    }

    /**
     * Only the most preferred format is preloaded
     *
     * @return void
     */
    public function testPreloadsOnlyTheMostPreferredFormat(): void
    {
        $links = $this->builder->build([
            $this->desktopCrop(['generate_webp' => 1, 'webp_image' => 'banner/desktop.webp']),
            $this->mobileCrop(['generate_webp' => 1, 'webp_image' => 'banner/mobile.webp']),
        ]);

        self::assertCount(1, $links);
        self::assertSame('image/webp', $links[0]['type'] ?? null);
        self::assertSame(self::MEDIA_URL . 'banner/desktop.webp', $links[0]['href']);
    }

    /**
     * Crops without images produce no preload link
     *
     * @return void
     */
    public function testNoLinkWithoutCroppedImages(): void
    {
        self::assertSame([], $this->builder->build([$this->desktopCrop(['cropped_image' => null])]));
    }
}
