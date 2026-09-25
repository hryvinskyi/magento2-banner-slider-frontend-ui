<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\BannerSliderFrontendUi\Block\Widget;

use Hryvinskyi\BannerSliderApi\Api\Banner\VisibleBannersProviderInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\BannerInterface;
use Hryvinskyi\BannerSliderApi\Api\Data\SliderInterface;
use Hryvinskyi\BannerSliderApi\Api\Slider\SliderLocatorInterface;
use Hryvinskyi\BannerSliderApi\Api\Value\LocationCode;
use Hryvinskyi\BannerSliderFrontendUi\Api\Render\TemplateRendererInterface;
use Hryvinskyi\BannerSliderFrontendUi\Model\Head\HeadAssetRegistrar;
use Hryvinskyi\BannerSliderFrontendUi\Model\StorefrontContextProvider;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\SliderView;
use Hryvinskyi\BannerSliderFrontendUi\Model\View\SliderViewBuilder;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use Psr\Log\LoggerInterface;

/**
 * Renders one banner slider, placed by layout XML or as a CMS widget.
 *
 * Placement arguments:
 * - `slider_id`: a slider id; takes precedence when given;
 * - `location`: a location code; the qualifying slider with the lowest priority value placed there renders.
 * A slider renders only when it is enabled, visible to the store view and customer group, inside its active window,
 * and has at least one slide that renders. Otherwise the block renders nothing.
 *
 * Caching: the block has no cache lifetime and no cache key of its own; the full page cache holds the page, and the
 * cache tags below invalidate it:
 * - the slider's tag and each shown banner's tag;
 * - by location: the location's tag, whether or not a slider was found, so a slider saved into the location
 *   refreshes the page;
 * - by id: the requested slider's tag, whether or not it was found, so a slider whose active window opens later
 *   refreshes pages cached while it was hidden;
 * - the generic slider tag when no slider was found.
 *
 * Without a `template` argument the block renders `slider.phtml`, so a widget directive that names no template
 * still shows the slider.
 *
 * Head elements (stylesheets, image preloads, custom CSS) are registered by the block before its template renders.
 * After every slider container the block renders the bootstrap template, which starts the slider on pages without
 * a module loader.
 */
class Slider extends Template implements BlockInterface, IdentityInterface
{
    public const DEFAULT_TEMPLATE = 'Hryvinskyi_BannerSliderFrontendUi::slider.phtml';
    public const BOOTSTRAP_TEMPLATE = 'Hryvinskyi_BannerSliderFrontendUi::bootstrap.phtml';

    private const ARGUMENT_SLIDER_ID = 'slider_id';
    private const ARGUMENT_LOCATION = 'location';

    /**
     * @var bool Whether the slider and its banners have been looked up
     */
    private bool $resolved = false;

    /**
     * @var SliderInterface|null
     */
    private ?SliderInterface $slider = null;

    /**
     * @var list<BannerInterface>
     */
    private array $banners = [];

    /**
     * @var bool Whether the view has been built
     */
    private bool $viewBuilt = false;

    /**
     * @var SliderView|null
     */
    private ?SliderView $view = null;

    /**
     * @var bool Whether the location argument has been parsed
     */
    private bool $locationParsed = false;

    /**
     * @var LocationCode|null
     */
    private ?LocationCode $location = null;

