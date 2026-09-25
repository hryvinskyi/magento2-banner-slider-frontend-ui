<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Api\View;

use Hryvinskyi\BannerSliderFrontendUi\Api\Value\HtmlAttributes;
use Hryvinskyi\BannerSliderFrontendUi\Api\View\PictureView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PictureView::class)]
class PictureViewTest extends TestCase
{
    /**
     * A picture with sources is responsive; its sources keep their order
     *
     * @return void
     */
    public function testResponsivePicture(): void
    {
        $wide = new HtmlAttributes(['media' => '(min-width: 768px)']);
        $narrow = new HtmlAttributes(['media' => '(min-width: 0px)']);
        $image = new HtmlAttributes(['src' => 'https://example.test/media/a.jpg']);

        $view = new PictureView([$wide, $narrow], $image);

        self::assertTrue($view->hasSources());
        self::assertSame([$wide, $narrow], $view->getSources());
        self::assertSame($image, $view->getImage());
    }

    /**
     * A picture without sources is a plain image
     *
     * @return void
     */
    public function testPlainImage(): void
    {
        $image = new HtmlAttributes(['src' => 'https://example.test/media/a.jpg']);

        $view = new PictureView([], $image);

        self::assertFalse($view->hasSources());
        self::assertSame([], $view->getSources());
        self::assertSame($image, $view->getImage());
    }
}
