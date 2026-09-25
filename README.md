# Magento 2 & Adobe Commerce Banner Slider Frontend UI

Storefront module of the banner slider: the slider block and CMS widget, responsive pictures, video embeds, and the
slider scripts and styles.

> **Part of [hryvinskyi/magento2-banner-slider-pack](https://github.com/hryvinskyi/magento2-banner-slider-pack)**, the
> complete banner slider for Magento 2.

## Requirements

- PHP 8.3 or 8.4, Magento 2.4.7 or later
- `hryvinskyi/magento2-banner-slider-api` and `hryvinskyi/magento2-banner-slider` 2.x
- `hryvinskyi/magento2-splide-js` (Splide 4) and `hryvinskyi/magento2-head-tag-manager`

## Placing a slider

A slider is chosen by id or by location code. When both are given, the id wins. With a location code, the enabled
slider with the lowest priority value placed there is shown. A slider shows only when it is enabled, visible to the
store view and customer group, inside its active dates, and has at least one slide that renders; otherwise the block
renders nothing.

### Layout XML

```xml
<referenceContainer name="content">
    <block class="Hryvinskyi\BannerSliderFrontendUi\Block\Widget\Slider" name="home.banner.slider">
        <arguments>
            <argument name="location" xsi:type="string">homepage-hero</argument>
        </arguments>
    </block>
</referenceContainer>
```

or with `<argument name="slider_id" xsi:type="number">1</argument>`. The `template` argument is optional; it defaults
to `Hryvinskyi_BannerSliderFrontendUi::slider.phtml`, and 1.x layouts that name that template keep working.

### CMS widget

```
{{widget type="Hryvinskyi\BannerSliderFrontendUi\Block\Widget\Slider" location="homepage-hero"}}
{{widget type="Hryvinskyi\BannerSliderFrontendUi\Block\Widget\Slider" slider_id="1"}}
```

| Parameter   | Type   | Description                                                                         |
|-------------|--------|-------------------------------------------------------------------------------------|
| `slider_id` | Select | The slider to show. Optional; "-- Use the location --" leaves it empty.             |
| `location`  | Text   | Location code, used when no slider is chosen.                                       |
| `template`  | Select | The slider template. Optional in a widget directive.                                |

## Themes with and without RequireJS

The block renders the same markup for every theme, and after each slider a small inline script (through
`SecureHtmlRenderer`, so CSP nonces apply).

- **A theme with RequireJS** (Luma and its children): the container's `data-mage-init` loads
  `Hryvinskyi_BannerSliderFrontendUi/js/banner-slider-requirejs`, which starts the slider with Splide. The inline script
  does nothing. RequireJS is recognised by a global `require` function with `defined`; a `require` configuration
  object or another AMD loader does not count.
- **A theme without RequireJS** (for example Hyvä): the inline script injects Splide and
  `Hryvinskyi_BannerSliderFrontendUi::js/banner-slider.js` once per page, in that order, and starts every slider when
  they have loaded. Sliders added to the page later, such as sections rendered on demand, start too. No Hyvä
  compatibility module is needed. The script URLs come from `FrontendAssets` in `di.xml`, so a theme can point them at
  its own copies. A script the page already has is not injected again: a theme's own `window.Splide` is used as it
  is. Each slider starts on its own, so one that fails logs a warning and the others still start.

Styles are plain CSS (`css/banner-slider.css`) and need no preprocessor. The block adds them, and the Splide styles, to
the page head.

If Splide cannot load, the slider keeps showing its first slide, click-to-load videos still work, and the console gets
one warning.

The slider script is also usable directly: `window.HryvinskyiBannerSlider.mount(element, Splide)` or
`mountAll(document, Splide)` without RequireJS, or the AMD module `Hryvinskyi_BannerSliderFrontendUi/js/banner-slider`
with it. It registers as an AMD module only when RequireJS is on the page; next to any other AMD loader it publishes
the global.

A slide's content may hold another slider (a widget in custom HTML): each slider only wires its own buttons, videos
and slides, never those of the slider inside it.

## Accessibility

- The container is the carousel region: `role="region"`, a translated `aria-roledescription` and the slider name as
  its label. Splide's own element is not announced a second time.
- A slider that plays automatically has a pause/play button (WCAG 2.2.2). Its label names the action a press
  performs.
- A visitor who prefers reduced motion gets autoplay paused (the button starts it) and background videos paused
  behind a play button. Every background video has a pause/play button.
- A slide that leaves the view pauses its videos: native videos directly, YouTube and Vimeo players through their
  message protocols.
- Every Splide label is translated. Images use the banner title as alternative text, or an empty `alt` when the banner
  has none; video frames have a title. A linked image without a title is decorative, so its link is named by
  `aria-label` with the banner name instead.
- Pagination dots have a 24 × 24 px hit area (WCAG 2.5.8). Buttons, arrows, dots and links show a focus outline.

## Video and privacy

- **Regular videos are click-to-load.** The slide shows the banner image (or a neutral background) with a play button.
  The provider's player is created only when the visitor presses it, so no request reaches YouTube or Vimeo before
  that. Starting the video stops the slider's autoplay (the pause button restarts it), and focus moves to the
  player.
- **Background videos** render their player directly. Only the first slide's background video starts with the page.
  A background video on a later slide is rendered without `src` (the URL waits in `data-hbs-src`): nothing is
  downloaded or played until its slide is shown, and not then either when the visitor paused it or prefers reduced
  motion; it loads when they press play. Right after the slider starts, videos in slides out of view are paused.
- The module allows the video players' hosts as frame sources in the storefront's content security policy
  (`etc/csp_whitelist.xml`: `www.youtube-nocookie.com`, `www.youtube.com`, `player.vimeo.com`). Posters are the
  banner's own image, so no image host of a video provider is needed.
- With privacy-enhanced mode on (the video setting in the banner slider configuration, on by default), embeds use
  `youtube-nocookie.com` and Vimeo's `dnt=1`.
- Local MP4/WebM files play in a native `<video>` element.

## Images and performance

- Responsive crops render as `<picture>` with one `<source>` per breakpoint and format (AVIF, WebP, then the original
  format). Every source carries its crop's width and height, so the browser reserves the right height before the
  image loads. The `<img>` inside the picture is the banner image with its stored size: a banner cropped for some
  breakpoints only (say, phones) shows its full image at the other widths. A banner without an image falls back to its
  widest crop.
- The first slide loads eagerly with `fetchpriority="high"`. The slider's lazy-load setting applies to the slides after
  it.
- Preload links for the first `preload banners count` slides and any banner flagged for preloading, following what the
  `<picture>` shows at each width. The widths are split by the slider's enabled breakpoints: each covers its min width
  up to the next wider breakpoint, so breakpoints whose media queries overlap never preload two crops for one width.
  A range with a crop of the banner preloads the crop's first format with its type (a browser that cannot decode it
  skips the link, as it skips that `<source>`); a range without one preloads the banner image. Neighbouring ranges
  that preload the same image share one link, so a banner without crops gets a single link. Only the first slide's
  links carry `fetchpriority="high"`.
