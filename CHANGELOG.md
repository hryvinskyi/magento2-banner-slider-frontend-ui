# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
