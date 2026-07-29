<?php
/**
 * Plugin Name: FetchPriority Featured Image
 * Plugin URI: https://wordpress.org/plugins/fetchpriority-featured-image/
 * Description: Self-learning LCP optimizer. Measures the real Largest Contentful Paint element from your visitors and auto-applies fetchpriority="high" + preload to it (foreground or CSS background), with a visual LCP picker, per-template control, AVIF/WebP detection, and a built-in Core Web Vitals before/after report.
 * Version: 1.6.0
 * Author: Gunjan Jaswal
 * Author URI: https://gunjanjaswal.me
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fetchpriority-featured-image
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Tested up to: 7.0
 */

if (!defined('WPINC')) {
    die;
}

define('FETCHPRIORITY_FEATURED_IMAGE_VERSION', '1.6.0');
define('FPFI_PLUGIN_FILE', __FILE__);

/* -------------------------------------------------------------------------
 * Module includes
 * ---------------------------------------------------------------------- */
require_once plugin_dir_path(__FILE__) . 'includes/lcp-store.php';
require_once plugin_dir_path(__FILE__) . 'includes/lcp-learning.php';
require_once plugin_dir_path(__FILE__) . 'includes/crux.php';
require_once plugin_dir_path(__FILE__) . 'includes/psi.php';
require_once plugin_dir_path(__FILE__) . 'includes/picker.php';
require_once plugin_dir_path(__FILE__) . 'includes/fonts.php';
require_once plugin_dir_path(__FILE__) . 'includes/preconnect.php';
require_once plugin_dir_path(__FILE__) . 'includes/woocommerce.php';
require_once plugin_dir_path(__FILE__) . 'includes/porting.php';
require_once plugin_dir_path(__FILE__) . 'includes/dashboard.php';

if (defined('WP_CLI') && WP_CLI) {
    require_once plugin_dir_path(__FILE__) . 'includes/cli.php';
}

/**
 * Load translations.
 *
 * Plugins hosted on WordPress.org get their translate.wordpress.org strings
 * loaded automatically, but this also picks up any hand-installed .mo files in
 * wp-content/languages/plugins/ or this plugin's /languages folder. Hooked on
 * init so it never fires before translations are ready (avoids the WP 6.7+
 * just-in-time loading notice).
 */
function fpfi_load_textdomain()
{
    load_plugin_textdomain(
        'fetchpriority-featured-image',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('init', 'fpfi_load_textdomain');

/**
 * Plugin activation hook.
 */
function fpfi_activate()
{
    delete_option('fpfi_coffee_notice_dismissed');
}
register_activation_hook(__FILE__, 'fpfi_activate');

/* -------------------------------------------------------------------------
 * Settings storage
 * ---------------------------------------------------------------------- */

function fpfi_default_settings()
{
    return array(
        // Contexts
        'enable_singular'              => 1,
        'enable_home'                  => 1,
        'enable_archive'               => 1,
        'enable_search'                => 1,
        // High budget
        'first_n_on_archive'           => 1,
        // Preload
        'enable_preload'               => 1,
        'enable_modern_format_preload' => 1,
        // Below-fold low
        'enable_low_below_fold'        => 0,
        // Exclusions
        'exclude_avatars'              => 1,
        // Theme preset
        'theme_preset'                 => 'auto',
        // Smart LCP (self-learning)
        'enable_lcp_learning'          => 1,
        'lcp_sample_rate'              => 20,
        'enable_bg_preload'            => 1,
        'enable_loading_optim'         => 1,
        // Font / text-LCP preload
        'enable_font_preload'          => 1,
        // Cross-origin (CDN) preconnect
        'enable_preconnect'            => 1,
        // WooCommerce
        'enable_woocommerce'           => 1,
        // Core Web Vitals (CrUX)
        'crux_api_key'                 => '',
        // Debug
        'enable_debug_badge'           => 0,
    );
}

function fpfi_get_settings()
{
    $stored = get_option('fpfi_settings', array());
    if (!is_array($stored)) {
        $stored = array();
    }
    return wp_parse_args($stored, fpfi_default_settings());
}

function fpfi_sanitize_settings($input)
{
    $clean = array();
    $bool_keys = array(
        'enable_singular', 'enable_home', 'enable_archive', 'enable_search',
        'enable_preload', 'enable_modern_format_preload',
        'enable_low_below_fold', 'exclude_avatars', 'enable_debug_badge',
        'enable_lcp_learning', 'enable_bg_preload', 'enable_loading_optim',
        'enable_font_preload', 'enable_preconnect', 'enable_woocommerce',
    );
    foreach ($bool_keys as $key) {
        $clean[$key] = !empty($input[$key]) ? 1 : 0;
    }
    $first_n = isset($input['first_n_on_archive']) ? (int) $input['first_n_on_archive'] : 1;
    $clean['first_n_on_archive'] = max(1, min(20, $first_n));

    $rate = isset($input['lcp_sample_rate']) ? (int) $input['lcp_sample_rate'] : 20;
    $clean['lcp_sample_rate'] = max(1, min(100, $rate));

    $clean['crux_api_key'] = isset($input['crux_api_key'])
        ? sanitize_text_field($input['crux_api_key'])
        : '';

    $allowed_presets = array('auto', 'astra', 'generatepress', 'kadence', 'divi', 'elementor', 'none');
    $preset = isset($input['theme_preset']) ? (string) $input['theme_preset'] : 'auto';
    $clean['theme_preset'] = in_array($preset, $allowed_presets, true) ? $preset : 'auto';

    return $clean;
}

function fpfi_register_settings()
{
    register_setting('fpfi_settings_group', 'fpfi_settings', array(
        'type'              => 'array',
        'sanitize_callback' => 'fpfi_sanitize_settings',
        'default'           => fpfi_default_settings(),
    ));
}
add_action('admin_init', 'fpfi_register_settings');

/* -------------------------------------------------------------------------
 * Theme presets
 * ---------------------------------------------------------------------- */

/**
 * Detect a known theme from active template/stylesheet.
 *
 * @return string Preset key (astra|generatepress|kadence|divi|elementor|none).
 */
function fpfi_detect_theme()
{
    $candidates = array(get_template(), get_stylesheet());
    $map = array(
        'astra'         => array('astra'),
        'generatepress' => array('generatepress', 'gp-premium'),
        'kadence'       => array('kadence'),
        'divi'          => array('Divi', 'divi'),
        'elementor'     => array('hello-elementor'),
    );
    foreach ($map as $key => $slugs) {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $slugs, true)) {
                return $key;
            }
        }
    }
    return 'none';
}

/**
 * Resolve effective theme preset (handles 'auto').
 *
 * @return string
 */
function fpfi_active_preset()
{
    $s = fpfi_get_settings();
    $preset = isset($s['theme_preset']) ? $s['theme_preset'] : 'auto';
    return $preset === 'auto' ? fpfi_detect_theme() : $preset;
}

/**
 * Class names whose images should NOT be tagged.
 *
 * @return string[]
 */
function fpfi_excluded_classes()
{
    $s = fpfi_get_settings();
    $classes = array('site-logo', 'custom-logo');

    if (!empty($s['exclude_avatars'])) {
        $classes[] = 'avatar';
        $classes[] = 'gravatar';
    }

    $preset_extras = array(
        'astra'         => array('ast-custom-logo', 'ast-builder-logo', 'astra-logo-svg'),
        'generatepress' => array('header-image', 'site-logo'),
        'kadence'       => array('kadence-header-logo', 'site-branding-icon'),
        'divi'          => array('et_pb_image_logo', 'et-logo'),
        'elementor'     => array('elementor-logo-item'),
    );
    $preset = fpfi_active_preset();
    if (isset($preset_extras[$preset])) {
        $classes = array_merge($classes, $preset_extras[$preset]);
    }

    /**
     * Filter the list of CSS class names that should be skipped.
     *
     * @param string[] $classes Class names.
     * @param string   $preset  Active theme preset.
     */
    return apply_filters('fpfi_excluded_classes', array_values(array_unique($classes)), $preset);
}

