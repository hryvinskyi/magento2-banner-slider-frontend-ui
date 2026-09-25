<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Api;

use Hryvinskyi\BannerSliderApi\Api\Value\StorefrontContext;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * The storefront context of the current request, which decides which slider and banners a visitor sees.
 *
 * The rendered slider is cached with the page, so the store view and customer group must come from values the full
 * page cache varies on; otherwise a page cached for one visitor would show another visitor's banners.
 *
 * @api
 */
interface StorefrontContextProviderInterface
{
    /**
     * The context of the current request: its store view, the visitor's customer group and the moment
     *
     * @return StorefrontContext
     * @throws NoSuchEntityException When the current store cannot be resolved
     */
    public function get(): StorefrontContext;
}
