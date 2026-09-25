<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\Render;

use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\ContentFilterInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideContext;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideLoading;
use Hryvinskyi\BannerSliderFrontendUi\Model\Render\CustomSlideRenderer;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\StorefrontFixtures;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\TemplateRendererSpy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CustomSlideRenderer::class)]
class CustomSlideRendererTest extends TestCase
{
    use StorefrontFixtures;

    /**
     * The filtered content renders in the custom template
     *
     * @return void
     */
    public function testRendersFilteredContent(): void
    {
        $filter = $this->createMock(ContentFilterInterface::class);
        $filter->method('filter')->with('{{block id="promo"}}')->willReturn('<div>Promo</div>');
        $templates = new TemplateRendererSpy();
        $renderer = new CustomSlideRenderer($filter, $templates);

        $html = $renderer->render($this->context('{{block id="promo"}}'));

        self::assertSame('[Hryvinskyi_BannerSliderFrontendUi::slide/custom.phtml]', $html);
        self::assertSame(
            '<div>Promo</div>',
            $templates->variable('Hryvinskyi_BannerSliderFrontendUi::slide/custom.phtml', 'contentHtml')
        );
        self::assertTrue($renderer->supports(BannerType::CUSTOM));
        self::assertFalse($renderer->supports(BannerType::IMAGE));
    }

    /**
     * Content the filter could not process renders no slide
     *
     * @return void
     */
    public function testFilterFailureRendersNothing(): void
    {
        $filter = $this->createMock(ContentFilterInterface::class);
        $filter->method('filter')->willReturn('');
        $templates = new TemplateRendererSpy();

        self::assertSame('', (new CustomSlideRenderer($filter, $templates))->render($this->context('{{broken')));
        self::assertSame([], $templates->getRenders());
    }

    /**
     * A slide context for a custom banner with content
     *
     * @param string $content
     * @return SlideContext
     */
    private function context(string $content): SlideContext
    {
        $banner = $this->banner(['type' => BannerType::CUSTOM, 'content' => $content]);

        return new SlideContext($this->slider(), $banner, 0, [], new SlideLoading(false, true));
    }
}
