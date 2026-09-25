<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Api\Render;

use Hryvinskyi\BannerSliderApi\Api\Value\BannerType;
use Hryvinskyi\BannerSliderFrontendUi\Api\Value\SlideContext;
use Magento\Framework\Exception\LocalizedException;

/**
 * Renders the inside of one slide for the banner types it supports.
 *
 * Renderers are registered in the `renderers` pool of the composite renderer in `di.xml`; the first one (by the
 * items' `sortOrder`) that supports a banner's type renders it. A renderer throws when a banner cannot be rendered;
 * the slider then leaves that slide out, logs why, and still renders the others.
 *
 * @api
 */
interface SlideRendererInterface
{
    /**
     * Whether this renderer renders banners of the type
     *
     * @param BannerType $type
     * @return bool
     */
    public function supports(BannerType $type): bool;

    /**
     * HTML of the slide's content (the element inside the slide's `<li>`); an empty string leaves the slide out
     *
     * @param SlideContext $context
     * @return string
     * @throws \InvalidArgumentException When a stored value of the banner cannot be rendered
     * @throws LocalizedException When a media URL or an embed cannot be built
     */
    public function render(SlideContext $context): string;
}
