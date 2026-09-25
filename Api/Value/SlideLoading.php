<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Api\Value;

/**
 * How the media of one slide loads: lazily or eagerly, and whether it is the page's high-priority image.
 *
 * Images always decode asynchronously. High priority is only meaningful for an eager slide, so a lazy one never
 * carries it.
 *
 * @api
 */
class SlideLoading
{
    /**
     * @param bool $lazy Whether the browser may defer the media until it is near the viewport
     * @param bool $highPriority Whether the media is fetched ahead of other images (the largest paint candidate)
     * @throws \InvalidArgumentException When a lazy slide is marked high priority
     */
    public function __construct(
        private readonly bool $lazy,
        private readonly bool $highPriority
    ) {
        if ($lazy && $highPriority) {
            throw new \InvalidArgumentException('A lazily loaded slide cannot be fetched with high priority.');
        }
    }

    /**
     * Whether the media loads lazily
     *
     * @return bool
     */
    public function isLazy(): bool
    {
        return $this->lazy;
    }

    /**
     * Whether the media is fetched with high priority
     *
     * @return bool
     */
    public function isHighPriority(): bool
    {
        return $this->highPriority;
    }

    /**
     * The `loading`, `fetchpriority` and `decoding` attributes of an image
     *
     * @return HtmlAttributes
     */
    public function toImageAttributes(): HtmlAttributes
    {
        return new HtmlAttributes([
            'loading' => $this->lazy ? 'lazy' : 'eager',
            'fetchpriority' => $this->highPriority ? 'high' : null,
            'decoding' => 'async',
        ]);
    }

    /**
     * The `loading` attribute of an embedded frame; an eager frame omits it
     *
     * @return HtmlAttributes
     */
    public function toFrameAttributes(): HtmlAttributes
    {
        return new HtmlAttributes(['loading' => $this->lazy ? 'lazy' : null]);
    }
}
