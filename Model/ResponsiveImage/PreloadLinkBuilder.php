<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\ResponsiveImage;

use Hryvinskyi\BannerSliderFrontendUi\Api\ResponsiveImage\CropOrderInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\ResponsiveImage\PreloadLinkBuilderInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds one preload link covering every breakpoint of a banner's responsive crops
 *
 * Only the most preferred format is preloaded (AVIF, then WebP, then the original) so the browser downloads one
 * file. The type attribute makes AVIF and WebP a progressive enhancement: a browser that cannot decode the type
 * skips the preload.
 */
class PreloadLinkBuilder implements PreloadLinkBuilderInterface
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param CropOrderInterface $cropOrder
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly CropOrderInterface $cropOrder
    ) {
    }

    /**
     * @inheritDoc
     */
    public function build(array $crops): array
    {
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        $srcsets = ['image/avif' => [], 'image/webp' => [], '' => []];
        $sizes = [];

        foreach ($this->cropOrder->sort($crops) as $crop) {
            $croppedImage = $crop->getCroppedImage();
            if ($croppedImage === null || $croppedImage === '') {
                continue;
            }

            $breakpoint = CropBreakpoint::fromCrop($crop);
            $descriptor = ' ' . $breakpoint->getWidth() . 'w';

            if ($breakpoint->getWidth() > 0) {
                $sizes[] = $breakpoint->getMediaQuery() . ' ' . $breakpoint->getWidth() . 'px';
            }

            $srcsets[''][] = $mediaUrl . $croppedImage . $descriptor;

            $avifImage = $crop->getAvifImage();
            if ($crop->isGenerateAvifEnabled() && $avifImage !== null && $avifImage !== '') {
                $srcsets['image/avif'][] = $mediaUrl . $avifImage . $descriptor;
            }

            $webpImage = $crop->getWebpImage();
            if ($crop->isGenerateWebpEnabled() && $webpImage !== null && $webpImage !== '') {
                $srcsets['image/webp'][] = $mediaUrl . $webpImage . $descriptor;
            }
        }

        $imageSizes = $sizes !== [] ? implode(', ', $sizes) . ', 100vw' : '100vw';

        foreach ($srcsets as $type => $candidates) {
            if ($candidates === []) {
                continue;
            }

            $link = [
                'rel' => 'preload',
                'as' => 'image',
                'href' => explode(' ', $candidates[0])[0],
            ];

            if ($type !== '') {
                $link['type'] = $type;
            }

            return [$link + ['imagesrcset' => implode(', ', $candidates), 'imagesizes' => $imageSizes]];
        }

        return [];
    }
}
