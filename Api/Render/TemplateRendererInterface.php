<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Api\Render;

use Magento\Framework\Exception\LocalizedException;

/**
 * Renders a storefront template with the variables it needs, for renderers that produce markup outside a layout
 * block of their own.
 *
 * The template sees each variable as a local of the same name, plus the usual `$escaper` and `$secureRenderer`.
 * Themes override the template by its id as for any other template.
 *
 * @api
 */
interface TemplateRendererInterface
{
    /**
     * HTML of the template
     *
     * @param string $template Template id, such as `Vendor_Module::path/file.phtml`
     * @param array<string,mixed> $variables Variable name => value
     * @return string
     * @throws LocalizedException When the template cannot be rendered
     */
    public function render(string $template, array $variables = []): string;
}
