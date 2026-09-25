<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Api\View;

use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\PictureSource;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\HtmlAttributes;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideLoading;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Builds the image markup of a slide, for slide renderers that show a banner's image.
 *
 * Stored media paths are turned into URLs on the way, so an unsafe path fails here rather than reaching the page.
 * Nothing is read from disk: sizes are the stored ones.
 *
 * @api
 */
interface PictureViewBuilderInterface
{
    /**
     * The view of a responsive picture
     *
     * The `<img>` is what the picture shows where no `<source>` matches: the banner image with its stored size, or,
     * for a banner without an image, the widest source's original-format image with that source's size.
     *
     * @param non-empty-list<PictureSource> $sources Widest first
     * @param string|null $bannerImage Media-relative path of the banner image, shown where no source matches
     * @param Dimensions|null $bannerImageDimensions Stored size of the banner image, when known
     * @param string $alt Alternative text; empty for a decorative image
     * @param SlideLoading $loading
     * @return PictureView
     * @throws \InvalidArgumentException When a stored path is not a safe media path
     * @throws NoSuchEntityException When the current store cannot be resolved
     */
    public function fromSources(
        array $sources,
        ?string $bannerImage,
        ?Dimensions $bannerImageDimensions,
        string $alt,
        SlideLoading $loading
    ): PictureView;

    /**
     * The view of a plain image, without sources
     *
     * @param string $relativePath Media-relative path
     * @param Dimensions|null $dimensions Stored size, when known
     * @param string $alt Alternative text; empty for a decorative image
     * @param SlideLoading $loading
     * @return PictureView
     * @throws \InvalidArgumentException When the path is not a safe media path
     * @throws NoSuchEntityException When the current store cannot be resolved
     */
    public function fromImage(
        string $relativePath,
        ?Dimensions $dimensions,
        string $alt,
        SlideLoading $loading
    ): PictureView;

    /**
     * Attributes of an `<img>` without a class, for images such as a video poster
     *
     * @param string $relativePath Media-relative path
     * @param Dimensions|null $dimensions Stored size, when known
     * @param string $alt Alternative text; empty for a decorative image
     * @param SlideLoading $loading
     * @return HtmlAttributes
     * @throws \InvalidArgumentException When the path is not a safe media path
     * @throws NoSuchEntityException When the current store cannot be resolved
     */
    public function image(
        string $relativePath,
        ?Dimensions $dimensions,
        string $alt,
        SlideLoading $loading
    ): HtmlAttributes;
}
