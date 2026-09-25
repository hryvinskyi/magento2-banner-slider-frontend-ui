/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Starts banner sliders rendered by the slider block.
 *
 * Loaded as an AMD module when RequireJS is on the page, otherwise it publishes `window.HryvinskyiBannerSlider`.
 * Another AMD `define` on a page without RequireJS is not used: nothing on such a page would ask it for this
 * module, and the inline start-up script looks for the global. It needs no library besides Splide, which the caller
 * passes in.
 *
 * A slider container carries `data-hbs-slider` and its settings in `data-hbs-config`:
 * `{splide, pauseLabel, playLabel, videoPlayLabel, videoPauseLabel, hasVideo, backgroundVideo}`.
 * The Splide options are applied as built by the server; the only change made here is that a visitor who
 * prefers reduced motion gets autoplay switched on but paused.
 *
 * Sliders may be nested (a slide's content may hold another slider): every element a slider looks up belongs to
 * that slider, never to a slider inside it.
 *
 * A background video on a slide after the first waits with its URL in `data-hbs-src` instead of `src`; it is loaded
 * the first time it is played, which happens when its slide is shown unless the visitor paused it or prefers
 * reduced motion.
 */
(function (root, factory) {
    'use strict';

    var loader = root && root.require;

    if (typeof define === 'function' && define.amd && typeof loader === 'function'
        && typeof loader.defined === 'function') {
        define([], factory);
    } else if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.HryvinskyiBannerSlider = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    var SLIDER_SELECTOR = '[data-hbs-slider]',
        MOUNTED_ATTRIBUTE = 'data-hbs-mounted',
        MEDIA_SELECTOR = '[data-hbs-video]',
        DEFERRED_SOURCE_ATTRIBUTE = 'data-hbs-src',
        SLIDE_SELECTOR = '.splide__slide',
        VISIBLE_SLIDE_SELECTOR = '.is-visible',
        REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)',
        warned = false,

        /**
         * Messages that pause or play an embedded player, by provider code.
         * A provider missing here is left alone: nothing is sent to a player whose protocol is unknown.
         */
        videoProviders = {
            youtube: {
                pause: {event: 'command', func: 'pauseVideo', args: ''},
                play: {event: 'command', func: 'playVideo', args: ''}
            },
            vimeo: {
                pause: {method: 'pause'},
                play: {method: 'play'}
            }
        },

        /**
         * How a media element is paused or played, by the kind in its `data-hbs-video` attribute.
         */
        mediaControls = {
            video: controlNativeVideo,
            iframe: controlEmbeddedPlayer
        };

    /**
     * Do nothing
     *
     * @return {void}
     */
    function noop() {
    }

    /**
     * Log one warning per page, however many sliders fail
     *
     * @param {string} message
     * @return {void}
     */
    function warnOnce(message) {
        if (warned || typeof console === 'undefined') {
            return;
        }

        warned = true;
        console.warn(message);
    }

    /**
     * Call a function for every item of a list-like collection
     *
     * @param {ArrayLike} list
     * @param {Function} callback
     * @return {void}
     */
    function each(list, callback) {
        Array.prototype.forEach.call(list || [], callback);
    }

    /**
     * The slider an element belongs to: the nearest slider container around it, or the element itself
     *
     * @param {Element} element
     * @return {Element|null}
     */
    function sliderOf(element) {
        return typeof element.closest === 'function' ? element.closest(SLIDER_SELECTOR) : null;
    }

    /**
     * Elements matching a selector inside a part of the page that belong to the same slider as that part
     *
     * Elements of a slider nested inside it are left out.
     *
     * @param {Element} root A slider container, or an element inside one
     * @param {string} selector
     * @return {Array<Element>}
     */
    function ownElements(root, selector) {
        var owner = sliderOf(root),
            found = [];

        each(root.querySelectorAll(selector), function (element) {
            if (sliderOf(element) === owner) {
                found.push(element);
            }
        });

        return found;
    }

    /**
     * The first element matching a selector that belongs to the same slider as a part of the page
     *
     * @param {Element} root
     * @param {string} selector
     * @return {Element|null}
     */
    function ownElement(root, selector) {
        return ownElements(root, selector)[0] || null;
    }

    /**
     * Start one slider, reporting a failure without stopping the caller
     *
     * @param {Element} element
     * @param {Function} Splide
     * @return {Object|null} The Splide instance, or null when the slider did not start
     */
    function mountSafely(element, Splide) {
        try {
            return mount(element, Splide);
        } catch (error) {
            if (typeof console !== 'undefined') {
                console.warn('Banner slider: a slider could not be started.', error);
            }

            return null;
        }
    }

    /**
     * Whether a value is a plain object
     *
     * @param {*} value
     * @return {boolean}
     */
    function isObject(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }

    /**
     * A string value, or an empty string
     *
     * @param {*} value
     * @return {string}
     */
    function text(value) {
        return typeof value === 'string' ? value : '';
    }

    /**
     * The slider settings from the JSON of `data-hbs-config`, or null when it is not a valid configuration
     *
     * @param {string|null} json
     * @return {Object|null}
     */
    function parseConfig(json) {
        var data;

        try {
            data = JSON.parse(json || '');
        } catch (e) {
            return null;
        }

        if (!isObject(data) || !isObject(data.splide)) {
            return null;
        }

        return {
            splide: data.splide,
            pauseLabel: text(data.pauseLabel),
            playLabel: text(data.playLabel),
            videoPlayLabel: text(data.videoPlayLabel),
            videoPauseLabel: text(data.videoPauseLabel),
            hasVideo: data.hasVideo === true,
            backgroundVideo: data.backgroundVideo === true
        };
    }

    /**
     * Whether the visitor asked the system to reduce motion
     *
     * @param {Window|Object} view
     * @return {boolean}
     */
    function prefersReducedMotion(view) {
        return !!(view && typeof view.matchMedia === 'function' && view.matchMedia(REDUCED_MOTION_QUERY).matches);
    }

    /**
     * The options handed to Splide: the server-built options, with autoplay paused for reduced motion
     *
     * Autoplay stays configured, so the visitor can still start it with the pause/play button when the slider has
     * one.
     *
     * @param {Object} splideConfig
     * @param {boolean} reducedMotion
     * @return {Object}
     */
    function splideOptions(splideConfig, reducedMotion) {
        var options = {};

        Object.keys(splideConfig).forEach(function (key) {
            options[key] = splideConfig[key];
        });

        if (reducedMotion && options.autoplay) {
            options.autoplay = 'pause';
        }

        return options;
    }

    /**
     * What a play/pause button shows for a state: the label names the action a press performs
     *
     * @param {boolean} paused
     * @param {{pause: string, play: string}} labels
     * @return {{label: string, state: string}}
     */
    function toggleView(paused, labels) {
        return {
            label: paused ? labels.play : labels.pause,
            state: paused ? 'paused' : 'playing'
        };
    }

    /**
     * Wire a play/pause button: shows it, keeps its label in step with the state, reports every change
     *
     * The button's label changes with the state, so it carries no `aria-pressed`: a toggle button whose name
     * changes would be announced as "Start autoplay, pressed".
     *
     * @param {HTMLButtonElement} button
     * @param {{pause: string, play: string}} labels
     * @param {boolean} paused Initial state
     * @param {function(boolean): void} onChange Called with the new state after every change
     * @return {{set: function(boolean): void, isPaused: function(): boolean}}
     */
    function createToggle(button, labels, paused, onChange) {
        var state = {paused: paused};

        /**
         * Show the current state on the button
         *
         * @return {void}
         */
        function render() {
            var view = toggleView(state.paused, labels);

            button.textContent = view.label;
            button.setAttribute('data-hbs-state', view.state);
        }

        /**
         * Change the state
         *
         * @param {boolean} value
         * @return {void}
         */
        function set(value) {
            if (value === state.paused) {
                return;
            }

            state.paused = value;
            render();
            onChange(value);
        }

        button.addEventListener('click', function () {
            set(!state.paused);
        });
        render();
        button.hidden = false;

        return {
            set: set,
            isPaused: function () {
                return state.paused;
            }
        };
    }

    /**
     * The origin of an embed URL, or null when it has none
     *
     * @param {string} url
     * @return {string|null}
     */
    function originOf(url) {
        var origin;

        try {
            origin = new URL(url).origin;
        } catch (e) {
            return null;
        }

        return origin && origin !== 'null' ? origin : null;
    }

    /**
     * Pause or play a native video element
     *
     * @param {HTMLVideoElement} element
     * @param {string} action `pause` or `play`
     * @return {void}
     */
    function controlNativeVideo(element, action) {
        var result;

        if (typeof element[action] !== 'function') {
            return;
        }

        result = element[action]();
        if (result && typeof result.catch === 'function') {
            result.catch(noop);
        }
    }

    /**
     * Pause or play an embedded player through the provider's message protocol
     *
     * @param {HTMLIFrameElement} element
     * @param {string} action `pause` or `play`
     * @return {void}
     */
    function controlEmbeddedPlayer(element, action) {
        var messages = videoProviders[element.getAttribute('data-hbs-provider')],
            origin = originOf(element.src);

        if (!messages || !messages[action] || !origin || !element.contentWindow) {
            return;
        }

        element.contentWindow.postMessage(JSON.stringify(messages[action]), origin);
    }

    /**
     * Give a waiting player its URL, so it starts loading
     *
     * @param {Element} element An element with `data-hbs-video`
     * @return {boolean} Whether the player was waiting
     */
    function loadDeferred(element) {
        var source = element.getAttribute(DEFERRED_SOURCE_ATTRIBUTE);

        if (!source) {
            return false;
        }

        element.removeAttribute(DEFERRED_SOURCE_ATTRIBUTE);
        element.src = source;

        return true;
    }

    /**
     * Pause or play one media element; an element of an unknown kind is left alone
     *
     * Playing a player that waits for its slide loads it first; pausing it leaves it waiting.
     *
     * @param {Element} element An element with `data-hbs-video`
     * @param {string} action `pause` or `play`
     * @return {void}
     */
    function controlMedia(element, action) {
        var control = mediaControls[element.getAttribute('data-hbs-video')];

        if (!control) {
            return;
        }

        if (action === 'play') {
            loadDeferred(element);
        }
        control(element, action);
    }

    /**
     * Pause every video of a slider in a part of the page
     *
     * Players still waiting behind a click-to-load button live in a template and are not affected, nor are the
     * videos of a slider nested inside it.
     *
     * @param {Element} container
     * @return {void}
     */
    function pauseMediaIn(container) {
        ownElements(container, MEDIA_SELECTOR).forEach(function (element) {
            controlMedia(element, 'pause');
        });
    }

    /**
     * Pause the videos of every slide of a slider that is not in view
     *
     * @param {Element} container The slider container
     * @return {void}
     */
    function pauseHiddenSlides(container) {
        ownElements(container, SLIDE_SELECTOR).forEach(function (slide) {
            if (!slide.matches(VISIBLE_SLIDE_SELECTOR)) {
                pauseMediaIn(slide);
            }
        });
    }

    /**
     * Add or replace the pause/play messages of an embedded player
     *
     * @param {string} code The provider code the server puts in `data-hbs-provider`
     * @param {{pause: Object, play: Object}} messages Messages posted to the player, as objects
     * @return {void}
     */
    function registerVideoProvider(code, messages) {
        videoProviders[code] = messages;
    }

    /**
     * Swap a click-to-load button for the player waiting in its template, and focus the player
     *
     * Nothing is requested from the video provider before this runs.
     *
     * @param {HTMLButtonElement} button An element with `data-hbs-facade`
     * @return {Element|null} The player, or null when the button has no player template
     */
    function openFacade(button) {
        var parent = button.parentNode,
            template = parent ? ownElement(parent, 'template[data-hbs-player]') : null,
            content,
            player;

        if (!template || !template.content) {
            return null;
        }

        content = template.content.cloneNode(true);
        player = content.querySelector(MEDIA_SELECTOR);
        parent.insertBefore(content, button);
        parent.removeChild(button);
        parent.removeChild(template);
        if (player && typeof player.focus === 'function') {
            player.focus();
        }

        return player;
    }

    /**
     * Wire every click-to-load button of a slider
     *
     * @param {Element} container
     * @param {function(): void} onOpen Called once a player has replaced its button
     * @return {void}
     */
    function setupFacades(container, onOpen) {
        ownElements(container, '[data-hbs-facade]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (openFacade(button) !== null) {
                    onOpen();
                }
            });
        });
    }

    /**
     * Give every background video a pause/play button; with reduced motion the videos start paused
     *
     * A player that loads while its button says paused (reduced motion, or the visitor paused it first) is paused
     * again once loaded, because it starts by itself.
     *
     * @param {Element} container
     * @param {Object} config Parsed slider settings
     * @param {boolean} reducedMotion
     * @return {Array<{wrapper: Element, player: Element, toggle: Object}>}
     */
    function setupBackgroundVideos(container, config, reducedMotion) {
        var backgrounds = [],
            labels = {pause: config.videoPauseLabel, play: config.videoPlayLabel};

        ownElements(container, '[data-hbs-background]').forEach(function (wrapper) {
            var player = ownElement(wrapper, MEDIA_SELECTOR),
                button,
                toggle;

            if (!player) {
                return;
            }

            button = wrapper.ownerDocument.createElement('button');
            button.type = 'button';
            button.className = 'hbs-slide__video-toggle';
            wrapper.appendChild(button);
            toggle = createToggle(button, labels, reducedMotion, function (paused) {
                controlMedia(player, paused ? 'pause' : 'play');
            });
            backgrounds.push({wrapper: wrapper, player: player, toggle: toggle});

            player.addEventListener('load', function () {
                if (toggle.isPaused()) {
                    controlMedia(player, 'pause');
                }
            });
            if (reducedMotion) {
                controlMedia(player, 'pause');
            }
        });

        return backgrounds;
    }

    /**
     * Play the background videos of a slide that came into view, unless the visitor paused them
     *
     * A video that waited for its slide is loaded now.
     *
     * @param {Array<{wrapper: Element, player: Element, toggle: Object}>} backgrounds
     * @param {Element} slide
     * @return {void}
     */
    function resumeBackgroundsIn(backgrounds, slide) {
        backgrounds.forEach(function (background) {
            if (!background.toggle.isPaused() && slide.contains(background.wrapper)) {
                controlMedia(background.player, 'play');
            }
        });
    }

    /**
     * Start one slider; a slider already started is left alone
     *
     * Click-to-load buttons and background video buttons work even when Splide is missing; the slider then
     * keeps showing its first slide.
     *
     * The autoplay pause/play button is wired only when the server rendered one (`[data-hbs-toggle]`); the script
     * never creates it. Without it autoplay runs as configured, reduced motion still starts it paused, and starting
     * a click-to-load video still stops it.
     *
     * @param {Element} element The slider container, with `data-hbs-slider`
     * @param {Function} Splide The Splide constructor
     * @return {Object|null} The Splide instance, or null when the slider did not start
     */
    function mount(element, Splide) {
        var config,
            reducedMotion,
            backgrounds,
            controls = {stopAutoplay: null},
            root,
            splide,
            button,
            autoplay,
            toggle;

        if (!element || element.hasAttribute(MOUNTED_ATTRIBUTE)) {
            return null;
        }

        element.setAttribute(MOUNTED_ATTRIBUTE, '');
        config = parseConfig(element.getAttribute('data-hbs-config'));
        if (config === null) {
            warnOnce('Banner slider: a slider has no valid configuration and keeps showing its first slide.');

            return null;
        }

        reducedMotion = prefersReducedMotion(element.ownerDocument && element.ownerDocument.defaultView);
        backgrounds = setupBackgroundVideos(element, config, reducedMotion);
        setupFacades(element, function () {
            if (controls.stopAutoplay) {
                controls.stopAutoplay();
            }
        });

        root = ownElement(element, '.splide');
        if (typeof Splide !== 'function' || !root) {
            warnOnce('Banner slider: Splide is not available; the sliders keep showing their first slide.');

            return null;
        }

        splide = new Splide(root, splideOptions(config.splide, reducedMotion));
        splide.on('hidden', function (slide) {
            pauseMediaIn(slide.slide);
        });
        splide.on('visible', function (slide) {
            resumeBackgroundsIn(backgrounds, slide.slide);
        });
        splide.mount();
        pauseHiddenSlides(element);

        autoplay = splide.Components && splide.Components.Autoplay;
        if (!autoplay || !config.splide.autoplay) {
            return splide;
        }

        controls.stopAutoplay = function () {
            autoplay.pause();
        };
        button = ownElement(element, '[data-hbs-toggle]');
        if (button) {
            toggle = createToggle(
                button,
                {pause: config.pauseLabel, play: config.playLabel},
                reducedMotion,
                function (paused) {
                    autoplay[paused ? 'pause' : 'play']();
                }
            );
            controls.stopAutoplay = function () {
                toggle.set(true);
            };
        }

        return splide;
    }

    /**
     * Start every slider in a part of the page that has not started yet
     *
     * A slider that fails to start logs a warning; the others still start.
     *
     * @param {Document|Element} root
     * @param {Function} Splide
     * @return {Array<Object>} The Splide instances started
     */
    function mountAll(root, Splide) {
        var instances = [];

        each(root.querySelectorAll(SLIDER_SELECTOR + ':not([' + MOUNTED_ATTRIBUTE + '])'), function (element) {
            var instance = mountSafely(element, Splide);

            if (instance) {
                instances.push(instance);
            }
        });

        return instances;
    }

    /**
     * Sliders inside nodes added to the page
     *
     * @param {Array<MutationRecord>} records
     * @return {Array<Element>}
     */
    function addedSliders(records) {
        var sliders = [];

        each(records, function (record) {
            each(record.addedNodes, function (node) {
                if (node.nodeType !== 1) {
                    return;
                }

                if (typeof node.matches === 'function' && node.matches(SLIDER_SELECTOR)) {
                    sliders.push(node);
                }

                each(node.querySelectorAll(SLIDER_SELECTOR), function (slider) {
                    sliders.push(slider);
                });
            });
        });

        return sliders;
    }

    /**
     * Once the document is parsed, start every slider in it, then every slider added to it later
     *
     * Watching starts only after parsing, so a slider the parser is still writing is never started half-built;
     * sliders the parser writes are started by their own bootstrap script instead.
     *
     * @param {Document} document
     * @param {Function} Splide
     * @return {void}
     */
    function observe(document, Splide) {
        var view = document.defaultView,
            Observer = view && view.MutationObserver;

        /**
         * Start the sliders present, then watch for new ones
         *
         * @return {void}
         */
        function start() {
            mountAll(document, Splide);
            if (typeof Observer !== 'function') {
                return;
            }

            new Observer(function (records) {
                addedSliders(records).forEach(function (element) {
                    mountSafely(element, Splide);
                });
            }).observe(document.documentElement, {childList: true, subtree: true});
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', start);

            return;
        }

        start();
    }

    return {
        mount: mount,
        mountAll: mountAll,
        observe: observe,
        registerVideoProvider: registerVideoProvider,
        parseConfig: parseConfig,
        prefersReducedMotion: prefersReducedMotion,
        splideOptions: splideOptions,
        toggleView: toggleView,
        createToggle: createToggle,
        controlMedia: controlMedia,
        pauseMediaIn: pauseMediaIn,
        pauseHiddenSlides: pauseHiddenSlides,
        ownElements: ownElements,
        openFacade: openFacade,
        addedSliders: addedSliders
    };
}));
