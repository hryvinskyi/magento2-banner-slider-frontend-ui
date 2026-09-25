<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\View;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\HtmlAttributes;

/**
 * One rendered slide: the attributes of its `<li>` and the HTML inside it.
 */
class SlideView
{
    /**
     * @param BannerInterface $banner
     * @param HtmlAttributes $attributes
     * @param string $contentHtml HTML produced by a slide renderer
     */
    public function __construct(
        private readonly BannerInterface $banner,
        private readonly HtmlAttributes $attributes,
        private readonly string $contentHtml
    ) {
    }

    /**
     * The banner shown by the slide
     *
     * @return BannerInterface
     */
    public function getBanner(): BannerInterface
    {
        return $this->banner;
    }

    /**
     * Attributes of the slide element
     *
     * @return HtmlAttributes
     */
    public function getAttributes(): HtmlAttributes
    {
        return $this->attributes;
    }

    /**
     * HTML inside the slide element
     *
     * @return string
     */
    public function getContentHtml(): string
    {
        return $this->contentHtml;
    }
}
