# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - 2026-09-25

Requires `hryvinskyi/magento2-banner-slider-api` 2.1 and `hryvinskyi/magento2-banner-slider` 2.1.

### Added
- Service interfaces, marked `@api`, for what the block and the slide renderers use:
  `Api\View\SliderViewBuilderInterface`, `Api\View\PictureViewBuilderInterface`,
  `Api\Head\HeadAssetRegistrarInterface` and `Api\StorefrontContextProviderInterface`, each preferred to the
  existing class in `di.xml`. The block and the image and video slide renderers now inject the interfaces, so a
  module replaces one of these services with a preference.

### Changed
- The view objects the templates read moved from `Model\View` to `Api\View` and are marked `@api`; their methods
  are unchanged. A theme override (or any code) that type-hints one of them must use the new name (all under
  `Hryvinskyi\BannerSliderFrontendUi\`):
  - `Model\View\SliderView` → `Api\View\SliderView`
  - `Model\View\SlideView` → `Api\View\SlideView`
  - `Model\View\ImageSlideView` → `Api\View\ImageSlideView`
  - `Model\View\VideoSlideView` → `Api\View\VideoSlideView`
  - `Model\View\PictureView` → `Api\View\PictureView`

  The old names are gone; there are no class aliases.
- The autoplay pause/play button follows the slider's new "Show Pause/Play Button" setting: it is rendered only when
  autoplay is on, there is more than one slide, and the setting is on (the default). Autoplay itself does not depend
  on the setting.

### Fixed
- Starting a click-to-load video stops the slider's autoplay also when the slider has no pause/play button.

## [2.0.0] - 2026-09-25

A breaking release on `hryvinskyi/magento2-banner-slider-api` and `hryvinskyi/magento2-banner-slider` 2.0. The block
class, the template id `Hryvinskyi_BannerSliderFrontendUi::slider.phtml` and the `slider_id` / `location` arguments
stay valid, so existing layout XML and widgets keep working. After deploying, flush the full page cache (and purge
Varnish): cached pages still reference the removed `css/styles.css` and the 1.x `splideWidget` start-up.

### Added
- Works in themes without RequireJS: after every slider the block renders an inline script (through
  `SecureHtmlRenderer`) that injects Splide and `js/banner-slider.js` once per page, in order, and starts the sliders,
  including sliders added to the page later. With RequireJS the container's `data-mage-init` starts the slider through
  `js/banner-slider-requirejs.js`.
- `js/banner-slider.js`: a vanilla script (AMD module or `window.HryvinskyiBannerSlider`) with `mount()`,
  `mountAll()`, `observe()` and `registerVideoProvider()`.
- Plain-CSS stylesheet `css/banner-slider.css` with custom properties for colours and sizes.
- Pause/play button for autoplay (WCAG 2.2.2); autoplay starts paused and background videos pause for visitors who
  prefer reduced motion; every background video has its own pause/play button.
- Videos in a slide that leaves the view are paused (native video, YouTube, Vimeo), and right after the slider
  starts, videos in slides out of view are paused.
- Background videos after the first slide wait for their slide: they render without `src` (the URL is in
  `data-hbs-src`) and load and play when the slide is shown, unless the visitor paused them or prefers reduced
  motion. Only the first slide's background video starts with the page.
- `etc/csp_whitelist.xml`: the video player hosts (`www.youtube-nocookie.com`, `www.youtube.com`,
  `player.vimeo.com`) as frame sources; depends on `Magento_Csp`.
- A linked image without a title (a decorative image) gets `aria-label` with the banner name on its link, so the
  link always has an accessible name.
- Nested sliders: a slider only wires its own buttons, videos and slides, never those of a slider inside one of its
  slides.
- Click-to-load videos: a regular video shows its poster and a play button, and the provider's player is created only
  when the visitor presses it. Starting it stops the slider's autoplay and moves focus to the player.
- Translated Splide labels and carousel semantics on the container (`role="region"`, `aria-roledescription`, the
  slider name); iframe titles; the "no video support" text is translated.
- Extension points in `Api/Render/`: `SlideRendererInterface` (a pool in `di.xml`, one renderer per banner type),
  `ContentFilterInterface`, `TemplateRendererInterface`; value objects `Api/Value/HtmlAttributes`, `SlideContext`,
  `SlideLoading`.
- The widget can place a slider by location: its slider field has an empty "-- Use the location --" choice, and its
  options come from core.
- `i18n/en_US.csv`.
- Unit tests for the block, the view builders, the slide renderers, the content filter, the attribute pool and the
  value objects; node tests for the scripts (`Test/Js/run.mjs`).

### Changed
- Splide options are applied as the server builds them, with no client-side defaults on top: `mediaQuery: 'min'`
  with breakpoints from the slider's responsive items (smallest first), `rewind` for every type but `loop` (a fading
  slider that plays automatically starts over instead of stopping), a slider with a video never loops, a single slide
  never plays automatically, the transition speed comes from `di.xml`.
- The first slide always loads eagerly with `fetchpriority="high"`; the slider's lazy-load setting now applies to the
  slides after it. Splide's own lazy loading is off in favour of native `loading`.
- Preload links follow what the picture shows at each viewport width, split by the slider's enabled breakpoints (from
  a breakpoint's min width up to the next wider one), so overlapping breakpoint media queries never preload two crops
  for one width. A width range with a crop of the banner preloads the crop's first format with its type; a range
  without one preloads the banner image; neighbouring ranges with the same image share a link. Only the first slide's
  links have high priority.
- The `<img>` of a responsive picture is the banner image with its stored size, so a banner cropped for some
  breakpoints only shows its full image, at the right height, where no crop applies; a banner without an image falls
  back to its widest crop.
- Images take their size from the banner's stored dimensions; nothing is read from disk while rendering. Media URLs
  come only from the API's media URL resolver.
- Alternative text is the banner title or empty, never the banner's internal name.
- `ElementAttributePoolInterface` takes and returns `HtmlAttributes`. Attribute providers return
  `array<string,string|int|bool|null>`; attribute names are checked, and event handler names (`on…`) are rejected.
  `true` renders a bare attribute, `false` and `null` leave it out.
- Link URLs are escaped as URLs and links opened in a new tab get `rel="noopener noreferrer"`.
- Banner content goes through the CMS filter with a nesting limit; when filtering fails the content is left out
  rather than shown with raw directives.
- The same slider twice on a page gets two ids: `banner-slider-{id}`, then `banner-slider-{id}-2`. The class
  `banner-slider-{id}` stays for custom CSS.
- Custom CSS goes to the page head, once per slider.
- Cache tags: a slider placed by id tags the page with that slider's tag even when it is not shown, so it appears once
  its dates start; a slider placed by location tags the location. The block has no cache lifetime or cache key of its
  own.
- One banner that cannot be rendered (an unsafe stored path, an unknown video source) is logged and left out; the
  other slides still render. A video banner whose image path is unusable loses only its poster.
- `js/banner-slider.js` registers as an AMD module only when RequireJS is on the page; next to another AMD loader it
  publishes `window.HryvinskyiBannerSlider`. The inline start-up script recognises RequireJS by a `require` function
  with `defined`, injects Splide only when `window.Splide` is absent (a theme's own Splide is used) and the slider
  script only when it is absent, and starts each slider on its own, so one failure logs a warning and the others
  still start.
- Requires PHP 8.3 or 8.4 and Magento 2.4.7 or later; every dependency has a version constraint.

### Removed
- **A theme override of `Hryvinskyi_BannerSliderFrontendUi::slider.phtml` must be redone.** The 1.x template called
  block and view-model methods that no longer exist, and its markup changed: the 2.0 template renders a
  `SliderView` (`$block->getSliderView()`), slides come pre-rendered from the slide renderers, and the 1.x classes
  (`banner-slider-container`, `banner-slider`, `banner-slider-item`, `banner-slider-image-wrapper`,
  `banner-slider-link`, `banner-slider-content-overlay`, `banner-slider-custom-content`) and the `data-slider-id` /
  `data-banner-id` attributes are replaced by the `hbs-*` classes and `data-hbs-*` attributes listed in the README.
  The `banner-slider-{id}` class and id stay for stored custom CSS.
- Block `Block\Widget\Slider` methods: `getBanners()`, `getBannerRenderer()`, `getSliderConfig()` and the
  `getCacheKeyInfo()` override (the block now has no cache key of its own). `getSlider()` and `getIdentities()` stay;
  the constructor's dependencies changed.
- `ViewModel/BannerRenderer` with all its public methods: `isVideoType()`, `isCustomType()`, `filterContent()`,
  `getImageUrl()`, `getVideoProvider()`, `getVideoData()`, `getVideoHtml()`, `getImageHtml()`,
  `preloadResponsiveCrops()`, `hasResponsiveCrops()`, `getResponsiveCrops()`, `getResponsiveImageHtml()`,
  `getPreloadLinks()`, `getPreloadLinksForBanner()`, `getImageDimensions()`, `getLinkAttributes()`, `hasLink()`,
  `getContainerAttributesHtml()`, `getSlideAttributesHtml()`.
- `Api/ResponsiveImage/*` (`CropOrderInterface`, `PictureRendererInterface`, `PreloadLinkBuilderInterface`) and
  `Model/ResponsiveImage/*` (`CropBreakpoint`, `CropOrder`, `PictureRenderer`, `PreloadLinkBuilder`): rendering moved
  to templates, `Model/View/*`, `Model/Render/*` and `Model/Head/*`.
- The slider no longer starts through the `splideWidget` component (`data-mage-init='{"splideWidget": …}'`); it
  starts through `js/banner-slider-requirejs.js` or the inline start-up script.
- `view/frontend/layout/default.xml`, which loaded a missing `_module.less` on every page, and `css/styles.less`.
- The widget's missing placeholder image.
- The dependencies on `hryvinskyi/magento2-base` and `hryvinskyi/magento2-media-uploader`.

### Known limitations
- A slider rendered inside a separately cached fragment (an ESI block) cannot add head elements (stylesheets,
  preloads, custom CSS).

## [1.0.8] - 2026-09-24

### Fixed
- Responsive banners reserving the mobile crop's height on desktop. When a banner's crops shared a sort order, the
  database row order decided which crop came first, and the fallback `<img>` took that crop's size. With the mobile
  crop first, a slide that had not loaded yet held the mobile aspect ratio at desktop width and the slider grew to
  that height (a 1920×294 banner took 1520px at 2307px wide).
  - Every `<source>` now carries the `width` and `height` of its own crop
  - Crops are ordered by breakpoint, widest first, then by their sort order and id, so the fallback `<img>` is the
    widest crop whatever order the rows come in
  - Preload links use the same order

### Changed
- `<picture>` rendering and preload links moved out of `BannerRenderer` into `PictureRendererInterface` and
  `PreloadLinkBuilderInterface`; crop ordering is `CropOrderInterface`. `BannerRenderer` keeps its public methods and
  delegates to them
- `BannerRenderer` constructor takes the two new services

### Added
- Unit tests for crop ordering, breakpoint reading, `<picture>` rendering and preload links

## [1.0.7] - 2026-02-03

### Added
- Location-based slider rendering support
  - New `location` widget parameter for rendering sliders by location identifier
  - `slider_id` parameter now optional (takes precedence over location when both provided)
  - Uses `SliderLocatorInterface::getByLocation()` for location-based lookups
  - Cache key includes location for proper FPC variation

## [1.0.6] - 2026-02-02

### Added
- Custom CSS rendering support in slider template
  - Outputs slider's custom CSS in `<style>` tag using `SecureHtmlRenderer`
  - CSS is rendered before the slider container for proper cascade

## [1.0.5] - 2026-02-02

### Added
- Extensible element attribute system for slider, slide, and link elements
- `ElementAttributePoolInterface` - Collects and merges attributes from registered providers
- `ElementAttributeProviderInterface` - Interface for modules to provide custom HTML attributes
- `ElementAttributePool` model implementation with sort order support
- `getContainerAttributesHtml()` method in BannerRenderer for slider container attributes
- `getSlideAttributesHtml()` method in BannerRenderer for individual slide attributes
- `data-slider-id` attribute on slider container element
- `data-banner-id` attribute on slide and link elements

### Changed
- `getLinkAttributes()` now accepts optional `SliderInterface` parameter for pool integration
- Slider template updated to use new attribute methods with extensibility support
- DI configuration updated with ElementAttributePool preference and empty providers array

### Technical Notes
- Attribute providers can be registered via DI by adding to the `providers` array
- Class attributes from multiple providers are automatically merged (not overwritten)
- Providers are sorted by `getSortOrder()` for predictable execution order
- Enables analytics modules to inject tracking attributes without template modification

## [1.0.4] - 2026-01-31

### Added
- Store ID and customer group validation for slider widget
- `HttpContext` integration for FPC-compatible customer group detection
- `getCacheKeyInfo()` method for proper cache variation by customer group

### Changed
- Widget now uses `SliderLocatorInterface` service instead of direct collection access
- Slider is only displayed if it matches current store and customer group assignment

### Fixed
- Widget now respects slider store and customer group restrictions

## [1.0.3] - 2026-01-31
### Fixed
- Update autoplay method calls to match renamed interface methods 

## [1.0.2] - 2026-01-31

### Changed
- Skip Splide widget initialization when slider contains only one banner (performance optimization)

### Fixed
- Custom content and overlay content now properly process Magento directives (e.g., `{{store url="..."}}`, `{{widget ...}}`)

## [1.0.1] - 2026-01-31
- Add missing dependency hryvinskyi/module-media-uploader

## [1.0.0] - 2026-01-31

### Added
- Initial release of Banner Slider Frontend UI module
- Widget implementation:
  - `Hryvinskyi\BannerSliderFrontendUi\Block\Widget\Slider` widget block
  - Widget configuration in `widget.xml`
  - Slider ID and template selection parameters
- Banner rendering:
  - `ViewModel\BannerRenderer` for rendering logic
  - Support for Image, Video, and Custom HTML banner types
  - Image URL generation with media path handling
- Responsive image support:
  - `<picture>` element generation with multiple sources
  - AVIF format support with automatic fallback
  - WebP format support with automatic fallback
  - Per-breakpoint image sources with media queries
  - Native lazy loading attribute support
- Video rendering:
  - YouTube embed support with iframe
  - Vimeo embed support with iframe
  - Local MP4 video support with `<video>` element
  - Local WebM video support
  - Background mode (autoplay, muted, loop, no controls)
  - Custom aspect ratio handling
- Performance optimizations:
  - Preload link generation for LCP images
  - N+1 query prevention with responsive crop preloading
  - Image dimension caching
  - Full Page Cache compatibility with proper cache tags
- Splide.js carousel integration:
  - OWL Carousel to Splide configuration conversion
  - Responsive breakpoint support
  - All standard carousel options (autoplay, navigation, pagination, etc.)
- Template:
  - `slider.phtml` main slider template
- Styling:
  - `styles.less` frontend styles
