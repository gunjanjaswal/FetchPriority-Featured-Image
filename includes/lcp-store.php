<?php
/**
 * Smart-LCP data store: template keys, learned/manual targets, resolver, URL matching.
 *
 * @package fetchpriority-featured-image
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Option name holding all per-template LCP data.
 */
function fpfi_lcp_option_name()
{
    return 'fpfi_lcp';
}

/**
 * Minimum samples a learned target needs before it is trusted/applied.
 *
 * @return int
 */
function fpfi_lcp_min_samples()
{
    return (int) apply_filters('fpfi_lcp_min_samples', 3);
}

/**
 * Max distinct candidate URLs stored per template (bounds option size).
 *
 * @return int
 */
function fpfi_lcp_max_candidates()
{
    return (int) apply_filters('fpfi_lcp_max_candidates', 12);
}

/**
 * Resolve a stable template key for the current request.
 *
 * @return string
 */
function fpfi_template_key()
{
    if (is_front_page() && is_home()) {
        return 'front_blog';
    }
    if (is_front_page()) {
        return 'front_page';
    }
    if (is_home()) {
        return 'blog_home';
    }
    if (is_search()) {
        return 'search';
    }
    if (is_singular()) {
        if (is_page()) {
            $tpl = get_page_template_slug();
            if ($tpl) {
                return 'page_tpl:' . sanitize_key($tpl);
            }
            return 'page';
        }
        $pt = get_post_type();
        return 'single:' . sanitize_key($pt ? $pt : 'post');
    }
    if (is_post_type_archive()) {
        $pt = get_post_type();
        return 'cpt_archive:' . sanitize_key($pt ? $pt : 'post');
    }
    if (is_category()) {
        return 'tax:category';
    }
    if (is_tag()) {
        return 'tax:post_tag';
    }
    if (is_tax()) {
        $obj = get_queried_object();
        $tax = isset($obj->taxonomy) ? $obj->taxonomy : 'tax';
        return 'tax:' . sanitize_key($tax);
    }
    if (is_author()) {
        return 'author';
    }
    if (is_date()) {
        return 'date';
    }
    if (is_archive()) {
        return 'archive';
    }
    return 'other';
}

/**
 * Human-readable label for a template key (for the admin table).
 *
 * @param string $key
 * @return string
 */
function fpfi_template_label($key)
{
    $labels = array(
        'front_blog'  => __('Front page (blog)', 'fetchpriority-featured-image'),
        'front_page'  => __('Front page (static)', 'fetchpriority-featured-image'),
        'blog_home'   => __('Blog posts index', 'fetchpriority-featured-image'),
        'search'      => __('Search results', 'fetchpriority-featured-image'),
        'page'        => __('Pages', 'fetchpriority-featured-image'),
        'author'      => __('Author archives', 'fetchpriority-featured-image'),
        'date'        => __('Date archives', 'fetchpriority-featured-image'),
        'archive'     => __('Archives', 'fetchpriority-featured-image'),
        'other'       => __('Other', 'fetchpriority-featured-image'),
        'tax:category' => __('Category archives', 'fetchpriority-featured-image'),
        'tax:post_tag' => __('Tag archives', 'fetchpriority-featured-image'),
    );
    if (isset($labels[$key])) {
        return $labels[$key];
    }
    if (strpos($key, 'single:') === 0) {
        /* translators: %s: post type */
        return sprintf(__('Single: %s', 'fetchpriority-featured-image'), substr($key, 7));
    }
    if (strpos($key, 'cpt_archive:') === 0) {
        /* translators: %s: post type */
        return sprintf(__('Archive: %s', 'fetchpriority-featured-image'), substr($key, 12));
    }
    if (strpos($key, 'page_tpl:') === 0) {
        /* translators: %s: template slug */
        return sprintf(__('Page template: %s', 'fetchpriority-featured-image'), substr($key, 9));
    }
    if (strpos($key, 'tax:') === 0) {
        /* translators: %s: taxonomy */
        return sprintf(__('Taxonomy: %s', 'fetchpriority-featured-image'), substr($key, 4));
    }
    return $key;
}

/* -------------------------------------------------------------------------
 * Storage
 * ---------------------------------------------------------------------- */

function fpfi_lcp_get_all()
{
    $data = get_option(fpfi_lcp_option_name(), array());
    return is_array($data) ? $data : array();
}

function fpfi_lcp_save_all($data)
{
    update_option(fpfi_lcp_option_name(), $data, false);
}

/**
 * Get the stored record for one template key.
 *
 * @param string $key
 * @return array
 */
function fpfi_lcp_get_template($key)
{
    $all = fpfi_lcp_get_all();
    return isset($all[$key]) && is_array($all[$key]) ? $all[$key] : array();
}

