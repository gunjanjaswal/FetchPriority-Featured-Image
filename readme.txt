=== FetchPriority Featured Image ===
Plugin URI: https://github.com/gunjanjaswal/FetchPriority-Featured-Image
Contributors: gunjanjaswal
Donate link: https://ko-fi.com/gunjanjaswal
Tags: performance, images, featured-image, web-vitals, fetchpriority
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 1.3.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically adds fetchpriority="high" attribute to featured images to improve page loading performance and Core Web Vitals.

== Description ==

FetchPriority Featured Image is a lightweight plugin that automatically adds the `fetchpriority="high"` attribute to featured images on your WordPress site. This helps browsers prioritize the loading of these important images, improving your site's performance and Core Web Vitals scores.

= Key Features =

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

No, the plugin works automatically once activated with sensible defaults. Optional fine-tuning is available under **Settings → FetchPriority** (contexts, first-N posts on archives, preload, debug badge).

= Will this slow down my site? =

No, the plugin adds minimal overhead and should actually improve your site's performance by helping browsers prioritize important images.

= How can I verify it's working? =

You can view the HTML source of your pages and look for `fetchpriority="high"` in the featured image HTML.

== Screenshots ==

1. Example of the fetchpriority attribute added to a featured image in HTML source.

== Changelog ==

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
