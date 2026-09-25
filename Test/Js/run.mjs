/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/*
 * Behaviour tests for the slider scripts. Plain node, no dependencies: `node Test/Js/run.mjs`.
 *
 * The scripts run against a small hand-rolled DOM (FakeElement below) that supports only what they use.
 */

import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {dirname, join} from 'node:path';
import {fileURLToPath} from 'node:url';

const packageDir = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const sliderSource = readFileSync(join(packageDir, 'view/frontend/web/js/banner-slider.js'), 'utf8');
const requireJsSource = readFileSync(join(packageDir, 'view/frontend/web/js/banner-slider-requirejs.js'), 'utf8');
const bootstrapTemplate = readFileSync(join(packageDir, 'view/frontend/templates/bootstrap.phtml'), 'utf8');

/* ------------------------------------------------------------------------------------------------------------ */
/* A minimal DOM                                                                                                 */
/* ------------------------------------------------------------------------------------------------------------ */

/**
 * Parse a compound selector such as `template[data-hbs-player]`, `.splide` or `[a]:not([b])`
 *
 * @param {string} selector
 * @return {{tag: string|null, classes: string[], attributes: Array<{name: string, value: string|null}>,
 *     without: string[]}}
 */
function parseSelector(selector) {
    const parsed = {tag: null, classes: [], attributes: [], without: []};
    const pattern = /^[a-z]+|\.[\w-]+|\[([\w-]+)(?:="([^"]*)")?\]|:not\(\[([\w-]+)\]\)/g;
    let match;
    let consumed = 0;

    while ((match = pattern.exec(selector)) !== null) {
        consumed += match[0].length;
        if (match[0][0] === '.') {
            parsed.classes.push(match[0].slice(1));
        } else if (match[0][0] === '[') {
            parsed.attributes.push({name: match[1], value: match[2] ?? null});
        } else if (match[0][0] === ':') {
            parsed.without.push(match[3]);
        } else {
            parsed.tag = match[0];
        }
    }
    if (consumed !== selector.length) {
        throw new Error('The fake DOM does not support the selector ' + selector);
    }

    return parsed;
}

class FakeElement {
    /**
     * @param {string} tag
     * @param {Object<string, string>} attributes
     * @param {FakeElement[]} children
     */
    constructor(tag, attributes = {}, children = []) {
        this.tagName = tag.toUpperCase();
        this.nodeType = tag === '#fragment' ? 11 : 1;
        this.attributes = {...attributes};
        this.children = [];
        this.parentNode = null;
        this.listeners = {};
        this.ownerDocument = null;
        this.calls = [];
        children.forEach((child) => this.appendChild(child));
    }

    get className() {
        return this.attributes.class ?? '';
    }

    set className(value) {
        this.attributes.class = value;
    }

    getAttribute(name) {
        return Object.prototype.hasOwnProperty.call(this.attributes, name) ? this.attributes[name] : null;
    }

    setAttribute(name, value) {
        this.attributes[name] = String(value);
    }

    hasAttribute(name) {
        return Object.prototype.hasOwnProperty.call(this.attributes, name);
    }

    removeAttribute(name) {
        delete this.attributes[name];
    }

    get previousElementSibling() {
        const siblings = this.parentNode ? this.parentNode.children : [];

        return siblings[siblings.indexOf(this) - 1] ?? null;
    }

    closest(selector) {
        let node = this;

        while (node && node.nodeType === 1) {
            if (node.matches(selector)) {
                return node;
            }
            node = node.parentNode;
        }

        return null;
    }

    addEventListener(type, listener) {
        (this.listeners[type] ??= []).push(listener);
    }

    dispatch(type, argument) {
        (this.listeners[type] ?? []).forEach((listener) => listener(argument));
    }

    click() {
        this.dispatch('click', {target: this});
    }

    appendChild(child) {
        return this.insertBefore(child, null);
    }

    insertBefore(child, reference) {
        const nodes = child.nodeType === 11 ? child.children.slice() : [child];
        const index = reference === null ? this.children.length : this.children.indexOf(reference);

        nodes.forEach((node) => {
            if (node.parentNode) {
                node.parentNode.removeChild(node);
            }
            node.parentNode = this;
        });
        this.children.splice(index, 0, ...nodes);

        return child;
    }

    removeChild(child) {
        this.children.splice(this.children.indexOf(child), 1);
        child.parentNode = null;

        return child;
    }

    contains(node) {
        return node === this || this.children.some((child) => child.contains(node));
    }

    matches(selector) {
        const parsed = parseSelector(selector);
        const classes = this.className.split(/\s+/);

        return (parsed.tag === null || parsed.tag.toUpperCase() === this.tagName)
            && parsed.classes.every((name) => classes.includes(name))
            && parsed.attributes.every(({name, value}) => this.hasAttribute(name)
                && (value === null || this.attributes[name] === value))
            && parsed.without.every((name) => !this.hasAttribute(name));
    }

    querySelectorAll(selector) {
        const found = [];
        const walk = (node) => node.children.forEach((child) => {
            if (child.matches(selector)) {
                found.push(child);
            }
            walk(child);
        });

        walk(this);

        return found;
    }

    querySelector(selector) {
        return this.querySelectorAll(selector)[0] ?? null;
    }

    cloneNode() {
        const copy = new FakeElement(this.nodeType === 11 ? '#fragment' : this.tagName.toLowerCase(),
            this.attributes, this.children.map((child) => child.cloneNode(true)));

        copy.src = this.src;
        copy.contentWindow = this.contentWindow;

        return copy;
    }