/**
 * Persist the mode for a template (auto|learned|manual|off).
 *
 * @param string $key
 * @param string $mode
 */
function fpfi_lcp_set_mode($key, $mode)
{
    $allowed = array('auto', 'learned', 'manual', 'off');
    if (!in_array($mode, $allowed, true)) {
        $mode = 'auto';
    }
    $all = fpfi_lcp_get_all();
    if (!isset($all[$key]) || !is_array($all[$key])) {
        $all[$key] = array();
    }
    $all[$key]['mode'] = $mode;
    fpfi_lcp_save_all($all);
}

/**
 * Persist a manual LCP override for a template.
 *
 * @param string $key
 * @param array  $target  [url,is_bg,selector]
 */
function fpfi_lcp_set_manual($key, $target)
{
    $all = fpfi_lcp_get_all();
    if (!isset($all[$key]) || !is_array($all[$key])) {
        $all[$key] = array();
    }
    $all[$key]['manual'] = array(
        'url'           => isset($target['url']) ? esc_url_raw($target['url']) : '',
        'is_bg'         => !empty($target['is_bg']) ? 1 : 0,
        'selector'      => isset($target['selector']) ? substr((string) $target['selector'], 0, 300) : '',
        'attachment_id' => isset($target['url']) ? (int) attachment_url_to_postid(fpfi_normalize_image_url($target['url'])) : 0,
        'updated'       => time(),
    );
    $all[$key]['mode'] = 'manual';
    fpfi_lcp_save_all($all);
}

/**
 * Clear all learned data (keeps manual overrides untouched unless $hard).
 *
 * @param bool $hard When true, remove the whole option.
 */
function fpfi_lcp_reset_learned($hard = false)
{
    if ($hard) {
        delete_option(fpfi_lcp_option_name());
        return;
    }
    $all = fpfi_lcp_get_all();
    foreach ($all as $key => $rec) {
        unset($all[$key]['learned'], $all[$key]['candidates']);
    }
    fpfi_lcp_save_all($all);
}

/* -------------------------------------------------------------------------
 * URL normalization + matching
 * ---------------------------------------------------------------------- */

/**
 * Strip query string and WordPress -WxH size suffix from an image URL.
 *
 * @param string $url
 * @return string
 */
function fpfi_normalize_image_url($url)
{
    $url = (string) $url;
    $hash = strpos($url, '#');
    if ($hash !== false) {
        $url = substr($url, 0, $hash);
    }
    $q = strpos($url, '?');
    if ($q !== false) {
        $url = substr($url, 0, $q);
    }
    return preg_replace('/-\d+x\d+(?=\.[a-zA-Z0-9]+$)/', '', $url);
}

/**
 * Comparison key for a URL: lowercased normalized basename.
 *
 * @param string $url
 * @return string
 */
function fpfi_url_key($url)
{
    $n = fpfi_normalize_image_url($url);
    return strtolower(wp_basename($n));
}

/**
 * Does an <img> HTML blob reference the given target URL (in src or srcset)?
 *
 * @param string $img_html
 * @param string $target_url
 * @return bool
 */
function fpfi_img_html_matches_url($img_html, $target_url)
{
    if (empty($target_url)) {
        return false;
    }
    $target = fpfi_url_key($target_url);
    if ($target === '') {
        return false;
    }
    if (preg_match_all('/(?:src|srcset|data-src)=["\']([^"\']+)["\']/i', $img_html, $m)) {
        foreach ($m[1] as $attr_val) {
            foreach (preg_split('/[,\s]+/', $attr_val) as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '' || preg_match('/^\d+(w|x)$/', $candidate)) {
                    continue;
                }
                if (fpfi_url_key($candidate) === $target) {
                    return true;
                }
            }
        }
    }
    return false;
}

/* -------------------------------------------------------------------------
 * Performance + sizing analysis (features 2 + 4)
 * ---------------------------------------------------------------------- */

/**
 * Average measured LCP time (ms) for a template record, or null.
 *
 * @param array $rec
 * @return int|null
 */
function fpfi_lcp_avg_ms($rec)
{
    if (empty($rec['perf']['n'])) {
        return null;
    }
    return (int) round($rec['perf']['sum_ms'] / max(1, (int) $rec['perf']['n']));
}

/**
 * LCP rating bucket from a millisecond value (good <=2500, ni <=4000).
 *
 * @param int $ms
 * @return string good|ni|poor
 */
function fpfi_lcp_ms_rating($ms)
{
    if ($ms <= 2500) {
        return 'good';
    }
    if ($ms <= 4000) {
        return 'ni';
    }
    return 'poor';
}