- Full page cache: the page is tagged with the slider, its banners and its location (or the requested slider id), so a
  saved slider or banner, or a slider whose dates start or end, refreshes the pages that show it.
- A slider rendered inside a separately cached fragment (an ESI block) cannot add head elements: place such a slider
  in the page itself if it needs preloads or its custom CSS.

## Styling

`css/banner-slider.css` uses these classes; everything else is Splide's (`splide__*`).

| Class                      | Element                                                                  |
|----------------------------|--------------------------------------------------------------------------|
| `.hbs-slider`              | The container. Holds the custom properties below.                        |
| `.hbs-slider__toggle`      | The autoplay pause/play button.                                          |
| `.hbs-slide`               | A slide (`li`).                                                          |
| `.hbs-slide__link`         | The banner link around the image.                                        |
| `.hbs-slide__media`        | The slider's own `<picture>`, `<img>` and video wrapper (full width).    |
| `.hbs-slide__overlay`      | Banner content over an image or video; only its links and controls take clicks. |
| `.hbs-slide__content`      | A custom HTML slide.                                                     |
| `.hbs-slide__video`        | The video wrapper; its ratio comes from `--hbs-aspect-ratio`.            |
| `.hbs-slide__player`       | The iframe or `<video>`.                                                 |
| `.hbs-slide__facade`       | The click-to-load button, with `.hbs-slide__poster` and a hidden label.  |
| `.hbs-slide__video-toggle` | The pause/play button of a background video.                             |

Custom properties on `.hbs-slider`: `--hbs-control-size`, `--hbs-control-background`, `--hbs-control-color`,
`--hbs-focus-color`, `--hbs-dot-size`, `--hbs-dot-color`, `--hbs-dot-active-color`, `--hbs-video-background`,
`--hbs-offset`.

A slider's custom CSS (admin field) uses `.banner-slider-{slider id}` as its root selector; the container also keeps
the 1.x id `banner-slider-{slider id}` on its first render in a page.

## Extension points

- **Slide renderers** (`Api/Render/SlideRendererInterface`): add an item to the `renderers` pool of
  `Model\Render\CompositeSlideRenderer` in `di.xml` to render a banner type your own way. The first renderer (by
  sort order) that supports a type renders it.
- **Element attributes** (`Api/Attribute/ElementAttributeProviderInterface`): add a provider to the `providers` pool
  of `Model\Attribute\ElementAttributePool` to add attributes to the container, slides and links (for example
  analytics data attributes). Values are strings, integers, booleans or null; event handler attributes (`on…`) are
  rejected.
- **Script and style files**: the `stylesheets` and `scripts` arguments of `Model\View\FrontendAssets`.
- **Video players in the script**: `HryvinskyiBannerSlider.registerVideoProvider(code, {pause: {…}, play: {…}})` adds
  the pause/play messages of another embedded player, keyed by its provider code.
- **Templates**: `slider.phtml`, `slide/*.phtml` and `bootstrap.phtml` can be overridden by a theme as usual.

## Tests

```bash
vendor/bin/phpunit Test/Unit
node Test/Js/run.mjs
```

## Installation

```bash
composer require hryvinskyi/magento2-banner-slider-pack
bin/magento setup:upgrade
bin/magento cache:flush
```

## Author

**Volodymyr Hryvinskyi** — volodymyr@hryvinskyi.com

## License

MIT