    focus() {
        this.calls.push('focus');
    }
}

/**
 * An element tree helper: h('div', {class: 'a'}, child, …)
 *
 * @return {FakeElement}
 */
function h(tag, attributes = {}, ...children) {
    return new FakeElement(tag, attributes, children);
}

/**
 * A `<template>` whose content holds the children
 *
 * @return {FakeElement}
 */
function template(attributes, ...children) {
    const element = h('template', attributes);

    element.content = h('#fragment', {}, ...children);

    return element;
}

/**
 * A native video element that records play/pause calls
 *
 * @return {FakeElement}
 */
function nativeVideo(attributes = {}) {
    const element = h('video', {'data-hbs-video': 'video', 'data-hbs-provider': 'local_mp4', ...attributes});

    element.pause = () => element.calls.push('pause');
    element.play = () => {
        element.calls.push('play');

        return Promise.reject(new Error('autoplay refused'));
    };

    return element;
}

/**
 * An embedded player that records the messages posted to it
 *
 * @return {FakeElement}
 */
function embed(provider, src, attributes = {}) {
    const element = h('iframe', {'data-hbs-video': 'iframe', 'data-hbs-provider': provider, ...attributes});

    element.src = src;
    element.contentWindow = {
        messages: [],
        postMessage(message, origin) {
            this.messages.push({message: JSON.parse(message), origin});
        }
    };

    return element;
}

/**
 * A document holding the elements, with a window that may prefer reduced motion
 *
 * @return {FakeElement}
 */
function fakeDocument({reducedMotion = false, readyState = 'complete'} = {}, ...children) {
    const documentElement = h('html', {}, h('head'), h('body', {}, ...children));
    const document = h('#document');
    const observers = [];

    document.children = [documentElement];
    documentElement.parentNode = document;
    document.documentElement = documentElement;
    document.head = documentElement.children[0];
    document.body = documentElement.children[1];
    document.readyState = readyState;
    document.observers = observers;
    document.createElement = (tag) => {
        const element = h(tag);

        element.ownerDocument = document;

        return element;
    };
    document.defaultView = {
        matchMedia: (query) => ({matches: reducedMotion && query === '(prefers-reduced-motion: reduce)'}),
        MutationObserver: class {
            constructor(callback) {
                this.callback = callback;
                observers.push(this);
            }

            observe(target, options) {
                this.target = target;
                this.options = options;
            }
        }
    };
    const adopt = (node) => {
        node.ownerDocument = document;
        node.children.forEach(adopt);
        if (node.content) {
            adopt(node.content);
        }
    };
    adopt(documentElement);

    return document;
}

/**
 * A fake Splide constructor recording what the slider script does with it
 *
 * @return {Function}
 */
function fakeSplide() {
    const instances = [];

    class Splide {
        constructor(root, options) {
            this.root = root;
            this.options = options;
            this.events = {};
            this.mounted = false;
            this.autoplayCalls = [];
            this.Components = {
                Autoplay: {
                    pause: () => this.autoplayCalls.push('pause'),
                    play: () => this.autoplayCalls.push('play')
                }
            };
            instances.push(this);
        }

        on(event, callback) {
            (this.events[event] ??= []).push(callback);

            return this;
        }

        emit(event, argument) {
            (this.events[event] ?? []).forEach((callback) => callback(argument));
        }

        mount() {
            const first = this.root.querySelector('.splide__slide');

            this.mounted = true;
            if (first) {
                first.className = first.className + ' is-visible';
            }

            return this;
        }
    }

    Splide.instances = instances;

    return Splide;
}

/**
 * A slider container as the server renders it
 *
 * @return {FakeElement}
 */
function sliderElement(config = {}, slides = [], attributes = {}) {
    const settings = {
        splide: {type: 'slide', autoplay: true, interval: 5000, mediaQuery: 'min', breakpoints: {768: {perPage: 2}}},
        pauseLabel: 'Pause autoplay',
        playLabel: 'Start autoplay',
        videoPlayLabel: 'Play background video',
        videoPauseLabel: 'Pause background video',
        hasVideo: false,
        backgroundVideo: false,
        ...config
    };

    return h('div', {
        class: 'hbs-slider banner-slider-1',
        id: 'banner-slider-1',
        'data-hbs-slider': '',
        'data-hbs-config': JSON.stringify(settings),
        'data-hbs-assets': JSON.stringify({splide: '/static/splide.min.js', slider: '/static/banner-slider.js'}),
        ...attributes
    },
    h('button', {type: 'button', class: 'hbs-slider__toggle', 'data-hbs-toggle': '', hidden: ''}),
    h('div', {class: 'splide'}, h('div', {class: 'splide__track'}, h('ul', {class: 'splide__list'}, ...slides))));
}

/**
 * A slide
 *
 * @return {FakeElement}
 */
function slide(...content) {
    return h('li', {class: 'splide__slide hbs-slide', 'data-hbs-banner-id': '1'}, ...content);
}

/* ------------------------------------------------------------------------------------------------------------ */
/* Loading the scripts                                                                                           */
/* ------------------------------------------------------------------------------------------------------------ */

/**
 * A fresh copy of the slider module, loaded the way node loads it
 *
 * @return {{api: Object, warnings: string[]}}
 */
