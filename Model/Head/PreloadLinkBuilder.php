<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\Head;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Media\MediaUrlResolverInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointSpec;
use Hryvinskyi\BannerSliderApi\Api\Value\PictureSource;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * Builds the `<link rel="preload" as="image">` elements of a slider's leading image banners.
 *
 * Which banners: the first `preloadBannersCount` slides, plus any banner flagged for preloading. Only image banners
 * are preloaded. Only the first slide's links carry `fetchpriority="high"`; the others keep the browser's default
 * priority, so they do not compete with the image the visitor sees first.
 *
 * What is preloaded is what the slide's `<picture>` shows at each viewport width:
 * - The widths are split by the slider's enabled breakpoints, not by the banner's crops, because a banner may be
 *   cropped for some breakpoints only. Breakpoints come widest first; each covers the widths from its min width up to
 *   the min width of the next wider one, the widest has no upper bound, and the widths below the narrowest
 *   breakpoint's min width form one more range. Every link's `media` is built from those min widths (`(min-width:
 *   …)`, `(max-width: …)`) rather than copied from the stored media queries, which may overlap: stored
 *   `(max-width: 768px)` and `(min-width: 768px)` both match at 768 px, where the picture still shows only the wider
 *   crop. This assumes each breakpoint's media query is a viewport-width condition that starts at its min width,
 *   which is how breakpoints are defined. Breakpoints sharing a min width share one range; the first of them with a
 *   crop of the banner supplies it, as the picture shows the first matching `<source>`.
 * - A range with a crop of the banner preloads that crop's preferred image (the first format, the one the
 *   `<picture>` offers first) with its MIME type. A browser that cannot decode that type skips the link, as it skips
 *   that `<source>`; a browser that can decode it would never fetch the original-format fallback, so preloading the
 *   fallback would be wasted.
 * - A range without a crop shows the picture's `<img>`, so it preloads the banner image (no type: its format is not
 *   stored); a banner without an image preloads the widest crop's original-format image there, which is then its
 *   `<img>`. A banner with neither preloads nothing for that range.
 * - Adjacent ranges preloading the same image share one link, so a banner without crops gets a single link for its
 *   image, without `media` when that covers every width.
 *
 * A banner whose stored paths cannot be turned into URLs gets no links and is logged; the others are unaffected.
 */
class PreloadLinkBuilder
{
    /**
     * @param MediaUrlResolverInterface $mediaUrlResolver
     * @param EnabledBreakpointReader $breakpointReader
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly MediaUrlResolverInterface $mediaUrlResolver,
        private readonly EnabledBreakpointReader $breakpointReader,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Attributes of every preload link of the slider
     *
     * @param SliderInterface $slider
     * @param list<BannerInterface> $banners Banners of the rendered slides, in order
     * @param array<int,list<PictureSource>> $pictures Picture sources by banner id
     * @return list<array<string,string>>
     */
    public function build(SliderInterface $slider, array $banners, array $pictures): array
    {
        $count = $slider->getPreloadBannersCount();
        $links = [];
        foreach ($banners as $position => $banner) {
            if (!$banner->getType()->requiresImage()) {
                continue;
            }

            if ($position >= $count && !$banner->isPreloadEnabled()) {
                continue;
            }

            $bannerId = (int)$banner->getBannerId();
            try {
                $bannerLinks = $this->bannerLinks($slider, $banner, $pictures[$bannerId] ?? [], $position === 0);
            } catch (\InvalidArgumentException | LocalizedException $e) {
                $this->logger->warning('Banner slider: the image of a banner could not be preloaded.', [
                    'banner_id' => $bannerId,
                    'exception' => $e,
                ]);
                continue;
            }

            array_push($links, ...$bannerLinks);
        }

        return $links;
    }

