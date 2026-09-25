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
 * An image slide: its picture, the link around it (if any) and the content shown over it (if any).
 *
 * An immutable read model, the `$view` of `slide/image.phtml`.
 *
 * @api
 */
class ImageSlideView
{
    /**
     * @param string $mediaHtml Rendered picture or image
     * @param HtmlAttributes|null $link Attributes of the link around the media; null without a link
     * @param string $overlayHtml Filtered banner content shown over the media; empty for none
     */
    public function __construct(
        private readonly string $mediaHtml,
        private readonly ?HtmlAttributes $link,
        private readonly string $overlayHtml
    ) {
    }

    /**
     * Rendered picture or image
     *
     * @return string
     */
    public function getMediaHtml(): string
    {
        return $this->mediaHtml;
    }

    /**
     * Attributes of the link around the media, or null without a link
     *
     * @return HtmlAttributes|null
     */
    public function getLink(): ?HtmlAttributes
    {
        return $this->link;
    }

    /**
     * Filtered banner content shown over the media; empty for none
     *
     * @return string
     */
    public function getOverlayHtml(): string
    {
        return $this->overlayHtml;
    }
}
