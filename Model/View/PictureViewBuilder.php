<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\View;

use Hryvinskyi\BannerSliderApi\Api\Media\MediaUrlResolverInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\Dimensions;
use Hryvinskyi\BannerSliderApi\Api\Value\PictureSource;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\HtmlAttributes;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideLoading;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Builds the view of a slide's image.
 *
 * A responsive picture gets one `<source>` per image of each picture source, widest breakpoint first and the
 * preferred format first within it. Every `<source>` carries the width and height of its own rendered crop, so the
 * browser reserves the height of the crop it picks before the file arrives.
 *
 * The `<img>` is what the picture shows at widths where no `<source>` matches, which happens whenever a banner's
 * crops do not cover every breakpoint (for example a banner cropped for phones only). So it is the banner image with
 * the banner's stored size, never one of the crops; only a banner without an image falls back to the widest source's
 * original-format image with that source's size.
 *
 * A plain image is an `<img>` with the banner's stored size, when known; nothing is read from disk while rendering.
 */
class PictureViewBuilder
{
    private const MEDIA_CLASS = 'hbs-slide__media';

    /**
     * @param MediaUrlResolverInterface $mediaUrlResolver
     */
    public function __construct(
        private readonly MediaUrlResolverInterface $mediaUrlResolver
    ) {
    }

    /**
     * The view of a responsive picture
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
    ): PictureView {
        $sourceAttributes = [];
        foreach ($sources as $source) {
            $dimensions = $source->getDimensions();
            $media = $source->getBreakpoint()->getMediaQuery();
            foreach ($source->getImages() as $image) {
                $sourceAttributes[] = new HtmlAttributes([
                    'media' => $media !== '' ? $media : null,
                    'srcset' => $this->mediaUrlResolver->getUrl($image->getRelativePath()),
                    'type' => $image->getFormat()->getMimeType(),
                    'width' => $dimensions->getWidth(),
                    'height' => $dimensions->getHeight(),
                ]);
            }
        }

        if ($bannerImage !== null && $bannerImage !== '') {
            return new PictureView(
                $sourceAttributes,
                $this->mediaImage($bannerImage, $bannerImageDimensions, $alt, $loading)
            );
        }

        $widest = $sources[0];

        return new PictureView(
            $sourceAttributes,
            $this->mediaImage($widest->getFallback()->getRelativePath(), $widest->getDimensions(), $alt, $loading)
        );
    }

    /**
     * The view of a plain image
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
    ): PictureView {
        return new PictureView([], $this->mediaImage($relativePath, $dimensions, $alt, $loading));
    }

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
    ): HtmlAttributes {
        return (new HtmlAttributes([
            'src' => $this->mediaUrlResolver->getUrl($relativePath),
            'alt' => $alt,
            'width' => $dimensions?->getWidth(),
            'height' => $dimensions?->getHeight(),
        ]))->merge($loading->toImageAttributes());
    }

    /**
     * Attributes of the slide's own `<img>`
     *
     * @param string $relativePath
     * @param Dimensions|null $dimensions
     * @param string $alt
     * @param SlideLoading $loading
     * @return HtmlAttributes
     * @throws \InvalidArgumentException
     * @throws NoSuchEntityException
     */
    private function mediaImage(
        string $relativePath,
        ?Dimensions $dimensions,
        string $alt,
        SlideLoading $loading
    ): HtmlAttributes {
        return (new HtmlAttributes(['class' => self::MEDIA_CLASS]))
            ->merge($this->image($relativePath, $dimensions, $alt, $loading));
    }
}