    /**
     * Preload links of one banner
     *
     * @param SliderInterface $slider
     * @param BannerInterface $banner
     * @param list<PictureSource> $sources Widest first
     * @param bool $highPriority
     * @return list<array<string,string>>
     * @throws \InvalidArgumentException When a stored path is not a safe media path
     * @throws LocalizedException When the current store cannot be resolved
     */
    private function bannerLinks(
        SliderInterface $slider,
        BannerInterface $banner,
        array $sources,
        bool $highPriority
    ): array {
        $breakpoints = $this->breakpointReader->forSlider((int)$slider->getSliderId());
        $ranges = $this->ranges($breakpoints, $this->byBreakpoint($sources));
        $uncovered = in_array(null, array_column($ranges, 'source'), true)
            ? $this->uncoveredImage($banner, $sources)
            : null;

        $targets = [];
        foreach ($ranges as $range) {
            $image = $range['source'] !== null ? $this->preferredImage($range['source']) : $uncovered;
            if ($image === null) {
                continue;
            }

            $last = array_key_last($targets);
            if ($last !== null
                && $targets[$last]['image'] === $image
                && $range['max'] !== null
                && $targets[$last]['min'] === $range['max'] + 1
            ) {
                $targets[$last]['min'] = $range['min'];
                continue;
            }

            $targets[] = ['image' => $image, 'min' => $range['min'], 'max' => $range['max']];
        }

        $priority = $highPriority ? ['fetchpriority' => 'high'] : [];
        $links = [];
        foreach ($targets as $target) {
            $media = $this->widthRange($target['min'], $target['max']);
            $type = $target['image']['type'];
            $links[] = ['rel' => 'preload', 'as' => 'image', 'href' => $target['image']['href']]
                + ($type !== null ? ['type' => $type] : [])
                + ($media !== '' ? ['media' => $media] : [])
                + $priority;
        }

        return $links;
    }

    /**
     * Viewport width ranges of the breakpoints, widest first, each with the banner's crop for it
     *
     * @param list<BreakpointSpec> $breakpoints Widest first
     * @param array<string,PictureSource> $sources Crops of the banner by breakpoint identifier
     * @return list<array{min: int, max: int|null, source: PictureSource|null}>
     */
    private function ranges(array $breakpoints, array $sources): array
    {
        $ranges = [];
        $upperBound = null;
        foreach ($breakpoints as $breakpoint) {
            $minWidth = $breakpoint->getMinWidth();
            $source = $sources[$breakpoint->getIdentifier()] ?? null;
            $last = array_key_last($ranges);
            if ($last !== null && $minWidth >= $upperBound) {
                $ranges[$last]['source'] ??= $source;
                continue;
            }

            $ranges[] = [
                'min' => $minWidth,
                'max' => $upperBound === null ? null : $upperBound - 1,
                'source' => $source,
            ];
            $upperBound = $minWidth;
        }

        if ($upperBound === null || $upperBound > 0) {
            $ranges[] = ['min' => 0, 'max' => $upperBound === null ? null : $upperBound - 1, 'source' => null];
        }

        return $ranges;
    }

    /**
     * Crops of the banner by breakpoint identifier
     *
     * @param list<PictureSource> $sources
     * @return array<string,PictureSource>
     */
    private function byBreakpoint(array $sources): array
    {
        $byBreakpoint = [];
        foreach ($sources as $source) {
            $byBreakpoint[$source->getBreakpoint()->getIdentifier()] ??= $source;
        }

        return $byBreakpoint;
    }

    /**
     * The preferred image of a crop, with its type
     *
     * @param PictureSource $source
     * @return array{href: string, type: string|null}
     * @throws \InvalidArgumentException When the stored path is not a safe media path
     * @throws LocalizedException When the current store cannot be resolved
     */
    private function preferredImage(PictureSource $source): array
    {
        $preferred = $source->getImages()[0];

        return [
            'href' => $this->mediaUrlResolver->getUrl($preferred->getRelativePath()),
            'type' => $preferred->getFormat()->getMimeType(),
        ];
    }

    /**
     * The image shown where no crop applies: the banner image, else the widest crop's original-format image
     *
     * @param BannerInterface $banner
     * @param list<PictureSource> $sources Widest first
     * @return array{href: string, type: string|null}|null Null when the banner has neither
     * @throws \InvalidArgumentException When the stored path is not a safe media path
     * @throws LocalizedException When the current store cannot be resolved
     */
    private function uncoveredImage(BannerInterface $banner, array $sources): ?array
    {
        $image = $banner->getImage();
        if ($image !== null && $image !== '') {
            return ['href' => $this->mediaUrlResolver->getUrl($image), 'type' => null];
        }

        if ($sources === []) {
            return null;
        }

        $fallback = $sources[0]->getFallback();

        return [
            'href' => $this->mediaUrlResolver->getUrl($fallback->getRelativePath()),
            'type' => $fallback->getFormat()->getMimeType(),
        ];
    }

    /**
     * Media query of the viewport widths from a min width up to a max width
     *
     * @param int $minWidth
     * @param int|null $maxWidth Null for no upper bound
     * @return string Empty when the range covers every width
     */
    private function widthRange(int $minWidth, ?int $maxWidth): string
    {
        $conditions = [];
        if ($minWidth > 0) {
            $conditions[] = sprintf('(min-width: %dpx)', $minWidth);
        }
        if ($maxWidth !== null) {
            $conditions[] = sprintf('(max-width: %dpx)', $maxWidth);
        }

        return implode(' and ', $conditions);
    }
}
