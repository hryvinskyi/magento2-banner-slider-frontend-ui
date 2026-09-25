<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Api\View;

use Hryvinskyi\BannerSliderFrontendUi\Api\Value\HtmlAttributes;

/**
 * A video slide: the wrapper that keeps the video's aspect ratio, the player element, and for a regular (not
 * background) video the click-to-load facade that stands in for the player until the visitor asks for it.
 *
 * An immutable read model, the `$view` of `slide/video-iframe.phtml` and `slide/video-local.phtml`.
 *
 * @api
 */
class VideoSlideView
{
    /**
     * @param HtmlAttributes $wrapper Attributes of the wrapper
     * @param HtmlAttributes $player Attributes of the player element (iframe or video)
     * @param bool $lazy Whether a player rendered directly may load lazily
     * @param string|null $posterUrl Poster of a video element; null for none
     * @param bool $facade Whether the player waits behind a click-to-load facade
     * @param HtmlAttributes|null $facadeImage Attributes of the facade's poster image; null for a plain placeholder
     * @param string $facadeLabel Accessible label of the facade button
     * @param string $fallbackText Text shown by browsers that cannot play the video element
     * @param string $overlayHtml Filtered banner content shown over the video; empty for none
     */
    public function __construct(
        private readonly HtmlAttributes $wrapper,
        private readonly HtmlAttributes $player,
        private readonly bool $lazy,
        private readonly ?string $posterUrl,
        private readonly bool $facade,
        private readonly ?HtmlAttributes $facadeImage,
        private readonly string $facadeLabel,
        private readonly string $fallbackText,
        private readonly string $overlayHtml
    ) {
    }

    /**
     * Attributes of the wrapper
     *
     * @return HtmlAttributes
     */
    public function getWrapper(): HtmlAttributes
    {
        return $this->wrapper;
    }

    /**
     * Attributes of the player element
     *
     * @return HtmlAttributes
     */
    public function getPlayer(): HtmlAttributes
    {
        return $this->player;
    }

    /**
     * Whether a player rendered directly (without a facade) may load lazily
     *
     * @return bool
     */
    public function isLazy(): bool
    {
        return $this->lazy;
    }

    /**
     * Poster of a video element, or null for none
     *
     * @return string|null
     */
    public function getPosterUrl(): ?string
    {
        return $this->posterUrl;
    }

    /**
     * Whether the player waits behind a click-to-load facade
     *
     * @return bool
     */
    public function hasFacade(): bool
    {
        return $this->facade;
    }

    /**
     * Attributes of the facade's poster image, or null for a plain placeholder
     *
     * @return HtmlAttributes|null
     */
    public function getFacadeImage(): ?HtmlAttributes
    {
        return $this->facadeImage;
    }

    /**
     * Accessible label of the facade button
     *
     * @return string
     */
    public function getFacadeLabel(): string
    {
        return $this->facadeLabel;
    }

    /**
     * Text shown by browsers that cannot play the video element
     *
     * @return string
     */
    public function getFallbackText(): string
    {
        return $this->fallbackText;
    }

    /**
     * Filtered banner content shown over the video; empty for none
     *
     * @return string
     */
    public function getOverlayHtml(): string
    {
        return $this->overlayHtml;
    }
}
