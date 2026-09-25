<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\Render;

use Hryvinskyi\BannerSliderApi\Api\Config\VideoConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Media\MediaUrlResolverInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\AspectRatio;
use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Hryvinskyi\BannerSliderApi\Api\Value\EmbedKind;
use Hryvinskyi\BannerSliderApi\Api\Value\EmbedOptions;
use Hryvinskyi\BannerSliderApi\Api\Value\VideoData;
use Hryvinskyi\BannerSliderApi\Api\Video\ProviderInterface;
use Hryvinskyi\BannerSliderApi\Api\Video\ProviderResolverInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\ContentFilterInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideContext;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideLoading;
use Hryvinskyi\BannerSliderFrontendUi\Api\View\VideoSlideView;
use Hryvinskyi\BannerSliderFrontendUi\Model\Render\VideoSlideRenderer;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\PictureViewBuilder;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\StorefrontFixtures;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\TemplateRendererSpy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(VideoSlideRenderer::class)]
class VideoSlideRendererTest extends TestCase
{
    use StorefrontFixtures;

    private const IFRAME = 'Vendor_Module::iframe.phtml';
    private const LOCAL = 'Vendor_Module::local.phtml';

    /**
     * @var ProviderResolverInterface&MockObject
     */
    private MockObject $providerResolver;

    /**
     * @var LoggerInterface&MockObject
     */
    private MockObject $logger;

    /**
     * @var TemplateRendererSpy
     */
    private TemplateRendererSpy $templates;

    /**
     * @var VideoSlideRenderer
     */
    private VideoSlideRenderer $renderer;

