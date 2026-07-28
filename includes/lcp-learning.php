<?php
/**
 * Self-learning LCP: real-user PerformanceObserver beacon + REST collector +
 * learned/manual preload emission.
 *
 * @package fetchpriority-featured-image
 */

if (!defined('WPINC')) {
    die;
}

/* -------------------------------------------------------------------------
 * Front-end beacon
 * ---------------------------------------------------------------------- */

/**
 * Decide (per request) whether this visit should measure + report LCP.
 *
 * @return bool
 */
function fpfi_lcp_should_sample()
{
    $s = fpfi_get_settings();
    if (empty($s['enable_lcp_learning'])) {
        return false;
    }
    if (is_admin() || is_preview() || is_404()) {
        return false;
    }
    if (fpfi_template_is_off()) {
        return false;
    }
    $rate = isset($s['lcp_sample_rate']) ? max(1, min(100, (int) $s['lcp_sample_rate'])) : 20;
    if ($rate >= 100) {
        return true;
    }
    // wp_rand avoids the disabled Math.random concern and is request-scoped.
    return (wp_rand(1, 100) <= $rate);
}

function fpfi_lcp_enqueue_beacon()
{
    if (!fpfi_lcp_should_sample()) {
        return;
    }

    $handle = 'fpfi-beacon';
    wp_register_script(
        $handle,
        plugins_url('assets/js/beacon.js', FPFI_PLUGIN_FILE),
        array(),
        FETCHPRIORITY_FEATURED_IMAGE_VERSION,
        true
    );
    wp_localize_script($handle, 'FPFI_BEACON', array(
        'endpoint' => esc_url_raw(rest_url('fpfi/v1/lcp')),
        'template' => fpfi_template_key(),
    ));
    wp_enqueue_script($handle);
}
add_action('wp_enqueue_scripts', 'fpfi_lcp_enqueue_beacon');

/* -------------------------------------------------------------------------
 * REST collector
 * ---------------------------------------------------------------------- */

