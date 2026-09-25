<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Api\Value;

use Hryvinskyi\BannerSliderFrontendUi\Api\Value\HtmlAttributes;
use Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Fixture\StorefrontFixtures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(HtmlAttributes::class)]
class HtmlAttributesTest extends TestCase
{
    use StorefrontFixtures;

    /**
     * One rule for every value: strings and integers escaped, true bare, false and null omitted
     *
     * @return void
     */
    public function testRenderAppliesOneBooleanRule(): void
    {
        $attributes = new HtmlAttributes([
            'alt' => 'Say "hi" <now>',
            'width' => 1920,
            'hidden' => true,
            'muted' => false,
            'title' => null,
            'data-empty' => '',
        ]);

        self::assertSame(
            ' alt="Say &quot;hi&quot; &lt;now&gt;" width="1920" hidden data-empty=""',
            $attributes->render($this->escaper())
        );
    }

    /**
     * URL attributes are escaped as URLs, whatever their case
     *
     * @return void
     */
    public function testUrlAttributesAreEscapedAsUrls(): void
    {
        $attributes = new HtmlAttributes([
            'href' => '/sale?a=1&b=2',
            'SRC' => 'x.jpg',
            'poster' => 'p.jpg',
            'data-hbs-src' => 'v.mp4',
        ]);

        self::assertSame(
            ' href="url:/sale?a=1&amp;b=2" SRC="url:x.jpg" poster="url:p.jpg" data-hbs-src="url:v.mp4"',
            $attributes->render($this->escaper())
        );
    }

    /**
     * Classes are joined without duplicates; other attributes take the later value, which may switch them off
     *
     * @return void
     */
    public function testMergeJoinsClassesAndLetsTheLaterValueWin(): void
    {
        $base = new HtmlAttributes(['class' => 'hbs-slide  splide__slide', 'data-x' => '1', 'hidden' => true]);
        $other = new HtmlAttributes(['class' => 'splide__slide promo', 'data-x' => '2', 'hidden' => null]);
        $merged = $base->merge($other);

        self::assertSame('hbs-slide splide__slide promo', $merged->get('class'));
        self::assertSame('2', $merged->get('data-x'));
        self::assertSame(' class="hbs-slide splide__slide promo" data-x="2"', $merged->render($this->escaper()));
        self::assertSame('hbs-slide  splide__slide', $base->get('class'), 'The original set is unchanged.');
    }

    /**
     * An added class list keeps the existing classes when the other set has none
     *
     * @return void
     */
    public function testMergeKeepsClassesWhenTheOtherSetClearsNone(): void
    {
        $merged = (new HtmlAttributes(['class' => 'a']))->merge(new HtmlAttributes(['class' => null]));

        self::assertSame('a', $merged->get('class'));
    }

    /**
     * A copy with one attribute replaced
     *
     * @return void
     */
    public function testWithReplacesOneAttribute(): void
    {
        $attributes = (new HtmlAttributes(['id' => 'a']))->with('id', 'b');

        self::assertSame('b', $attributes->get('id'));
        self::assertNull($attributes->get('missing'));
        self::assertSame(['id' => 'b'], $attributes->toArray());
    }

    /**
     * Names that are not plain attribute names, and event handlers, are rejected
     *
     * @param string $name
     * @return void
     */
    #[TestWith(['onclick'])]
    #[TestWith(['OnLoad'])]
    #[TestWith(['data x'])]
    #[TestWith(['"><script'])]
    #[TestWith(['1st'])]
    #[TestWith([''])]
    public function testRejectsUnsafeNames(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new HtmlAttributes([$name => 'x']);
    }

    /**
     * `with()` applies the same name rule
     *
     * @return void
     */
    public function testWithRejectsUnsafeNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new HtmlAttributes())->with('onerror', 'x');
    }
}