/**
 * Oversize analysis for a template's measured LCP image.
 *
 * Returns null when there is not enough data, otherwise:
 * [factor, nat_w, target_w, disp_w, dpr, wasteful(bool)].
 *
 * @param array $rec
 * @return array|null
 */
function fpfi_lcp_oversize($rec)
{
    if (empty($rec['size']['nat_w']) || empty($rec['size']['disp_w'])) {
        return null;
    }
    $nat_w  = (int) $rec['size']['nat_w'];
    $disp_w = (int) $rec['size']['disp_w'];
    $dpr    = isset($rec['size']['dpr']) ? (float) $rec['size']['dpr'] : 1.0;
    $target = (int) ceil($disp_w * $dpr);
    if ($target < 1) {
        return null;
    }
    $factor = $nat_w / $target;
    return array(
        'factor'   => $factor,
        'nat_w'    => $nat_w,
        'target_w' => $target,
        'disp_w'   => $disp_w,
        'dpr'      => $dpr,
        // Flag only meaningful over-resolution (>= 1.5x and at least 400px waste).
        'wasteful' => ($factor >= 1.5 && ($nat_w - $target) >= 400),
    );
}

/* -------------------------------------------------------------------------
 * Effective-LCP resolver
 * ---------------------------------------------------------------------- */

/**
 * Is the current template explicitly turned off?
 *
 * @return bool
 */
function fpfi_template_is_off()
{
    $rec = fpfi_lcp_get_template(fpfi_template_key());
    return isset($rec['mode']) && $rec['mode'] === 'off';
}

/**
 * Resolve the effective LCP target for a template, honoring mode.
 *
 * Returns null when there is no usable target (caller falls back to the
 * featured-image guess).
 *
 * @param string|null $key
 * @return array|null [url,is_bg,attachment_id,selector,source]
 */
function fpfi_effective_lcp($key = null)
{
    $key = $key ? $key : fpfi_template_key();
    $rec = fpfi_lcp_get_template($key);
    $mode = isset($rec['mode']) ? $rec['mode'] : 'auto';

    if ($mode === 'off') {
        return null;
    }

    // Manual override wins when present (and mode allows it).
    if (($mode === 'manual' || $mode === 'auto') && !empty($rec['manual']['url'])) {
        $m = $rec['manual'];
        return array(
            'url'           => $m['url'],
            'is_bg'         => !empty($m['is_bg']),
            'attachment_id' => isset($m['attachment_id']) ? (int) $m['attachment_id'] : 0,
            'selector'      => isset($m['selector']) ? $m['selector'] : '',
            'source'        => 'manual',
        );
    }
    if ($mode === 'manual') {
        return null; // forced manual but none set
    }

    // Learned target (auto or learned mode).
    if (($mode === 'auto' || $mode === 'learned') && !empty($rec['learned']['url'])) {
        $l = $rec['learned'];
        if ((int) (isset($l['samples']) ? $l['samples'] : 0) >= fpfi_lcp_min_samples()) {
            return array(
                'url'           => $l['url'],
                'is_bg'         => !empty($l['is_bg']),
                'attachment_id' => isset($l['attachment_id']) ? (int) $l['attachment_id'] : 0,
                'selector'      => isset($l['selector']) ? $l['selector'] : '',
                'source'        => 'learned',
            );
        }
    }

    return null;
}

/**
 * Memoized effective LCP for the current request.
 *
 * @return array|null
 */
function fpfi_current_lcp()
{
    static $cache = false;
    if ($cache === false) {
        $cache = fpfi_effective_lcp();
    }
    return $cache;
}

/**
 * Does an <img> blob match the current request's effective LCP target?
 *
 * @param string $img_html
 * @return bool
 */
function fpfi_lcp_matches_html($img_html)
{
    $lcp = fpfi_current_lcp();
    if (!$lcp || !empty($lcp['is_bg']) || empty($lcp['url'])) {
        return false;
    }
    return fpfi_img_html_matches_url($img_html, $lcp['url']);
}

/**
 * Does an attachment ID match the current request's effective LCP target?
 *
 * @param int $attachment_id
 * @return bool
 */
function fpfi_lcp_matches_id($attachment_id)
{
    $lcp = fpfi_current_lcp();
    if (!$lcp || !empty($lcp['is_bg']) || empty($attachment_id)) {
        return false;
    }
    if (!empty($lcp['attachment_id']) && (int) $lcp['attachment_id'] === (int) $attachment_id) {
        return true;
    }
    $url = wp_get_attachment_url($attachment_id);
    return $url ? (fpfi_url_key($url) === fpfi_url_key($lcp['url'])) : false;
}