function fpfi_html_has_excluded_class($html)
{
    foreach (fpfi_excluded_classes() as $cls) {
        if (preg_match('/class=["\'][^"\']*\b' . preg_quote($cls, '/') . '\b[^"\']*["\']/i', $html)) {
            return true;
        }
    }
    return false;
}

function fpfi_attr_has_excluded_class($attr)
{
    if (empty($attr['class'])) {
        return false;
    }
    foreach (fpfi_excluded_classes() as $cls) {
        if (stripos($attr['class'], $cls) !== false) {
            return true;
        }
    }
    return false;
}

function fpfi_attr_is_gravatar($attr)
{
    if (!empty($attr['src']) && stripos($attr['src'], 'gravatar.com') !== false) {
        return true;
    }
    return false;
}

function fpfi_html_is_gravatar($html)
{
    return (bool) preg_match('/src=["\'][^"\']*gravatar\.com/i', $html);
}

/* -------------------------------------------------------------------------
 * Eligibility helpers
 * ---------------------------------------------------------------------- */

function fpfi_context_enabled()
{
    $s = fpfi_get_settings();
    // Per-template kill switch (Smart LCP "Off" mode).
    if (fpfi_template_is_off()) {
        return false;
    }
    if (is_singular()) {
        return !empty($s['enable_singular']);
    }
    if (is_search()) {
        return !empty($s['enable_search']);
    }
    if (is_home()) {
        return !empty($s['enable_home']);
    }
    if (is_archive()) {
        return !empty($s['enable_archive']);
    }
    return false;
}

/**
 * Decide priority value for the current image: 'high', 'low', or null (skip).
 *
 * @return string|null
 */
function fpfi_decide_priority()
{
    $s = fpfi_get_settings();
    if (!fpfi_context_enabled()) {
        return null;
    }

    $high_count = isset($GLOBALS['fpfi_high_count']) ? (int) $GLOBALS['fpfi_high_count'] : 0;
    $high_budget = is_singular() ? 1 : max(1, (int) $s['first_n_on_archive']);

    if (!is_singular()) {
        $index = isset($GLOBALS['fpfi_archive_post_index']) ? (int) $GLOBALS['fpfi_archive_post_index'] : 1;
        if ($index > (int) $s['first_n_on_archive']) {
            return !empty($s['enable_low_below_fold']) ? 'low' : null;
        }
    }

    if ($high_count < $high_budget) {
        return 'high';
    }

    return !empty($s['enable_low_below_fold']) ? 'low' : null;
}

/**
 * Force a loading attribute on an <img> blob (eager for LCP, lazy for below-fold).
 *
 * @param string $html  Image HTML.
 * @param string $value eager|lazy
 * @return string
 */
function fpfi_set_loading_attr($html, $value)
{
    if (preg_match('/\sloading\s*=\s*("|\').*?\1/i', $html)) {
        return preg_replace('/\sloading\s*=\s*("|\').*?\1/i', ' loading="' . $value . '"', $html, 1);
    }
    return preg_replace('/<img\b/i', '<img loading="' . $value . '"', $html, 1);
}

/**
 * Apply loading optimization to an image given its resolved priority.
 *
 * high -> eager (and drop any lazy that would block the LCP);
 * low  -> lazy.
 *
 * @param string $html
 * @param string $priority high|low
 * @return string
 */
function fpfi_apply_loading_optim($html, $priority)
{
    $s = fpfi_get_settings();
    if (empty($s['enable_loading_optim'])) {
        return $html;
    }
    if ($priority === 'high') {
        return fpfi_set_loading_attr($html, 'eager');
    }
    if ($priority === 'low') {
        return fpfi_set_loading_attr($html, 'lazy');
    }
    return $html;
}

function fpfi_record_priority_applied($priority)
{
    if ($priority === 'high') {
        $GLOBALS['fpfi_high_count'] = (isset($GLOBALS['fpfi_high_count']) ? (int) $GLOBALS['fpfi_high_count'] : 0) + 1;
    }
    $GLOBALS['fpfi_tagged_count'] = (isset($GLOBALS['fpfi_tagged_count']) ? (int) $GLOBALS['fpfi_tagged_count'] : 0) + 1;
}

/**
 * Track post index inside the main Loop for archive-style contexts.
 *
 * @param WP_Post  $post  Current post object (unused).
 * @param WP_Query $query Current WP_Query instance.
 */
function fpfi_track_loop_post($post, $query = null)
{
    if (is_singular()) {
        return;
    }
    if ($query instanceof WP_Query && !$query->is_main_query()) {
        return;
    }
    $GLOBALS['fpfi_archive_post_index'] = (isset($GLOBALS['fpfi_archive_post_index']) ? (int) $GLOBALS['fpfi_archive_post_index'] : 0) + 1;
}
add_action('the_post', 'fpfi_track_loop_post', 10, 2);

/* -------------------------------------------------------------------------
 * Filters that add fetchpriority="high"|"low"
 * ---------------------------------------------------------------------- */

function fpfi_add_fetchpriority_to_featured_image($html, $post_id, $post_thumbnail_id, $size, $attr)
{
    if (fpfi_html_has_excluded_class($html) || fpfi_html_is_gravatar($html)) {
        return $html;
    }
    if (strpos($html, 'fetchpriority=') !== false) {
        return $html;
    }

    // Measured/manual LCP wins: force high regardless of budget.
    if (fpfi_context_enabled() && (fpfi_lcp_matches_id($post_thumbnail_id) || fpfi_lcp_matches_html($html))) {
        $priority = 'high';
    } else {
        $priority = fpfi_decide_priority();
    }
    if ($priority === null) {
        return $html;
    }

    $html = str_replace('<img ', '<img fetchpriority="' . esc_attr($priority) . '" ', $html);
    $html = fpfi_apply_loading_optim($html, $priority);
    fpfi_record_priority_applied($priority);

    return $html;
}
add_filter('post_thumbnail_html', 'fpfi_add_fetchpriority_to_featured_image', 10, 5);

function fpfi_add_fetchpriority_to_attachment_attributes($attr, $attachment, $size)
{
    if (fpfi_attr_has_excluded_class($attr) || fpfi_attr_is_gravatar($attr)) {
        return $attr;
    }
    if (isset($attr['fetchpriority'])) {
        return $attr;
    }

    $s = fpfi_get_settings();
    $att_id = is_object($attachment) && isset($attachment->ID) ? (int) $attachment->ID : 0;
    if (fpfi_context_enabled() && $att_id && fpfi_lcp_matches_id($att_id)) {
        $priority = 'high';
    } else {
        $priority = fpfi_decide_priority();
    }
    if ($priority === null) {
        return $attr;
    }

    $attr['fetchpriority'] = $priority;

    if (!empty($s['enable_loading_optim'])) {
        if ($priority === 'high') {
            $attr['loading'] = 'eager';
        } elseif ($priority === 'low') {
            $attr['loading'] = 'lazy';
        }
    }

    fpfi_record_priority_applied($priority);

    return $attr;
}
add_filter('wp_get_attachment_image_attributes', 'fpfi_add_fetchpriority_to_attachment_attributes', 10, 3);

function fpfi_add_fetchpriority_to_content($content)
{
    if (!fpfi_context_enabled()) {
        return $content;
    }

    // Walk images in order; stop after we've consumed the high budget + (optionally) tagged below-fold low.
    return preg_replace_callback(
        '/<img[^>]*>/i',
        function ($matches) {
            $img = $matches[0];

            if (fpfi_html_has_excluded_class($img) || fpfi_html_is_gravatar($img)) {
                return $img;
            }
            if (strpos($img, 'fetchpriority=') !== false) {
                return $img;
            }

            if (fpfi_lcp_matches_html($img)) {
                $priority = 'high';
            } else {
                $priority = fpfi_decide_priority();
            }
            if ($priority === null) {
                return $img;
            }

            fpfi_record_priority_applied($priority);
            $img = str_replace('<img ', '<img fetchpriority="' . esc_attr($priority) . '" ', $img);
            return fpfi_apply_loading_optim($img, $priority);
        },
        $content
    );
}
add_filter('the_content', 'fpfi_add_fetchpriority_to_content', 999);