function loadSlider() {
    const module = {exports: {}};
    const warnings = [];
    const console = {warn: (message) => warnings.push(message)};

    new Function('module', 'define', 'self', 'console', sliderSource)(module, undefined, undefined, console);

    return {api: module.exports, warnings};
}

/**
 * The inline script of the bootstrap template
 *
 * @return {string}
 */
function bootstrapScript() {
    const match = /<<<'JS'\n([\s\S]*?)\nJS;/.exec(bootstrapTemplate);

    assert.ok(match, 'the bootstrap template holds its script in a JS nowdoc');

    return match[1];
}

/**
 * Run the bootstrap script once against a window and document
 *
 * @return {void}
 */
function runBootstrap(window, document) {
    new Function('window', 'document', bootstrapScript())(window, document);
}

/* ------------------------------------------------------------------------------------------------------------ */
/* Tests                                                                                                         */
/* ------------------------------------------------------------------------------------------------------------ */

const tests = [];

/**
 * Register a test
 *
 * @param {string} name
 * @param {Function} body
 * @return {void}
 */
function test(name, body) {
    tests.push({name, body});
}

test('the module publishes a global without RequireJS and an AMD module with it', () => {
    const root = {};
    let defined = null;
    const define = (dependencies, factory) => {
        defined = {dependencies, factory};
    };
    const requireJs = () => {};

    define.amd = {};
    requireJs.defined = () => false;
    new Function('module', 'define', 'self', 'console', sliderSource)(undefined, undefined, root, console);
    assert.equal(typeof root.HryvinskyiBannerSlider.mount, 'function');

    new Function('module', 'define', 'self', 'console', sliderSource)(undefined, define, {require: requireJs}, console);
    assert.deepEqual(defined.dependencies, []);
    assert.equal(typeof defined.factory().mountAll, 'function');
});

test('another AMD loader without RequireJS cannot swallow the module: it publishes the global', () => {
    const plain = {};
    const withConfigObject = {require: {baseUrl: '/'}};
    const withoutDefined = {require: () => {}};
    const define = () => {
        throw new Error('the module must not register with this loader');
    };

    define.amd = {};
    [plain, withConfigObject, withoutDefined].forEach((root) => {
        new Function('module', 'define', 'self', 'console', sliderSource)(undefined, define, root, console);
        assert.equal(typeof root.HryvinskyiBannerSlider.mount, 'function');
    });
});

test('config parsing keeps the server-built options and normalises the labels', () => {
    const {api} = loadSlider();
    const config = api.parseConfig(JSON.stringify({
        splide: {type: 'fade', rewind: true},
        pauseLabel: 'Pause',
        playLabel: 7,
        hasVideo: true,
        backgroundVideo: 'yes'
    }));

    assert.deepEqual(config, {
        splide: {type: 'fade', rewind: true},
        pauseLabel: 'Pause',
        playLabel: '',
        videoPlayLabel: '',
        videoPauseLabel: '',
        hasVideo: true,
        backgroundVideo: false
    });
});

test('config parsing rejects invalid JSON and a configuration without Splide options', () => {
    const {api} = loadSlider();

    assert.equal(api.parseConfig('{not json'), null);
    assert.equal(api.parseConfig(null), null);
    assert.equal(api.parseConfig('[]'), null);
    assert.equal(api.parseConfig('{"splide": []}'), null);
    assert.equal(api.parseConfig('{"pauseLabel": "Pause"}'), null);
});

test('reduced motion follows the media query and defaults to off', () => {
    const {api} = loadSlider();
    const queries = [];

    assert.equal(api.prefersReducedMotion({matchMedia: (query) => {
        queries.push(query);

        return {matches: true};
    }}), true);
    assert.deepEqual(queries, ['(prefers-reduced-motion: reduce)']);
    assert.equal(api.prefersReducedMotion({matchMedia: () => ({matches: false})}), false);
    assert.equal(api.prefersReducedMotion({}), false);
    assert.equal(api.prefersReducedMotion(null), false);
});

test('Splide options are applied as built; reduced motion only pauses autoplay', () => {
    const {api} = loadSlider();
    const built = {type: 'slide', rewind: true, autoplay: true, mediaQuery: 'min', breakpoints: {0: {perPage: 1}}};

    const options = api.splideOptions(built, false);
    assert.deepEqual(options, built);
    assert.notEqual(options, built);

    assert.equal(api.splideOptions(built, true).autoplay, 'pause');
    assert.equal(built.autoplay, true, 'the parsed configuration is not changed');
    assert.equal(api.splideOptions({autoplay: false}, true).autoplay, false);
});

test('the toggle label names the action a press performs', () => {
    const {api} = loadSlider();
    const labels = {pause: 'Pause autoplay', play: 'Start autoplay'};

    assert.deepEqual(api.toggleView(false, labels), {label: 'Pause autoplay', state: 'playing'});
    assert.deepEqual(api.toggleView(true, labels), {label: 'Start autoplay', state: 'paused'});
});

test('the toggle state machine shows the button and reports each change once', () => {
    const {api} = loadSlider();
    const button = h('button', {hidden: ''});
    const changes = [];

    button.hidden = true;
    const toggle = api.createToggle(button, {pause: 'Pause', play: 'Play'}, false, (paused) => changes.push(paused));

    assert.equal(button.hidden, false);
    assert.equal(button.textContent, 'Pause');
    assert.equal(button.getAttribute('data-hbs-state'), 'playing');
    assert.equal(button.hasAttribute('aria-pressed'), false);

    button.click();
    assert.equal(toggle.isPaused(), true);
    assert.equal(button.textContent, 'Play');
    assert.equal(button.getAttribute('data-hbs-state'), 'paused');

    toggle.set(true);
    button.click();
    assert.equal(button.textContent, 'Pause');
    assert.deepEqual(changes, [true, false]);
});