    /**
     * @var list<EmbedOptions>
     */
    private array $options = [];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->providerResolver = $this->createMock(ProviderResolverInterface::class);
        $videoConfig = $this->createMock(VideoConfigInterface::class);
        $videoConfig->method('isPrivacyEnhanced')->willReturn(true);
        $resolver = $this->createMock(MediaUrlResolverInterface::class);
        $resolver->method('getUrl')->willReturnCallback(function (string $path): string {
            if (str_contains($path, '..')) {
                throw new \InvalidArgumentException('unsafe');
            }

            return 'https://shop.test/media/' . $path;
        });
        $filter = $this->createMock(ContentFilterInterface::class);
        $filter->method('filter')->willReturnCallback(fn (?string $content): string => (string)$content);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->templates = new TemplateRendererSpy();
        $this->renderer = new VideoSlideRenderer(
            $this->providerResolver,
            $videoConfig,
            $resolver,
            new PictureViewBuilder($resolver),
            $this->templates,
            $filter,
            $this->logger,
            ['iframe' => self::IFRAME, 'video' => self::LOCAL]
        );
    }

    /**
     * Video banners only
     *
     * @return void
     */
    public function testSupportsVideoBannersOnly(): void
    {
        self::assertTrue($this->renderer->supports(BannerType::VIDEO));
        self::assertFalse($this->renderer->supports(BannerType::IMAGE));
    }

    /**
     * A regular iframe video waits behind a facade with the banner image as poster; the player is built to start
     * once inserted, with controls and the privacy-preserving embed
     *
     * @return void
     */
    public function testRegularIframeVideoRendersAFacade(): void
    {
        $this->providerResolver->method('resolve')->with('https://youtu.be/abcdefghijk')
            ->willReturn($this->provider('youtube', EmbedKind::IFRAME));
        $banner = $this->banner([
            'type' => BannerType::VIDEO,
            'videoUrl' => 'https://youtu.be/abcdefghijk',
            'title' => 'Product tour',
            'image' => 'banner_slider/image/poster.jpg',
            'ratio' => new AspectRatio(4, 3),
            'content' => '<p>Watch</p>',
        ]);

        self::assertSame('[' . self::IFRAME . ']', $this->renderer->render($this->context($banner, 1, true)));

        $view = $this->view(self::IFRAME);
        self::assertTrue($view->hasFacade());
        self::assertSame('Play video: Product tour', $view->getFacadeLabel());
        $poster = $view->getFacadeImage();
        self::assertNotNull($poster);
        self::assertSame('hbs-slide__poster', $poster->get('class'));
        self::assertSame('https://shop.test/media/banner_slider/image/poster.jpg', $poster->get('src'));
        self::assertSame('lazy', $poster->get('loading'));
        self::assertSame([
            'class' => 'hbs-slide__player',
            'src' => 'https://embed.test/youtube/abcdefghijk',
            'title' => 'Product tour',
            'data-hbs-video' => 'iframe',
            'data-hbs-provider' => 'youtube',
            'allow' => 'autoplay',
            'allowfullscreen' => true,
        ], $view->getPlayer()->toArray());
        self::assertSame([
            'class' => 'hbs-slide__video hbs-slide__media',
            'style' => '--hbs-aspect-ratio: 4 / 3',
            'data-hbs-background' => false,
        ], $view->getWrapper()->toArray());
        self::assertSame('<p>Watch</p>', $view->getOverlayHtml());
        self::assertCount(1, $this->options);
        $options = $this->options[0];
        self::assertFalse($options->isBackground());
        self::assertTrue($options->isAutoplay());
        self::assertFalse($options->isMuted());
        self::assertFalse($options->isLoop());
        self::assertTrue($options->hasControls());
        self::assertTrue($options->isPrivacyEnhanced());
    }

    /**
     * A background local video on a later slide renders its player directly, muted and looped, but waits for its
     * slide: the URL sits in `data-hbs-src` and the element loses `autoplay`; the uploaded file wins over the URL
     *
     * @return void
     */
    public function testBackgroundLocalVideoOnALaterSlideWaitsForItsSlide(): void
    {
        $this->providerResolver->method('resolve')->with('banner_slider/video/loop.mp4')
            ->willReturn($this->provider('local_mp4', EmbedKind::VIDEO, ['autoplay' => true, 'preload' => 'metadata']));
        $banner = $this->banner([
            'type' => BannerType::VIDEO,
            'videoPath' => 'banner_slider/video/loop.mp4',
            'videoUrl' => 'https://youtu.be/abcdefghijk',
            'background' => true,
            'image' => 'banner_slider/image/poster.jpg',
        ]);

        $this->renderer->render($this->context($banner, 2, true));

        $view = $this->view(self::LOCAL);
        self::assertFalse($view->hasFacade());
        self::assertNull($view->getFacadeImage());
        self::assertTrue($view->isLazy());
        self::assertSame('https://shop.test/media/banner_slider/image/poster.jpg', $view->getPosterUrl());
        $player = $view->getPlayer();
        self::assertSame('Video', $player->get('title'));
        self::assertSame('video', $player->get('data-hbs-video'));
        self::assertNull($player->get('src'));
        self::assertSame('https://embed.test/local_mp4/abcdefghijk', $player->get('data-hbs-src'));
        self::assertNull($player->get('autoplay'));
        self::assertSame('metadata', $player->get('preload'));
        self::assertSame(
            'hbs-slide__video hbs-slide__media hbs-slide__video--background',
            $view->getWrapper()->get('class')
        );
        self::assertTrue($view->getWrapper()->get('data-hbs-background'));
        self::assertSame('--hbs-aspect-ratio: 16 / 9', $view->getWrapper()->get('style'));
        self::assertSame('Your browser does not support the video tag.', $view->getFallbackText());
        $options = $this->options[0];
        self::assertTrue($options->isBackground());
        self::assertTrue($options->isAutoplay(), 'the waiting URL still starts the player once it is loaded');
        self::assertTrue($options->isMuted());
        self::assertTrue($options->isLoop());
        self::assertFalse($options->hasControls());
    }

    /**
     * The first slide's background video starts with the page: its player keeps `src` and `autoplay`
     *
     * @param string $code
     * @param string $kind
     * @param string $template
     * @return void
     */
    #[TestWith(['youtube', 'iframe', self::IFRAME])]
    #[TestWith(['local_mp4', 'video', self::LOCAL])]
    public function testFirstSlideBackgroundVideoStartsWithThePage(string $code, string $kind, string $template): void
    {
        $this->providerResolver->method('resolve')
            ->willReturn($this->provider($code, EmbedKind::from($kind), ['autoplay' => true]));
        $banner = $this->banner(['type' => BannerType::VIDEO, 'videoUrl' => 'x', 'background' => true]);

        $this->renderer->render($this->context($banner, 0, true));

        $player = $this->view($template)->getPlayer();
        self::assertSame('https://embed.test/' . $code . '/abcdefghijk', $player->get('src'));
        self::assertNull($player->get('data-hbs-src'));
        self::assertTrue($player->get('autoplay'));
        self::assertFalse($this->view($template)->isLazy());
    }

    /**
     * A background embedded video on a later slide keeps its autoplaying URL in `data-hbs-src`
     *
     * @return void
     */
    public function testBackgroundIframeVideoOnALaterSlideWaitsForItsSlide(): void
    {
        $this->providerResolver->method('resolve')->willReturn($this->provider('vimeo', EmbedKind::IFRAME));
        $banner = $this->banner(['type' => BannerType::VIDEO, 'videoUrl' => 'x', 'background' => true]);

        $this->renderer->render($this->context($banner, 1, false));

        $player = $this->view(self::IFRAME)->getPlayer();
        self::assertNull($player->get('src'));
        self::assertSame('https://embed.test/vimeo/abcdefghijk', $player->get('data-hbs-src'));
        self::assertSame('autoplay', $player->get('allow'));
    }

    /**
     * A regular video on a later slide is not deferred: the facade already keeps the player from loading
     *
     * @return void
     */
    public function testRegularVideoOnALaterSlideKeepsItsSourceInTheTemplate(): void
    {
        $this->providerResolver->method('resolve')->willReturn($this->provider('youtube', EmbedKind::IFRAME));
        $banner = $this->banner(['type' => BannerType::VIDEO, 'videoUrl' => 'x']);

        $this->renderer->render($this->context($banner, 3, true));

        $player = $this->view(self::IFRAME)->getPlayer();
        self::assertSame('https://embed.test/youtube/abcdefghijk', $player->get('src'));
        self::assertNull($player->get('data-hbs-src'));
    }

    /**
     * A banner image with an unsafe path costs the video its poster only; the failure is logged
     *
     * @param bool $background
     * @return void
     */
    #[TestWith([false])]
    #[TestWith([true])]
    public function testUnusablePosterDropsOnlyThePoster(bool $background): void
    {
        $this->providerResolver->method('resolve')->willReturn($this->provider('local_mp4', EmbedKind::VIDEO));
        $this->logger->expects(self::once())->method('warning')->with(
            self::anything(),
            self::callback(fn (array $context): bool => $context['banner_id'] === 11
                && $context['exception'] instanceof \InvalidArgumentException)
        );
        $banner = $this->banner([
            'type' => BannerType::VIDEO,
            'videoUrl' => 'x',
            'background' => $background,
            'image' => '../outside.jpg',
        ]);

        self::assertSame('[' . self::LOCAL . ']', $this->renderer->render($this->context($banner, 0, true)));

        $view = $this->view(self::LOCAL);
        self::assertNull($view->getPosterUrl());
        self::assertNull($view->getFacadeImage());
        self::assertSame(!$background, $view->hasFacade());
    }

    /**
     * A regular video without an image gets a plain facade; the first slide is not lazy
     *
     * @return void
     */
    public function testFacadeWithoutPoster(): void
    {
        $this->providerResolver->method('resolve')->willReturn($this->provider('vimeo', EmbedKind::IFRAME));
        $banner = $this->banner(['type' => BannerType::VIDEO, 'videoUrl' => 'https://vimeo.com/1']);

        $this->renderer->render($this->context($banner, 0, true));

        $view = $this->view(self::IFRAME);
        self::assertTrue($view->hasFacade());
        self::assertNull($view->getFacadeImage());
        self::assertNull($view->getPosterUrl());
        self::assertFalse($view->isLazy());
        self::assertSame('Play video: Video', $view->getFacadeLabel());
    }

    /**
     * A banner without a video source cannot be rendered
     *
     * @return void
     */
    public function testNoSourceFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $banner = $this->banner(['type' => BannerType::VIDEO, 'videoUrl' => ' ']);
        $this->renderer->render($this->context($banner, 0, true));
    }

    /**
     * A source no provider understands cannot be rendered
     *
     * @return void
     */
    public function testUnknownSourceFails(): void
    {
        $this->providerResolver->method('resolve')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        $banner = $this->banner(['type' => BannerType::VIDEO, 'videoUrl' => 'x']);
        $this->renderer->render($this->context($banner, 0, true));
    }

    /**
     * An embed kind without a template fails loudly
     *
     * @return void
     */
    public function testKindWithoutTemplateFails(): void
    {
        $this->providerResolver->method('resolve')->willReturn($this->provider('youtube', EmbedKind::IFRAME));
        $renderer = new VideoSlideRenderer(
            $this->providerResolver,
            $this->createMock(VideoConfigInterface::class),
            $this->createMock(MediaUrlResolverInterface::class),
            new PictureViewBuilder($this->createMock(MediaUrlResolverInterface::class)),
            $this->templates,
            $this->createMock(ContentFilterInterface::class),
            $this->logger,
            ['video' => self::LOCAL]
        );

        $this->expectException(\InvalidArgumentException::class);

        $renderer->render($this->context($this->banner(['type' => BannerType::VIDEO, 'videoUrl' => 'x']), 0, false));
    }

    /**
     * A provider that records the options it is asked for
     *
     * @param string $code
     * @param EmbedKind $kind
     * @param array<string,string|bool> $attributes Embed attributes the provider returns
     * @return ProviderInterface
     */
    private function provider(
        string $code,
        EmbedKind $kind,
        array $attributes = ['allow' => 'autoplay', 'allowfullscreen' => true]
    ): ProviderInterface {
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('getCode')->willReturn($code);
        $provider->method('getEmbedKind')->willReturn($kind);
        $provider->method('parse')->willReturnCallback(
            fn (string $source): VideoData => new VideoData($code, 'abcdefghijk', $source)
        );
        $provider->method('getEmbedUrl')->willReturnCallback(
            function (VideoData $data, EmbedOptions $options) use ($code): string {
                $this->options[] = $options;

                return 'https://embed.test/' . $code . '/' . $data->getVideoId();
            }
        );
        $provider->method('getEmbedAttributes')->willReturn($attributes);

        return $provider;
    }

    /**
     * A slide context for the banner
     *
     * @param BannerInterface $banner
     * @param int $position
     * @param bool $sliderLazy
     * @return SlideContext
     */
    private function context(BannerInterface $banner, int $position, bool $sliderLazy): SlideContext
    {
        $loading = $position === 0 ? new SlideLoading(false, true) : new SlideLoading($sliderLazy, false);

        return new SlideContext($this->slider(), $banner, $position, [], $loading);
    }

    /**
     * The view passed to a template
     *
     * @param string $template
     * @return VideoSlideView
     */
    private function view(string $template): VideoSlideView
    {
        $view = $this->templates->variable($template, 'view');
        self::assertInstanceOf(VideoSlideView::class, $view);

        return $view;
    }
}
