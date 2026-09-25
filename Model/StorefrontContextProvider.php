<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model;

use Hryvinskyi\BannerSliderApi\Api\Value\StorefrontContext;
use Hryvinskyi\BannerSliderFrontendUi\Api\StorefrontContextProviderInterface;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * The storefront context of the current request: its store view, the visitor's customer group and the moment.
 *
 * The customer group comes from the HTTP context, which the full page cache varies on, so a cached page always
 * matches the group it was rendered for. A request without a group in the context is a guest (group 0).
 */
class StorefrontContextProvider implements StorefrontContextProviderInterface
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param HttpContext $httpContext
     * @param ClockInterface $clock
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly HttpContext $httpContext,
        private readonly ClockInterface $clock
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(): StorefrontContext
    {
        return new StorefrontContext(
            $this->toId($this->storeManager->getStore()->getId()),
            $this->toId($this->httpContext->getValue(CustomerContext::CONTEXT_GROUP)),
            $this->clock->now()
        );
    }

    /**
     * A non-negative id from a loosely typed value; anything else is 0
     *
     * @param mixed $value
     * @return int
     */
    private function toId(mixed $value): int
    {
        return is_numeric($value) && (int)$value > 0 ? (int)$value : 0;
    }
}
