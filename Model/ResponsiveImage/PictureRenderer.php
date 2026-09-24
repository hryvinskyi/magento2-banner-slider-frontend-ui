<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Model\ResponsiveImage;

use Hryvinskyi\BannerSliderFrontendUi\Api\ResponsiveImage\CropOrderInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\ResponsiveImage\PictureRendererInterface;
use Hryvinskyi\Base\Helper\Html;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Renders responsive crops as a <picture> with AVIF, WebP and original sources per breakpoint
 *
 * Every source carries the width and height of its own crop, so the browser reserves the height of the crop
 * it picks, even before that image has loaded. The fallback <img> is the widest breakpoint's crop.
 */
class PictureRenderer implements PictureRendererInterface
{
    private const INDENT = '    ';

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
    public function render(array $crops, string $alt, bool $lazyLoad = false): string
    {
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        $sources = [];
        $fallback = null;

        foreach ($this->cropOrder->sort($crops) as $crop) {
            $croppedImage = $crop->getCroppedImage();
            if ($croppedImage === null || $croppedImage === '') {
                continue;
            }

            $breakpoint = CropBreakpoint::fromCrop($crop);
            $croppedUrl = $mediaUrl . $croppedImage;
            $fallback ??= ['url' => $croppedUrl, 'breakpoint' => $breakpoint];

            $avifImage = $crop->getAvifImage();
            if ($crop->isGenerateAvifEnabled() && $avifImage !== null && $avifImage !== '') {
                $sources[] = $this->renderSource($mediaUrl . $avifImage, $breakpoint, 'image/avif');
            }

            $webpImage = $crop->getWebpImage();
            if ($crop->isGenerateWebpEnabled() && $webpImage !== null && $webpImage !== '') {
                $sources[] = $this->renderSource($mediaUrl . $webpImage, $breakpoint, 'image/webp');
            }

            $sources[] = $this->renderSource($croppedUrl, $breakpoint);
        }

        if ($fallback === null) {
            return '';
        }

        $image = self::INDENT . Html::img(
            $fallback['url'],
            $this->withDimensions(
                ['alt' => $alt, 'class' => 'banner-slider-image'] + ($lazyLoad ? ['loading' => 'lazy'] : []),
                $fallback['breakpoint']
            )
        );

        return Html::tag('picture', "\n" . implode("\n", $sources) . "\n" . $image . "\n");
    }

    /**
     * Render one <source> of the picture
     *
     * @param string $srcset
     * @param CropBreakpoint $breakpoint
     * @param string|null $type
     * @return string
     */
    private function renderSource(string $srcset, CropBreakpoint $breakpoint, ?string $type = null): string
    {
        $options = ['media' => $breakpoint->getMediaQuery(), 'srcset' => $srcset];

        if ($type !== null) {
            $options['type'] = $type;
        }

        return self::INDENT . Html::tag('source', '', $this->withDimensions($options, $breakpoint));
    }

    /**
     * Add the breakpoint's image size to element attributes when it is known
     *
     * @param array<string,string> $options
     * @param CropBreakpoint $breakpoint
     * @return array<string,string|int>
     */
    private function withDimensions(array $options, CropBreakpoint $breakpoint): array
    {
        if (!$breakpoint->hasDimensions()) {
            return $options;
        }

        return $options + ['width' => $breakpoint->getWidth(), 'height' => $breakpoint->getHeight()];
    }
}
