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

/**
 * Pool for collecting and merging element attributes from registered providers
 */
class ElementAttributePool implements ElementAttributePoolInterface
{
    /**
     * @var array<ElementAttributeProviderInterface>|null
     */
    private ?array $sortedProviders = null;

    /**
     * @param array<ElementAttributeProviderInterface> $providers
     */
    public function __construct(
        private readonly array $providers = []
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function getContainerAttributes(SliderInterface $slider, array $banners): array
    {
        $attributes = [];
        foreach ($this->getSortedProviders() as $provider) {
            $providerAttributes = $provider->getContainerAttributes($slider, $banners);
            $attributes = $this->mergeAttributes($attributes, $providerAttributes);
        }

        return $attributes;
    }

    /**
     * {@inheritDoc}
     */
    public function getSlideAttributes(SliderInterface $slider, BannerInterface $banner): array
    {
        $attributes = [];
        foreach ($this->getSortedProviders() as $provider) {
            $providerAttributes = $provider->getSlideAttributes($slider, $banner);
            $attributes = $this->mergeAttributes($attributes, $providerAttributes);
        }

        return $attributes;
    }

    /**
     * {@inheritDoc}
     */
    public function getLinkAttributes(SliderInterface $slider, BannerInterface $banner): array
    {
        $attributes = [];
        foreach ($this->getSortedProviders() as $provider) {
            $providerAttributes = $provider->getLinkAttributes($slider, $banner);
            $attributes = $this->mergeAttributes($attributes, $providerAttributes);
        }

        return $attributes;
    }

    /**
     * Get providers sorted by sort order
     *
     * @return array<ElementAttributeProviderInterface>
     */
    private function getSortedProviders(): array
    {
        if ($this->sortedProviders === null) {
            $this->sortedProviders = $this->providers;
            usort(
                $this->sortedProviders,
                static fn(ElementAttributeProviderInterface $a, ElementAttributeProviderInterface $b): int =>
                    $a->getSortOrder() <=> $b->getSortOrder()
            );
        }

        return $this->sortedProviders;
    }

    /**
     * Merge attributes with special handling for class attribute
     *
     * @param array<string, string|bool|int> $existing
     * @param array<string, string|bool|int> $new
     * @return array<string, string|bool|int>
     */
    private function mergeAttributes(array $existing, array $new): array
    {
        foreach ($new as $name => $value) {
            if ($name === 'class' && isset($existing['class'])) {
                $existingClasses = is_string($existing['class'])
                    ? explode(' ', $existing['class'])
                    : [$existing['class']];
                $newClasses = is_string($value)
                    ? explode(' ', $value)
                    : [$value];
                $merged = array_unique(array_merge($existingClasses, $newClasses));
                $existing['class'] = implode(' ', array_filter($merged));
            } else {
                $existing[$name] = $value;
            }
        }

        return $existing;
    }
}
