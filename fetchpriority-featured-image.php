<?php
/**
 * Plugin Name: FetchPriority Featured Image
 * Plugin URI: https://wordpress.org/plugins/fetchpriority-featured-image/
 * Description: Adds fetchpriority="high" to hero/featured images and (optionally) fetchpriority="low" to below-fold images. Includes hero <link rel="preload"> with AVIF/WebP detection, theme presets, and an admin-bar debug badge.
 * Version: 1.3.0
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

define('FETCHPRIORITY_FEATURED_IMAGE_VERSION', '1.3.0');

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
    );
    foreach ($bool_keys as $key) {
        $clean[$key] = !empty($input[$key]) ? 1 : 0;
    }
    $first_n = isset($input['first_n_on_archive']) ? (int) $input['first_n_on_archive'] : 1;
    $clean['first_n_on_archive'] = max(1, min(20, $first_n));

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

    $priority = fpfi_decide_priority();
    if ($priority === null) {
        return $html;
    }

    $html = str_replace('<img ', '<img fetchpriority="' . esc_attr($priority) . '" ', $html);
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

    $priority = fpfi_decide_priority();
    if ($priority === null) {
        return $attr;
    }

    $attr['fetchpriority'] = $priority;
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

            $priority = fpfi_decide_priority();
            if ($priority === null) {
                return $img;
            }

            fpfi_record_priority_applied($priority);
            return str_replace('<img ', '<img fetchpriority="' . esc_attr($priority) . '" ', $img);
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
    <div class="wrap">
        <h1><?php esc_html_e('FetchPriority Featured Image', 'fetchpriority-featured-image'); ?></h1>
        <p><?php esc_html_e('Control where the fetchpriority attribute is added and enable optional preload + debug helpers.', 'fetchpriority-featured-image'); ?></p>
        <form method="post" action="options.php">
            <?php settings_fields('fpfi_settings_group'); ?>

            <h2><?php esc_html_e('Contexts', 'fetchpriority-featured-image'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Apply on', 'fetchpriority-featured-image'); ?></th>
                    <td>
                        <fieldset>
                            <label><input type="checkbox" name="fpfi_settings[enable_singular]" value="1" <?php checked($s['enable_singular']); ?>> <?php esc_html_e('Single posts & pages', 'fetchpriority-featured-image'); ?></label><br>
                            <label><input type="checkbox" name="fpfi_settings[enable_home]" value="1" <?php checked($s['enable_home']); ?>> <?php esc_html_e('Blog home', 'fetchpriority-featured-image'); ?></label><br>
                            <label><input type="checkbox" name="fpfi_settings[enable_archive]" value="1" <?php checked($s['enable_archive']); ?>> <?php esc_html_e('Archive pages (category, tag, author, date, CPT)', 'fetchpriority-featured-image'); ?></label><br>
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
            </table>

            <h2><?php esc_html_e('Preload (hero featured image)', 'fetchpriority-featured-image'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Preload featured image', 'fetchpriority-featured-image'); ?></th>
                    <td>
                        <label><input type="checkbox" name="fpfi_settings[enable_preload]" value="1" <?php checked($s['enable_preload']); ?>> <?php esc_html_e('Emit <link rel="preload" as="image"> in <head> on singular pages.', 'fetchpriority-featured-image'); ?></label>
                        <p class="description"><?php esc_html_e('Strongest LCP signal. Only fires when a featured image exists.', 'fetchpriority-featured-image'); ?></p>
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

            <h2><?php esc_html_e('Below-fold priority', 'fetchpriority-featured-image'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('fetchpriority="low" below the fold', 'fetchpriority-featured-image'); ?></th>
                    <td>
                        <label><input type="checkbox" name="fpfi_settings[enable_low_below_fold]" value="1" <?php checked($s['enable_low_below_fold']); ?>> <?php esc_html_e('After the hero (or first-N posts on archives), tag remaining images with fetchpriority="low".', 'fetchpriority-featured-image'); ?></label>
                        <p class="description"><?php esc_html_e('Tells the browser to defer below-fold work so the hero loads faster. Paired complement to the high tag.', 'fetchpriority-featured-image'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Exclusions', 'fetchpriority-featured-image'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Skip avatars', 'fetchpriority-featured-image'); ?></th>
                    <td>
                        <label><input type="checkbox" name="fpfi_settings[exclude_avatars]" value="1" <?php checked($s['exclude_avatars']); ?>> <?php esc_html_e('Never tag images with class "avatar"/"gravatar" or hosted on gravatar.com.', 'fetchpriority-featured-image'); ?></label>
                        <p class="description"><?php esc_html_e('Author avatars are rarely LCP candidates and would waste the priority budget.', 'fetchpriority-featured-image'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Theme preset', 'fetchpriority-featured-image'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="fpfi_theme_preset"><?php esc_html_e('Theme preset', 'fetchpriority-featured-image'); ?></label></th>
                    <td>
                        <select id="fpfi_theme_preset" name="fpfi_settings[theme_preset]">
                            <?php foreach ($preset_labels as $val => $label) : ?>
                                <option value="<?php echo esc_attr($val); ?>" <?php selected($s['theme_preset'], $val); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: detected theme key */
                                esc_html__('Auto-detected: %s. Presets add theme-specific logo/header class exclusions so the priority budget is spent on the real hero image. Divi & Elementor already work out of the box via the attachment-attributes filter.', 'fetchpriority-featured-image'),
                                '<code>' . esc_html($detected) . '</code>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Debug', 'fetchpriority-featured-image'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Admin-bar badge', 'fetchpriority-featured-image'); ?></th>
                    <td>
                        <label><input type="checkbox" name="fpfi_settings[enable_debug_badge]" value="1" <?php checked($s['enable_debug_badge']); ?>> <?php esc_html_e('Show a badge on the front-end admin bar with tagged-image counts.', 'fetchpriority-featured-image'); ?></label>
                        <p class="description"><?php esc_html_e('Visible only to users with the manage_options capability.', 'fetchpriority-featured-image'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
        <hr>
        <p>
            <a href="https://ko-fi.com/gunjanjaswal" target="_blank" class="button button-secondary"><?php esc_html_e('Support on Ko-fi', 'fetchpriority-featured-image'); ?></a>
            <a href="https://github.com/gunjanjaswal/FetchPriority-Featured-Image" target="_blank" class="button button-secondary"><?php esc_html_e('GitHub', 'fetchpriority-featured-image'); ?></a>
        </p>
    </div>
    <?php
}

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