function fpfi_lcp_register_rest()
{
    register_rest_route('fpfi/v1', '/lcp', array(
        'methods'             => 'POST',
        'callback'            => 'fpfi_lcp_collect',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'fpfi_lcp_register_rest');

/**
 * Same-origin guard: only accept URLs hosted on this site.
 *
 * @param string $url
 * @return bool
 */
function fpfi_lcp_is_local_url($url)
{
    $host = wp_parse_url($url, PHP_URL_HOST);
    if (!$host) {
        return false;
    }
    $home = wp_parse_url(home_url(), PHP_URL_HOST);
    return $home && strcasecmp($host, $home) === 0;
}

/**
 * Collect one real-user LCP report and update the learned winner.
 *
 * @param WP_REST_Request $req
 * @return WP_REST_Response
 */
function fpfi_lcp_collect($req)
{
    $s = fpfi_get_settings();
    if (empty($s['enable_lcp_learning'])) {
        return new WP_REST_Response(array('ok' => false), 200);
    }

    $params   = $req->get_json_params();
    $template = isset($params['template']) ? sanitize_text_field($params['template']) : '';
    $url      = isset($params['url']) ? esc_url_raw($params['url']) : '';
    $is_bg    = !empty($params['is_bg']) ? 1 : 0;

    // Text LCP: record the web font used, on its own voting track.
    if (!empty($params['is_font'])) {
        return fpfi_lcp_collect_font($template, $url, $params);
    }

    $selector = isset($params['selector']) ? substr(sanitize_text_field($params['selector']), 0, 300) : '';
    $tag      = isset($params['tag']) ? sanitize_key($params['tag']) : '';
    $lcp_ms   = isset($params['lcp_ms']) ? max(0, min(120000, (int) $params['lcp_ms'])) : 0;
    $disp_w   = isset($params['disp_w']) ? max(0, (int) $params['disp_w']) : 0;
    $disp_h   = isset($params['disp_h']) ? max(0, (int) $params['disp_h']) : 0;
    $nat_w    = isset($params['nat_w']) ? max(0, (int) $params['nat_w']) : 0;
    $nat_h    = isset($params['nat_h']) ? max(0, (int) $params['nat_h']) : 0;
    $dpr      = isset($params['dpr']) ? max(1, min(4, (float) $params['dpr'])) : 1.0;

    if ($template === '' || $url === '' || !fpfi_lcp_is_local_url($url)) {
        return new WP_REST_Response(array('ok' => false), 200);
    }
    // Ignore obvious non-images (favicons, svg sprites stay allowed).
    if (!preg_match('/\.(jpe?g|png|gif|webp|avif|svg)(\?.*)?$/i', $url) && !$is_bg) {
        // Could be a <video poster> or data: URI — keep only http(s).
        if (strpos($url, 'http') !== 0) {
            return new WP_REST_Response(array('ok' => false), 200);
        }
    }

    $all = fpfi_lcp_get_all();
    if (!isset($all[$template]) || !is_array($all[$template])) {
        $all[$template] = array();
    }
    if (empty($all[$template]['candidates']) || !is_array($all[$template]['candidates'])) {
        $all[$template]['candidates'] = array();
    }

    $key = fpfi_url_key($url);
    $cands = &$all[$template]['candidates'];

    if (isset($cands[$key])) {
        $cands[$key]['count']++;
        $cands[$key]['url']      = $url;
        $cands[$key]['is_bg']    = $is_bg;
        $cands[$key]['selector'] = $selector;
    } else {
        // Bound the candidate map: drop the weakest if full.
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
            'url'      => $url,
            'is_bg'    => $is_bg,
            'selector' => $selector,
            'count'    => 1,
        );
    }

    // Recompute the winner.
    $total = 0;
    $winner = null;
    foreach ($cands as $c) {
        $total += $c['count'];
        if (!$winner || $c['count'] > $winner['count']) {
            $winner = $c;
        }
    }

    if ($winner) {
        $all[$template]['learned'] = array(
            'url'           => $winner['url'],
            'is_bg'         => (int) $winner['is_bg'],
            'selector'      => $winner['selector'],
            'attachment_id' => (int) attachment_url_to_postid(fpfi_normalize_image_url($winner['url'])),
            'samples'       => (int) $winner['count'],
            'total'         => (int) $total,
            'updated'       => time(),
        );
    }

    // Running performance + sizing stats per template (feature 2 + 4).
    if (empty($all[$template]['perf']) || !is_array($all[$template]['perf'])) {
        $all[$template]['perf'] = array('n' => 0, 'sum_ms' => 0, 'max_ms' => 0, 'last_ms' => 0);
    }
    if ($lcp_ms > 0) {
        $p = &$all[$template]['perf'];
        $p['n']       = (int) $p['n'] + 1;
        $p['sum_ms']  = (int) $p['sum_ms'] + $lcp_ms;
        $p['last_ms'] = $lcp_ms;
        if ($lcp_ms > (int) $p['max_ms']) {
            $p['max_ms'] = $lcp_ms;
        }
        unset($p);
    }
    if ($tag === 'img' && !$is_bg && $disp_w > 0 && $nat_w > 0) {
        $all[$template]['size'] = array(
            'disp_w'  => $disp_w,
            'disp_h'  => $disp_h,
            'nat_w'   => $nat_w,
            'nat_h'   => $nat_h,
            'dpr'     => $dpr,
            'updated' => time(),
        );
    }

    fpfi_lcp_save_all($all);

    return new WP_REST_Response(array('ok' => true), 200);
}

/* -------------------------------------------------------------------------
 * Learned/manual preload (supersedes the featured-image guess)
 * ---------------------------------------------------------------------- */

/**
 * Emit a <link rel=preload> for the measured LCP resource, on any template.
 *
 * Returns true if it emitted (so the featured-image fallback can stand down).
 *
 * @return bool
 */
function fpfi_preload_learned_lcp()
{
    $s = fpfi_get_settings();
    if (empty($s['enable_preload'])) {
        return false;
    }
    if (!fpfi_context_enabled()) {
        return false;
    }

    $lcp = fpfi_current_lcp();
    if (!$lcp || empty($lcp['url'])) {
        return false;
    }
    if (!empty($lcp['is_bg']) && empty($s['enable_bg_preload'])) {
        return false;
    }

    // Modern-format siblings when we can resolve the attachment.
    if (!empty($s['enable_modern_format_preload']) && !empty($lcp['attachment_id']) && empty($lcp['is_bg'])) {
        $variants = fpfi_get_modern_format_variants((int) $lcp['attachment_id']);
        if (isset($variants['image/avif'])) {
            printf(
                '<link rel="preload" as="image" href="%s" type="image/avif" fetchpriority="high">' . "\n",
                esc_url($variants['image/avif'])
            );
        }
        if (isset($variants['image/webp'])) {
            printf(
                '<link rel="preload" as="image" href="%s" type="image/webp" fetchpriority="high">' . "\n",
                esc_url($variants['image/webp'])
            );
        }
    }

    // srcset/sizes when the attachment is known and it is a foreground <img>.
    $srcset = '';
    $sizes  = '';
    if (!empty($lcp['attachment_id']) && empty($lcp['is_bg'])) {
        $srcset = wp_get_attachment_image_srcset((int) $lcp['attachment_id'], 'full');
        $sizes  = wp_get_attachment_image_sizes((int) $lcp['attachment_id'], 'full');
    }

    echo '<link rel="preload" as="image" href="' . esc_url($lcp['url']) . '"';
    if ($srcset) {
        echo ' imagesrcset="' . esc_attr($srcset) . '"';
    }
    if ($sizes) {
        echo ' imagesizes="' . esc_attr($sizes) . '"';
    }
    echo ' fetchpriority="high">' . "\n";

    $GLOBALS['fpfi_lcp_preloaded'] = true;
    $GLOBALS['fpfi_tagged_count'] = (isset($GLOBALS['fpfi_tagged_count']) ? (int) $GLOBALS['fpfi_tagged_count'] : 0) + 1;

    return true;
}
add_action('wp_head', 'fpfi_preload_learned_lcp', 1);