test('pausing a slide pauses native videos and known embedded players only', () => {
    const {api} = loadSlider();
    const video = nativeVideo();
    const youtube = embed('youtube', 'https://www.youtube-nocookie.com/embed/abc?enablejsapi=1');
    const vimeo = embed('vimeo', 'https://player.vimeo.com/video/42?dnt=1');
    const unknown = embed('other', 'https://video.example.com/embed/1');
    const blank = embed('youtube', 'about:blank');
    const waiting = embed('youtube', 'https://www.youtube-nocookie.com/embed/zzz?enablejsapi=1');
    const container = slide(video, youtube, vimeo, unknown, blank, template({'data-hbs-player': ''}, waiting));

    api.pauseMediaIn(container);

    assert.deepEqual(video.calls, ['pause']);
    assert.deepEqual(youtube.contentWindow.messages, [{
        message: {event: 'command', func: 'pauseVideo', args: ''},
        origin: 'https://www.youtube-nocookie.com'
    }]);
    assert.deepEqual(vimeo.contentWindow.messages, [{message: {method: 'pause'}, origin: 'https://player.vimeo.com'}]);
    assert.deepEqual(unknown.contentWindow.messages, []);
    assert.deepEqual(blank.contentWindow.messages, [], 'no message without a real origin');
    assert.deepEqual(waiting.contentWindow.messages, [], 'a player still behind its button is not touched');
});

test('a registered provider is paused through its own messages', () => {
    const {api} = loadSlider();
    const player = embed('other', 'https://video.example.com/embed/1');

    api.registerVideoProvider('other', {pause: {type: 'pause'}, play: {type: 'play'}});
    api.controlMedia(player, 'pause');

    assert.deepEqual(player.contentWindow.messages, [{message: {type: 'pause'}, origin: 'https://video.example.com'}]);
});

test('opening a click-to-load button puts the player in its place and focuses it', () => {
    const {api} = loadSlider();
    const player = embed('youtube', 'https://www.youtube-nocookie.com/embed/abc?autoplay=1');
    const button = h('button', {'data-hbs-facade': ''});
    const playerTemplate = template({'data-hbs-player': ''}, player);
    const wrapper = h('div', {class: 'hbs-slide__video'}, button, playerTemplate);

    const opened = api.openFacade(button);

    assert.equal(wrapper.children.length, 1);
    assert.equal(wrapper.children[0], opened);
    assert.equal(opened.getAttribute('data-hbs-provider'), 'youtube');
    assert.deepEqual(opened.calls, ['focus']);
});

test('a click-to-load button without a player template is left alone', () => {
    const {api} = loadSlider();
    const button = h('button', {'data-hbs-facade': ''});
    const wrapper = h('div', {}, button);

    assert.equal(api.openFacade(button), null);
    assert.deepEqual(wrapper.children, [button]);
});

test('mounting applies the configuration once and wires the pause toggle', () => {
    const {api} = loadSlider();
    const Splide = fakeSplide();
    const element = sliderElement({}, [slide(), slide()]);
    fakeDocument({}, element);

    const instance = api.mount(element, Splide);

    assert.equal(instance, Splide.instances[0]);
    assert.equal(instance.root, element.querySelector('.splide'));
    assert.deepEqual(instance.options, {
        type: 'slide', autoplay: true, interval: 5000, mediaQuery: 'min', breakpoints: {768: {perPage: 2}}
    });
    assert.equal(instance.mounted, true);
    assert.equal(element.hasAttribute('data-hbs-mounted'), true);
    assert.equal(api.mount(element, Splide), null, 'a second mount does nothing');
    assert.equal(Splide.instances.length, 1);

    const toggle = element.querySelector('[data-hbs-toggle]');
    assert.equal(toggle.hidden, false);
    assert.equal(toggle.textContent, 'Pause autoplay');
    toggle.click();
    toggle.click();
    assert.deepEqual(instance.autoplayCalls, ['pause', 'play']);
    assert.equal(toggle.textContent, 'Pause autoplay');
});

test('a slider without autoplay leaves the toggle hidden', () => {
    const {api} = loadSlider();
    const element = sliderElement({splide: {type: 'slide', autoplay: false}});
    fakeDocument({}, element);
    const toggle = element.querySelector('[data-hbs-toggle]');

    toggle.hidden = true;
    api.mount(element, fakeSplide());

    assert.equal(toggle.hidden, true);
});

