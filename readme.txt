=== FetchPriority Featured Image ===
Plugin URI: https://github.com/gunjanjaswal/FetchPriority-Featured-Image
Contributors: gunjanjaswal
Donate link: https://www.buymeacoffee.com/gunjanjaswal
Tags: performance, images, featured-image, web-vitals, fetchpriority
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically adds fetchpriority="high" attribute to featured images to improve page loading performance and Core Web Vitals.

== Description ==

FetchPriority Featured Image is a lightweight plugin that automatically adds the `fetchpriority="high"` attribute to featured images on your WordPress site. This helps browsers prioritize the loading of these important images, improving your site's performance and Core Web Vitals scores.

= Key Features =

* Automatically adds `fetchpriority="high"` to featured images
* Intelligently applies the attribute only where it matters most:
  * On single posts and pages
  * On the first post of archive pages, blog home, and search results
* Zero configuration required - install and activate
* No settings page to keep things simple and lightweight
* Compatible with most WordPress themes

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

This plugin should work with any theme that uses WordPress's standard featured image functions. If your theme uses custom code to display featured images, the plugin might not affect those images.

= Do I need to configure anything? =

No, the plugin works automatically once activated. There are no settings to configure.

= Will this slow down my site? =

No, the plugin adds minimal overhead and should actually improve your site's performance by helping browsers prioritize important images.

= How can I verify it's working? =

You can view the HTML source of your pages and look for `fetchpriority="high"` in the featured image HTML.

== Screenshots ==

1. Example of the fetchpriority attribute added to a featured image in HTML source.

== Changelog ==

= 1.1.0 =
* Updated for WordPress 6.7 compatibility
* Improved security with nonce verification for AJAX calls
* Updated minimum PHP requirement to 7.4
* Enhanced code quality and WordPress coding standards compliance
* Added proper input sanitization and escaping

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.1.0 =
Compatibility update for WordPress 6.7 with security improvements. Requires PHP 7.4 or higher.

= 1.0.0 =
Initial release of the plugin.
