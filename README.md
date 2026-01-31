# Magento 2 & Adobe Commerce Banner Slider Frontend UI

Frontend display module for Banner Sliders.

> **Part of [hryvinskyi/magento2-banner-slider-pack](https://github.com/hryvinskyi/magento2-banner-slider-pack)** - Complete Banner Slider solution for Magento 2

## Description

This module provides the frontend display functionality for banner sliders. It includes a widget for placing sliders on CMS pages, categories, or any widget-enabled area, with full support for responsive images, video embeds, and custom HTML content.

## Features

- Widget-based slider placement
- Splide.js carousel integration
- Responsive image rendering with `<picture>` elements
- AVIF and WebP format support with automatic fallbacks
- Video rendering (YouTube, Vimeo, local files)
- LCP optimization with preload links
- Full Page Cache compatible with proper cache tags

## Widget Usage

### CMS Page/Block
```
{{widget type="Hryvinskyi\BannerSliderFrontendUi\Block\Widget\Slider" slider_id="1" template="Hryvinskyi_BannerSliderFrontendUi::slider.phtml"}}
```

### Layout XML
```xml
<referenceContainer name="content">
    <block class="Hryvinskyi\BannerSliderFrontendUi\Block\Widget\Slider" name="banner.slider">
        <arguments>
            <argument name="slider_id" xsi:type="number">1</argument>
        </arguments>
    </block>
</referenceContainer>
```

## Widget Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `slider_id` | Select | The slider to display (required) |
| `template` | Select | Template file for rendering (required) |

## Components

### Block/Widget/Slider

Main widget block that:
- Loads slider and associated banners
- Filters by active status, date range, and position
- Generates Splide.js configuration as JSON
- Preloads responsive crops to prevent N+1 queries
- Provides cache tags for FPC invalidation

### ViewModel/BannerRenderer

Banner rendering logic that handles:
- Banner type detection (image, video, custom)
- Image URL generation
- Responsive image HTML with `<picture>` element
- Video provider detection and embed HTML
- Preload link generation for LCP optimization
- Image dimension caching

## Responsive Images

The module generates `<picture>` elements with multiple sources for optimal loading:

```html
<picture>
    <source srcset="image.avif" type="image/avif" media="(min-width: 1200px)">
    <source srcset="image.webp" type="image/webp" media="(min-width: 1200px)">
    <source srcset="image-tablet.avif" type="image/avif" media="(min-width: 768px)">
    <source srcset="image-tablet.webp" type="image/webp" media="(min-width: 768px)">
    <source srcset="image-mobile.avif" type="image/avif">
    <source srcset="image-mobile.webp" type="image/webp">
    <img src="image.jpg" alt="Banner title" loading="lazy">
</picture>
```

## Video Support

### YouTube/Vimeo
- Iframe embeds with responsive sizing
- Background mode (no controls, autoplay, muted, loop)
- Custom aspect ratio support

### Local Videos
- Native `<video>` element
- MP4 and WebM format support
- Autoplay, muted, and loop options

## Splide.js Integration

The carousel uses Splide.js with the following configurable options:

| Option | Description |
|--------|-------------|
| `type` | Carousel type (loop, slide, fade) |
| `perPage` | Slides visible at once |
| `perMove` | Slides to move per action |
| `autoplay` | Enable auto-advance |
| `interval` | Time between slides (ms) |
| `arrows` | Show navigation arrows |
| `pagination` | Show dot pagination |
| `lazyLoad` | Enable lazy loading |
| `autoWidth` | Automatic slide width |
| `autoHeight` | Automatic slide height |
| `speed` | Animation speed (ms) |
| `rewind` | Rewind instead of infinite loop |
| `breakpoints` | Responsive configuration |

## Performance Optimization

- **Preload Links**: Generates `<link rel="preload">` for above-the-fold images
- **Lazy Loading**: Uses native `loading="lazy"` for below-fold images
- **Format Selection**: Serves AVIF/WebP to supporting browsers
- **N+1 Prevention**: Preloads all responsive crops in a single query
- **Cache Tags**: Proper FPC invalidation on content changes

## Dependencies

- PHP 8.1+
- magento/framework
- magento/module-widget
- hryvinskyi/magento2-base ^2.1.5
- hryvinskyi/magento2-banner-slider-api
- hryvinskyi/magento2-banner-slider
- hryvinskyi/magento2-splide-js
- hryvinskyi/magento2-head-tag-manager

## Installation

This module is typically installed as part of the `hryvinskyi/magento2-banner-slider-pack` metapackage:

```bash
composer require hryvinskyi/magento2-banner-slider-pack
php bin/magento module:enable Hryvinskyi_BannerSliderFrontendUi
php bin/magento setup:upgrade
php bin/magento cache:flush
```

## Author

**Volodymyr Hryvinskyi**
- Email: volodymyr@hryvinskyi.com

## License

MIT