test('with reduced motion autoplay starts paused and background videos pause behind a play button', () => {
    const {api} = loadSlider();
    const Splide = fakeSplide();
    const video = nativeVideo({autoplay: ''});
    const youtube = embed('youtube', 'https://www.youtube-nocookie.com/embed/abc?autoplay=1&enablejsapi=1');
    const localWrapper = h('div', {class: 'hbs-slide__video', 'data-hbs-background': ''}, video);
    const embedWrapper = h('div', {class: 'hbs-slide__video', 'data-hbs-background': ''}, youtube);
    const element = sliderElement({hasVideo: true, backgroundVideo: true}, [slide(localWrapper), slide(embedWrapper)]);
    fakeDocument({reducedMotion: true}, element);

    const instance = api.mount(element, Splide);

    assert.equal(instance.options.autoplay, 'pause');
    const toggle = element.querySelector('[data-hbs-toggle]');
    assert.equal(toggle.textContent, 'Start autoplay');
    assert.equal(toggle.getAttribute('data-hbs-state'), 'paused');

    assert.deepEqual(video.calls, ['pause']);
    assert.equal(youtube.contentWindow.messages.length, 2, 'paused as a background and as a slide out of view');
    youtube.dispatch('load');
    assert.equal(youtube.contentWindow.messages.length, 3, 'paused again once the player loaded');

    const videoToggle = localWrapper.querySelector('.hbs-slide__video-toggle');
    assert.equal(videoToggle.textContent, 'Play background video');
    assert.equal(videoToggle.type, 'button');
    videoToggle.click();
    assert.deepEqual(video.calls, ['pause', 'play']);
    assert.equal(videoToggle.textContent, 'Pause background video');
});

test('without reduced motion background videos keep playing and get a pause button', () => {
    const {api} = loadSlider();
    const video = nativeVideo({autoplay: ''});
    const wrapper = h('div', {class: 'hbs-slide__video', 'data-hbs-background': ''}, video);
    const element = sliderElement({hasVideo: true, backgroundVideo: true}, [slide(wrapper)]);
    fakeDocument({}, element);

    api.mount(element, fakeSplide());

    assert.deepEqual(video.calls, []);
    assert.equal(wrapper.querySelector('.hbs-slide__video-toggle').textContent, 'Pause background video');
});

test('a slide leaving the view pauses its videos; a background video resumes unless the visitor paused it', () => {
    const {api} = loadSlider();
    const Splide = fakeSplide();
    const background = nativeVideo({autoplay: ''});
    const player = embed('vimeo', 'https://player.vimeo.com/video/42?dnt=1');
    const first = slide(h('div', {class: 'hbs-slide__video', 'data-hbs-background': ''}, background));
    const second = slide(h('div', {class: 'hbs-slide__video'}, player));
    const element = sliderElement({hasVideo: true, backgroundVideo: true}, [first, second]);
    fakeDocument({}, element);

    const instance = api.mount(element, Splide);
    const pause = {message: {method: 'pause'}, origin: 'https://player.vimeo.com'};

    assert.deepEqual(player.contentWindow.messages, [pause], 'a slide out of view after mounting is paused');
    instance.emit('hidden', {slide: second});
    assert.deepEqual(player.contentWindow.messages, [pause, pause]);

    instance.emit('hidden', {slide: first});
    instance.emit('visible', {slide: first});
    assert.deepEqual(background.calls, ['pause', 'play']);

    first.querySelector('.hbs-slide__video-toggle').click();
    instance.emit('visible', {slide: first});
    assert.deepEqual(background.calls, ['pause', 'play', 'pause']);
});

test('starting a click-to-load video stops the autoplay of its slider', () => {
    const {api} = loadSlider();
    const Splide = fakeSplide();
    const player = embed('youtube', 'https://www.youtube-nocookie.com/embed/abc?autoplay=1');
    const button = h('button', {type: 'button', class: 'hbs-slide__facade', 'data-hbs-facade': ''});
    const wrapper = h('div', {class: 'hbs-slide__video'}, button, template({'data-hbs-player': ''}, player));
    const element = sliderElement({hasVideo: true}, [slide(wrapper), slide()]);
    fakeDocument({}, element);

    const instance = api.mount(element, Splide);
    button.click();

    assert.equal(wrapper.querySelector('[data-hbs-facade]'), null);
    assert.equal(wrapper.querySelector('[data-hbs-video]').getAttribute('data-hbs-provider'), 'youtube');
    assert.deepEqual(instance.autoplayCalls, ['pause']);
    assert.equal(element.querySelector('[data-hbs-toggle]').textContent, 'Start autoplay');
});

test('without Splide one warning is logged, the slider stays as rendered, and videos still open', () => {
    const {api, warnings} = loadSlider();
    const player = embed('youtube', 'https://www.youtube-nocookie.com/embed/abc?autoplay=1');
    const button = h('button', {'data-hbs-facade': ''});
    const first = sliderElement({hasVideo: true}, [slide(h('div', {}, button, template({'data-hbs-player': ''},
        player)))]);
    const second = sliderElement();
    fakeDocument({}, first, second);

    assert.equal(api.mount(first, undefined), null);
    assert.equal(api.mount(second, undefined), null);
    assert.equal(warnings.length, 1);
    assert.equal(first.querySelector('[data-hbs-toggle]').hasAttribute('hidden'), true);

    button.click();
    assert.equal(first.querySelector('[data-hbs-video]').getAttribute('data-hbs-provider'), 'youtube');
    assert.equal(first.querySelector('[data-hbs-facade]'), null);
});

test('an invalid configuration logs one warning and mounts nothing', () => {
    const {api, warnings} = loadSlider();
    const Splide = fakeSplide();
    const element = sliderElement({}, [], {'data-hbs-config': '{broken'});
    fakeDocument({}, element);

    assert.equal(api.mount(element, Splide), null);
    assert.equal(Splide.instances.length, 0);
    assert.equal(warnings.length, 1);
});

test('mountAll starts only the sliders not started yet', () => {
    const {api} = loadSlider();
    const Splide = fakeSplide();
    const started = sliderElement({}, [], {id: 'a', 'data-hbs-mounted': ''});
    const fresh = sliderElement({}, [], {id: 'b'});
    const document = fakeDocument({}, started, fresh);

    const instances = api.mountAll(document, Splide);

    assert.equal(instances.length, 1);
    assert.equal(Splide.instances[0].root, fresh.querySelector('.splide'));
});

