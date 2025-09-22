# FetchPriority Featured Image

[![GitHub release (latest by date)](https://img.shields.io/github/v/release/gunjanjaswal/FetchPriority-Featured-Image?style=flat-square)](https://github.com/gunjanjaswal/FetchPriority-Featured-Image/releases)
[![GitHub stars](https://img.shields.io/github/stars/gunjanjaswal/FetchPriority-Featured-Image?style=flat-square)](https://github.com/gunjanjaswal/FetchPriority-Featured-Image/stargazers)
[![GitHub forks](https://img.shields.io/github/forks/gunjanjaswal/FetchPriority-Featured-Image?style=flat-square)](https://github.com/gunjanjaswal/FetchPriority-Featured-Image/network/members)
[![License](https://img.shields.io/github/license/gunjanjaswal/FetchPriority-Featured-Image?style=flat-square)](https://github.com/gunjanjaswal/FetchPriority-Featured-Image/blob/main/LICENSE)

A lightweight WordPress plugin that automatically adds the `fetchpriority="high"` attribute to featured images to improve page loading performance and Core Web Vitals scores.

## Description

FetchPriority Featured Image is a simple plugin that helps improve your website's performance by adding the `fetchpriority="high"` attribute to featured images. This tells browsers to prioritize loading these important images, which can improve your Largest Contentful Paint (LCP) scores and overall user experience.

### Key Features

* Automatically adds `fetchpriority="high"` to featured images
* Intelligently applies the attribute only where it matters most:
  * On single posts and pages
  * On the first post of archive pages, blog home, and search results
* Zero configuration required - install and activate
* No settings page to keep things simple and lightweight
* Compatible with most WordPress themes

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

This plugin should work with any theme that uses WordPress's standard featured image functions. If your theme uses custom code to display featured images, the plugin might not affect those images.

### Do I need to configure anything?

No, the plugin works automatically once activated. There are no settings to configure.

### How can I verify it's working?

You can view the HTML source of your pages and look for `fetchpriority="high"` in the featured image HTML.

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Created by [Gunjan Jaswaal](https://gunjanjaswal.me)

## Technical Details

### How It Works

The plugin uses WordPress's `post_thumbnail_html` filter to add the `fetchpriority="high"` attribute to the HTML of featured images. It intelligently applies this attribute only to:

- Featured images on single posts and pages
- The first post's featured image on archive pages, blog home, and search results

This targeted approach ensures that only the most important images get the high priority treatment, which is in line with best practices for using the `fetchpriority` attribute.

### Code Example

```php
function fpfi_add_fetchpriority_to_featured_image( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
    // Only add fetchpriority to featured images on single posts/pages or the first post on archive pages
    if ( is_singular() || ( is_home() || is_archive() || is_search() ) && ! isset( $GLOBALS['fpfi_first_post_processed'] ) ) {
        // Mark that we've processed the first post on archive pages
        if ( ! is_singular() ) {
            $GLOBALS['fpfi_first_post_processed'] = true;
        }
        
        // Add fetchpriority="high" attribute if it doesn't already exist
        if ( strpos( $html, 'fetchpriority=' ) === false ) {
            $html = str_replace( '<img ', '<img fetchpriority="high" ', $html );
        }
    }
    
    return $html;
}
```

## Contributing

Contributions are welcome! Feel free to:

1. Fork the repository
2. Create a feature branch: `git checkout -b my-new-feature`
3. Commit your changes: `git commit -am 'Add some feature'`
4. Push to the branch: `git push origin my-new-feature`
5. Submit a pull request

## Support

If you find this plugin useful, consider [buying me a coffee](https://www.buymeacoffee.com/gunjanjaswal) to support the development.

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.