/**
 * Reset per-request flags on each page load.
 */
function fpfi_reset_request_flags()
{
    unset($GLOBALS['fpfi_archive_post_index']);
    unset($GLOBALS['fpfi_tagged_count']);
    unset($GLOBALS['fpfi_high_count']);
    unset($GLOBALS['fpfi_lcp_preloaded']);
    unset($GLOBALS['fpfi_wc_gallery_done']);
}
add_action('template_redirect', 'fpfi_reset_request_flags');

/* -------------------------------------------------------------------------
 * <link rel="preload"> for the hero featured image (with AVIF/WebP)
 * ---------------------------------------------------------------------- */

/**
 * Detect sibling modern-format variants for an attachment.
 *
 * Returns map of mime => URL when a sibling .avif / .webp file exists on disk.
 *
 * @param int $attachment_id
 * @return array<string,string>
 */
function fpfi_get_modern_format_variants($attachment_id)
{
    $variants = array();

    $file = get_attached_file($attachment_id);
    $url  = wp_get_attachment_url($attachment_id);
    if (!$file || !$url || !file_exists($file)) {
        return $variants;
    }

    $url_no_ext  = preg_replace('/\.[^.\/]+$/', '', $url);
    $path_no_ext = preg_replace('/\.[^.\/]+$/', '', $file);

    $checks = array(
        'image/avif' => 'avif',
        'image/webp' => 'webp',
    );

    foreach ($checks as $mime => $ext) {
        // Pattern A: file.avif (extension replaced) — common for Imagify, ShortPixel "replace" mode.
        if (file_exists($path_no_ext . '.' . $ext)) {
            $variants[$mime] = $url_no_ext . '.' . $ext;
            continue;
        }
        // Pattern B: file.jpg.avif (extension appended) — common for many WebP converters.
        if (file_exists($file . '.' . $ext)) {
            $variants[$mime] = $url . '.' . $ext;
        }
    }

    /**
     * Filter the modern format variants resolved for an attachment.
     *
     * @param array $variants Map of mime => URL.
     * @param int   $attachment_id Attachment ID.
     */
    return apply_filters('fpfi_modern_format_variants', $variants, $attachment_id);
}

function fpfi_preload_featured_image()
{
    if (!is_singular()) {
        return;
    }

    // The measured/manual LCP preload already fired for this request.
    if (!empty($GLOBALS['fpfi_lcp_preloaded'])) {
        return;
    }

    $s = fpfi_get_settings();
    if (empty($s['enable_preload']) || empty($s['enable_singular'])) {
        return;
    }

    $post_id = get_queried_object_id();
    if (!$post_id || !has_post_thumbnail($post_id)) {
        return;
    }

    $thumb_id = get_post_thumbnail_id($post_id);
    if (!$thumb_id) {
        return;
    }

    $img = wp_get_attachment_image_src($thumb_id, 'full');
    if (!$img || empty($img[0])) {
        return;
    }

    $srcset = wp_get_attachment_image_srcset($thumb_id, 'full');
    $sizes  = wp_get_attachment_image_sizes($thumb_id, 'full');

    $variants = !empty($s['enable_modern_format_preload']) ? fpfi_get_modern_format_variants($thumb_id) : array();

    // Emit modern-format preloads first; browsers ignore unsupported types.
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

    echo '<link rel="preload" as="image" href="' . esc_url($img[0]) . '"';
    if ($srcset) {
        echo ' imagesrcset="' . esc_attr($srcset) . '"';
    }
    if ($sizes) {
        echo ' imagesizes="' . esc_attr($sizes) . '"';
    }
    echo ' fetchpriority="high">' . "\n";

    // Count this as a single tag operation for the debug badge.
    $GLOBALS['fpfi_tagged_count'] = (isset($GLOBALS['fpfi_tagged_count']) ? (int) $GLOBALS['fpfi_tagged_count'] : 0) + 1;
}
add_action('wp_head', 'fpfi_preload_featured_image', 1);

/* -------------------------------------------------------------------------
 * Admin-bar debug badge
 * ---------------------------------------------------------------------- */

function fpfi_admin_bar_badge($wp_admin_bar)
{
    if (is_admin()) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    $s = fpfi_get_settings();
    if (empty($s['enable_debug_badge'])) {
        return;
    }

    $tagged = isset($GLOBALS['fpfi_tagged_count']) ? (int) $GLOBALS['fpfi_tagged_count'] : 0;
    $high   = isset($GLOBALS['fpfi_high_count']) ? (int) $GLOBALS['fpfi_high_count'] : 0;

    $wp_admin_bar->add_node(array(
        'id'    => 'fpfi-debug',
        'title' => sprintf(
            /* translators: 1: total tagged images, 2: number tagged with priority=high */
            esc_html__('FetchPriority: %1$d (high: %2$d)', 'fetchpriority-featured-image'),
            $tagged,
            $high
        ),
        'href'  => esc_url(admin_url('options-general.php?page=fetchpriority-featured-image')),
        'meta'  => array(
            'title' => esc_attr__('Images tagged with fetchpriority on this page (click to open settings).', 'fetchpriority-featured-image'),
        ),
    ));
}
add_action('admin_bar_menu', 'fpfi_admin_bar_badge', 999);

/* -------------------------------------------------------------------------
 * Settings page UI
 * ---------------------------------------------------------------------- */

function fpfi_settings_menu()
{
    add_options_page(
        __('FetchPriority Featured Image', 'fetchpriority-featured-image'),
        __('FetchPriority', 'fetchpriority-featured-image'),
        'manage_options',
        'fetchpriority-featured-image',
        'fpfi_render_settings_page'
    );
}
add_action('admin_menu', 'fpfi_settings_menu');

