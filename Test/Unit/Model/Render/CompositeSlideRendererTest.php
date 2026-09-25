<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\Render;

use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\SlideRendererInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideContext;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideLoading;
use Hryvinskyi\BannerSliderFrontendUi\Model\Render\CompositeSlideRenderer;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\StorefrontFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompositeSlideRenderer::class)]
class CompositeSlideRendererTest extends TestCase
{
    use StorefrontFixtures;

    /**
     * The first renderer supporting the banner type renders it
     *
     * @return void
     */
    public function testFirstSupportingRendererRenders(): void
    {
        $context = $this->context(BannerType::VIDEO);
        $image = $this->renderer(BannerType::IMAGE, 'image');
        $video = $this->renderer(BannerType::VIDEO, 'video');
        $override = $this->renderer(BannerType::VIDEO, 'late video');

        $composite = new CompositeSlideRenderer(['image' => $image, 'video' => $video, 'late' => $override]);

        self::assertSame('video', $composite->render($context));
        self::assertTrue($composite->supports(BannerType::IMAGE));
        self::assertFalse($composite->supports(BannerType::CUSTOM));
    }

    /**
     * A banner type without a renderer fails loudly
     *
     * @return void
     */
    public function testUnsupportedTypeFails(): void
    {
        $composite = new CompositeSlideRenderer(['image' => $this->renderer(BannerType::IMAGE, 'image')]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom HTML');

        $composite->render($this->context(BannerType::CUSTOM));
    }

    /**
     * A renderer for one type
     *
     * @param BannerType $type
     * @param string $html
     * @return SlideRendererInterface
     */
    private function renderer(BannerType $type, string $html): SlideRendererInterface
    {
        $renderer = $this->createMock(SlideRendererInterface::class);
        $renderer->method('supports')->willReturnCallback(fn (BannerType $candidate): bool => $candidate === $type);
        $renderer->method('render')->willReturn($html);

        return $renderer;
    }

    /**
     * A slide context for a banner of a type
     *
     * @param BannerType $type
     * @return SlideContext
     */
    private function context(BannerType $type): SlideContext
    {
        $banner = $this->banner(['type' => $type]);

        return new SlideContext($this->slider(), $banner, 0, [], new SlideLoading(false, true));
    }
}
