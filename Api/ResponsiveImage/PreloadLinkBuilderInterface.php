<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Api\ResponsiveImage;

use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Builds the <link rel="preload"> attributes for a banner's responsive crops
 */
interface PreloadLinkBuilderInterface
{
    /**
     * Build preload link attributes for the crops' most preferred image format
     *
     * @param array<ResponsiveCropInterface> $crops
     * @phpstan-return list<array{
     *     rel: string, href: string, as: string, type?: string, imagesrcset?: string, imagesizes?: string
     * }>
     * @return array<int, array<string, string>>
     * @throws NoSuchEntityException
     */
    public function build(array $crops): array;
}
