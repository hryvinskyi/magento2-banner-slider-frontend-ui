<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\ViewModel;

use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\ResponsiveCropInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\ResponsiveCropRepositoryInterface;
use Hryvinskyi\BannerSliderApi\Api\Video\ProviderInterface;
use Hryvinskyi\BannerSliderApi\Api\Video\ProviderResolverInterface;
use Hryvinskyi\BannerSliderApi\Api\Video\VideoDataInterface;
use Hryvinskyi\BannerSliderFrontendUi\Api\Attribute\ElementAttributePoolInterface;
use Hryvinskyi\Base\Helper\Html;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Banner renderer view model for generating banner HTML
 */
class BannerRenderer implements ArgumentInterface
{
    /**
     * @var array<int, array<ResponsiveCropInterface>>
     */
    private array $responsiveCropsCache = [];

    /**
     * @var array<string, array{width: int, height: int}|null>
     */
    private array $imageDimensionsCache = [];

    /**
     * @param StoreManagerInterface $storeManager
     * @param ProviderResolverInterface $providerResolver
     * @param Escaper $escaper
     * @param ResponsiveCropRepositoryInterface $responsiveCropRepository
     * @param LoggerInterface $logger
     * @param Filesystem $filesystem
     * @param FilterProvider $filterProvider
     * @param ElementAttributePoolInterface $elementAttributePool
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly ProviderResolverInterface $providerResolver,
        private readonly Escaper $escaper,
        private readonly ResponsiveCropRepositoryInterface $responsiveCropRepository,
        private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem,
        private readonly FilterProvider $filterProvider,
        private readonly ElementAttributePoolInterface $elementAttributePool
    ) {
    }

    /**
     * Check if banner is video type
     *
     * @param BannerInterface $banner
     * @return bool
     */
    public function isVideoType(BannerInterface $banner): bool
    {
        return $banner->getType() === BannerInterface::TYPE_VIDEO;
    }

    /**
     * Check if banner is custom HTML type
     *
     * @param BannerInterface $banner
     * @return bool
     */
    public function isCustomType(BannerInterface $banner): bool
    {
        return $banner->getType() === BannerInterface::TYPE_CUSTOM;
    }

    /**
     * Filter content to process Magento directives like {{store url="..."}}
     *
     * @param string|null $content
     * @return string
     */
    public function filterContent(?string $content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        try {
            return $this->filterProvider->getBlockFilter()->filter($content);
        } catch (\Exception $e) {
            $this->logger->error(
                'Error filtering banner content',
                ['error' => $e->getMessage()]
            );
            return $content;
        }
    }

    /**
     * Get image URL for banner
     *
     * @param BannerInterface $banner
     * @return string|null
     * @throws NoSuchEntityException
     */
    public function getImageUrl(BannerInterface $banner): ?string
    {
        $image = $banner->getImage();

        if (empty($image)) {
            return null;
        }

        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        return rtrim($mediaUrl, '/') . '/' . ltrim($image, '/');
    }

    /**
     * Get video provider for banner
     *
     * @param BannerInterface $banner
     * @return ProviderInterface|null
     */
    public function getVideoProvider(BannerInterface $banner): ?ProviderInterface
    {
        $videoUrl = $banner->getVideoUrl();
        $videoPath = $banner->getVideoPath();

        if (!empty($videoPath)) {
            return $this->providerResolver->resolve($videoPath);
        }

        if (!empty($videoUrl)) {
            return $this->providerResolver->resolve($videoUrl);
        }

        return null;
    }