test('observe waits for the parsed document, then starts present and added sliders', () => {
    const {api} = loadSlider();
    const Splide = fakeSplide();
    const present = sliderElement({}, [], {id: 'present'});
    const document = fakeDocument({readyState: 'loading'}, present);

    api.observe(document, Splide);
    assert.equal(Splide.instances.length, 0, 'nothing starts while the parser may still be writing a slider');
    assert.equal(document.observers.length, 0);

    document.readyState = 'interactive';
    document.dispatch('DOMContentLoaded');
    assert.equal(Splide.instances.length, 1);
    assert.equal(document.observers.length, 1);
    assert.equal(document.observers[0].target, document.documentElement);
    assert.deepEqual(document.observers[0].options, {childList: true, subtree: true});

    const added = sliderElement({}, [], {id: 'added'});
    const section = h('section', {}, added);
    const text = {nodeType: 3};
    document.body.appendChild(section);
    added.ownerDocument = document;
    document.observers[0].callback([{addedNodes: [text, section]}, {addedNodes: [present]}]);

    assert.equal(Splide.instances.length, 2);
    assert.equal(Splide.instances[1].root, added.querySelector('.splide'));
});

test('addedSliders finds a slider that is itself the added node', () => {
    const {api} = loadSlider();
    const added = sliderElement();

    assert.deepEqual(api.addedSliders([{addedNodes: [added]}]), [added]);
});

/**
 * A background video wrapper as the server renders it
 *
 * @return {FakeElement}
 */
function backgroundWrapper(player) {
    return h('div', {class: 'hbs-slide__video hbs-slide__video--background', 'data-hbs-background': ''}, player);
}

test('a background video waiting for its slide loads and plays when the slide comes into view', () => {
    const {api} = loadSlider();
    const Splide = fakeSplide();
    const first = slide(backgroundWrapper(nativeVideo({autoplay: ''})));
    const waitingVideo = nativeVideo({'data-hbs-src': '/media/banner_slider/video/loop.mp4'});
    const waitingEmbed = embed('youtube', '', {
        'data-hbs-src': 'https://www.youtube-nocookie.com/embed/abc?autoplay=1&enablejsapi=1'
    });
    const second = slide(backgroundWrapper(waitingVideo));
    const third = slide(backgroundWrapper(waitingEmbed));
    const element = sliderElement({hasVideo: true, backgroundVideo: true}, [first, second, third]);
    fakeDocument({}, element);

    const instance = api.mount(element, Splide);

    assert.equal(waitingVideo.src, undefined, 'nothing is loaded for a slide out of view');
    assert.equal(waitingVideo.getAttribute('data-hbs-src'), '/media/banner_slider/video/loop.mp4');
    assert.deepEqual(waitingEmbed.contentWindow.messages, [], 'a waiting player has no origin to message');

    instance.emit('visible', {slide: second});
    assert.equal(waitingVideo.src, '/media/banner_slider/video/loop.mp4');
    assert.equal(waitingVideo.hasAttribute('data-hbs-src'), false);
    assert.deepEqual(waitingVideo.calls, ['pause', 'play']);

    instance.emit('visible', {slide: third});
    assert.equal(waitingEmbed.src, 'https://www.youtube-nocookie.com/embed/abc?autoplay=1&enablejsapi=1');
    assert.equal(waitingEmbed.hasAttribute('data-hbs-src'), false);
});

test('with reduced motion a waiting background video loads only when the visitor presses play', () => {
    const {api} = loadSlider();
    const Splide = fakeSplide();
    const waiting = embed('vimeo', '', {'data-hbs-src': 'https://player.vimeo.com/video/42?background=1&dnt=1'});
    const wrapper = backgroundWrapper(waiting);
    const second = slide(wrapper);
    const element = sliderElement({hasVideo: true, backgroundVideo: true}, [slide(), second]);
    fakeDocument({reducedMotion: true}, element);

    const instance = api.mount(element, Splide);
    instance.emit('visible', {slide: second});
    assert.equal(waiting.src, '', 'still waiting');
    assert.equal(waiting.hasAttribute('data-hbs-src'), true);

    wrapper.querySelector('.hbs-slide__video-toggle').click();
    assert.equal(waiting.src, 'https://player.vimeo.com/video/42?background=1&dnt=1');
    waiting.contentWindow.messages = [];
    waiting.dispatch('load');
    assert.deepEqual(waiting.contentWindow.messages, [], 'a player the visitor started is not paused once loaded');
});

test('a background video the visitor paused before its slide came into view keeps waiting', () => {
    const {api} = loadSlider();
    const waiting = nativeVideo({'data-hbs-src': '/media/loop.webm'});
    const wrapper = backgroundWrapper(waiting);
    const second = slide(wrapper);
    const element = sliderElement({hasVideo: true, backgroundVideo: true}, [slide(), second]);
    fakeDocument({}, element);

    const instance = api.mount(element, fakeSplide());
    wrapper.querySelector('.hbs-slide__video-toggle').click();
    instance.emit('visible', {slide: second});

    assert.equal(waiting.src, undefined);
    assert.equal(waiting.getAttribute('data-hbs-src'), '/media/loop.webm');
});