function fpfi_render_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $s = fpfi_get_settings();
    $detected = fpfi_detect_theme();
    $preset_labels = array(
        'auto'          => __('Auto-detect', 'fetchpriority-featured-image'),
        'astra'         => 'Astra',
        'generatepress' => 'GeneratePress',
        'kadence'       => 'Kadence',
        'divi'          => 'Divi',
        'elementor'     => 'Hello Elementor',
        'none'          => __('Generic (no theme extras)', 'fetchpriority-featured-image'),
    );
    ?>
    <div class="wrap fpfi-admin">

        <div class="fpfi-header">
            <h1>
                <span class="dashicons dashicons-superhero"></span>
                <?php esc_html_e('FetchPriority Featured Image', 'fetchpriority-featured-image'); ?>
                <span class="fpfi-version-pill">v<?php echo esc_html(FETCHPRIORITY_FEATURED_IMAGE_VERSION); ?></span>
            </h1>
            <p><?php esc_html_e('Self-learning LCP optimizer — it measures the real Largest Contentful Paint element from your visitors and automatically prioritises it. No guessing.', 'fetchpriority-featured-image'); ?></p>
            <div class="fpfi-header-links">
                <a href="https://ko-fi.com/gunjanjaswal" target="_blank"><span class="dashicons dashicons-coffee"></span><?php esc_html_e('Support on Ko-fi', 'fetchpriority-featured-image'); ?></a>
                <a href="https://github.com/gunjanjaswal/FetchPriority-Featured-Image" target="_blank"><span class="dashicons dashicons-editor-code"></span><?php esc_html_e('GitHub', 'fetchpriority-featured-image'); ?></a>
                <a href="https://wordpress.org/support/plugin/fetchpriority-featured-image/" target="_blank"><span class="dashicons dashicons-sos"></span><?php esc_html_e('Support', 'fetchpriority-featured-image'); ?></a>
            </div>
        </div>

        <nav class="fpfi-tabs">
            <button type="button" class="fpfi-tab is-active" data-tab="smart"><span class="dashicons dashicons-chart-line"></span><?php esc_html_e('Smart LCP', 'fetchpriority-featured-image'); ?></button>
            <button type="button" class="fpfi-tab" data-tab="targeting"><span class="dashicons dashicons-filter"></span><?php esc_html_e('Targeting', 'fetchpriority-featured-image'); ?></button>
            <button type="button" class="fpfi-tab" data-tab="preload"><span class="dashicons dashicons-performance"></span><?php esc_html_e('Preload', 'fetchpriority-featured-image'); ?></button>
            <button type="button" class="fpfi-tab" data-tab="diagnostics"><span class="dashicons dashicons-chart-area"></span><?php esc_html_e('Diagnostics', 'fetchpriority-featured-image'); ?></button>
        </nav>

        <form method="post" action="options.php">
            <?php settings_fields('fpfi_settings_group'); ?>

            <!-- =============== SMART LCP =============== -->
            <div class="fpfi-panel is-active" data-panel="smart">

                <div class="fpfi-card">
                    <div class="fpfi-card-head">
                        <span class="dashicons dashicons-superhero-alt"></span>
                        <h2><?php esc_html_e('Self-learning LCP', 'fetchpriority-featured-image'); ?></h2>
                        <span class="fpfi-card-sub"><?php esc_html_e('The headline feature', 'fetchpriority-featured-image'); ?></span>
                    </div>
                    <div class="fpfi-card-body">
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e('Learn real LCP', 'fetchpriority-featured-image'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="fpfi_settings[enable_lcp_learning]" value="1" <?php checked($s['enable_lcp_learning']); ?>> <?php esc_html_e('Measure the real Largest Contentful Paint element from visitors and auto-prioritise it.', 'fetchpriority-featured-image'); ?></label>
                                    <p class="description"><?php esc_html_e('A tiny script reports the actual LCP image per template. Once enough samples are collected, the plugin preloads and tags that exact image (foreground or CSS background) instead of guessing — and self-corrects as your site changes.', 'fetchpriority-featured-image'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="fpfi_sample_rate"><?php esc_html_e('Sample rate', 'fetchpriority-featured-image'); ?></label></th>
                                <td>
                                    <input type="number" id="fpfi_sample_rate" name="fpfi_settings[lcp_sample_rate]" value="<?php echo esc_attr($s['lcp_sample_rate']); ?>" min="1" max="100" class="small-text"> %
                                    <p class="description"><?php esc_html_e('Percentage of page views that load the measurement script. Lower = lighter; higher = faster learning. Default: 20%.', 'fetchpriority-featured-image'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Background-image preload', 'fetchpriority-featured-image'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="fpfi_settings[enable_bg_preload]" value="1" <?php checked($s['enable_bg_preload']); ?>> <?php esc_html_e('Preload the hero when the LCP is a CSS background-image (sliders, hero sections).', 'fetchpriority-featured-image'); ?></label>
                                    <p class="description"><?php esc_html_e('CSS background heroes are a blind spot for most performance plugins — the browser discovers them late. This preloads the measured/manual background URL.', 'fetchpriority-featured-image'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Loading optimization', 'fetchpriority-featured-image'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="fpfi_settings[enable_loading_optim]" value="1" <?php checked($s['enable_loading_optim']); ?>> <?php esc_html_e('Force loading="eager" on the LCP image and loading="lazy" on below-fold images.', 'fetchpriority-featured-image'); ?></label>
                                    <p class="description"><?php esc_html_e('Stops native lazy-loading from delaying your hero, and defers everything below it.', 'fetchpriority-featured-image'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Visual picker', 'fetchpriority-featured-image'); ?></th>
                                <td>
                                    <p class="description"><?php esc_html_e('Prefer to choose manually? On the front end, open the admin-bar menu and click "Pick LCP element", then click your hero image. It is saved as a manual override for that template.', 'fetchpriority-featured-image'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="fpfi-card">
                    <div class="fpfi-card-head">
                        <span class="dashicons dashicons-images-alt2"></span>
                        <h2><?php esc_html_e('Per-template LCP targets', 'fetchpriority-featured-image'); ?></h2>
                    </div>
                    <div class="fpfi-card-body">
                        <p class="description"><?php esc_html_e('What the plugin will prioritise on each template it has seen. "Auto" uses a manual pick if set, else the learned LCP, else the featured-image guess.', 'fetchpriority-featured-image'); ?></p>
                        <?php fpfi_render_lcp_table(); ?>
                        <p>
                            <button type="button" class="button" id="fpfi-lcp-reset"><?php esc_html_e('Clear learned data', 'fetchpriority-featured-image'); ?></button>
                            <span id="fpfi-lcp-status" style="margin-left:8px;"></span>
                        </p>
                    </div>
                </div>

            </div>

            <!-- =============== TARGETING =============== -->
            <div class="fpfi-panel" data-panel="targeting">

                <div class="fpfi-card">
                    <div class="fpfi-card-head">
                        <span class="dashicons dashicons-visibility"></span>
                        <h2><?php esc_html_e('Where to apply', 'fetchpriority-featured-image'); ?></h2>
                    </div>
                    <div class="fpfi-card-body">
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e('Apply on', 'fetchpriority-featured-image'); ?></th>
                                <td>
                                    <fieldset>
                                        <label><input type="checkbox" name="fpfi_settings[enable_singular]" value="1" <?php checked($s['enable_singular']); ?>> <?php esc_html_e('Single posts & pages', 'fetchpriority-featured-image'); ?></label>
                                        <label><input type="checkbox" name="fpfi_settings[enable_home]" value="1" <?php checked($s['enable_home']); ?>> <?php esc_html_e('Blog home', 'fetchpriority-featured-image'); ?></label>
                                        <label><input type="checkbox" name="fpfi_settings[enable_archive]" value="1" <?php checked($s['enable_archive']); ?>> <?php esc_html_e('Archive pages (category, tag, author, date, CPT)', 'fetchpriority-featured-image'); ?></label>
                                        <label><input type="checkbox" name="fpfi_settings[enable_search]" value="1" <?php checked($s['enable_search']); ?>> <?php esc_html_e('Search results', 'fetchpriority-featured-image'); ?></label>
                                    </fieldset>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="fpfi_first_n"><?php esc_html_e('First N posts on archives', 'fetchpriority-featured-image'); ?></label></th>
                                <td>
                                    <input type="number" id="fpfi_first_n" name="fpfi_settings[first_n_on_archive]" value="<?php echo esc_attr($s['first_n_on_archive']); ?>" min="1" max="20" class="small-text">
                                    <p class="description"><?php esc_html_e('How many posts at the top of an archive/blog/search loop get fetchpriority="high". Default: 1. Range: 1–20.', 'fetchpriority-featured-image'); ?></p>
                                </td>
                            </tr>
                            <?php if (function_exists('fpfi_wc_active') && fpfi_wc_active()) : ?>
                            <tr>
                                <th scope="row"><span class="dashicons dashicons-cart" style="color:#7f54b3;"></span> <?php esc_html_e('WooCommerce', 'fetchpriority-featured-image'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="fpfi_settings[enable_woocommerce]" value="1" <?php checked($s['enable_woocommerce']); ?>> <?php esc_html_e('Prioritise the main product image in the single-product gallery.', 'fetchpriority-featured-image'); ?></label>
                                    <p class="description"><?php esc_html_e('Shop and product-category pages are already covered by the archive rules above. This adds the product gallery, whose markup bypasses the standard image path.', 'fetchpriority-featured-image'); ?></p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <div class="fpfi-card">
                    <div class="fpfi-card-head">
                        <span class="dashicons dashicons-arrow-down-alt"></span>
                        <h2><?php esc_html_e('Below-fold priority', 'fetchpriority-featured-image'); ?></h2>
                    </div>
                    <div class="fpfi-card-body">
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e('fetchpriority="low" below the fold', 'fetchpriority-featured-image'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="fpfi_settings[enable_low_below_fold]" value="1" <?php checked($s['enable_low_below_fold']); ?>> <?php esc_html_e('After the hero (or first-N posts on archives), tag remaining images with fetchpriority="low".', 'fetchpriority-featured-image'); ?></label>
                                    <p class="description"><?php esc_html_e('Tells the browser to defer below-fold work so the hero loads faster. Paired complement to the high tag.', 'fetchpriority-featured-image'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="fpfi-card">
                    <div class="fpfi-card-head">
                        <span class="dashicons dashicons-dismiss"></span>
                        <h2><?php esc_html_e('Exclusions', 'fetchpriority-featured-image'); ?></h2>
                    </div>
                    <div class="fpfi-card-body">
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e('Skip avatars', 'fetchpriority-featured-image'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="fpfi_settings[exclude_avatars]" value="1" <?php checked($s['exclude_avatars']); ?>> <?php esc_html_e('Never tag images with class "avatar"/"gravatar" or hosted on gravatar.com.', 'fetchpriority-featured-image'); ?></label>
                                    <p class="description"><?php esc_html_e('Author avatars are rarely LCP candidates and would waste the priority budget.', 'fetchpriority-featured-image'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="fpfi-card">
                    <div class="fpfi-card-head">
                        <span class="dashicons dashicons-admin-appearance"></span>
                        <h2><?php esc_html_e('Theme preset', 'fetchpriority-featured-image'); ?></h2>
                        <span class="fpfi-card-sub"><?php
                            /* translators: %s: detected theme key */
                            printf(esc_html__('Detected: %s', 'fetchpriority-featured-image'), esc_html($detected)); ?></span>
                    </div>
                    <div class="fpfi-card-body">
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="fpfi_theme_preset"><?php esc_html_e('Theme preset', 'fetchpriority-featured-image'); ?></label></th>
                                <td>
                                    <select id="fpfi_theme_preset" name="fpfi_settings[theme_preset]">
                                        <?php foreach ($preset_labels as $val => $label) : ?>
                                            <option value="<?php echo esc_attr($val); ?>" <?php selected($s['theme_preset'], $val); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php esc_html_e('Presets add theme-specific logo/header class exclusions so the priority budget is spent on the real hero image. Divi & Elementor already work out of the box.', 'fetchpriority-featured-image'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

            </div>

            <!-- =============== PRELOAD =============== -->
            <div class="fpfi-panel" data-panel="preload">
                <div class="fpfi-card">
                    <div class="fpfi-card-head">
                        <span class="dashicons dashicons-performance"></span>
                        <h2><?php esc_html_e('Preload (hero featured image)', 'fetchpriority-featured-image'); ?></h2>
                    </div>
                    <div class="fpfi-card-body">
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e('Preload featured image', 'fetchpriority-featured-image'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="fpfi_settings[enable_preload]" value="1" <?php checked($s['enable_preload']); ?>> <?php esc_html_e('Emit <link rel="preload" as="image"> in <head> on singular pages.', 'fetchpriority-featured-image'); ?></label>
                                    <p class="description"><?php esc_html_e('Strongest LCP signal. The learned/manual LCP is used when available, otherwise the featured image.', 'fetchpriority-featured-image'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Modern format preload', 'fetchpriority-featured-image'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="fpfi_settings[enable_modern_format_preload]" value="1" <?php checked($s['enable_modern_format_preload']); ?>> <?php esc_html_e('Also preload AVIF/WebP variants when sibling files exist on disk.', 'fetchpriority-featured-image'); ?></label>
                                    <p class="description"><?php esc_html_e('Detects "file.avif" / "file.webp" or "file.jpg.avif" / "file.jpg.webp" siblings (works with ShortPixel, Imagify, Optimole, and similar). Browsers automatically pick the supported type.', 'fetchpriority-featured-image'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="fpfi-card">
                    <div class="fpfi-card-head">
                        <span class="dashicons dashicons-editor-textcolor"></span>
                        <h2><?php esc_html_e('Fonts & connections', 'fetchpriority-featured-image'); ?></h2>
                        <span class="fpfi-card-sub"><?php esc_html_e('For text heroes & CDNs', 'fetchpriority-featured-image'); ?></span>
                    </div>
                    <div class="fpfi-card-body">
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e('Preload text-LCP font', 'fetchpriority-featured-image'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="fpfi_settings[enable_font_preload]" value="1" <?php checked($s['enable_font_preload']); ?>> <?php esc_html_e('When the measured LCP is a block of text, preload the web font it uses.', 'fetchpriority-featured-image'); ?></label>
                                    <p class="description"><?php esc_html_e('Text is the LCP on a huge number of pages. Preloading the exact font (self-hosted or Google Fonts) removes the render delay while the browser waits for it. Learned from real visitors, per template.', 'fetchpriority-featured-image'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('CDN preconnect', 'fetchpriority-featured-image'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="fpfi_settings[enable_preconnect]" value="1" <?php checked($s['enable_preconnect']); ?>> <?php esc_html_e('Open an early connection to the host serving your hero image or font when it is on another domain.', 'fetchpriority-featured-image'); ?></label>
                                    <p class="description"><?php esc_html_e('Emits <link rel="preconnect"> + dns-prefetch for the exact cross-origin host the hero loads from (image CDN, Google Fonts). Saves a DNS + TLS round trip on the critical path. Same-origin heroes need nothing, so nothing is emitted.', 'fetchpriority-featured-image'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- =============== DIAGNOSTICS =============== -->
            <div class="fpfi-panel" data-panel="diagnostics">

                <div class="fpfi-card">
                    <div class="fpfi-card-head">
                        <span class="dashicons dashicons-admin-network"></span>
                        <h2><?php esc_html_e('Google API key', 'fetchpriority-featured-image'); ?></h2>
                    </div>
                    <div class="fpfi-card-body">
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="fpfi_crux_key"><?php esc_html_e('CrUX / PSI API key', 'fetchpriority-featured-image'); ?></label></th>
                                <td>
                                    <input type="text" id="fpfi_crux_key" name="fpfi_settings[crux_api_key]" value="<?php echo esc_attr($s['crux_api_key']); ?>" class="regular-text" autocomplete="off">
                                    <p class="description">
                                        <?php
                                        printf(
                                            /* translators: %s: link to Google API console */
                                            wp_kses(__('Free Google API key with the <em>Chrome UX Report API</em> (and optionally <em>PageSpeed Insights API</em>) enabled. Get one at %s. PageSpeed also works without a key at low volume.', 'fetchpriority-featured-image'), array('em' => array(), 'a' => array('href' => array(), 'target' => array()))),
                                            '<a href="https://developer.chrome.com/docs/crux/api" target="_blank">developer.chrome.com/docs/crux/api</a>'
                                        );
                                        ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="fpfi-card">
                    <div class="fpfi-card-head">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <h2><?php esc_html_e('Core Web Vitals — before / after', 'fetchpriority-featured-image'); ?></h2>
                    </div>
                    <div class="fpfi-card-body">
                        <p class="description"><?php esc_html_e('Real-world field data from the Chrome UX Report. The first measurement is saved as your baseline; later runs show the change.', 'fetchpriority-featured-image'); ?></p>
                        <p>
                            <button type="button" class="button button-primary" id="fpfi-crux-measure"><?php esc_html_e('Measure Core Web Vitals', 'fetchpriority-featured-image'); ?></button>
                            <button type="button" class="button" id="fpfi-crux-baseline"><?php esc_html_e('Set current as new baseline', 'fetchpriority-featured-image'); ?></button>
                            <span id="fpfi-crux-status" style="margin-left:8px;"></span>
                        </p>
                        <div id="fpfi-crux-result">
                            <?php
                            $snaps = get_option(fpfi_crux_option_name(), array());
                            echo fpfi_crux_render_table(is_array($snaps) ? $snaps : array()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            ?>
                        </div>
                    </div>
                </div>

                <div class="fpfi-card">
                    <div class="fpfi-card-head">
                        <span class="dashicons dashicons-dashboard"></span>
                        <h2><?php esc_html_e('PageSpeed audit (Lighthouse lab)', 'fetchpriority-featured-image'); ?></h2>
                    </div>
                    <div class="fpfi-card-body">
                        <p class="description"><?php esc_html_e('Run Google PageSpeed Insights on any URL. Shows the score, LCP, page weight, image-saving opportunities, and Google\'s own detected LCP element.', 'fetchpriority-featured-image'); ?></p>
                        <p>
                            <input type="url" id="fpfi-psi-url" class="regular-text" value="<?php echo esc_attr(home_url('/')); ?>" placeholder="<?php echo esc_attr(home_url('/')); ?>">
                            <select id="fpfi-psi-strategy">
                                <option value="mobile"><?php esc_html_e('Mobile', 'fetchpriority-featured-image'); ?></option>
                                <option value="desktop"><?php esc_html_e('Desktop', 'fetchpriority-featured-image'); ?></option>
                            </select>
                            <button type="button" class="button button-primary" id="fpfi-psi-run"><?php esc_html_e('Run PageSpeed audit', 'fetchpriority-featured-image'); ?></button>
                            <span id="fpfi-psi-status" style="margin-left:8px;"></span>
                        </p>
                        <div id="fpfi-psi-result">
                            <?php
                            echo fpfi_psi_render(get_option(fpfi_psi_option_name(), array())); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            ?>
                        </div>
                    </div>
                </div>

                <div class="fpfi-card">
                    <div class="fpfi-card-head">
                        <span class="dashicons dashicons-clock"></span>
                        <h2><?php esc_html_e('Slowest templates (real-user LCP)', 'fetchpriority-featured-image'); ?></h2>
                    </div>
                    <div class="fpfi-card-body">
                        <p class="description"><?php esc_html_e('Measured from your visitors. Sorted slowest first — your optimization to-do list.', 'fetchpriority-featured-image'); ?></p>
                        <?php fpfi_render_lcp_leaderboard(); ?>
                    </div>
                </div>

                <div class="fpfi-card">
                    <div class="fpfi-card-head">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <h2><?php esc_html_e('Debug', 'fetchpriority-featured-image'); ?></h2>
                    </div>
                    <div class="fpfi-card-body">
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><?php esc_html_e('Admin-bar badge', 'fetchpriority-featured-image'); ?></th>
                                <td>
                                    <label><input type="checkbox" name="fpfi_settings[enable_debug_badge]" value="1" <?php checked($s['enable_debug_badge']); ?>> <?php esc_html_e('Show a badge on the front-end admin bar with tagged-image counts.', 'fetchpriority-featured-image'); ?></label>
                                    <p class="description"><?php esc_html_e('Visible only to users with the manage_options capability.', 'fetchpriority-featured-image'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="fpfi-card">
                    <div class="fpfi-card-head">
                        <span class="dashicons dashicons-migrate"></span>
                        <h2><?php esc_html_e('Backup & migrate', 'fetchpriority-featured-image'); ?></h2>
                    </div>
                    <div class="fpfi-card-body">
                        <p class="description"><?php esc_html_e('Save your configuration to a file and load it on another site. Learned LCP data and your API key are kept out of the file — those stay per-site.', 'fetchpriority-featured-image'); ?></p>
                        <p>
                            <a class="button fpfi-btn-icon" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fpfi_export_settings'), 'fpfi_export_settings')); ?>"><span class="dashicons dashicons-download"></span> <?php esc_html_e('Export settings', 'fetchpriority-featured-image'); ?></a>
                            <label class="button fpfi-btn-icon"><span class="dashicons dashicons-upload"></span> <?php esc_html_e('Import settings', 'fetchpriority-featured-image'); ?><input type="file" id="fpfi-import-file" accept="application/json,.json" style="display:none;"></label>
                            <button type="button" class="button" id="fpfi-reset-settings"><?php esc_html_e('Reset to defaults', 'fetchpriority-featured-image'); ?></button>
                            <span id="fpfi-porting-status" style="margin-left:8px;"></span>
                        </p>
                    </div>
                </div>

            </div>

            <div class="fpfi-savebar">
                <?php submit_button(__('Save changes', 'fetchpriority-featured-image'), 'primary', 'submit', false); ?>
                <span class="fpfi-savebar-hint"><?php esc_html_e('Changes apply to the front end immediately after saving.', 'fetchpriority-featured-image'); ?></span>
            </div>
        </form>
    </div>
    <?php
}

/**
 * Render the per-template LCP target table on the settings page.
 */
function fpfi_render_lcp_table()
{
    $all = fpfi_lcp_get_all();
    if (empty($all)) {
        echo '<p>' . esc_html__('No templates recorded yet. Browse your site (with Smart LCP enabled) or use the visual picker, and entries will appear here.', 'fetchpriority-featured-image') . '</p>';
        return;
    }

    $modes = array(
        'auto'    => __('Auto', 'fetchpriority-featured-image'),
        'learned' => __('Learned only', 'fetchpriority-featured-image'),
        'manual'  => __('Manual only', 'fetchpriority-featured-image'),
        'off'     => __('Off', 'fetchpriority-featured-image'),
    );
    ?>
    <table class="widefat striped" id="fpfi-lcp-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Template', 'fetchpriority-featured-image'); ?></th>
                <th><?php esc_html_e('Effective target', 'fetchpriority-featured-image'); ?></th>
                <th><?php esc_html_e('Source', 'fetchpriority-featured-image'); ?></th>
                <th><?php esc_html_e('Samples', 'fetchpriority-featured-image'); ?></th>
                <th><?php esc_html_e('Image sizing', 'fetchpriority-featured-image'); ?></th>
                <th><?php esc_html_e('Mode', 'fetchpriority-featured-image'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($all as $key => $rec) :
                $eff = fpfi_effective_lcp($key);
                $mode = isset($rec['mode']) ? $rec['mode'] : 'auto';
                $samples = isset($rec['learned']['samples']) ? (int) $rec['learned']['samples'] : 0;
                ?>
                <tr>
                    <td><strong><?php echo esc_html(fpfi_template_label($key)); ?></strong><br><code style="font-size:11px;"><?php echo esc_html($key); ?></code></td>
                    <td>
                        <?php if ($eff && !empty($eff['url'])) : ?>
                            <?php if (empty($eff['is_bg'])) : ?>
                                <img src="<?php echo esc_url($eff['url']); ?>" alt="" style="max-width:60px;max-height:40px;vertical-align:middle;border:1px solid #ddd;margin-right:6px;">
                            <?php else : ?>
                                <span class="dashicons dashicons-format-image" title="<?php esc_attr_e('CSS background image', 'fetchpriority-featured-image'); ?>"></span>
                            <?php endif; ?>
                            <span style="font-size:11px;word-break:break-all;"><?php echo esc_html(wp_basename($eff['url'])); ?></span>
                            <?php if (!empty($eff['is_bg'])) : ?><em>(<?php esc_html_e('background', 'fetchpriority-featured-image'); ?>)</em><?php endif; ?>
                        <?php else : ?>
                            <em><?php esc_html_e('Featured-image guess (fallback)', 'fetchpriority-featured-image'); ?></em>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($eff && isset($eff['source']) ? $eff['source'] : '—'); ?></td>
                    <td><?php echo esc_html($samples); ?></td>
                    <td>
                        <?php
                        $ov = fpfi_lcp_oversize($rec);
                        if ($ov === null) {
                            echo '<span style="color:#787c82;">—</span>';
                        } elseif ($ov['wasteful']) {
                            printf(
                                '<span style="color:#c0341a;font-weight:600;" title="%s">⚠ %s×</span><br><span style="font-size:11px;">%s</span>',
                                esc_attr__('LCP image is larger than it displays — serving wasted bytes.', 'fetchpriority-featured-image'),
                                esc_html(number_format($ov['factor'], 1)),
                                esc_html(sprintf(
                                    /* translators: 1: natural width, 2: recommended width */
                                    __('%1$dpx → resize to ~%2$dpx', 'fetchpriority-featured-image'),
                                    $ov['nat_w'],
                                    $ov['target_w']
                                ))
                            );
                        } else {
                            echo '<span style="color:#0a7d28;">✓ ' . esc_html__('OK', 'fetchpriority-featured-image') . '</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <select class="fpfi-lcp-mode" data-template="<?php echo esc_attr($key); ?>">
                            <?php foreach ($modes as $mval => $mlabel) : ?>
                                <option value="<?php echo esc_attr($mval); ?>" <?php selected($mode, $mval); ?>><?php echo esc_html($mlabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Render the slow-template leaderboard from measured real-user LCP.
 */
function fpfi_render_lcp_leaderboard()
{
    $all = fpfi_lcp_get_all();
    $rows = array();
    foreach ($all as $key => $rec) {
        $avg = fpfi_lcp_avg_ms($rec);
        if ($avg === null) {
            continue;
        }
        $rows[] = array(
            'key'    => $key,
            'avg'    => $avg,
            'max'    => isset($rec['perf']['max_ms']) ? (int) $rec['perf']['max_ms'] : 0,
            'n'      => isset($rec['perf']['n']) ? (int) $rec['perf']['n'] : 0,
        );
    }

    if (empty($rows)) {
        echo '<p>' . esc_html__('No timing data yet. Once visitors browse your site (with Smart LCP enabled), measured LCP times appear here.', 'fetchpriority-featured-image') . '</p>';
        return;
    }

    usort($rows, function ($a, $b) {
        return $b['avg'] - $a['avg'];
    });

    $colors = array('good' => '#0a7d28', 'ni' => '#bd7b00', 'poor' => '#c0341a');
    ?>
    <table class="widefat striped" style="max-width:720px;">
        <thead>
            <tr>
                <th><?php esc_html_e('Template', 'fetchpriority-featured-image'); ?></th>
                <th><?php esc_html_e('Avg LCP', 'fetchpriority-featured-image'); ?></th>
                <th><?php esc_html_e('Worst LCP', 'fetchpriority-featured-image'); ?></th>
                <th><?php esc_html_e('Samples', 'fetchpriority-featured-image'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row) :
                $rating = fpfi_lcp_ms_rating($row['avg']);
                $color = isset($colors[$rating]) ? $colors[$rating] : '#787c82';
                ?>
                <tr>
                    <td><strong><?php echo esc_html(fpfi_template_label($row['key'])); ?></strong></td>
                    <td><span style="background:<?php echo esc_attr($color); ?>;color:#fff;padding:2px 8px;border-radius:10px;font-size:12px;"><?php echo esc_html(number_format($row['avg'] / 1000, 2)); ?>s</span></td>
                    <td><?php echo esc_html(number_format($row['max'] / 1000, 2)); ?>s</td>
                    <td><?php echo esc_html($row['n']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Enqueue settings-page admin JS (CrUX + PSI + per-template AJAX).
 *
 * @param string $hook
 */
function fpfi_enqueue_settings_assets($hook)
{
    if ('settings_page_fetchpriority-featured-image' !== $hook) {
        return;
    }
    $css_path = plugin_dir_path(FPFI_PLUGIN_FILE) . 'assets/css/admin.css';
    $css_ver  = file_exists($css_path) ? filemtime($css_path) : FETCHPRIORITY_FEATURED_IMAGE_VERSION;
    wp_enqueue_style(
        'fpfi-admin',
        plugins_url('assets/css/admin.css', FPFI_PLUGIN_FILE),
        array('dashicons'),
        $css_ver
    );
    wp_enqueue_script('jquery');
    $data = array(
        'ajax'        => admin_url('admin-ajax.php'),
        'cruxNonce'   => wp_create_nonce('fpfi_crux'),
        'lcpNonce'    => wp_create_nonce('fpfi_lcp_admin'),
        'psiNonce'    => wp_create_nonce('fpfi_psi'),
        'measuring'   => __('Measuring…', 'fetchpriority-featured-image'),
        'running'     => __('Running Lighthouse (up to a minute)…', 'fetchpriority-featured-image'),
        'saved'       => __('Saved.', 'fetchpriority-featured-image'),
        'resetConfirm' => __('Clear all learned LCP data? Manual picks are kept.', 'fetchpriority-featured-image'),
        'error'       => __('Error.', 'fetchpriority-featured-image'),
        'importing'   => __('Importing…', 'fetchpriority-featured-image'),
        'settingsResetConfirm' => __('Reset all settings to their defaults? Your saved API key is kept.', 'fetchpriority-featured-image'),
    );
    $js = '
    jQuery(function($){
        var D = ' . wp_json_encode($data) . ';

        function notice($box, msg){
            $box.html($("<div class=\'notice notice-error inline\'><p></p></div>").find("p").text(msg).end());
        }

        // ---- Tabs ----
        var KEY = "fpfi_active_tab";
        function activate(tab){
            if(!tab) return;
            var $btn = $(".fpfi-tab[data-tab=\'" + tab + "\']");
            if(!$btn.length){ tab = "smart"; $btn = $(".fpfi-tab[data-tab=\'smart\']"); }
            $(".fpfi-tab").removeClass("is-active");
            $btn.addClass("is-active");
            $(".fpfi-panel").removeClass("is-active");
            $(".fpfi-panel[data-panel=\'" + tab + "\']").addClass("is-active");
            try{ window.localStorage.setItem(KEY, tab); }catch(e){}
        }
        $(document).on("click", ".fpfi-tab", function(){ activate($(this).data("tab")); });
        var saved = null;
        try{ saved = window.localStorage.getItem(KEY); }catch(e){}
        if(saved){ activate(saved); }

        function crux(setBaseline){
            var $s = $("#fpfi-crux-status").text(D.measuring);
            $.post(D.ajax, {action:"fpfi_crux_fetch", nonce:D.cruxNonce, set_baseline: setBaseline?1:0})
            .done(function(r){
                $s.text("");
                if(r && r.success){ $("#fpfi-crux-result").html(r.data.html); }
                else { notice($("#fpfi-crux-result"), (r && r.data && r.data.message) ? r.data.message : D.error); }
            }).fail(function(){ $s.text(""); notice($("#fpfi-crux-result"), D.error); });
        }
        $("#fpfi-crux-measure").on("click", function(){ crux(false); });
        $("#fpfi-crux-baseline").on("click", function(){ crux(true); });
        $("#fpfi-psi-run").on("click", function(){
            var $s = $("#fpfi-psi-status").text(D.running);
            $.post(D.ajax, {action:"fpfi_psi_fetch", nonce:D.psiNonce, url:$("#fpfi-psi-url").val(), strategy:$("#fpfi-psi-strategy").val()})
            .done(function(r){
                $s.text("");
                if(r && r.success){ $("#fpfi-psi-result").html(r.data.html); }
                else { notice($("#fpfi-psi-result"), (r && r.data && r.data.message) ? r.data.message : D.error); }
            }).fail(function(){ $s.text(""); notice($("#fpfi-psi-result"), D.error); });
        });
        $(document).on("change", ".fpfi-lcp-mode", function(){
            var $sel=$(this);
            $.post(D.ajax, {action:"fpfi_lcp_save_mode", nonce:D.lcpNonce, template:$sel.data("template"), mode:$sel.val()})
            .done(function(){ $("#fpfi-lcp-status").text(D.saved).delay(1500).queue(function(n){$(this).text("");n();}); });
        });
        $("#fpfi-lcp-reset").on("click", function(){
            if(!window.confirm(D.resetConfirm)) return;
            $.post(D.ajax, {action:"fpfi_lcp_reset", nonce:D.lcpNonce})
            .done(function(){ $("#fpfi-lcp-status").text(D.saved); location.reload(); });
        });

        // ---- Backup & migrate ----
        $("#fpfi-import-file").on("change", function(){
            var file = this.files && this.files[0];
            if(!file) return;
            var $s = $("#fpfi-porting-status").text(D.importing);
            var reader = new FileReader();
            reader.onload = function(e){
                $.post(D.ajax, {action:"fpfi_import_settings", nonce:D.lcpNonce, data:e.target.result})
                .done(function(r){
                    if(r && r.success){ $s.text((r.data && r.data.message) ? r.data.message : D.saved); location.reload(); }
                    else { $s.text((r && r.data && r.data.message) ? r.data.message : D.error); }
                }).fail(function(){ $s.text(D.error); });
            };
            reader.onerror = function(){ $s.text(D.error); };
            reader.readAsText(file);
            $(this).val("");
        });
        $("#fpfi-reset-settings").on("click", function(){
            if(!window.confirm(D.settingsResetConfirm)) return;
            var $s = $("#fpfi-porting-status").text(D.importing);
            $.post(D.ajax, {action:"fpfi_reset_settings", nonce:D.lcpNonce})
            .done(function(r){
                if(r && r.success){ $s.text((r.data && r.data.message) ? r.data.message : D.saved); location.reload(); }
                else { $s.text((r && r.data && r.data.message) ? r.data.message : D.error); }
            }).fail(function(){ $s.text(D.error); });
        });
    });
    ';
    wp_add_inline_script('jquery', $js);
}
add_action('admin_enqueue_scripts', 'fpfi_enqueue_settings_assets');

/* -------------------------------------------------------------------------
 * Plugin meta + admin notice
 * ---------------------------------------------------------------------- */

function fpfi_plugin_action_links($links)
{
    $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=fetchpriority-featured-image')) . '">' . __('Settings', 'fetchpriority-featured-image') . '</a>';
    $coffee_link = '<a href="https://ko-fi.com/gunjanjaswal" target="_blank" style="color: #0073aa; font-weight: bold;">' . __('Support on Ko-fi', 'fetchpriority-featured-image') . '</a>';
    array_unshift($links, $settings_link, $coffee_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'fpfi_plugin_action_links');

function fpfi_plugin_meta_links($links, $file)
{
    if (plugin_basename(__FILE__) === $file) {
        $links[] = '<a href="https://wordpress.org/support/plugin/fetchpriority-featured-image/" target="_blank">' . __('Plugin Support', 'fetchpriority-featured-image') . '</a>';
        $links[] = '<a href="mailto:hello@gunjanjaswal.me">' . __('Contact Developer', 'fetchpriority-featured-image') . '</a>';
    }
    return $links;
}
add_filter('plugin_row_meta', 'fpfi_plugin_meta_links', 10, 2);

function fpfi_enqueue_admin_scripts($hook)
{
    if ('plugins.php' !== $hook) {
        return;
    }
    if (get_option('fpfi_coffee_notice_dismissed')) {
        return;
    }
    wp_enqueue_script('jquery');
    $inline_script = sprintf(
        '
        jQuery(document).ready(function($) {
            $(document).on("click", ".fpfi-coffee-notice .notice-dismiss", function() {
                $.ajax({
                    url: ajaxurl,
                    data: {
                        action: "fpfi_dismiss_coffee_notice",
                        nonce: "%s"
                    }
                });
            });
        });
        ',
        wp_create_nonce('fpfi_dismiss_notice')
    );
    wp_add_inline_script('jquery', $inline_script);
}
add_action('admin_enqueue_scripts', 'fpfi_enqueue_admin_scripts');

function fpfi_admin_notice()
{
    if (get_option('fpfi_coffee_notice_dismissed')) {
        return;
    }
    $screen = get_current_screen();
    if ($screen->id !== 'plugins') {
        return;
    }
    ?>
    <div class="notice notice-info is-dismissible fpfi-coffee-notice">
        <p><?php echo wp_kses(
            sprintf(
                /* translators: %s: Link to Ko-fi support page */
                __('Thank you for using FetchPriority Featured Image! If you find it useful, please consider %s.', 'fetchpriority-featured-image'),
                '<a href="https://ko-fi.com/gunjanjaswal" target="_blank">supporting on Ko-fi</a>'
            ),
            array(
                'a' => array(
                    'href'   => array(),
                    'target' => array(),
                ),
            )
        ); ?></p>
    </div>
    <?php
    update_option('fpfi_coffee_notice_dismissed', true);
}
add_action('admin_notices', 'fpfi_admin_notice');

function fpfi_dismiss_coffee_notice()
{
    if (!isset($_REQUEST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['nonce'])), 'fpfi_dismiss_notice')) {
        wp_die(esc_html__('Security check failed', 'fetchpriority-featured-image'), 403);
    }
    update_option('fpfi_coffee_notice_dismissed', true);
    wp_die();
}
add_action('wp_ajax_fpfi_dismiss_coffee_notice', 'fpfi_dismiss_coffee_notice');

/* -------------------------------------------------------------------------
 * "What's new" notice after a plugin update
 * ---------------------------------------------------------------------- */

/**
 * Track the installed version so we can detect updates.
 *
 * Sets the dismissed flag to the current version on a *fresh* install so new
 * users are not shown an upgrade announcement.
 */
function fpfi_track_version()
{
    $prev = get_option('fpfi_version', '');
    $cur  = FETCHPRIORITY_FEATURED_IMAGE_VERSION;

    if ($prev === $cur) {
        return;
    }

    if ($prev === '') {
        // No version tracked yet. Distinguish a genuinely fresh install from an
        // upgrade out of a pre-1.4.0 build (which never stored fpfi_version).
        $looks_existing = (get_option('fpfi_settings', null) !== null)
            || (get_option('fpfi_coffee_notice_dismissed', null) !== null);
        if (!$looks_existing) {
            // Fresh install — suppress the "what's new" notice.
            update_option('fpfi_whatsnew_dismissed', $cur, false);
        }
        // else: leave the dismissed flag unset so the upgrade notice shows.
    }

    update_option('fpfi_version', $cur, false);
}
add_action('admin_init', 'fpfi_track_version');

/**
 * Short list of headline features for the current version.
 *
 * @return string[]
 */
function fpfi_whatsnew_highlights()
{
    return array(
        __('Self-learning LCP — measures the real Largest Contentful Paint element from your visitors and auto-prioritises it.', 'fetchpriority-featured-image'),
        __('Visual LCP picker — click your hero on the front end to lock it in per template.', 'fetchpriority-featured-image'),
        __('Core Web Vitals report + one-click PageSpeed (Lighthouse) audit, right in the dashboard.', 'fetchpriority-featured-image'),
        __('Oversized-image warnings, CSS background-image preload, and a slowest-templates leaderboard.', 'fetchpriority-featured-image'),
    );
}

/**
 * Show a dismissible "what's new" banner after an update.
 */
function fpfi_whatsnew_notice()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $cur = FETCHPRIORITY_FEATURED_IMAGE_VERSION;
    if (get_option('fpfi_whatsnew_dismissed') === $cur) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->id, array('dashboard', 'plugins', 'settings_page_fetchpriority-featured-image'), true)) {
        return;
    }
    $settings_url = admin_url('options-general.php?page=fetchpriority-featured-image');
    ?>
    <div class="notice notice-info is-dismissible fpfi-whatsnew-notice" data-nonce="<?php echo esc_attr(wp_create_nonce('fpfi_whatsnew')); ?>">
        <p style="font-size:14px;margin-bottom:6px;">
            <strong>
                <?php
                printf(
                    /* translators: %s: version number */
                    esc_html__('FetchPriority Featured Image %s — what\'s new', 'fetchpriority-featured-image'),
                    esc_html($cur)
                );
                ?>
            </strong>
        </p>
        <ul style="list-style:disc;margin:0 0 8px 20px;">
            <?php foreach (fpfi_whatsnew_highlights() as $line) : ?>
                <li><?php echo esc_html($line); ?></li>
            <?php endforeach; ?>
        </ul>
        <p>
            <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary"><?php esc_html_e('Open settings', 'fetchpriority-featured-image'); ?></a>
        </p>
    </div>
    <script>
    (function(){
        document.addEventListener('click', function(e){
            var n = e.target.closest ? e.target.closest('.fpfi-whatsnew-notice .notice-dismiss') : null;
            if (!n) { return; }
            var box = e.target.closest('.fpfi-whatsnew-notice');
            var data = new FormData();
            data.append('action', 'fpfi_dismiss_whatsnew');
            data.append('nonce', box ? box.getAttribute('data-nonce') : '');
            fetch(ajaxurl, { method:'POST', credentials:'same-origin', body:data });
        });
    })();
    </script>
    <?php
}
add_action('admin_notices', 'fpfi_whatsnew_notice');

function fpfi_dismiss_whatsnew()
{
    if (!current_user_can('manage_options')) {
        wp_die('', '', 403);
    }
    if (!isset($_REQUEST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['nonce'])), 'fpfi_whatsnew')) {
        wp_die('', '', 403);
    }
    update_option('fpfi_whatsnew_dismissed', FETCHPRIORITY_FEATURED_IMAGE_VERSION, false);
    wp_die();
}
add_action('wp_ajax_fpfi_dismiss_whatsnew', 'fpfi_dismiss_whatsnew');
