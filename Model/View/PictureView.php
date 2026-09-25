<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\View;

use Hryvinskyi\BannerSliderFrontendUi\Api\Value\HtmlAttributes;

/**
 * A slide's image: the `<source>` elements of a responsive picture (none for a plain image) and the `<img>`.
 */
class PictureView
{
    /**
     * @var list<HtmlAttributes>
     */
    private readonly array $sources;

    /**
     * @param list<HtmlAttributes> $sources Attributes of each `<source>`, in document order
     * @param HtmlAttributes $image Attributes of the `<img>`
     */
    public function __construct(
        array $sources,
        private readonly HtmlAttributes $image
    ) {
        $this->sources = $sources;
    }

    /**
     * Attributes of each `<source>`, in document order
     *
     * @return list<HtmlAttributes>
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * Whether the image is a responsive picture with sources
     *
     * @return bool
     */
    public function hasSources(): bool
    {
        return $this->sources !== [];
    }

    /**
     * Attributes of the `<img>`
     *
     * @return HtmlAttributes
     */
    public function getImage(): HtmlAttributes
    {
        return $this->image;
    }
}
