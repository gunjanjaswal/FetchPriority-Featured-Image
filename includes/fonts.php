<?php
/**
 * Font / text-LCP preload.
 *
 * When a visitor's Largest Contentful Paint element is a block of text rather
 * than an image, the real bottleneck is usually the web font. The beacon
 * reports which font the text used; this module learns the winner per template
 * (same voting model as images) and preloads it from <head>.
 *
 * @package FetchPriority_Featured_Image
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Whether a reported font URL is safe to store and preload.
 *
 * Only same-origin fonts and Google Fonts (fonts.gstatic.com) are accepted, so
 * a hostile beacon payload can't make the site preload an arbitrary third-party
 * resource. The URL must also look like a real font file.
 *
 * @param string $url
 * @return bool
 */
function fpfi_font_url_allowed($url)
{
    if (!$url || strpos($url, 'http') !== 0) {
        return false;
    }
    if (!preg_match('/\.(woff2|woff|ttf|otf)(\?.*)?$/i', $url)) {
        return false;
    }
    $host = wp_parse_url($url, PHP_URL_HOST);
    if (!$host) {
        return false;
    }
    $host = strtolower($host);

    $allowed = array(strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)), 'fonts.gstatic.com');

    /**
     * Filter the host allow-list for font preloading.
     *
     * @param string[] $allowed Lower-case host names.
     */
    $allowed = apply_filters('fpfi_font_allowed_hosts', array_filter($allowed));

    return in_array($host, $allowed, true);
}

/**
 * Map a font file extension to its preload MIME type.
 *
 * @param string $url
 * @return string
 */
function fpfi_font_mime($url)
{
    if (preg_match('/\.woff2(\?.*)?$/i', $url)) {
        return 'font/woff2';
    }
    if (preg_match('/\.woff(\?.*)?$/i', $url)) {
        return 'font/woff';
    }
    if (preg_match('/\.ttf(\?.*)?$/i', $url)) {
        return 'font/ttf';
    }
    if (preg_match('/\.otf(\?.*)?$/i', $url)) {
        return 'font/otf';
    }
    return '';
}

/**
 * Collect a text-LCP font sample and recompute the per-template winner.
 *
 * @param string $template Template key.
 * @param string $url      Reported font URL.
 * @param array  $params   Full beacon payload.
 * @return WP_REST_Response
 */
function fpfi_lcp_collect_font($template, $url, $params)
{
    if ($template === '' || !fpfi_font_url_allowed($url)) {
        return new WP_REST_Response(array('ok' => false), 200);
    }

    $family = isset($params['font_family']) ? substr(sanitize_text_field($params['font_family']), 0, 100) : '';
    $weight = isset($params['font_weight']) ? substr(sanitize_text_field($params['font_weight']), 0, 20) : '';
    $style  = isset($params['font_style']) ? substr(sanitize_text_field($params['font_style']), 0, 20) : '';

    $all = fpfi_lcp_get_all();
    if (!isset($all[$template]) || !is_array($all[$template])) {
        $all[$template] = array();
    }
    if (empty($all[$template]['font_cands']) || !is_array($all[$template]['font_cands'])) {
        $all[$template]['font_cands'] = array();
    }

    $key   = fpfi_url_key($url);
    $cands = &$all[$template]['font_cands'];

    if (isset($cands[$key])) {
        $cands[$key]['count']++;
        $cands[$key]['url']    = $url;
        $cands[$key]['family'] = $family;
        $cands[$key]['weight'] = $weight;
        $cands[$key]['style']  = $style;
    } else {
        if (count($cands) >= fpfi_lcp_max_candidates()) {
            $min_key = null;
            $min = PHP_INT_MAX;
            foreach ($cands as $k => $c) {
                if ($c['count'] < $min) {
                    $min = $c['count'];
                    $min_key = $k;
                }
            }
            if ($min_key !== null) {
                unset($cands[$min_key]);
            }
        }
        $cands[$key] = array(
            'url'    => $url,
            'family' => $family,
            'weight' => $weight,
            'style'  => $style,
            'count'  => 1,
        );
    }

    $total  = 0;
    $winner = null;
    foreach ($cands as $c) {
        $total += $c['count'];
        if (!$winner || $c['count'] > $winner['count']) {
            $winner = $c;
        }
    }
    if ($winner) {
        $all[$template]['font'] = array(
            'url'     => $winner['url'],
            'family'  => $winner['family'],
            'weight'  => $winner['weight'],
            'style'   => $winner['style'],
            'samples' => (int) $winner['count'],
            'total'   => (int) $total,
            'updated' => time(),
        );
    }
    unset($cands);

    fpfi_lcp_save_all($all);

    return new WP_REST_Response(array('ok' => true), 200);
}

/**
 * The learned font record for the current template (once it has enough samples).
 *
 * @return array|null
 */
function fpfi_current_font()
{
    $rec = fpfi_lcp_get_template(fpfi_template_key());
    if (empty($rec['font']) || empty($rec['font']['url'])) {
        return null;
    }
    $font = $rec['font'];
    if ((int) $font['samples'] < fpfi_lcp_min_samples()) {
        return null;
    }
    if (!fpfi_font_url_allowed($font['url'])) {
        return null;
    }
    return $font;
}

/**
 * Preload the measured text-LCP font from <head>.
 */
function fpfi_preload_lcp_font()
{
    $s = fpfi_get_settings();
    if (empty($s['enable_font_preload']) || empty($s['enable_preload'])) {
        return;
    }
    if (!fpfi_context_enabled()) {
        return;
    }

    $font = fpfi_current_font();
    if (!$font) {
        return;
    }

    $type = fpfi_font_mime($font['url']);

    // Fonts are always fetched in CORS mode, so the preload needs crossorigin
    // (even same-origin) or the browser downloads the file twice.
    printf(
        '<link rel="preload" as="font" href="%s"%s crossorigin>' . "\n",
        esc_url($font['url']),
        $type ? ' type="' . esc_attr($type) . '"' : ''
    );

    $GLOBALS['fpfi_tagged_count'] = (isset($GLOBALS['fpfi_tagged_count']) ? (int) $GLOBALS['fpfi_tagged_count'] : 0) + 1;
}
add_action('wp_head', 'fpfi_preload_lcp_font', 2);
