# GitHub Repository Description

## Short Description (for the repository description field)
A lightweight WordPress plugin that adds fetchpriority="high" to featured images to improve page loading performance and Core Web Vitals scores.

## Detailed Description (for your GitHub profile or project showcase)

### FetchPriority Featured Image

FetchPriority Featured Image is a zero-configuration WordPress plugin that automatically adds the `fetchpriority="high"` attribute to featured images. This simple optimization helps browsers prioritize the loading of these important images, which can significantly improve your site's Largest Contentful Paint (LCP) scores and overall user experience.

#### Key Features:

- **Performance Optimization**: Improves Core Web Vitals scores by prioritizing featured images
- **Smart Implementation**: Only applies to featured images on single posts/pages and the first post on archive pages
- **Zero Configuration**: Works automatically after activation with no settings page
- **Lightweight**: Minimal code footprint with no external dependencies
- **Developer Friendly**: Uses WordPress's native filters without modifying your database or image files

#### Technical Implementation:

The plugin uses WordPress's `post_thumbnail_html` filter to add the `fetchpriority="high"` attribute to the HTML of featured images. It intelligently applies this attribute only where it matters most, following best practices for using the `fetchpriority` attribute.

#### Perfect For:

- Performance-focused WordPress sites
- Blogs and news sites where featured images are crucial
- Any WordPress site looking to improve Core Web Vitals scores

#### About the Author:

Created by [Gunjan Jaswaal](https://gunjanjaswal.me), a WordPress developer focused on performance optimization and user experience.

If you find this plugin useful, consider [buying me a coffee](https://www.buymeacoffee.com/gunjanjaswal) to support the development.