test('after mounting, the videos of slides out of view are paused and those in view are not', () => {
    const {api} = loadSlider();
    const inView = nativeVideo({autoplay: ''});
    const outOfView = nativeVideo({autoplay: ''});
    const clone = nativeVideo({autoplay: ''});
    const element = sliderElement({hasVideo: true}, [
        slide(inView),
        slide(outOfView),
        h('li', {class: 'splide__slide splide__slide--clone'}, clone)
    ]);
    fakeDocument({}, element);

    api.mount(element, fakeSplide());

    assert.deepEqual(inView.calls, []);
    assert.deepEqual(outOfView.calls, ['pause']);
    assert.deepEqual(clone.calls, ['pause']);
});

test('a slider touches only its own elements, never those of a slider nested in one of its slides', () => {
    const {api} = loadSlider();
    const Splide = fakeSplide();
    const innerVideo = nativeVideo({autoplay: ''});
    const innerFacade = h('button', {'data-hbs-facade': ''});
    const inner = sliderElement({hasVideo: true, backgroundVideo: true}, [
        slide(backgroundWrapper(innerVideo)),
        slide(h('div', {class: 'hbs-slide__video'}, innerFacade, template({'data-hbs-player': ''},
            embed('youtube', 'https://www.youtube-nocookie.com/embed/abc?autoplay=1'))))
    ], {id: 'inner'});
    const outerSlide = slide(h('div', {class: 'hbs-slide__content'}, inner));
    const outer = sliderElement({}, [slide(), outerSlide], {id: 'outer'});
    fakeDocument({}, outer);

    const instance = api.mount(outer, Splide);

    assert.equal(instance.root, outer.children[1], 'the outer slider mounts its own Splide root');
    assert.equal(inner.querySelector('.hbs-slide__video-toggle'), null, 'no button for the inner background video');
    assert.equal(inner.querySelector('[data-hbs-toggle]').hidden, undefined, 'the inner toggle is left alone');
    assert.deepEqual(innerVideo.calls, [], 'the outer slide out of view does not pause the inner video');

    instance.emit('hidden', {slide: outerSlide});
    assert.deepEqual(innerVideo.calls, []);

    innerFacade.click();
    assert.notEqual(inner.querySelector('[data-hbs-facade]'), null, 'the outer slider wired no inner facade');
    assert.deepEqual(api.ownElements(inner, '[data-hbs-facade]'), [innerFacade]);
});

test('mountAll starts every slider even when one fails, with a warning for the failure', () => {
    const warnings = [];
    const module = {exports: {}};
    new Function('module', 'define', 'self', 'console', sliderSource)(module, undefined, undefined, {
        warn: (message, error) => warnings.push([message, error.message])
    });
    const api = module.exports;
    const started = [];
    const Splide = function (root) {
        if (root.parentNode.getAttribute('id') === 'broken') {
            throw new Error('boom');
        }
        started.push(root.parentNode.getAttribute('id'));
        this.on = () => this;
        this.mount = () => this;
    };
    const document = fakeDocument({}, sliderElement({}, [], {id: 'broken'}), sliderElement({}, [], {id: 'fine'}));

    const instances = api.mountAll(document, Splide);

    assert.equal(instances.length, 1);
    assert.deepEqual(started, ['fine']);
    assert.deepEqual(warnings, [['Banner slider: a slider could not be started.', 'boom']]);
});

test('the RequireJS component mounts its element with Splide', () => {
    let definition = null;
    const define = (dependencies, factory) => {
        definition = {dependencies, factory};
    };
    const mounted = [];
    const Splide = function () {};

    new Function('define', requireJsSource)(define);

    assert.deepEqual(definition.dependencies, ['splide', 'Hryvinskyi_BannerSliderFrontendUi/js/banner-slider']);
    const component = definition.factory(Splide, {mount: (element, constructor) => mounted.push([element, constructor])});
    const element = h('div');
    component({}, element);
    assert.deepEqual(mounted, [[element, Splide]]);
});

/**
 * A window and document for the bootstrap script
 *
 * @return {{window: Object, document: FakeElement, sliders: FakeElement[]}}
 */
function bootstrapPage(windowProperties = {}) {
    const document = fakeDocument();
    const window = {...windowProperties};

    document.currentScript = {nonce: 'n0nce'};

    return {
        window,
        document,
        addSlider(id) {
            const element = sliderElement({}, [], {id});

            document.body.appendChild(element);

            return element;
        }
    };
}

test('bootstrap: with RequireJS on the page it injects nothing', () => {
    const requireJs = () => {};

    requireJs.defined = () => false;
    const page = bootstrapPage({require: requireJs});

    page.addSlider('one');
    runBootstrap(page.window, page.document);

    assert.equal(page.document.head.children.length, 0);
    assert.equal(page.window.hryvinskyiBannerSliderLoader, undefined);
});

test('bootstrap: a require configuration object or a require without defined is not RequireJS', () => {
    [{baseUrl: '/'}, () => {}].forEach((require) => {
        const page = bootstrapPage({require});

        page.addSlider('one');
        runBootstrap(page.window, page.document);

        assert.equal(page.document.head.children.length, 2);
    });
});

test('bootstrap: a theme\'s own Splide is used; only the slider script is injected and it starts the queue', () => {
    const mounted = [];
    const Splide = function () {};
    const page = bootstrapPage({Splide});

    page.addSlider('one');
    runBootstrap(page.window, page.document);

    const scripts = page.document.head.children;
    assert.deepEqual(scripts.map((script) => script.src), ['/static/banner-slider.js']);
    page.window.HryvinskyiBannerSlider = {
        mount: (element, constructor) => mounted.push([element.getAttribute('id'), constructor]),
        observe: () => {}
    };
    scripts[0].dispatch('load');
    assert.deepEqual(mounted, [['one', Splide]]);
});

