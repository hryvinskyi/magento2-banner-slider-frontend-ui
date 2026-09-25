<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model\View;

use Hryvinskyi\BannerSliderFrontendUi\Model\View\JsonAttributeEncoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonAttributeEncoder::class)]
class JsonAttributeEncoderTest extends TestCase
{
    /**
     * Markup and quote characters become unicode escapes, so the JSON is inert in any attribute
     *
     * @return void
     */
    public function testEscapesMarkupAndQuotes(): void
    {
        $json = (new JsonAttributeEncoder())->encode(['label' => "Tom's <b>\"deal\"</b> & more", 'url' => 'a/b']);

        self::assertSame(
            '{"label":"Tom\\u0027s \\u003Cb\\u003E\\u0022deal\\u0022\\u003C/b\\u003E \\u0026 more","url":"a/b"}',
            $json
        );
    }

    /**
     * Data that cannot be encoded throws
     *
     * @return void
     */
    public function testInvalidDataThrows(): void
    {
        $this->expectException(\JsonException::class);

        (new JsonAttributeEncoder())->encode(['bad' => "\xB1\x31"]);
    }
}
