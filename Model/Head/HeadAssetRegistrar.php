<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\Head;

use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Head\HeadAssetRegistrarInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\View\SliderView;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\FrontendAssets;
use Hryvinskyi\HeadTagManager\Api\HeadTagManagerInterface;

/**
 * Adds what a rendered slider needs to the page head: its stylesheets, the preload links of its leading images and
 * its custom CSS.
 *
 * Elements are keyed, so several sliders on a page add each stylesheet once, and each slider's custom CSS once. The
 * custom CSS was checked on save to contain no `<`; `</` is still written as `<\/` so the CSS can never close its
 * `<style>` element.
 *
 * The head is assembled when the page response is built, so this only reaches the page for a slider rendered as
 * part of it; a slider served as a separately cached fragment (an ESI block) cannot add head elements.
 */
class HeadAssetRegistrar implements HeadAssetRegistrarInterface
{
    private const CUSTOM_CSS_KEY_PREFIX = 'hryvinskyi_banner_slider_css_';

    /**
     * @param HeadTagManagerInterface $headTagManager
     * @param FrontendAssets $frontendAssets
     * @param PreloadLinkBuilder $preloadLinkBuilder
     */
    public function __construct(
        private readonly HeadTagManagerInterface $headTagManager,
        private readonly FrontendAssets $frontendAssets,
        private readonly PreloadLinkBuilder $preloadLinkBuilder
    ) {
    }

    /**
     * @inheritDoc
     */
    public function register(SliderView $view): void
    {
        foreach ($this->frontendAssets->getStylesheetUrls() as $url) {
            $stylesheet = ['rel' => 'stylesheet', 'href' => $url];
            $this->headTagManager->addStylesheet($url, [], $this->key('link', $stylesheet));
        }

        $slider = $view->getSlider();
        $links = $this->preloadLinkBuilder->build($slider, $view->getBanners(), $view->getPictureSources());
        foreach ($links as $link) {
            $this->headTagManager->addLink($link, $this->key('link', $link));
        }

        $this->registerCustomCss($slider);
    }

    /**
     * Register the slider's custom CSS, once per slider
     *
     * @param SliderInterface $slider
     * @return void
     */
    private function registerCustomCss(SliderInterface $slider): void
    {
        $css = trim((string)$slider->getCustomCss());
        if ($css === '') {
            return;
        }

        $this->headTagManager->addInlineStyle(
            str_replace('</', '<\/', $css),
            [],
            self::CUSTOM_CSS_KEY_PREFIX . (int)$slider->getSliderId()
        );
    }

    /**
     * Key of a head element, derived from its content so identical elements share one
     *
     * @param string $type
     * @param array<string,string> $attributes
     * @return string
     */
    private function key(string $type, array $attributes): string
    {
        return $this->headTagManager->generateElementKey($type, ['attributes' => $attributes]);
    }
}