    /**
     * @param Template\Context $context
     * @param SliderLocatorInterface $sliderLocator
     * @param VisibleBannersProviderInterface $visibleBannersProvider
     * @param StorefrontContextProvider $storefrontContextProvider
     * @param SliderViewBuilder $sliderViewBuilder
     * @param HeadAssetRegistrar $headAssetRegistrar
     * @param TemplateRendererInterface $templateRenderer
     * @param LoggerInterface $logger
     * @param array<string,mixed> $data Block data; `template` defaults to the slider template
     */
    public function __construct(
        Template\Context $context,
        private readonly SliderLocatorInterface $sliderLocator,
        private readonly VisibleBannersProviderInterface $visibleBannersProvider,
        private readonly StorefrontContextProvider $storefrontContextProvider,
        private readonly SliderViewBuilder $sliderViewBuilder,
        private readonly HeadAssetRegistrar $headAssetRegistrar,
        private readonly TemplateRendererInterface $templateRenderer,
        private readonly LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data + ['template' => self::DEFAULT_TEMPLATE]);
    }

    /**
     * The `slider_id` argument as a slider id, or null when it is absent or not a positive whole number
     *
     * @return int|null
     */
    public function getSliderIdArgument(): ?int
    {
        $value = $this->getData(self::ARGUMENT_SLIDER_ID);
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && preg_match('/^\s*[1-9]\d*\s*$/', $value) === 1) {
            return (int)$value;
        }

        return null;
    }

    /**
     * The `location` argument as a location code, or null when it is absent or not a valid code
     *
     * An invalid code is logged once per block.
     *
     * @return LocationCode|null
     */
    public function getLocationArgument(): ?LocationCode
    {
        if ($this->locationParsed) {
            return $this->location;
        }

        $this->locationParsed = true;
        $value = $this->getData(self::ARGUMENT_LOCATION);
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $this->location = new LocationCode(trim($value));
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Banner slider: the location argument is not a valid location code.', [
                'block' => $this->getNameInLayout(),
                'exception' => $e,
            ]);
        }

        return $this->location;
    }

    /**
     * The slider this block renders, or null when none qualifies
     *
     * @return SliderInterface|null
     */
    public function getSlider(): ?SliderInterface
    {
        $this->resolve();

        return $this->slider;
    }

    /**
     * The view the template renders, or null when there is nothing to render
     *
     * @return SliderView|null
     */
    public function getSliderView(): ?SliderView
    {
        if ($this->viewBuilt) {
            return $this->view;
        }

        $this->viewBuilt = true;
        $slider = $this->getSlider();
        if ($slider === null || $this->banners === []) {
            return null;
        }

        try {
            $this->view = $this->sliderViewBuilder->build($slider, $this->banners);
        } catch (\InvalidArgumentException | LocalizedException | \JsonException $e) {
            $this->logger->error('Banner slider: the slider could not be rendered.', [
                'slider_id' => $slider->getSliderId(),
                'exception' => $e,
            ]);
        }

        return $this->view;
    }

    /**
     * @inheritDoc
     */
    public function getIdentities(): array
    {
        $identities = [];
        $sliderId = $this->getSliderIdArgument();
        if ($sliderId !== null) {
            $identities[] = SliderInterface::CACHE_TAG . '_' . $sliderId;
        }

        $location = $sliderId === null ? $this->getLocationArgument() : null;
        if ($location !== null) {
            $identities[] = $location->toCacheTag();
        }

        $slider = $this->getSlider();
        if ($slider === null) {
            $identities[] = SliderInterface::CACHE_TAG;

            return array_values(array_unique($identities));
        }

        $identities[] = SliderInterface::CACHE_TAG . '_' . (int)$slider->getSliderId();
        foreach ($this->banners as $banner) {
            $identities[] = BannerInterface::CACHE_TAG . '_' . (int)$banner->getBannerId();
        }

        return array_values(array_unique($identities));
    }

    /**
     * Render the slider, after registering its head elements, followed by the bootstrap
     *
     * @return string
     */
    protected function _toHtml(): string
    {
        $view = $this->getSliderView();
        if ($view === null) {
            return '';
        }

        try {
            $this->headAssetRegistrar->register($view);
            $html = parent::_toHtml();
            if (trim($html) === '') {
                return '';
            }

            return $html . $this->templateRenderer->render(self::BOOTSTRAP_TEMPLATE);
        } catch (LocalizedException $e) {
            $this->logger->error('Banner slider: the slider could not be rendered.', [
                'slider_id' => $view->getSlider()->getSliderId(),
                'exception' => $e,
            ]);

            return '';
        }
    }

    /**
     * Look up the slider and its visible banners once
     *
     * @return void
     */
    private function resolve(): void
    {
        if ($this->resolved) {
            return;
        }

        $this->resolved = true;
        $sliderId = $this->getSliderIdArgument();
        $location = $sliderId === null ? $this->getLocationArgument() : null;
        if ($sliderId === null && $location === null) {
            return;
        }

        try {
            $context = $this->storefrontContextProvider->get();
            $this->slider = $sliderId !== null
                ? $this->sliderLocator->findById($sliderId, $context)
                : $this->sliderLocator->findByLocation($location->getCode(), $context);
            $foundId = $this->slider?->getSliderId();
            $this->banners = $foundId !== null
                ? $this->visibleBannersProvider->getForSlider($foundId, $context->getNow())
                : [];
        } catch (LocalizedException $e) {
            $this->logger->error('Banner slider: the slider could not be looked up.', ['exception' => $e]);
            $this->slider = null;
            $this->banners = [];
        }
    }
}
