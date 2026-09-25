<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\Render;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Media\MediaUrlResolverInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\PictureSource;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\ContentFilterInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideContext;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideLoading;
use Hryvinskyi\BannerSliderFrontendUi\Model\Attribute\ElementAttributePool;
use Hryvinskyi\BannerSliderFrontendUi\Model\Render\ImageSlideRenderer;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\ImageSlideView;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\PictureView;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\PictureViewBuilder;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\StorefrontFixtures;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\TemplateRendererSpy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageSlideRenderer::class)]
class ImageSlideRendererTest extends TestCase
{
    use StorefrontFixtures;

    private const PICTURE = 'Hryvinskyi_BannerSliderFrontendUi::slide/picture.phtml';
    private const SLIDE = 'Hryvinskyi_BannerSliderFrontendUi::slide/image.phtml';

    /**
     * @var TemplateRendererSpy
     */
    private TemplateRendererSpy $templates;

    /**
     * @var ImageSlideRenderer
     */
    private ImageSlideRenderer $renderer;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $resolver = $this->createMock(MediaUrlResolverInterface::class);
        $resolver->method('getUrl')->willReturnCallback(
            fn (string $path): string => 'https://shop.test/media/' . $path
        );
        $filter = $this->createMock(ContentFilterInterface::class);
        $filter->method('filter')->willReturnCallback(
            fn (?string $content): string => $content === null ? '' : '<b>' . $content . '</b>'
        );
        $this->templates = new TemplateRendererSpy();
        $this->renderer = new ImageSlideRenderer(
            new PictureViewBuilder($resolver),
            $this->templates,
            $filter,
            new ElementAttributePool()
        );
    }

    /**
     * Image banners only
     *
     * @return void
     */
    public function testSupportsImageBannersOnly(): void
    {
        self::assertTrue($this->renderer->supports(BannerType::IMAGE));
        self::assertFalse($this->renderer->supports(BannerType::VIDEO));
        self::assertFalse($this->renderer->supports(BannerType::CUSTOM));
    }

    /**
     * Crops render as a responsive picture inside a new-tab link, with the content as an overlay
     *
     * @return void
     */
    public function testPictureInsideALinkWithOverlay(): void
    {
        $banner = $this->banner([
            'title' => 'Spring sale',
            'link' => 'https://shop.test/sale',
            'newTab' => true,
            'content' => 'Save 20%',
        ]);
        $source = $this->pictureSource('desktop', '(min-width: 768px)', 1920, 600);

        $html = $this->renderer->render($this->context($banner, [$source]));

        self::assertSame('[' . self::SLIDE . ']', $html);
        $picture = $this->templates->variable(self::PICTURE, 'view');
        self::assertInstanceOf(PictureView::class, $picture);
        self::assertCount(2, $picture->getSources());
        self::assertSame('Spring sale', $picture->getImage()->get('alt'));
        $view = $this->templates->variable(self::SLIDE, 'view');
        self::assertInstanceOf(ImageSlideView::class, $view);
        self::assertSame('[' . self::PICTURE . ']', $view->getMediaHtml());
        self::assertSame('<b>Save 20%</b>', $view->getOverlayHtml());
        self::assertSame([
            'class' => 'hbs-slide__link',
            'href' => 'https://shop.test/sale',
            'title' => 'Spring sale',
            'aria-label' => null,
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
        ], $view->getLink()?->toArray());
    }

    /**
     * A first slide cropped for phones only: the phone crop as its source, the banner image with the banner's stored
     * size as its `<img>` for wider screens, loading eagerly with high priority
     *
     * @return void
     */
    public function testFirstSlideCroppedForPhonesOnlyFallsBackToTheBannerImage(): void
    {
        $banner = $this->banner([
            'title' => 'Networking',
            'image' => 'banner_slider/image/networking.png',
            'dimensions' => new Dimensions(1365, 209),
        ]);
        $mobile = $this->pictureSource('mobile', '(max-width: 767px)', 892, 588, 0);

        $this->renderer->render($this->context($banner, [$mobile]));

        $picture = $this->templates->variable(self::PICTURE, 'view');
        self::assertInstanceOf(PictureView::class, $picture);
        self::assertSame(['(max-width: 767px)', '(max-width: 767px)'], array_map(
            fn ($source): mixed => $source->get('media'),
            $picture->getSources()
        ));
        self::assertSame([
            'class' => 'hbs-slide__media',
            'src' => 'https://shop.test/media/banner_slider/image/networking.png',
            'alt' => 'Networking',
            'width' => 1365,
            'height' => 209,
            'loading' => 'eager',
            'fetchpriority' => 'high',
            'decoding' => 'async',
        ], $picture->getImage()->toArray());
    }

    /**
     * Without crops the banner image renders with its stored size; without a title the image is decorative
     *
     * @return void
     */
    public function testPlainImageWithoutTitleIsDecorative(): void
    {
        $banner = $this->banner(['image' => 'banner_slider/image/a.jpg', 'dimensions' => new Dimensions(1600, 500)]);

        $this->renderer->render($this->context($banner, []));

        $picture = $this->templates->variable(self::PICTURE, 'view');
        self::assertInstanceOf(PictureView::class, $picture);
        self::assertFalse($picture->hasSources());
        self::assertSame('', $picture->getImage()->get('alt'));
        self::assertSame(1600, $picture->getImage()->get('width'));
        self::assertSame('https://shop.test/media/banner_slider/image/a.jpg', $picture->getImage()->get('src'));
        $view = $this->templates->variable(self::SLIDE, 'view');
        self::assertInstanceOf(ImageSlideView::class, $view);
        self::assertNull($view->getLink());
        self::assertSame('', $view->getOverlayHtml());
    }

    /**
     * A same-tab link has no target or rel; without a banner title its image is decorative, so the banner name
     * names the link
     *
     * @return void
     */
    public function testSameTabLinkWithoutTitleIsNamedByTheBannerName(): void
    {
        $banner = $this->banner(['image' => 'banner_slider/image/a.jpg', 'link' => '/sale', 'name' => ' Summer ']);

        $this->renderer->render($this->context($banner, []));

        $view = $this->templates->variable(self::SLIDE, 'view');
        self::assertInstanceOf(ImageSlideView::class, $view);
        self::assertSame([
            'class' => 'hbs-slide__link',
            'href' => '/sale',
            'title' => null,
            'aria-label' => 'Summer',
            'target' => null,
            'rel' => null,
        ], $view->getLink()?->toArray());
        $picture = $this->templates->variable(self::PICTURE, 'view');
        self::assertInstanceOf(PictureView::class, $picture);
        self::assertSame('', $picture->getImage()->get('alt'));
    }

    /**
     * A link around a decorative image of a banner without a name still gets a name
     *
     * @return void
     */
    public function testLinkOfAnUnnamedBannerGetsAGenericName(): void
    {
        $banner = $this->banner(['image' => 'banner_slider/image/a.jpg', 'link' => '/sale', 'name' => '']);

        $this->renderer->render($this->context($banner, []));

        $view = $this->templates->variable(self::SLIDE, 'view');
        self::assertInstanceOf(ImageSlideView::class, $view);
        self::assertSame('Banner', $view->getLink()?->get('aria-label'));
    }

    /**
     * A banner with neither crops nor an image cannot be rendered
     *
     * @return void
     */
    public function testNothingToShowFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->renderer->render($this->context($this->banner(), []));
    }

    /**
     * A slide context for the banner
     *
     * @param BannerInterface $banner
     * @param list<PictureSource> $sources
     * @return SlideContext
     */
    private function context(BannerInterface $banner, array $sources): SlideContext
    {
        return new SlideContext($this->slider(), $banner, 0, $sources, new SlideLoading(false, true));
    }
}
