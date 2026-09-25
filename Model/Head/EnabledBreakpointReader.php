<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\Head;

use Hryvinskyi\BannerSliderApi\Api\BreakpointRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BreakpointInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\BreakpointSpec;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * The enabled breakpoints of a slider, widest first, read once per slider and request.
 *
 * Every preloaded banner of a slider needs the same breakpoints, and the same slider may render more than once on a
 * page, so they are kept for the rest of the request and cleared after it.
 */
class EnabledBreakpointReader implements ResetAfterRequestInterface
{
    /**
     * @var array<int, list<BreakpointSpec>>
     */
    private array $bySlider = [];

    /**
     * @param BreakpointRepositoryInterface $breakpointRepository
     */
    public function __construct(
        private readonly BreakpointRepositoryInterface $breakpointRepository
    ) {
    }

    /**
     * Enabled breakpoints of a slider, widest first (min width descending, then sort order)
     *
     * @param int $sliderId
     * @return list<BreakpointSpec> Empty for a slider without enabled breakpoints
     * @throws \InvalidArgumentException When a stored breakpoint holds a value its rendering view rejects
     */
    public function forSlider(int $sliderId): array
    {
        if (!isset($this->bySlider[$sliderId])) {
            $this->bySlider[$sliderId] = array_map(
                fn (BreakpointInterface $breakpoint): BreakpointSpec => $breakpoint->toSpec(),
                $this->breakpointRepository->getBySliderId($sliderId, true)
            );
        }

        return $this->bySlider[$sliderId];
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->bySlider = [];
    }
}
