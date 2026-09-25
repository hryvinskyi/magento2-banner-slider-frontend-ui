<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Api\Render;

/**
 * Turns a banner's trusted admin HTML into storefront HTML by processing its CMS directives.
 *
 * @api
 */
interface ContentFilterInterface
{
    /**
     * The content with its directives processed
     *
     * Never returns the unprocessed directives: when processing fails, or when content nests sliders deeper than
     * allowed, the result is an empty string and the failure is logged.
     *
     * @param string|null $content Trusted admin HTML
     * @return string
     */
    public function filter(?string $content): string;
}