    /**
     * Get video data for banner
     *
     * @param BannerInterface $banner
     * @return VideoDataInterface|null
     */
    public function getVideoData(BannerInterface $banner): ?VideoDataInterface
    {
        $provider = $this->getVideoProvider($banner);

        if (!$provider) {
            return null;
        }

        $videoUrl = $banner->getVideoPath() ?: $banner->getVideoUrl();

        if (!$videoUrl) {
            return null;
        }

        try {
            return $provider->parse($videoUrl);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * Get video HTML for banner
     *
     * @param BannerInterface $banner
     * @return string
     */
    public function getVideoHtml(BannerInterface $banner): string
    {
        $provider = $this->getVideoProvider($banner);
        $videoData = $this->getVideoData($banner);

        if (!$provider || !$videoData) {
            return '';
        }

        $embedUrl = $provider->getEmbedUrl($videoData);
        $attributes = $provider->getEmbedAttributes();
        $aspectRatio = $banner->getVideoAspectRatio() ?: '16:9';
        $isBackground = $banner->isVideoAsBackground();
        $overlayContent = $isBackground ? $banner->getContent() : null;

        if ($provider->isLocal()) {
            return $this->renderLocalVideo($embedUrl, $attributes, $aspectRatio, $isBackground, $overlayContent);
        }

        if ($isBackground) {
            $embedUrl = $this->modifyEmbedUrlForBackground($embedUrl, $provider->getCode());
        }

        return $this->renderIframeVideo($embedUrl, $attributes, $aspectRatio, $isBackground, $overlayContent);
    }

    /**
     * Render local video element
     *
     * @param string $url
     * @param array<string, string> $attributes
     * @param string $aspectRatio
     * @param bool $isBackground
     * @param string|null $overlayContent
     * @return string
     */
    private function renderLocalVideo(
        string $url,
        array $attributes,
        string $aspectRatio,
        bool $isBackground = false,
        ?string $overlayContent = null
    ): string {
        if ($isBackground) {
            $attributes['autoplay'] = 'autoplay';
            $attributes['loop'] = 'loop';
            $attributes['muted'] = 'muted';
            $attributes['playsinline'] = 'playsinline';
            unset($attributes['controls']);
        }

        $paddingBottom = $this->calculateAspectRatioPadding($aspectRatio);

        $wrapperClass = 'banner-slider-video-wrapper';
        if ($isBackground) {
            $wrapperClass .= ' banner-slider-video-background';
        }

        $attributes['src'] = $url;
        $attributes['style'] = 'position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;';

        $videoHtml = Html::tag('video', 'Your browser does not support the video tag.', $attributes);

        $overlay = '';
        if ($isBackground && !empty($overlayContent)) {
            $overlay = Html::tag('div', $this->filterContent($overlayContent), ['class' => 'banner-slider-video-overlay']);
        }

        return Html::tag('div', $videoHtml . $overlay, [
            'class' => $wrapperClass,
            'style' => 'position:relative;padding-bottom:' . $paddingBottom . '%;'
        ]);
    }

    /**
     * Render iframe video element
     *
     * @param string $url
     * @param array<string, string> $attributes
     * @param string $aspectRatio
     * @param bool $isBackground
     * @param string|null $overlayContent
     * @return string
     */
    private function renderIframeVideo(
        string $url,
        array $attributes,
        string $aspectRatio,
        bool $isBackground = false,
        ?string $overlayContent = null
    ): string {
        $paddingBottom = $this->calculateAspectRatioPadding($aspectRatio);

        $wrapperClass = 'banner-slider-video-wrapper';
        if ($isBackground) {
            $wrapperClass .= ' banner-slider-video-background';
        }

        $attributes['src'] = $url;
        $attributes['style'] = 'position:absolute;top:0;left:0;width:100%;height:100%;';

        $iframeHtml = Html::tag('iframe', '', $attributes);

        $overlay = '';
        if ($isBackground && !empty($overlayContent)) {
            $overlay = Html::tag('div', $this->filterContent($overlayContent), ['class' => 'banner-slider-video-overlay']);
        }

        return Html::tag('div', $iframeHtml . $overlay, [
            'class' => $wrapperClass,
            'style' => 'position:relative;padding-bottom:' . $paddingBottom . '%;'
        ]);
    }

    /**
     * Modify embed URL to add background mode parameters for YouTube/Vimeo
     *
     * @param string $url
     * @param string $providerCode
     * @return string
     */
    private function modifyEmbedUrlForBackground(string $url, string $providerCode): string
    {
        $params = [];

        if ($providerCode === 'youtube') {
            $params['autoplay'] = '1';
            $params['mute'] = '1';
            $params['loop'] = '1';
            $params['controls'] = '0';
            $params['showinfo'] = '0';
            $params['rel'] = '0';
            $params['modestbranding'] = '1';
            $params['playlist'] = $this->extractYouTubeVideoId($url);
        } elseif ($providerCode === 'vimeo') {
            $params['autoplay'] = '1';
            $params['muted'] = '1';
            $params['loop'] = '1';
            $params['background'] = '1';
        }

        if (empty($params)) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($params);
    }

    /**
     * Extract YouTube video ID from embed URL
     *
     * @param string $url
     * @return string
     */
    private function extractYouTubeVideoId(string $url): string
    {
        if (preg_match('/\/embed\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Calculate padding bottom percentage from aspect ratio
     *
     * @param string $aspectRatio
     * @return float
     */
    private function calculateAspectRatioPadding(string $aspectRatio): float
    {
        $parts = explode(':', $aspectRatio);

        if (count($parts) !== 2) {
            return 56.25;
        }

        $width = (float)$parts[0];
        $height = (float)$parts[1];

        if ($width <= 0) {
            return 56.25;
        }

        return round(($height / $width) * 100, 2);
    }

    /**
     * Build HTML attribute string
     *
     * @param array $attributes
     * @return string
     */
    private function buildAttributeString(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $key => $value) {
            if ($value === $key) {
                $parts[] = $this->escaper->escapeHtmlAttr($key);
            } else {
                $parts[] = sprintf(
                    '%s="%s"',
                    $this->escaper->escapeHtmlAttr($key),
                    $this->escaper->escapeHtmlAttr($value)
                );
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Get image HTML for banner
     *
     * @param BannerInterface $banner
     * @param bool $lazyLoad
     * @return string
     * @throws NoSuchEntityException
     */
    public function getImageHtml(BannerInterface $banner, bool $lazyLoad = false): string
    {
        $imageUrl = $this->getImageUrl($banner);

        if (!$imageUrl) {
            return '';
        }

        $options = [
            'alt' => $banner->getTitle() ?: $banner->getName() ?: '',
            'class' => 'banner-slider-image',
        ];

        if ($lazyLoad) {
            $options['loading'] = 'lazy';
        }

        $imagePath = '/' . ltrim($banner->getImage(), '/');
        $dimensions = $this->getImageDimensions($imagePath);

        if ($dimensions) {
            $options['width'] = $dimensions['width'];
            $options['height'] = $dimensions['height'];
        }

        return Html::img($imageUrl, $options);
    }

    /**
     * Preload responsive crops for multiple banners in a single query
     *
     * Call this method before rendering multiple banners to avoid N+1 queries.
     *
     * @param array<BannerInterface> $banners
     * @return void
     */
    public function preloadResponsiveCrops(array $banners): void
    {
        $bannerIds = [];
        foreach ($banners as $banner) {
            $bannerId = (int)$banner->getBannerId();
            if ($bannerId && !isset($this->responsiveCropsCache[$bannerId])) {
                $bannerIds[] = $bannerId;
            }
        }

        if (empty($bannerIds)) {
            return;
        }

        try {
            $cropsGrouped = $this->responsiveCropRepository->getByBannerIds($bannerIds);
            foreach ($cropsGrouped as $bannerId => $crops) {
                $this->responsiveCropsCache[$bannerId] = $crops;
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'Error preloading responsive crops for banners',
                ['banner_ids' => $bannerIds, 'error' => $e->getMessage()]
            );
            foreach ($bannerIds as $bannerId) {
                $this->responsiveCropsCache[$bannerId] = [];
            }
        }
    }

    /**
     * Check if banner has responsive crops configured
     *
     * @param BannerInterface $banner
     * @return bool
     */
    public function hasResponsiveCrops(BannerInterface $banner): bool
    {
        $crops = $this->getResponsiveCrops($banner);
        return !empty($crops);
    }

    /**
     * Get responsive crops for banner
     *
     * @param BannerInterface $banner
     * @return array<ResponsiveCropInterface>
     */
    public function getResponsiveCrops(BannerInterface $banner): array
    {
        $bannerId = (int)$banner->getBannerId();

        if (!$bannerId) {
            return [];
        }

        if (!isset($this->responsiveCropsCache[$bannerId])) {
            try {
                $this->responsiveCropsCache[$bannerId] = $this->responsiveCropRepository->getByBannerId($bannerId);
            } catch (\Exception $e) {
                $this->logger->error(
                    'Error loading responsive crops for banner',
                    ['banner_id' => $bannerId, 'error' => $e->getMessage()]
                );
                $this->responsiveCropsCache[$bannerId] = [];
            }
        }

        return $this->responsiveCropsCache[$bannerId];
    }

    /**
     * Get responsive image HTML with picture element for banner
     *
     * Generates a <picture> element with sources ordered:
     * AVIF → WebP → Original per breakpoint (sorted by min_width descending for desktop-first)
     *
     * @param BannerInterface $banner
     * @param bool $lazyLoad
     * @return string
     * @throws NoSuchEntityException
     */
    public function getResponsiveImageHtml(BannerInterface $banner, bool $lazyLoad = false): string
    {
        $crops = $this->getResponsiveCrops($banner);

        if (empty($crops)) {
            return $this->getImageHtml($banner, $lazyLoad);
        }

        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        $alt = $banner->getTitle() ?: $banner->getName() ?: '';

        // Sort crops by sort_order (which corresponds to breakpoint sort_order - desktop first)
        usort($crops, function (ResponsiveCropInterface $a, ResponsiveCropInterface $b) {
            return ($a->getSortOrder() ?? 0) <=> ($b->getSortOrder() ?? 0);
        });

        $sources = [];
        $fallbackUrl = null;
        $fallbackWidth = null;
        $fallbackHeight = null;

        foreach ($crops as $crop) {
            if (!$crop->getCroppedImage()) {
                continue;
            }

            $mediaQuery = $this->getMediaQueryFromCrop($crop);
            $croppedUrl = $mediaUrl . $crop->getCroppedImage();
            $cropData = $crop->getData();

            // Track fallback dimensions (first crop = largest/desktop breakpoint for proper CLS)
            if ($fallbackUrl === null) {
                $fallbackUrl = $croppedUrl;
                $fallbackWidth = $cropData['target_width'] ?? null;
                $fallbackHeight = $cropData['target_height'] ?? null;
            }

            // Add AVIF source if available
            if ($crop->isGenerateAvifEnabled() && $crop->getAvifImage()) {
                $avifUrl = $mediaUrl . $crop->getAvifImage();
                $sources[] = $this->buildSourceElement($avifUrl, $mediaQuery, 'image/avif');
            }

            // Add WebP source if available
            if ($crop->isGenerateWebpEnabled() && $crop->getWebpImage()) {
                $webpUrl = $mediaUrl . $crop->getWebpImage();
                $sources[] = $this->buildSourceElement($webpUrl, $mediaQuery, 'image/webp');
            }

            // Add original format source
            $sources[] = $this->buildSourceElement($croppedUrl, $mediaQuery);
        }

        if (empty($sources)) {
            return $this->getImageHtml($banner, $lazyLoad);
        }

        // Use legacy image as ultimate fallback if no responsive fallback
        if (!$fallbackUrl) {
            $fallbackUrl = $this->getImageUrl($banner) ?: '';
        }

        $imgOptions = [
            'alt' => $alt,
            'class' => 'banner-slider-image',
        ];

        if ($fallbackWidth && $fallbackHeight) {
            $imgOptions['width'] = (int)$fallbackWidth;
            $imgOptions['height'] = (int)$fallbackHeight;
        }

        if ($lazyLoad) {
            $imgOptions['loading'] = 'lazy';
        }

        $imgHtml = '    ' . Html::img($fallbackUrl, $imgOptions);
        $content = "\n" . implode("\n", $sources) . "\n" . $imgHtml . "\n";

        return Html::tag('picture', $content);
    }

    /**
     * Build a source element for the picture tag
     *
     * @param string $srcset
     * @param string $mediaQuery
     * @param string|null $type
     * @return string
     */
    private function buildSourceElement(string $srcset, string $mediaQuery, ?string $type = null): string
    {
        $options = [
            'media' => $mediaQuery,
            'srcset' => $srcset,
        ];

        if ($type !== null) {
            $options['type'] = $type;
        }

        return '    ' . Html::tag('source', '', $options);
    }

    /**
     * Get media query from crop (loaded via breakpoint join)
     *
     * @param ResponsiveCropInterface $crop
     * @return string
     */
    private function getMediaQueryFromCrop(ResponsiveCropInterface $crop): string
    {
        // The media_query should be loaded via joined breakpoint data
        $data = $crop->getData();
        return $data['media_query'] ?? '(min-width: 0px)';
    }

    /**
     * Get preload link attributes for a banner (checks isPreloadEnabled)
     *
     * @param BannerInterface $banner
     * @return array<array{rel: string, href: string, as: string, type?: string, imagesrcset?: string, imagesizes?: string}>
     * @throws NoSuchEntityException
     */
    public function getPreloadLinks(BannerInterface $banner): array
    {
        if (!$banner->isPreloadEnabled()) {
            return [];
        }

        return $this->getPreloadLinksForBanner($banner);
    }

    /**
     * Get preload link attributes for a banner (without checking isPreloadEnabled)
     *
     * @param BannerInterface $banner
     * @return array<array{rel: string, href: string, as: string, type?: string, imagesrcset?: string, imagesizes?: string}>
     * @throws NoSuchEntityException
     */
    public function getPreloadLinksForBanner(BannerInterface $banner): array
    {
        $links = [];
        $crops = $this->getResponsiveCrops($banner);

        if (!empty($crops)) {
            $links = $this->buildResponsivePreloadLinks($crops);
        } else {
            $imageUrl = $this->getImageUrl($banner);
            if ($imageUrl) {
                $links[] = [
                    'rel' => 'preload',
                    'href' => $imageUrl,
                    'as' => 'image'
                ];
            }
        }

        return $links;
    }

    /**
     * Build preload link for responsive images with srcset
     *
     * Only preloads the most preferred format (AVIF > WebP > original) to avoid
     * multiple downloads. The type attribute acts as progressive enhancement -
     * browsers that don't support the type won't download it.
     *
     * @param array<ResponsiveCropInterface> $crops
     * @return array<array{rel: string, href: string, as: string, type?: string, imagesrcset?: string, imagesizes?: string}>
     * @throws NoSuchEntityException
     */
    private function buildResponsivePreloadLinks(array $crops): array
    {
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        // Sort by sort_order (desktop first)
        usort($crops, function (ResponsiveCropInterface $a, ResponsiveCropInterface $b) {
            return ($a->getSortOrder() ?? 0) <=> ($b->getSortOrder() ?? 0);
        });

        $avifSrcset = [];
        $webpSrcset = [];
        $originalSrcset = [];
        $sizes = [];

        foreach ($crops as $crop) {
            if (!$crop->getCroppedImage()) {
                continue;
            }

            $cropData = $crop->getData();
            $mediaQuery = $cropData['media_query'] ?? '';
            $targetWidth = $cropData['target_width'] ?? 0;

            if ($mediaQuery && $targetWidth) {
                $sizes[] = $mediaQuery . ' ' . $targetWidth . 'px';
            }

            $croppedUrl = $mediaUrl . $crop->getCroppedImage();
            $originalSrcset[] = $croppedUrl . ' ' . $targetWidth . 'w';

            if ($crop->isGenerateAvifEnabled() && $crop->getAvifImage()) {
                $avifSrcset[] = $mediaUrl . $crop->getAvifImage() . ' ' . $targetWidth . 'w';
            }

            if ($crop->isGenerateWebpEnabled() && $crop->getWebpImage()) {
                $webpSrcset[] = $mediaUrl . $crop->getWebpImage() . ' ' . $targetWidth . 'w';
            }
        }

        $sizesString = !empty($sizes) ? implode(', ', $sizes) . ', 100vw' : '100vw';

        // Only preload the most preferred format to avoid multiple downloads
        // AVIF is preferred (with type for progressive enhancement)
        if (!empty($avifSrcset)) {
            return [[
                'rel' => 'preload',
                'as' => 'image',
                'href' => explode(' ', $avifSrcset[0])[0],
                'type' => 'image/avif',
                'imagesrcset' => implode(', ', $avifSrcset),
                'imagesizes' => $sizesString
            ]];
        }

        // WebP fallback (with type for progressive enhancement)
        if (!empty($webpSrcset)) {
            return [[
                'rel' => 'preload',
                'as' => 'image',
                'href' => explode(' ', $webpSrcset[0])[0],
                'type' => 'image/webp',
                'imagesrcset' => implode(', ', $webpSrcset),
                'imagesizes' => $sizesString
            ]];
        }

        // Original format as last resort (no type, all browsers will download)
        if (!empty($originalSrcset)) {
            return [[
                'rel' => 'preload',
                'as' => 'image',
                'href' => explode(' ', $originalSrcset[0])[0],
                'imagesrcset' => implode(', ', $originalSrcset),
                'imagesizes' => $sizesString
            ]];
        }

        return [];
    }

    /**
     * Get image dimensions from file
     *
     * @param string $imagePath
     * @return array{width: int, height: int}|null
     */
    public function getImageDimensions(string $imagePath): ?array
    {
        if (isset($this->imageDimensionsCache[$imagePath])) {
            return $this->imageDimensionsCache[$imagePath];
        }

        try {
            $mediaDir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            $absolutePath = $mediaDir->getAbsolutePath($imagePath);

            if (!$mediaDir->isFile($imagePath)) {
                $this->imageDimensionsCache[$imagePath] = null;
                return null;
            }

            $imageInfo = getimagesize($absolutePath);

            if ($imageInfo === false) {
                $this->imageDimensionsCache[$imagePath] = null;
                return null;
            }

            $this->imageDimensionsCache[$imagePath] = [
                'width' => (int)$imageInfo[0],
                'height' => (int)$imageInfo[1]
            ];
        } catch (\Exception $e) {
            $this->logger->error(
                'Error getting image dimensions',
                ['image' => $imagePath, 'error' => $e->getMessage()]
            );
            $this->imageDimensionsCache[$imagePath] = null;
        }

        return $this->imageDimensionsCache[$imagePath];
    }

    /**
     * Get link attributes for banner
     *
     * @param BannerInterface $banner
     * @param SliderInterface|null $slider
     * @return string
     */
    public function getLinkAttributes(BannerInterface $banner, ?SliderInterface $slider = null): string
    {
        $attributes = [];

        if ($banner->getLinkUrl()) {
            $attributes['href'] = $banner->getLinkUrl();

            if ($banner->isOpenInNewTab()) {
                $attributes['target'] = '_blank';
                $attributes['rel'] = 'noopener noreferrer';
            }

            if ($banner->getTitle()) {
                $attributes['title'] = $banner->getTitle();
            }
        }

        if ($slider !== null) {
            $poolAttributes = $this->elementAttributePool->getLinkAttributes($slider, $banner);
            $attributes = $this->mergeAttributeArrays($attributes, $poolAttributes);
        }

        return $this->buildAttributeString($attributes);
    }

    /**
     * Check if banner has link
     *
     * @param BannerInterface $banner
     * @return bool
     */
    public function hasLink(BannerInterface $banner): bool
    {
        return !empty($banner->getLinkUrl());
    }

    /**
     * Get container attributes HTML string with merged pool attributes
     *
     * @param SliderInterface $slider
     * @param array<BannerInterface> $banners
     * @param array<string, string|bool|int> $baseAttributes
     * @return string
     */
    public function getContainerAttributesHtml(
        SliderInterface $slider,
        array $banners,
        array $baseAttributes = []
    ): string {
        $poolAttributes = $this->elementAttributePool->getContainerAttributes($slider, $banners);
        $mergedAttributes = $this->mergeAttributeArrays($baseAttributes, $poolAttributes);

        return Html::renderTagAttributes($mergedAttributes);
    }

    /**
     * Get slide attributes HTML string with merged pool attributes
     *
     * @param SliderInterface $slider
     * @param BannerInterface $banner
     * @param array<string, string|bool|int> $baseAttributes
     * @return string
     */
    public function getSlideAttributesHtml(
        SliderInterface $slider,
        BannerInterface $banner,
        array $baseAttributes = []
    ): string {
        $poolAttributes = $this->elementAttributePool->getSlideAttributes($slider, $banner);
        $mergedAttributes = $this->mergeAttributeArrays($baseAttributes, $poolAttributes);

        return Html::renderTagAttributes($mergedAttributes);
    }

    /**
     * Merge two attribute arrays with special handling for class attribute
     *
     * @param array<string, string|bool|int> $base
     * @param array<string, string|bool|int> $additional
     * @return array<string, string|bool|int>
     */
    private function mergeAttributeArrays(array $base, array $additional): array
    {
        foreach ($additional as $name => $value) {
            if ($name === 'class' && isset($base['class'])) {
                $baseClasses = is_string($base['class'])
                    ? explode(' ', $base['class'])
                    : [$base['class']];
                $additionalClasses = is_string($value)
                    ? explode(' ', $value)
                    : [$value];
                $merged = array_unique(array_merge($baseClasses, $additionalClasses));
                $base['class'] = implode(' ', array_filter($merged));
            } else {
                $base[$name] = $value;
            }
        }

        return $base;
    }
}
