<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Test\Unit\Model;

use DateTimeImmutable;
use DateTimeZone;
use Hryvinskyi\BannerSliderFrontendUi\Model\StorefrontContextProvider;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[CoversClass(StorefrontContextProvider::class)]
class StorefrontContextProviderTest extends TestCase
{
    /**
     * Store, customer group and moment of the request
     *
     * @param mixed $storeId
     * @param mixed $groupId
     * @param int $expectedStoreId
     * @param int $expectedGroupId
     * @return void
     */
    #[TestWith([3, '2', 3, 2])]
    #[TestWith(['4', 1, 4, 1])]
    #[TestWith([null, null, 0, 0])]
    #[TestWith(['x', -5, 0, 0])]
    public function testBuildsTheContext(
        mixed $storeId,
        mixed $groupId,
        int $expectedStoreId,
        int $expectedGroupId
    ): void {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $httpContext = $this->createMock(HttpContext::class);
        $httpContext->method('getValue')->with(CustomerContext::CONTEXT_GROUP)->willReturn($groupId);
        $now = new DateTimeImmutable('2026-09-25 12:00:00', new DateTimeZone('Europe/Kyiv'));
        $clock = $this->createMock(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $context = (new StorefrontContextProvider($storeManager, $httpContext, $clock))->get();

        self::assertSame($expectedStoreId, $context->getStoreId());
        self::assertSame($expectedGroupId, $context->getCustomerGroupId());
        self::assertSame('2026-09-25 09:00:00', $context->getNow()->format('Y-m-d H:i:s'));
    }
}
