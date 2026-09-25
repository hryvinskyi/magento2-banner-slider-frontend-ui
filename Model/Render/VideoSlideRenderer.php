<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\Render;

use Hryvinskyi\BannerSliderApi\Api\Config\VideoConfigInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Media\MediaUrlResolverInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Hryvinskyi\BannerSliderApi\Api\Value\EmbedOptions;
use Hryvinskyi\BannerSliderApi\Api\Video\ProviderResolverInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\ContentFilterInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\SlideRendererInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\TemplateRendererInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\HtmlAttributes;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideContext;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\PictureViewBuilder;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\VideoSlideView;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Renders video banners through the video provider that understands the banner's source.
 *
 * The provider decides the embed: its URL, its attributes, and whether it is an iframe or a video element, which
 * picks the template (`templates` in `di.xml`, keyed by embed kind). The uploaded file takes precedence over the
 * video URL, as it always has.
 *
 * - A background video plays muted, looped and without controls, and is rendered directly. Only the first slide's
 *   background video starts with the page. A background video on a later slide is rendered without a `src`: its URL
 *   waits in `data-hbs-src`, and the slider script sets it once the slide is shown (and the visitor has not paused
 *   it), so nothing is downloaded or played for a slide nobody has seen. The waiting URL keeps its autoplay
 *   parameters, because a player loaded that late must start by itself: an embedded player cannot be told to play
 *   before it has loaded, and some providers' background mode always starts playing. A waiting `<video>` also loses
 *   its `autoplay` attribute; the script starts it explicitly.
 * - A regular video waits behind a click-to-load facade: a button with the banner image as poster (or a plain
 *   placeholder, never an image from the video provider) and the real player inside a `<template>`, built to start
 *   playing once inserted. Nothing is requested from the provider before the visitor asks for the video.
 * - Both use the provider's privacy-preserving variant when the store's video settings ask for it.
 * - The wrapper keeps the video's aspect ratio through the `--hbs-aspect-ratio` custom property.
 * - The banner content, filtered, is shown over the video.
 * - A banner image that cannot be turned into a URL (an unsafe stored path) costs the video its poster only; it is
 *   logged and the video still renders.
 */
class VideoSlideRenderer implements SlideRendererInterface
{
    private const DEFERRED_SOURCE_ATTRIBUTE = 'data-hbs-src';

