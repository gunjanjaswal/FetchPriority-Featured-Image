=== FetchPriority Featured Image ===
Plugin URI: https://github.com/gunjanjaswal/FetchPriority-Featured-Image
Contributors: gunjanjaswal
Donate link: https://ko-fi.com/gunjanjaswal
Tags: performance, core-web-vitals, lcp, fetchpriority, image-optimization
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.5.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Measures your real LCP element from visitors and auto-applies fetchpriority + preload. Visual picker and Core Web Vitals report.

== Description ==

FetchPriority Featured Image is a self-learning LCP (Largest Contentful Paint) optimizer. Instead of *guessing* which image is your hero like every other plugin, it **measures the real LCP element from your actual visitors** (via the browser's PerformanceObserver), learns it per template, and then automatically applies `fetchpriority="high"` plus a `<link rel="preload">` to that exact image — whether it's a normal `<img>` or a CSS `background-image`. It self-corrects as your design and content change, with zero configuration.

= What makes it different =

* **Self-learning real LCP** — no competitor in this space measures field LCP from real users and auto-targets it. Most plugins blindly prioritize the featured image and hope it's the hero.
* **CSS background-image preload** — hero sliders and background heroes are a blind spot for most performance plugins; this preloads them.
* **Visual LCP picker** — click your hero element on the front end to lock it in as a manual override per template.
* **Built-in Core Web Vitals before/after report** — pulls real-world LCP, INP, and CLS from the Chrome UX Report so you can prove the impact.
* **Per-template control** — Auto / Learned-only / Manual-only / Off for every template the plugin sees.

= Key Features =

* Self-learning LCP detection from real-user field data (PerformanceObserver beacon), aggregated per template
* Visual click-to-pick LCP element on the front end (admin-bar → "Pick LCP element")
* Core Web Vitals before/after report via the Chrome UX Report (CrUX) API
* One-click PageSpeed Insights (Lighthouse) audit from the admin, showing the score, LCP, page weight, image-saving opportunities, and Google's own detected LCP element
* Oversized-LCP detection — warns when your hero image is larger than it displays, with a recommended width
* Slowest-templates leaderboard built from real-user LCP timing
* `loading="eager"` on the LCP image and `loading="lazy"` below the fold so native lazy-loading never delays the hero
* Preloads + prioritizes the measured LCP, including CSS `background-image`, video poster, and `<picture>` heroes
* Text-LCP web-font preload — when the largest element is a heading or paragraph, learns the web font it used (self-hosted or Google Fonts) and preloads it
* Cross-origin CDN preconnect — opens an early connection to the exact host serving your hero image or font, with a `dns-prefetch` fallback
* WooCommerce — prioritizes the main image in the single-product gallery (shop and product-category pages are covered by the archive rules)
* Settings import/export to move one tuned configuration across sites, plus a reset-to-defaults button
* WP-CLI commands: `wp fpfi lcp list`, `wp fpfi lcp reset`, and `wp fpfi settings-export/import/reset`
* Translation-ready — ships a full `.pot` template for localisation into any language
* Per-template modes: Auto, Learned-only, Manual-only, Off
* Tabbed settings screen (Smart LCP / Targeting / Preload / Diagnostics) with a clean, flat interface
* Automatically adds `fetchpriority="high"` to the hero / featured image
* Optional `fetchpriority="low"` for below-fold images — paired complement that tells the browser to defer non-critical loads
* `<link rel="preload" as="image">` for the hero featured image on singular pages — strongest LCP signal
* AVIF / WebP detection — when a sibling `.avif` / `.webp` file exists on disk, an extra `<link rel="preload" type="image/avif|image/webp">` is emitted so the browser picks the supported modern format automatically (works with ShortPixel, Imagify, Optimole, and similar)
* Theme presets — auto-detects Astra, GeneratePress, Kadence, Divi, and Hello Elementor and excludes their site-logo / header-image classes so the priority budget is spent on the real hero
* Avatar / Gravatar exclusion — never tags images with class `avatar` / `gravatar` or hosted on gravatar.com
* Settings page (Settings → FetchPriority) for per-context toggles, first-N control, preload, and exclusions
* Admin-bar debug badge showing how many images were tagged on the current page (total + how many got `high`)
* Compatible with most WordPress themes including Divi, Elementor, Astra, GeneratePress, Kadence, and any theme using standard `the_post_thumbnail()` / `wp_get_attachment_image()`

= Why Use FetchPriority? =

The `fetchpriority` attribute is a modern web standard that tells browsers which images should be prioritized during page load. By marking featured images as high priority, you can improve:

* Largest Contentful Paint (LCP) scores
* User experience with faster loading of important images
* Overall page performance

= Developer-Friendly =

The plugin uses WordPress's native filters and doesn't modify your database or image files.

== Installation ==

1. Upload the `fetchpriority-featured-image` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. That's it! The plugin works automatically with no configuration needed

== Frequently Asked Questions ==

= Does this plugin modify my images? =

No, this plugin only adds an HTML attribute to the image tag. It doesn't modify your actual image files or database entries.

= Will this work with my theme? =

Yes! This plugin works with any theme that uses WordPress's standard featured image functions. It also includes specific support for popular page builders like Divi and Elementor that use custom image rendering methods.

= Do I need to configure anything? =

No. The plugin works automatically once activated with sensible defaults, and self-learning LCP is on out of the box. Optional fine-tuning lives under **Settings → FetchPriority**, organised into tabs: Smart LCP, Targeting, Preload, and Diagnostics.

= How does the self-learning work, and how long does it take? =

A small first-party script measures the Largest Contentful Paint element in real visitors' browsers and reports it back per template. After a few samples on a template, the plugin starts preloading and prioritising that exact image. The more traffic a template gets, the faster it settles — and it re-learns automatically if your hero changes. You can also set it manually with the visual picker.

= Does it send any data to third parties? =

The self-learning measurement is entirely first-party — reports go only to your own site, and store image URLs and timings, never visitor data. The optional Core Web Vitals report and PageSpeed audit call Google's APIs (Chrome UX Report and PageSpeed Insights) only when you click those buttons in the admin.

= Will this slow down my site? =

No. The measurement script is tiny and loads on only a sample of page views (20% by default, adjustable). Everything else runs in PHP at output time. The plugin's job is to make pages faster by prioritising the right image.

= How can I verify it's working? =

View the HTML source and look for `fetchpriority="high"` on the hero image and a matching `<link rel="preload">` in the `<head>`. The admin-bar badge (enable it in Diagnostics) also shows how many images were tagged on the current page.

== Screenshots ==

1. Smart LCP tab — self-learning toggle, sample rate, and the per-template targets table.
2. Diagnostics tab — Core Web Vitals before/after report and the one-click PageSpeed audit.
3. Slowest-templates leaderboard built from real-user LCP timing.
4. Visual LCP picker — click your hero element on the front end to set it as the target.
5. Example of the fetchpriority attribute added to a featured image in HTML source.

== Changelog ==

= 1.5.0 =
* NEW: WooCommerce support — the main image in the single-product gallery now gets prioritised as the hero. Shop and product-category pages were already covered by the archive rules; this fills the one gap, since the gallery builds its own markup. Toggle it under Targeting (shown only when WooCommerce is active).
* NEW: Text-LCP font preload — on the many pages where the largest element is a heading or paragraph rather than an image, the plugin now learns which web font that text used and preloads it. Works with self-hosted fonts and Google Fonts, per template, from the same real-visitor measurements.
* NEW: CDN preconnect — when your hero image or font lives on another host (an image CDN, Google Fonts), the plugin opens the connection early with `<link rel="preconnect">` plus a `dns-prefetch` fallback, matched to the exact cross-origin host. Same-origin heroes emit nothing.
* NEW: Backup & migrate — export your settings to a JSON file and import them on another site. Great for rolling one tuned configuration across a fleet. Learned data and your API key stay per-site and are kept out of the file. There's also a "Reset to defaults" button.
* NEW: WP-CLI — `wp fpfi lcp list`, `wp fpfi lcp reset`, and `wp fpfi settings-export/import/reset` for reading learned data and scripting deployments.
* Full translation template (.pot) shipped so the plugin can be localised into any language via translate.wordpress.org or a tool like Poedit.
* Friendlier template labels for WooCommerce (Product pages, Shop, Product categories) in the learning tables.

= 1.4.0 =
* NEW: Self-learning LCP — a lightweight PerformanceObserver beacon reports the real Largest Contentful Paint element per template; once enough samples are collected the plugin auto-preloads and tags that exact image with `fetchpriority="high"`.
* NEW: CSS `background-image` hero support — preloads the measured/manual background image, a blind spot for most performance plugins.
* NEW: Visual LCP picker — open the front-end admin bar, click "Pick LCP element", click your hero, done. Saved as a manual override per template.
* NEW: Core Web Vitals before/after report — connect a free Chrome UX Report (CrUX) API key to see real-world LCP, INP, and CLS, with a saved baseline to measure improvement.
* NEW: Per-template control table — Auto / Learned-only / Manual-only / Off for every template the plugin has seen.
* NEW: PageSpeed audit — run Google Lighthouse on any URL from the admin; see the performance score, LCP, page weight, image-saving opportunities, and Google's own detected LCP element to confirm correct targeting.
* NEW: Oversized-LCP detection — compares the measured LCP image's real pixels against its displayed size and warns when you're serving wasted bytes, with a recommended width.
* NEW: Loading optimization — forces `loading="eager"` on the LCP image and `loading="lazy"` on below-fold images so native lazy-loading never delays your hero.
* NEW: Slowest-templates leaderboard — measured real-user LCP per template, sorted slowest first, as a built-in to-do list.
* Video poster and `<picture>` heroes are supported as LCP targets via the learned/manual preload.
* Configurable sampling rate for the measurement script to keep front-end overhead minimal.
* Learned/manual targets supersede the featured-image guess for both preload and the `fetchpriority` tag.
* Redesigned settings screen — tabbed (Smart LCP / Targeting / Preload / Diagnostics), card-based, flat interface with toggle controls and a sticky save bar.
* Post-update notice highlighting what's new (shown to upgrading sites, not fresh installs).

= 1.3.0 =
* Added Settings page under Settings → FetchPriority (Contexts / Preload / Below-fold / Exclusions / Theme preset / Debug).
* Added per-context toggles: Single posts & pages, Blog home, Archives, Search results.
* Added "First N posts on archives" setting (1–20) — previously hardcoded to first post only.
* Added optional `<link rel="preload" as="image" fetchpriority="high">` for the featured image on singular pages (strongest LCP signal).
* Added AVIF / WebP detection — when a sibling modern-format file exists on disk, additional `<link rel="preload" type="image/avif|webp">` tags are emitted; browsers pick the supported variant automatically.
* Added `fetchpriority="low"` for below-fold images (opt-in) as a paired complement to the hero `high` tag.
* Added theme presets (Astra / GeneratePress / Kadence / Divi / Hello Elementor) with auto-detection — excludes theme logo & header classes so the priority budget hits the real hero.
* Added Avatar / Gravatar exclusion to keep author avatars from consuming the priority budget.
* Added admin-bar debug badge showing total tagged + how many were tagged `high`.
* Added Settings link to plugin action links on the Plugins screen.
* Content filter rewritten to use a `preg_replace_callback` walk so the high/low budget is honored across all images in the content, not only the first.
* Cleaner reset logic on each request via `template_redirect`.
* Author display name updated to "Gunjan Jaswal".

= 1.2.1 =
* Updated "Tested up to" to WordPress 7.0.
* Updated donation link to Ko-fi (https://ko-fi.com/gunjanjaswal).
* Removed extraneous GITHUB_DESCRIPTION.md from plugin root for WordPress.org compliance.

= 1.2.0 =
* Added support for Divi theme and Elementor page builder
* Implemented `wp_get_attachment_image_attributes` filter for broader compatibility
* Added content filter fallback to catch custom image implementations
* Improved image detection across different theme rendering methods
* Enhanced compatibility with themes that bypass standard WordPress image functions

= 1.1.0 =
* Updated for WordPress 6.9 compatibility
* Improved security with nonce verification for AJAX calls
* Updated minimum PHP requirement to 7.4
* Enhanced code quality and WordPress coding standards compliance
* Added proper input sanitization and escaping
* Aligns with WordPress 6.9's frontend performance improvements

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.5.0 =
Adds WooCommerce product-gallery support, web-font preload for text heroes, cross-origin CDN preconnect, settings import/export, WP-CLI commands, and a full translation template. Backward-compatible; the new options default on but only act where they apply.

= 1.4.0 =
Major update: self-learning LCP from real visitors, CSS background-image preload, visual LCP picker, and a built-in Core Web Vitals before/after report. Backward-compatible; learning is on by default at a 20% sample rate.

= 1.3.0 =
Settings page; hero preload with AVIF/WebP detection; optional low-priority below-fold tagging; theme presets (Astra/GeneratePress/Kadence/Divi/Elementor); avatar exclusion; admin-bar debug badge. Defaults backward-compatible; low-priority tagging opt-in.

= 1.2.1 =
Compatibility with WordPress 7.0 and donation link updated to Ko-fi.

= 1.2.0 =
Major compatibility update! Now works with Divi, Elementor, and other page builders. Highly recommended for all users.

= 1.1.0 =
Compatibility update for WordPress 6.9 with security improvements. Requires PHP 7.4 or higher.

= 1.0.0 =
Initial release of the plugin.
