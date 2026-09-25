<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\View;

use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\SlideEffect;

/**
 * Builds the Splide options of a slider.
 *
 * - Type: `fade` for the fade effect; otherwise `loop` when looping is on, else `slide`. A slider that contains a
 *   video never uses `loop`, because Splide clones slides for it and a cloned background video would play twice.
 * - `rewind` is on for every type but `loop`, so a fading or non-looping slider that plays automatically starts over
 *   after the last slide instead of stopping.
 * - Slides per page: one by default, and each responsive item becomes a `min-width` breakpoint (`mediaQuery: 'min'`),
 *   which is how the items are stored (ascending minimum widths). The breakpoints are an object keyed by width, so
 *   they encode as a JSON object even when the only key is 0.
 * - A single slide never plays automatically and shows no arrows or pagination.
 * - Splide's own lazy loading stays off: images carry native `loading` attributes instead.
 * - The slider container is the carousel region: it carries the role, the role description and the slider name,
 *   and it also holds the pause button. Splide's own root therefore gets no role, no label and no role description
 *   (an empty `role` and an empty `carousel` label), so assistive technology announces the carousel once.
 * - Every other label Splide announces is translated.
 */
class SplideConfigBuilder
{
    /**
     * @param int $speed Transition speed in milliseconds
     */
    public function __construct(
        private readonly int $speed
    ) {
    }

    /**
     * Splide options of a slider
     *
     * @param SliderInterface $slider
     * @param int $slideCount Number of slides rendered
     * @param bool $hasVideo Whether any slide is a video
     * @return array<string,mixed>
     */
    public function build(SliderInterface $slider, int $slideCount, bool $hasVideo): array
    {
        $type = $this->type($slider, $hasVideo);
        $moves = $slideCount > 1;

        return [
            'type' => $type,
            'rewind' => $type !== 'loop',
            'perPage' => 1,
            'perMove' => 1,
            'mediaQuery' => 'min',
            'breakpoints' => (object)$this->breakpoints($slider),
            'autoplay' => $moves && $slider->isAutoPlayEnabled(),
            'interval' => $slider->getAutoPlayInterval(),
            'pauseOnHover' => true,
            'pauseOnFocus' => true,
            'arrows' => $moves && $slider->isNavigationEnabled(),
            'pagination' => $moves && $slider->isPaginationEnabled(),
            'autoWidth' => $slider->isAutoWidthEnabled(),
            'autoHeight' => $slider->isAutoHeightEnabled(),
            'speed' => $this->speed,
            'lazyLoad' => false,
            'waitForTransition' => true,
            'role' => '',
            'i18n' => $this->labels(),
        ];
    }

    /**
     * Splide type of the slider
     *
     * @param SliderInterface $slider
     * @param bool $hasVideo
     * @return string
     */
    private function type(SliderInterface $slider, bool $hasVideo): string
    {
        if ($slider->getEffect() === SlideEffect::FADE) {
            return 'fade';
        }

        return $slider->isLoopEnabled() && !$hasVideo ? 'loop' : 'slide';
    }

    /**
     * Min-width breakpoints from the slider's responsive items
     *
     * @param SliderInterface $slider
     * @return array<int,array{perPage: int,gap?: string}>
     */
    private function breakpoints(SliderInterface $slider): array
    {
        $breakpoints = [];
        foreach ($slider->getResponsiveItems() as $item) {
            $options = ['perPage' => $item->getPerPage()];
            $gap = $item->getGap();
            if ($gap !== null) {
                $options['gap'] = $gap;
            }

            $breakpoints[$item->getMinWidth()] = $options;
        }

        return $breakpoints;
    }

    /**
     * Translated Splide labels; the carousel role description is empty because the container carries it
     *
     * @return array<string,string>
     */
    private function labels(): array
    {
        return [
            'prev' => (string)__('Previous slide'),
            'next' => (string)__('Next slide'),
            'first' => (string)__('Go to first slide'),
            'last' => (string)__('Go to last slide'),
            'slideX' => (string)__('Go to slide %s'),
            'pageX' => (string)__('Go to page %s'),
            'play' => (string)__('Start autoplay'),
            'pause' => (string)__('Pause autoplay'),
            'carousel' => '',
            'select' => (string)__('Select a slide to show'),
            'slide' => (string)__('slide'),
            'slideLabel' => (string)__('%s of %s'),
        ];
    }
}
