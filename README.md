# FetchPriority Featured Image

[![GitHub release (latest by date)](https://img.shields.io/github/v/release/gunjanjaswal/FetchPriority-Featured-Image?style=flat-square)](https://github.com/gunjanjaswal/FetchPriority-Featured-Image/releases)
[![GitHub stars](https://img.shields.io/github/stars/gunjanjaswal/FetchPriority-Featured-Image?style=flat-square)](https://github.com/gunjanjaswal/FetchPriority-Featured-Image/stargazers)
[![GitHub forks](https://img.shields.io/github/forks/gunjanjaswal/FetchPriority-Featured-Image?style=flat-square)](https://github.com/gunjanjaswal/FetchPriority-Featured-Image/network/members)
[![License](https://img.shields.io/github/license/gunjanjaswal/FetchPriority-Featured-Image?style=flat-square)](https://github.com/gunjanjaswal/FetchPriority-Featured-Image/blob/main/LICENSE)

A lightweight WordPress plugin that automatically adds the `fetchpriority="high"` attribute to featured images to improve page loading performance and Core Web Vitals scores.

## Description

FetchPriority Featured Image is a simple plugin that helps improve your website's performance by adding the `fetchpriority="high"` attribute to featured images. This tells browsers to prioritize loading these important images, which can improve your Largest Contentful Paint (LCP) scores and overall user experience.

### Key Features

* **Self-learning LCP** — a tiny PerformanceObserver beacon measures the real Largest Contentful Paint element from your visitors and auto-prioritizes it per template, instead of guessing
* **CSS `background-image` hero support** — preloads the measured background image, a blind spot for most performance plugins
* **Text-LCP web-font preload** — when the largest element is a heading or paragraph, learns the web font it used (self-hosted or Google Fonts) and preloads it
* **Cross-origin CDN preconnect** — opens an early connection to the exact host serving your hero image or font, with a `dns-prefetch` fallback
* **WooCommerce** — prioritizes the main image in the single-product gallery (shop and product-category pages are covered by the archive rules)
* Automatically adds `fetchpriority="high"` to the hero / featured image
* Optional `fetchpriority="low"` for below-fold images — paired complement that tells the browser to defer non-critical loads
* `<link rel="preload" as="image">` for the hero featured image on singular pages — strongest LCP signal
* **AVIF / WebP detection** — when a sibling `.avif` / `.webp` file exists on disk, extra `<link rel="preload" type="image/avif|image/webp">` tags are emitted so browsers pick the supported modern format automatically. Works with ShortPixel, Imagify, Optimole, and similar conversion plugins.
* **Theme presets** — auto-detects Astra, GeneratePress, Kadence, Divi, and Hello Elementor and excludes their site-logo / header-image classes so the priority budget hits the real hero
* **Avatar / Gravatar exclusion** — never tags images with class `avatar` / `gravatar` or hosted on `gravatar.com`
* **Settings import/export** and **WP-CLI** commands (`wp fpfi lcp list`, `wp fpfi settings-export/import/reset`) for managing many sites
* **Translation-ready** — ships a full `.pot` template for localisation into any language
* Settings page (Settings → FetchPriority) for per-context toggles, first-N control, preload, and exclusions
* Admin-bar debug badge showing how many images were tagged on the current page (total + how many got `high`)
* Compatible with most WordPress themes including **Divi**, **Elementor**, **Astra**, **GeneratePress**, **Kadence**, and any theme using standard `the_post_thumbnail()` / `wp_get_attachment_image()`

### Settings (Settings → FetchPriority)

| Section | Setting | Default | Description |
|---------|---------|---------|-------------|
| Contexts | Single posts & pages | ✅ | Tag featured image on singular requests |
| Contexts | Blog home | ✅ | Tag top images on the blog index |
| Contexts | Archives | ✅ | Tag top images on category/tag/author/CPT archives |
| Contexts | Search results | ✅ | Tag top images on search results |
| Contexts | First N posts on archives | `1` | How many top posts in a loop get `fetchpriority="high"` (1–20) |
| Preload | Preload featured image | ✅ | Emit `<link rel="preload" as="image" fetchpriority="high">` on singular pages |
| Preload | Modern format preload | ✅ | Also preload AVIF / WebP sibling files when present |
| Below-fold | `fetchpriority="low"` below the fold | ❌ | After the hero / first-N, tag remaining images with `low` |
| Exclusions | Skip avatars | ✅ | Don't tag images with class `avatar`/`gravatar` or `gravatar.com` src |
| Theme preset | Theme preset | `auto` | Astra / GeneratePress / Kadence / Divi / Hello Elementor / Generic |
| Debug | Admin-bar badge | ❌ | Front-end badge with tagged-image counts (manage_options only) |

### AVIF / WebP detection

The plugin probes two sibling file patterns on disk for the featured image:

| Pattern | Example |
|---------|---------|
| Extension replaced | `uploads/2026/05/hero.jpg` → checks `hero.avif`, `hero.webp` |
| Extension appended | `uploads/2026/05/hero.jpg` → checks `hero.jpg.avif`, `hero.jpg.webp` |

When a variant is found, an additional `<link rel="preload" type="image/avif">` or `<link rel="preload" type="image/webp">` is emitted ahead of the original. Modern browsers download whichever type they support; older browsers ignore the unsupported `type` and fall back to the original. No `Accept` header sniffing required.

You can override resolution with the `fpfi_modern_format_variants` filter:

```php
add_filter( 'fpfi_modern_format_variants', function ( $variants, $attachment_id ) {
    // Force a CDN-hosted AVIF for a specific attachment
    $variants['image/avif'] = 'https://cdn.example.com/hero-' . $attachment_id . '.avif';
    return $variants;
}, 10, 2 );
```

### Theme presets

| Preset | Extra excluded classes |
|--------|------------------------|
| Astra | `ast-custom-logo`, `ast-builder-logo`, `astra-logo-svg` |
| GeneratePress | `header-image`, `site-logo` |
| Kadence | `kadence-header-logo`, `site-branding-icon` |
| Divi | `et_pb_image_logo`, `et-logo` |
| Hello Elementor | `elementor-logo-item` |
| All presets | `site-logo`, `custom-logo` (always), `avatar`, `gravatar` (when avatar exclusion is on) |

Extend the list with the `fpfi_excluded_classes` filter:

```php
add_filter( 'fpfi_excluded_classes', function ( $classes, $preset ) {
    $classes[] = 'my-theme-icon';
    return $classes;
}, 10, 2 );
```

### Below-fold low priority

When enabled, the plugin tracks the per-request priority budget:

- **Singular page:** the first image gets `high`; every subsequent image gets `low`.
- **Archive / home / search:** posts inside the first-N window get `high`; posts past that window get `low`.

This is the recommended pairing for `fetchpriority="high"` — `high` alone shifts the hero up, but `low` on below-fold images also frees bandwidth for it.

## Installation

### From GitHub

1. Download the latest release from the [GitHub repository](https://github.com/gunjanjaswal/FetchPriority-Featured-Image/releases)
2. Upload the plugin folder to the `/wp-content/plugins/` directory of your WordPress installation
3. Activate 'FetchPriority Featured Image' from your Plugins page

### Manual Installation

1. Clone the repository: `git clone https://github.com/gunjanjaswal/FetchPriority-Featured-Image.git`
2. Upload the `fetchpriority-featured-image` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress

## Frequently Asked Questions

### Does this plugin modify my images?

No, this plugin only adds an HTML attribute to the image tag. It doesn't modify your actual image files or database entries.

### Will this work with my theme?

Yes! This plugin works with any theme that uses WordPress's standard featured image functions. It also includes specific support for popular page builders like Divi and Elementor that use custom image rendering methods.

### Do I need to configure anything?

No, the plugin ships with sensible defaults — install and activate. Optional fine-tuning is available under **Settings → FetchPriority** (contexts, first-N posts on archives, preload, debug badge).

### How can I verify it's working?

You can view the HTML source of your pages and look for `fetchpriority="high"` in the featured image HTML.

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Created by [Gunjan Jaswal](https://gunjanjaswal.me)

## Technical Details

### How It Works

The plugin uses multiple WordPress filters to ensure broad compatibility:

1. **`post_thumbnail_html`** - Catches featured images rendered via `the_post_thumbnail()`
2. **`wp_get_attachment_image_attributes`** - Catches images rendered via `wp_get_attachment_image()` (used by Divi and other page builders)
3. **`the_content`** - Fallback filter to catch any remaining images in post content

It intelligently applies the `fetchpriority="high"` attribute only to:

- Featured images on single posts and pages
- The first post's featured image on archive pages, blog home, and search results

This targeted approach ensures that only the most important images get the high priority treatment, which is in line with best practices for using the `fetchpriority` attribute.

### Code Example

```php
function fpfi_add_fetchpriority_to_featured_image( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
    if ( fpfi_html_has_excluded_class( $html ) || fpfi_html_is_gravatar( $html ) ) {
        return $html;
    }
    if ( strpos( $html, 'fetchpriority=' ) !== false ) {
        return $html;
    }

    $priority = fpfi_decide_priority();  // 'high' | 'low' | null
    if ( $priority === null ) {
        return $html;
    }

    fpfi_record_priority_applied( $priority );
    return str_replace( '<img ', '<img fetchpriority="' . esc_attr( $priority ) . '" ', $html );
}
```

`fpfi_decide_priority()` is the shared decision point. It reads saved settings, the in-request main-loop post index (advanced via the `the_post` action), and the in-request "high budget" counter — so every filter (`post_thumbnail_html`, `wp_get_attachment_image_attributes`, `the_content`) and the preload tag spend the same budget on a single page load.

## Contributing

Contributions are welcome! Feel free to:

1. Fork the repository
2. Create a feature branch: `git checkout -b my-new-feature`
3. Commit your changes: `git commit -am 'Add some feature'`
4. Push to the branch: `git push origin my-new-feature`
5. Submit a pull request

## Support

If you find this plugin useful, consider [supporting on Ko-fi](https://ko-fi.com/gunjanjaswal) to back the development.

## Changelog

### Version 1.5.4
* **Fix:** Core Web Vitals and PageSpeed requests now send the site URL as the `Referer`, so a Google API key restricted to your domain works from the server (previously blocked with "Requests from referer <empty> are blocked").
* **Improved:** a failed *keyed* PageSpeed request now surfaces the key error (referrer / "API not enabled") instead of the misleading shared-quota message from the keyless fallback.

### Version 1.5.3
* **Fix:** settings toggles showed a hard outline ring after a click (Chrome treats a toggle click as keyboard focus) — replaced with a soft focus glow.
* **Fix:** the Export / Import buttons rendered their icon dropped to the bottom-left instead of beside the label — now a centered flex layout.

### Version 1.5.2
* **Improved:** redesigned the settings toggle switches — larger, shadowed knob, smooth slide animation, cleaner off state, and a proper keyboard focus ring. Visual only.

### Version 1.5.1
* **Fix:** the "Measure Core Web Vitals" and "Run PageSpeed audit" buttons (and "Clear learned data" / import / reset) did nothing when clicked — the admin script bound its handlers before the DOM was ready. Now wrapped in a ready callback.
* **Improved:** PageSpeed audit falls back to a keyless request when a Google API key is rejected (e.g. a key enabled only for the Chrome UX Report).
* **Improved:** CrUX / PageSpeed errors now render in the panel instead of a small status line, so messages like "no field data for this origin" on lower-traffic sites are visible.

### Version 1.5.0
* **WooCommerce** — the main image in the single-product gallery is now prioritized as the hero. Shop and product-category pages were already covered by the archive rules; this fills the one gap, since the gallery builds its own markup.
* **Text-LCP web-font preload** — on pages where the largest element is text, the plugin learns the web font that text used and preloads it. Works with self-hosted fonts and Google Fonts, per template.
* **Cross-origin CDN preconnect** — when the hero image or font lives on another host, an early `<link rel="preconnect">` (plus `dns-prefetch` fallback) is emitted for that exact host. Same-origin heroes emit nothing.
* **Settings import/export** — save your configuration to a JSON file and load it on another site; learned data and the API key stay per-site. Plus a reset-to-defaults button.
* **WP-CLI** — `wp fpfi lcp list`, `wp fpfi lcp reset`, and `wp fpfi settings-export/import/reset`.
* **Translation template** — ships a full `.pot` so the plugin can be localised into any language.

### Version 1.4.0
* **Self-learning LCP** — a lightweight PerformanceObserver beacon reports the real LCP element per template; once enough samples land, the plugin auto-preloads and tags that exact image.
* **CSS `background-image` hero support**, **visual LCP picker**, and a **Core Web Vitals before/after report** via the Chrome UX Report (CrUX) API.
* **Per-template control** (Auto / Learned-only / Manual-only / Off), **PageSpeed audit** from the admin, **oversized-LCP detection**, and a **slowest-templates leaderboard**.
* Loading optimization (`eager` on the hero, `lazy` below the fold) and a redesigned tabbed settings screen.

### Version 1.3.0
* Added Settings page under **Settings → FetchPriority** (Contexts / Preload / Below-fold / Exclusions / Theme preset / Debug).
* Added per-context toggles: Single posts & pages, Blog home, Archives, Search results.
* Added "First N posts on archives" setting (1–20) — previously hardcoded to first post only.
* Added optional `<link rel="preload" as="image" fetchpriority="high">` for the featured image on singular pages.
* Added AVIF / WebP sibling detection — extra `<link rel="preload" type="image/avif|image/webp">` tags emitted when modern variants exist on disk.
* Added `fetchpriority="low"` for below-fold images (opt-in) — paired complement to the hero `high` tag.
* Added theme presets with auto-detection: Astra, GeneratePress, Kadence, Divi, Hello Elementor.
* Added Avatar / Gravatar exclusion to keep the priority budget on the real hero.
* Added `fpfi_modern_format_variants` and `fpfi_excluded_classes` filters for extension.
* Added admin-bar debug badge showing total tagged + how many got `high`.
* Added Settings link to plugin action links on the Plugins screen.
* Content filter rewritten with `preg_replace_callback` so the high/low budget is honored across **all** images in content, not just the first.
* Author display name updated to "Gunjan Jaswal".

### Version 1.2.1
* Updated "Tested up to" to WordPress 7.0.
* Updated donation link to Ko-fi (https://ko-fi.com/gunjanjaswal).
* Removed extraneous GITHUB_DESCRIPTION.md from plugin root for WordPress.org compliance.

### Version 1.2.0
* Added support for Divi theme and other page builders
* Implemented `wp_get_attachment_image_attributes` filter for broader compatibility
* Added content filter fallback to catch custom image implementations
* Improved image detection across different theme rendering methods
* Enhanced compatibility with themes that bypass standard WordPress image functions

### Version 1.1.0
* Updated for WordPress 6.9 compatibility
* Improved security with nonce verification for AJAX calls
* Updated minimum PHP requirement to 7.4
* Enhanced code quality and WordPress coding standards compliance
* Added proper input sanitization and escaping
* Aligns with WordPress 6.9's frontend performance improvements

### Version 1.0.0
* Initial release

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.
