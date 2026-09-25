<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\View;

/**
 * Encodes data as JSON for an HTML attribute value.
 *
 * `<`, `>`, `&`, `'` and `"` become `\u00XX` escapes, so the JSON stays inert whatever quoting the attribute uses;
 * the attribute value is HTML-escaped on output as well. Invalid data throws instead of producing a broken value.
 */
class JsonAttributeEncoder
{
    private const FLAGS = JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

    /**
     * JSON of the data
     *
     * @param array<mixed> $data
     * @return string
     * @throws \JsonException When the data cannot be encoded
     */
    public function encode(array $data): string
    {
        return json_encode($data, self::FLAGS);
    }
}
