<?php
/**
 * Settings backup & migration (export / import / reset).
 *
 * Export downloads the current configuration as a JSON file; import restores it
 * (validated through the same sanitiser the settings screen uses). Handy for
 * agencies rolling one tuned configuration out across many sites. Learned LCP
 * data is intentionally left out — it is measured per-site and is not portable.
 *
 * @package FetchPriority_Featured_Image
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Settings keys that are site-specific and should never travel in an export.
 *
 * @return string[]
 */
function fpfi_porting_excluded_keys()
{
    return array('crux_api_key');
}

/**
 * Build the export payload (settings minus site-specific secrets).
 *
 * @return array
 */
function fpfi_build_export()
{
    $settings = fpfi_get_settings();
    foreach (fpfi_porting_excluded_keys() as $key) {
        unset($settings[$key]);
    }
    return array(
        'type'           => 'fetchpriority-featured-image-settings',
        'plugin_version' => FETCHPRIORITY_FEATURED_IMAGE_VERSION,
        'exported'       => time(),
        'settings'       => $settings,
    );
}

/**
 * Apply an imported settings bundle. Returns true on success.
 *
 * @param mixed $bundle Decoded JSON (array).
 * @return bool
 */
function fpfi_apply_import($bundle)
{
    if (!is_array($bundle) || empty($bundle['settings']) || !is_array($bundle['settings'])) {
        return false;
    }
    if (isset($bundle['type']) && $bundle['type'] !== 'fetchpriority-featured-image-settings') {
        return false;
    }

    $incoming = $bundle['settings'];

    // Never let an import clobber this site's own secrets — carry them forward.
    $current = fpfi_get_settings();
    foreach (fpfi_porting_excluded_keys() as $key) {
        if (!isset($incoming[$key]) && isset($current[$key])) {
            $incoming[$key] = $current[$key];
        }
    }

    // Reuse the exact same validation the settings form runs.
    $clean = fpfi_sanitize_settings($incoming);
    update_option('fpfi_settings', $clean);

    return true;
}

/* -------------------------------------------------------------------------
 * Export — GET download via admin-post.
 * ---------------------------------------------------------------------- */

function fpfi_handle_export()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to export settings.', 'fetchpriority-featured-image'));
    }
    check_admin_referer('fpfi_export_settings');

    $json = wp_json_encode(fpfi_build_export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=fetchpriority-settings-' . gmdate('Ymd') . '.json');
    header('Content-Length: ' . strlen($json));
    echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML.
    exit;
}
add_action('admin_post_fpfi_export_settings', 'fpfi_handle_export');

/* -------------------------------------------------------------------------
 * Import / reset — AJAX (mirrors the learned-data reset flow).
 * ---------------------------------------------------------------------- */

function fpfi_handle_import()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Not allowed.', 'fetchpriority-featured-image')));
    }
    check_ajax_referer('fpfi_lcp_admin', 'nonce');

    $raw = isset($_POST['data']) ? wp_unslash($_POST['data']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON decoded + sanitised below.
    if (!is_string($raw) || $raw === '') {
        wp_send_json_error(array('message' => __('No file contents received.', 'fetchpriority-featured-image')));
    }

    $bundle = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(array('message' => __('That file is not valid JSON.', 'fetchpriority-featured-image')));
    }

    if (!fpfi_apply_import($bundle)) {
        wp_send_json_error(array('message' => __('That file is not a FetchPriority settings export.', 'fetchpriority-featured-image')));
    }

    wp_send_json_success(array('message' => __('Settings imported.', 'fetchpriority-featured-image')));
}
add_action('wp_ajax_fpfi_import_settings', 'fpfi_handle_import');

function fpfi_handle_reset_settings()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Not allowed.', 'fetchpriority-featured-image')));
    }
    check_ajax_referer('fpfi_lcp_admin', 'nonce');

    // Keep site-specific secrets across a reset.
    $current = fpfi_get_settings();
    $defaults = fpfi_default_settings();
    foreach (fpfi_porting_excluded_keys() as $key) {
        if (isset($current[$key])) {
            $defaults[$key] = $current[$key];
        }
    }
    update_option('fpfi_settings', $defaults);

    wp_send_json_success(array('message' => __('Settings reset to defaults.', 'fetchpriority-featured-image')));
}
add_action('wp_ajax_fpfi_reset_settings', 'fpfi_handle_reset_settings');
