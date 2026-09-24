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
 * Renders a banner's responsive crops as a <picture> element
 */
interface PictureRendererInterface
{
    /**
     * Render the crops as a <picture>; an empty string when no crop has an image
     *
     * @param array<ResponsiveCropInterface> $crops
     * @param string $alt
     * @param bool $lazyLoad
     * @return string
     * @throws NoSuchEntityException
     */
    public function render(array $crops, string $alt, bool $lazyLoad = false): string;
}
