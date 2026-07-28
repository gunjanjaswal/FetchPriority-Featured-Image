<?php
/**
 * Cross-origin (CDN) preconnect.
 *
 * The hero image and web font are often served from a different host than the
 * site — an image CDN (BunnyCDN, Cloudflare, Jetpack/Photon, Optimole…) or
 * Google Fonts. The browser can't open that connection until it discovers the
 * URL, which costs a DNS + TLS round trip on the critical path. This module
 * emits <link rel="preconnect"> for exactly the cross-origin hosts the hero
 * resources will load from, so the connection is warm before the preload fires.
 *
 * @package FetchPriority_Featured_Image
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Pull the host out of a URL, lower-cased, or '' when it has none / is relative.
 *
 * @param string $url
 * @return string
 */
function fpfi_host_of($url)
{
    if (!$url || !is_string($url)) {
        return '';
    }
    $host = wp_parse_url($url, PHP_URL_HOST);
    return $host ? strtolower($host) : '';
}

/**
 * Collect the hosts of every URL inside a srcset string.
 *
 * @param string $srcset
 * @return string[]
 */
function fpfi_srcset_hosts($srcset)
{
    $hosts = array();
    if (!$srcset) {
        return $hosts;
    }
    foreach (explode(',', $srcset) as $part) {
        $url = trim($part);
        $url = preg_split('/\s+/', $url);
        $url = isset($url[0]) ? $url[0] : '';
        $h = fpfi_host_of($url);
        if ($h) {
            $hosts[] = $h;
        }
    }
    return $hosts;
}

/**
 * Emit preconnect / dns-prefetch for the cross-origin hero hosts.
 */
function fpfi_preconnect_lcp()
{
    $s = fpfi_get_settings();
    if (empty($s['enable_preconnect'])) {
        return;
    }
    if (!fpfi_context_enabled()) {
        return;
    }

    $site_host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));

    // host => whether it needs crossorigin (fonts always do).
    $hosts = array();

    // --- Hero image hosts (post-CDN-rewrite src + srcset) ---
    if (!empty($s['enable_preload'])) {
        $att_id = 0;
        $lcp = function_exists('fpfi_current_lcp') ? fpfi_current_lcp() : null;
        if ($lcp && !empty($lcp['attachment_id']) && empty($lcp['is_bg'])) {
            $att_id = (int) $lcp['attachment_id'];
        } elseif ((!$lcp || empty($lcp['url'])) && is_singular() && !empty($s['enable_singular'])) {
            // Featured-image fallback path (same one fpfi_preload_featured_image uses).
            $pid = get_queried_object_id();
            if ($pid && has_post_thumbnail($pid)) {
                $att_id = (int) get_post_thumbnail_id($pid);
            }
        }

        $urls = array();
        if ($lcp && !empty($lcp['url'])) {
            $urls[] = $lcp['url']; // covers background-image heroes too.
        }
        if ($att_id) {
            $src = wp_get_attachment_image_src($att_id, 'full');
            if ($src && !empty($src[0])) {
                $urls[] = $src[0];
            }
            foreach (fpfi_srcset_hosts(wp_get_attachment_image_srcset($att_id, 'full')) as $h) {
                if ($h && $h !== $site_host && !isset($hosts[$h])) {
                    $hosts[$h] = false;
                }
            }
        }
        foreach ($urls as $u) {
            $h = fpfi_host_of($u);
            if ($h && $h !== $site_host && !isset($hosts[$h])) {
                $hosts[$h] = false;
            }
        }
    }

    // --- Font host ---
    if (!empty($s['enable_font_preload']) && !empty($s['enable_preload']) && function_exists('fpfi_current_font')) {
        $font = fpfi_current_font();
        if ($font && !empty($font['url'])) {
            $h = fpfi_host_of($font['url']);
            if ($h && $h !== $site_host) {
                $hosts[$h] = true; // fonts need crossorigin
            }
        }
    }

    if (empty($hosts)) {
        return;
    }

    /**
     * Filter the resolved cross-origin hosts before they are emitted.
     *
     * @param array $hosts Map of host => needs-crossorigin (bool).
     */
    $hosts = apply_filters('fpfi_preconnect_hosts', $hosts);

    foreach ($hosts as $host => $crossorigin) {
        $origin = '//' . $host;
        printf(
            '<link rel="preconnect" href="%s"%s>' . "\n",
            esc_url($origin),
            $crossorigin ? ' crossorigin' : ''
        );
        // dns-prefetch as a fallback for browsers that ignore preconnect.
        printf('<link rel="dns-prefetch" href="%s">' . "\n", esc_url($origin));
    }
}
add_action('wp_head', 'fpfi_preconnect_lcp', 0);
