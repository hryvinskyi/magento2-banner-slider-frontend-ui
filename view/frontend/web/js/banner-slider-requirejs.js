/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Starts a banner slider on a page with RequireJS, through the container's `data-mage-init`.
 *
 * The component takes no options: everything the slider needs is in the container's `data-hbs-config`.
 */
define([
    'splide',
    'Hryvinskyi_BannerSliderFrontendUi/js/banner-slider'
], function (Splide, bannerSlider) {
    'use strict';

    /**
     * @param {Object} config Unused; the settings are read from the element
     * @param {HTMLElement} element The slider container
     * @return {void}
     */
    return function (config, element) {
        bannerSlider.mount(element, Splide);
    };
});