test('bootstrap: with the slider script already present only Splide is injected, and it starts the queue', () => {
    const mounted = [];
    const page = bootstrapPage({
        HryvinskyiBannerSlider: {mount: (element) => mounted.push(element.getAttribute('id')), observe: () => {}}
    });

    page.addSlider('one');
    runBootstrap(page.window, page.document);

    const scripts = page.document.head.children;
    assert.deepEqual(scripts.map((script) => script.src), ['/static/splide.min.js']);
    assert.deepEqual(mounted, []);
    page.window.Splide = function () {};
    scripts[0].dispatch('load');
    assert.deepEqual(mounted, ['one']);
});

test('bootstrap: a slider that fails to start logs a warning and the others still start', () => {
    const mounted = [];
    const warnings = [];
    const page = bootstrapPage({
        console: {warn: (message, error) => warnings.push([message, error.message])},
        Splide: function () {},
        HryvinskyiBannerSlider: {
            mount: (element) => {
                if (element.getAttribute('id') === 'broken') {
                    throw new Error('boom');
                }
                mounted.push(element.getAttribute('id'));
            },
            observe: () => {}
        }
    });

    page.addSlider('broken');
    runBootstrap(page.window, page.document);
    page.addSlider('fine');
    runBootstrap(page.window, page.document);

    assert.deepEqual(mounted, ['fine']);
    assert.deepEqual(warnings, [['Banner slider: a slider could not be started.', 'boom']]);
});

test('bootstrap: the slider right before the script is the one it starts, even with a nested slider after it', () => {
    const mounted = [];
    const page = bootstrapPage({
        Splide: function () {},
        HryvinskyiBannerSlider: {mount: (element) => mounted.push(element.getAttribute('id')), observe: () => {}}
    });
    const outer = page.addSlider('outer');
    const script = h('script');

    outer.querySelector('.splide__list').appendChild(slide(sliderElement({}, [], {id: 'inner'})));
    page.document.body.appendChild(script);
    page.document.currentScript = script;
    runBootstrap(page.window, page.document);

    assert.deepEqual(mounted, ['outer']);
});

test('bootstrap: injects Splide, then the slider script, in order, and mounts only after the second load', () => {
    const page = bootstrapPage();
    const mounted = [];
    const observed = [];
    const one = page.addSlider('one');

    runBootstrap(page.window, page.document);

    const scripts = page.document.head.children;
    assert.equal(scripts.length, 2);
    assert.deepEqual(scripts.map((script) => script.src), ['/static/splide.min.js', '/static/banner-slider.js']);
    assert.deepEqual(scripts.map((script) => script.async), [false, false]);
    assert.deepEqual(scripts.map((script) => script.nonce), ['n0nce', 'n0nce']);

    const two = page.addSlider('two');
    runBootstrap(page.window, page.document);
    assert.equal(page.document.head.children.length, 2, 'the second slider does not inject the scripts again');

    scripts[0].dispatch('load');
    assert.deepEqual(mounted, [], 'Splide alone mounts nothing');

    page.window.Splide = function () {};
    page.window.HryvinskyiBannerSlider = {
        mount: (element, Splide) => mounted.push([element.getAttribute('id'), Splide]),
        observe: (document, Splide) => observed.push([document, Splide])
    };
    scripts[1].dispatch('load');
    assert.deepEqual(mounted, [['one', page.window.Splide], ['two', page.window.Splide]]);
    assert.deepEqual(observed, [[page.document, page.window.Splide]]);

    page.addSlider('three');
    runBootstrap(page.window, page.document);
    assert.deepEqual(mounted.map((entry) => entry[0]), ['one', 'two', 'three'], 'a later slider mounts at once');
    assert.equal(page.document.head.children.length, 2);
    assert.ok(one && two);
});

test('bootstrap: scripts already on the page are used without injecting them again', () => {
    const mounted = [];
    const page = bootstrapPage({
        Splide: function () {},
        HryvinskyiBannerSlider: {mount: (element) => mounted.push(element.getAttribute('id')), observe: () => {}}
    });

    page.addSlider('one');
    runBootstrap(page.window, page.document);

    assert.equal(page.document.head.children.length, 0);
    assert.deepEqual(mounted, ['one']);
});

test('bootstrap: a container without valid asset URLs sets no flag, so the next slider can load them', () => {
    const page = bootstrapPage();

    page.addSlider('broken').setAttribute('data-hbs-assets', '{"splide": 1}');
    runBootstrap(page.window, page.document);
    assert.equal(page.window.hryvinskyiBannerSliderLoader, undefined);
    assert.equal(page.document.head.children.length, 0);

    page.addSlider('fine');
    runBootstrap(page.window, page.document);
    assert.equal(page.document.head.children.length, 2);
});

/* ------------------------------------------------------------------------------------------------------------ */

let failed = 0;

tests.forEach(({name, body}) => {
    try {
        body();
    } catch (error) {
        failed++;
        console.error('FAIL ' + name + '\n  ' + (error && error.stack ? error.stack : error));
    }
});

console.log((tests.length - failed) + ' passed, ' + failed + ' failed');
process.exitCode = failed === 0 ? 0 : 1;
