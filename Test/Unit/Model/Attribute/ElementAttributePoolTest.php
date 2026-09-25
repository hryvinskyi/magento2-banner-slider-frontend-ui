<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\Attribute;

use Hryvinskyi\BannerSliderFrontendUi\Api\Attribute\ElementAttributeProviderInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\HtmlAttributes;
use Hryvinskyi\BannerSliderFrontendUi\Model\Attribute\ElementAttributePool;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\StorefrontFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ElementAttributePool::class)]
class ElementAttributePoolTest extends TestCase
{
    use StorefrontFixtures;

    /**
     * Providers apply in sort order on top of the element's own attributes, classes joined
     *
     * @return void
     */
    public function testProvidersMergeInSortOrderOnTopOfTheBase(): void
    {
        $late = $this->provider(20, ['class' => 'late', 'data-track' => 'late']);
        $early = $this->provider(10, ['class' => 'early', 'data-track' => 'early', 'id' => false]);
        $pool = new ElementAttributePool(['late' => $late, 'early' => $early]);
        $base = new HtmlAttributes(['class' => 'hbs-slider', 'id' => 'banner-slider-7']);

        $attributes = $pool->getContainerAttributes($this->slider(), [$this->banner()], $base);

        self::assertSame(
            ['class' => 'hbs-slider early late', 'id' => false, 'data-track' => 'late'],
            $attributes->toArray()
        );
    }

    /**
     * Slide and link attributes go through the same merge
     *
     * @return void
     */
    public function testSlideAndLinkAttributes(): void
    {
        $pool = new ElementAttributePool(['one' => $this->provider(0, ['data-promo' => 'spring', 'class' => 'x'])]);
        $slider = $this->slider();
        $banner = $this->banner();

        $slide = $pool->getSlideAttributes($slider, $banner, new HtmlAttributes(['class' => 'hbs-slide']));
        $link = $pool->getLinkAttributes($slider, $banner, new HtmlAttributes(['class' => 'hbs-slide__link']));

        self::assertSame(['class' => 'hbs-slide x', 'data-promo' => 'spring'], $slide->toArray());
        self::assertSame(['class' => 'hbs-slide__link x', 'data-promo' => 'spring'], $link->toArray());
    }

    /**
     * Without providers the base comes back as it is
     *
     * @return void
     */
    public function testNoProvidersKeepsTheBase(): void
    {
        $base = new HtmlAttributes(['class' => 'hbs-slide']);

        self::assertSame(
            $base->toArray(),
            (new ElementAttributePool())->getSlideAttributes($this->slider(), $this->banner(), $base)->toArray()
        );
    }

    /**
     * A provider returning a disallowed attribute name fails loudly
     *
     * @return void
     */
    public function testDisallowedNameFromAProviderFails(): void
    {
        $pool = new ElementAttributePool(['bad' => $this->provider(0, ['onclick' => 'alert(1)'])]);

        $this->expectException(\InvalidArgumentException::class);

        $pool->getLinkAttributes($this->slider(), $this->banner(), new HtmlAttributes());
    }

    /**
     * A provider returning the same attributes for every element
     *
     * @param int $sortOrder
     * @param array<string,string|int|bool|null> $attributes
     * @return ElementAttributeProviderInterface
     */
    private function provider(int $sortOrder, array $attributes): ElementAttributeProviderInterface
    {
        $provider = $this->createMock(ElementAttributeProviderInterface::class);
        $provider->method('getSortOrder')->willReturn($sortOrder);
        $provider->method('getContainerAttributes')->willReturn($attributes);
        $provider->method('getSlideAttributes')->willReturn($attributes);
        $provider->method('getLinkAttributes')->willReturn($attributes);

        return $provider;
    }
}
