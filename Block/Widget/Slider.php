<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Block\Widget;

use Hryvinskyi\BannerSlider\Model\Banner;
use Hryvinskyi\BannerSlider\Model\ResourceModel\Banner\CollectionFactory as BannerCollectionFactory;
use Hryvinskyi\BannerSlider\Model\Slider as SliderModel;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Slider\Locator\SliderLocatorInterface;
use Hryvinskyi\BannerSliderFrontendUi\ViewModel\BannerRenderer;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;

/**
 * Banner slider widget block
 */
class Slider extends Template implements BlockInterface, IdentityInterface
{
    /**
     * @var SliderInterface|null|false
     */
    private SliderInterface|null|false $slider = null;

    /**
     * @var BannerInterface[]|null
     */
    private ?array $banners = null;

    /**
     * @param Template\Context $context
     * @param SliderLocatorInterface $sliderLocator
     * @param BannerCollectionFactory $bannerCollectionFactory
     * @param BannerRenderer $bannerRenderer
     * @param HttpContext $httpContext
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        private readonly SliderLocatorInterface $sliderLocator,
        private readonly BannerCollectionFactory $bannerCollectionFactory,
        private readonly BannerRenderer $bannerRenderer,
        private readonly HttpContext $httpContext,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get slider instance with store and customer group validation
     *
     * @return SliderInterface|null
     * @throws NoSuchEntityException
     */
    public function getSlider(): ?SliderInterface
    {
        if ($this->slider === null) {
            $sliderId = (int)$this->getData('slider_id');

            if (!$sliderId) {
                $this->slider = false;
                return null;
            }

            $storeId = (int)$this->_storeManager->getStore()->getId();
            $customerGroupId = (int)$this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);

            $this->slider = $this->sliderLocator->getById($sliderId, $storeId, $customerGroupId) ?? false;
        }

        return $this->slider ?: null;
    }

    /**
     * Get banners for the slider
     *
     * @return BannerInterface[]
     * @throws NoSuchEntityException
     */
    public function getBanners(): array
    {
        if ($this->banners !== null) {
            return $this->banners;
        }

        $this->banners = [];
        $slider = $this->getSlider();

        if (!$slider) {
            return $this->banners;
        }

        $collection = $this->bannerCollectionFactory->create();
        $collection->addSliderFilter($slider->getSliderId());
        $collection->addActiveFilter();
        $collection->addDateFilter();
        $collection->addPositionOrder();

        $this->banners = $collection->getItems();

        // Preload responsive crops for all banners in a single query to avoid N+1
        if (!empty($this->banners)) {
            $this->bannerRenderer->preloadResponsiveCrops($this->banners);
        }

        return $this->banners;
    }

    /**
     * Get banner renderer view model
     *
     * @return BannerRenderer
     */
    public function getBannerRenderer(): BannerRenderer
    {
        return $this->bannerRenderer;
    }

    /**
     * Get slider JSON configuration for Splide.js
     *
     * @return string
     * @throws NoSuchEntityException
     * @throws \JsonException
     */
    public function getSliderConfig(): string
    {
        $slider = $this->getSlider();

        if (!$slider) {
            return '{}';
        }

        $type = 'slide';
        if ($slider->getEffect() === 'fade') {
            $type = 'fade';
        } elseif ($slider->isLoopEnabled()) {
            $type = 'loop';
        }

        $bannerCount = count($this->getBanners());
        $hasSingleBanner = $bannerCount <= 1;

        $config = [
            'type' => $type,
            'perPage' => 1,
            'perMove' => 1,
            'autoplay' => !$hasSingleBanner && $slider->isAutoPlayEnabled(),
            'interval' => $slider->getAutoPlayTimeout(),
            'pauseOnHover' => true,
            'pauseOnFocus' => true,
            'arrows' => !$hasSingleBanner && $slider->isNavigationEnabled(),
            'pagination' => !$hasSingleBanner && $slider->isPaginationEnabled(),
            'lazyLoad' => $slider->isLazyLoadEnabled() ? 'nearby' : false,
            'autoWidth' => $slider->isAutoWidthEnabled(),
            'autoHeight' => $slider->isAutoHeightEnabled(),
            'speed' => 400,
            'rewind' => !$slider->isLoopEnabled() && $type !== 'fade',
            'waitForTransition' => true,
        ];

        if ($slider->isResponsiveEnabled() && $slider->getResponsiveItems()) {
            $responsiveItems = json_decode($slider->getResponsiveItems(), true);
            if (is_array($responsiveItems)) {
                $config['breakpoints'] = $this->convertResponsiveConfig($responsiveItems);
            }
        }

        return json_encode($config, JSON_THROW_ON_ERROR);
    }

    /**
     * Convert OWL Carousel responsive config to Splide breakpoints format
     *
     * @param array<int|string, array<string, mixed>> $owlResponsive
     * @return array<int, array<string, mixed>>
     */
    private function convertResponsiveConfig(array $owlResponsive): array
    {
        $splideBreakpoints = [];

        foreach ($owlResponsive as $breakpoint => $settings) {
            $splideSettings = [];

            if (isset($settings['items'])) {
                $splideSettings['perPage'] = (int)$settings['items'];
            }

            if (isset($settings['nav'])) {
                $splideSettings['arrows'] = (bool)$settings['nav'];
            }

            if (isset($settings['dots'])) {
                $splideSettings['pagination'] = (bool)$settings['dots'];
            }

            if (isset($settings['autoplay'])) {
                $splideSettings['autoplay'] = (bool)$settings['autoplay'];
            }

            if (isset($settings['gap'])) {
                $splideSettings['gap'] = $settings['gap'];
            }

            if (!empty($splideSettings)) {
                $splideBreakpoints[(int)$breakpoint] = $splideSettings;
            }
        }

        return $splideBreakpoints;
    }

    /**
     * @inheritDoc
     */
    public function getIdentities(): array
    {
        $identities = [];
        $slider = $this->getSlider();

        if ($slider) {
            $identities[] = SliderModel::CACHE_TAG . '_' . $slider->getSliderId();

            foreach ($this->getBanners() as $banner) {
                $identities[] = Banner::CACHE_TAG . '_' . $banner->getBannerId();
            }
        }

        return $identities;
    }

    /**
     * Get cache key info for block caching with customer group variation
     *
     * @return array<int, string|int|null>
     * @throws NoSuchEntityException
     */
    public function getCacheKeyInfo(): array
    {
        return [
            'BANNER_SLIDER_WIDGET',
            $this->_storeManager->getStore()->getId(),
            $this->_design->getDesignTheme()->getId(),
            $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP),
            $this->getData('slider_id'),
            md5($this->getTemplate() . $this->getNameInLayout())
        ];
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml(): string
    {
        if (!$this->getSlider() || empty($this->getBanners())) {
            return '';
        }

        return parent::_toHtml();
    }
}
