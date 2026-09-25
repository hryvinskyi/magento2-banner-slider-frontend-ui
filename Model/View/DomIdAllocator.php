<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\View;

use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * Hands out a page-unique DOM id for every render of a slider.
 *
 * The first render of a slider on a page gets `banner-slider-{id}`, the id earlier releases rendered, so stored
 * custom CSS that targets it keeps working. Later renders of the same slider on that page get
 * `banner-slider-{id}-2`, `-3`, and so on. The counters live for one request and are cleared after it.
 */
class DomIdAllocator implements ResetAfterRequestInterface
{
    private const PREFIX = 'banner-slider-';

    /**
     * @var array<int, int>
     */
    private array $renders = [];

    /**
     * The DOM id of the next render of a slider
     *
     * @param int $sliderId
     * @return string
     */
    public function allocate(int $sliderId): string
    {
        $count = ($this->renders[$sliderId] ?? 0) + 1;
        $this->renders[$sliderId] = $count;

        return self::PREFIX . $sliderId . ($count > 1 ? '-' . $count : '');
    }

    /**
     * The class every render of a slider carries, whatever its DOM id
     *
     * @param int $sliderId
     * @return string
     */
    public function getSliderClass(int $sliderId): string
    {
        return self::PREFIX . $sliderId;
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->renders = [];
    }
}
