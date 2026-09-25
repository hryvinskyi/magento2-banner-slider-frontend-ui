<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\View;

use Hryvinskyi\BannerSliderFrontendUi\Model\View\FrontendAssets;
use Magento\Framework\View\Asset\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FrontendAssets::class)]
class FrontendAssetsTest extends TestCase
{
    /**
     * File ids resolve to URLs; stylesheets keep their order, scripts their names
     *
     * @return void
     */
    public function testResolvesUrls(): void
    {
        $repository = $this->createMock(Repository::class);
        $repository->method('getUrl')->willReturnCallback(fn (string $id): string => 'https://cdn.test/' . $id);

        $assets = new FrontendAssets(
            $repository,
            ['splide' => 'Vendor_A::a.css', 'slider' => 'Vendor_B::b.css'],
            ['splide' => 'Vendor_A::a.js', 'slider' => 'Vendor_B::b.js']
        );

        self::assertSame(
            ['https://cdn.test/Vendor_A::a.css', 'https://cdn.test/Vendor_B::b.css'],
            $assets->getStylesheetUrls()
        );
        self::assertSame(
            ['splide' => 'https://cdn.test/Vendor_A::a.js', 'slider' => 'https://cdn.test/Vendor_B::b.js'],
            $assets->getScriptUrls()
        );
    }
}
