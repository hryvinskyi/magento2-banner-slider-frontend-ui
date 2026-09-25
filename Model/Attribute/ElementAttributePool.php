<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\Attribute;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Attribute\ElementAttributePoolInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Attribute\ElementAttributeProviderInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\HtmlAttributes;

/**
 * Merges the attributes of every registered provider, lowest sort order first, on top of an element's own
 * attributes.
 */
class ElementAttributePool implements ElementAttributePoolInterface
{
    /**
     * @var list<ElementAttributeProviderInterface>
     */
    private readonly array $providers;

    /**
     * @param array<string,ElementAttributeProviderInterface> $providers Registered in `di.xml`
     */
    public function __construct(array $providers = [])
    {
        $sorted = array_values($providers);
        usort(
            $sorted,
            fn (ElementAttributeProviderInterface $a, ElementAttributeProviderInterface $b): int =>
                $a->getSortOrder() <=> $b->getSortOrder()
        );
        $this->providers = $sorted;
    }

    /**
     * @inheritDoc
     */
    public function getContainerAttributes(
        SliderInterface $slider,
        array $banners,
        HtmlAttributes $base
    ): HtmlAttributes {
        $attributes = $base;
        foreach ($this->providers as $provider) {
            $attributes = $attributes->merge(
                new HtmlAttributes($provider->getContainerAttributes($slider, $banners))
            );
        }

        return $attributes;
    }

    /**
     * @inheritDoc
     */
    public function getSlideAttributes(
        SliderInterface $slider,
        BannerInterface $banner,
        HtmlAttributes $base
    ): HtmlAttributes {
        $attributes = $base;
        foreach ($this->providers as $provider) {
            $attributes = $attributes->merge(new HtmlAttributes($provider->getSlideAttributes($slider, $banner)));
        }

        return $attributes;
    }

    /**
     * @inheritDoc
     */
    public function getLinkAttributes(
        SliderInterface $slider,
        BannerInterface $banner,
        HtmlAttributes $base
    ): HtmlAttributes {
        $attributes = $base;
        foreach ($this->providers as $provider) {
            $attributes = $attributes->merge(new HtmlAttributes($provider->getLinkAttributes($slider, $banner)));
        }

        return $attributes;
    }
}