    /**
     * @param ProviderResolverInterface $providerResolver
     * @param VideoConfigInterface $videoConfig
     * @param MediaUrlResolverInterface $mediaUrlResolver
     * @param PictureViewBuilder $pictureViewBuilder
     * @param TemplateRendererInterface $templateRenderer
     * @param ContentFilterInterface $contentFilter
     * @param LoggerInterface $logger
     * @param array<string,string> $templates Embed kind value => template of the slide
     */
    public function __construct(
        private readonly ProviderResolverInterface $providerResolver,
        private readonly VideoConfigInterface $videoConfig,
        private readonly MediaUrlResolverInterface $mediaUrlResolver,
        private readonly PictureViewBuilder $pictureViewBuilder,
        private readonly TemplateRendererInterface $templateRenderer,
        private readonly ContentFilterInterface $contentFilter,
        private readonly LoggerInterface $logger,
        private readonly array $templates = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function supports(BannerType $type): bool
    {
        return $type === BannerType::VIDEO;
    }

    /**
     * @inheritDoc
     */
    public function render(SlideContext $context): string
    {
        $banner = $context->getBanner();
        $source = $this->source($banner);
        $provider = $this->providerResolver->resolve($source);
        if ($provider === null) {
            throw new \InvalidArgumentException(sprintf(
                'No video provider understands the source of video banner %d.',
                (int)$banner->getBannerId()
            ));
        }

        $kind = $provider->getEmbedKind();
        $template = $this->templates[$kind->value] ?? null;
        if ($template === null) {
            throw new \InvalidArgumentException(
                sprintf('No video template is registered for "%s" embeds.', $kind->value)
            );
        }

        $background = $banner->isVideoAsBackground();
        $data = $provider->parse($source);
        $options = $this->embedOptions($background);
        $title = trim((string)$banner->getTitle());
        $title = $title !== '' ? $title : (string)__('Video');

        $player = (new HtmlAttributes([
            'class' => 'hbs-slide__player',
            'src' => $provider->getEmbedUrl($data, $options),
            'title' => $title,
            'data-hbs-video' => $kind->value,
            'data-hbs-provider' => $provider->getCode(),
        ]))->merge(new HtmlAttributes($provider->getEmbedAttributes($data, $options)));
        if ($background && !$context->isFirst()) {
            $player = $this->deferred($player);
        }

        $posterUrl = $this->posterUrl($banner);
        $wrapper = new HtmlAttributes([
            'class' => 'hbs-slide__video hbs-slide__media' . ($background ? ' hbs-slide__video--background' : ''),
            'style' => '--hbs-aspect-ratio: ' . $banner->getVideoAspectRatio()->toCss(),
            'data-hbs-background' => $background,
        ]);

        return $this->templateRenderer->render($template, [
            'view' => new VideoSlideView(
                $wrapper,
                $player,
                $context->getLoading()->isLazy(),
                $posterUrl,
                !$background,
                $background || $posterUrl === null ? null : $this->facadeImage($context),
                (string)__('Play video: %1', $title),
                (string)__('Your browser does not support the video tag.'),
                $this->contentFilter->filter($banner->getContent())
            ),
        ]);
    }

    /**
     * The video source of the banner: its uploaded file, else its video URL
     *
     * @param BannerInterface $banner
     * @return string
     * @throws \InvalidArgumentException When the banner has neither
     */
    private function source(BannerInterface $banner): string
    {
        foreach ([$banner->getVideoPath(), $banner->getVideoUrl()] as $candidate) {
            $source = trim((string)$candidate);
            if ($source !== '') {
                return $source;
            }
        }

        throw new \InvalidArgumentException(
            sprintf('Video banner %d has neither a video file nor a video URL.', (int)$banner->getBannerId())
        );
    }

    /**
     * Embed options of a background video or of a regular player
     *
     * A background video plays muted and looped without controls; a regular one is a player that starts once the
     * visitor has asked for it.
     *
     * @param bool $background
     * @return EmbedOptions
     */
    private function embedOptions(bool $background): EmbedOptions
    {
        return new EmbedOptions(
            background: $background,
            autoplay: true,
            muted: $background,
            loop: $background,
            controls: !$background,
            privacyEnhanced: $this->videoConfig->isPrivacyEnhanced()
        );
    }

    /**
     * The player of a background video on a later slide: its URL waits until the slide is shown
     *
     * @param HtmlAttributes $player
     * @return HtmlAttributes
     */
    private function deferred(HtmlAttributes $player): HtmlAttributes
    {
        return $player->merge(new HtmlAttributes([
            'src' => null,
            self::DEFERRED_SOURCE_ATTRIBUTE => $player->get('src'),
            'autoplay' => null,
        ]));
    }

    /**
     * URL of the banner image as a video poster, or null when it has none or it cannot be used
     *
     * @param BannerInterface $banner
     * @return string|null
     * @throws NoSuchEntityException When the current store cannot be resolved
     */
    private function posterUrl(BannerInterface $banner): ?string
    {
        $image = $banner->getImage();
        if ($image === null || $image === '') {
            return null;
        }

        try {
            return $this->mediaUrlResolver->getUrl($image);
        } catch (\InvalidArgumentException $e) {
            $this->logPosterFailure($banner, $e);

            return null;
        }
    }

    /**
     * Attributes of the facade's poster image, for a banner whose image resolves to a URL
     *
     * @param SlideContext $context
     * @return HtmlAttributes
     * @throws \InvalidArgumentException When the stored path is not a safe media path
     * @throws NoSuchEntityException When the current store cannot be resolved
     */
    private function facadeImage(SlideContext $context): HtmlAttributes
    {
        $banner = $context->getBanner();

        return (new HtmlAttributes(['class' => 'hbs-slide__poster']))->merge($this->pictureViewBuilder->image(
            (string)$banner->getImage(),
            $banner->getImageDimensions(),
            '',
            $context->getLoading()
        ));
    }

    /**
     * Log a banner image that could not be used as a video poster
     *
     * @param BannerInterface $banner
     * @param \InvalidArgumentException $exception
     * @return void
     */
    private function logPosterFailure(BannerInterface $banner, \InvalidArgumentException $exception): void
    {
        $this->logger->warning('Banner slider: the image of a video banner cannot be used as its poster.', [
            'banner_id' => $banner->getBannerId(),
            'exception' => $exception,
        ]);
    }
}
