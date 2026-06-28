<?php
/**
 * Visual LCP picker: click-to-select the hero element on the front end and
 * save it as a manual per-template override.
 *
 * @package fetchpriority-featured-image
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Add a "Pick LCP element" item to the front-end admin bar.
 *
 * @param WP_Admin_Bar $wp_admin_bar
 */
function fpfi_picker_admin_bar($wp_admin_bar)
{
    if (is_admin() || !current_user_can('manage_options')) {
        return;
    }
    $current = add_query_arg(array('fpfi_picker' => '1'));
    $wp_admin_bar->add_node(array(
        'id'    => 'fpfi-pick-lcp',
        'title' => esc_html__('Pick LCP element', 'fetchpriority-featured-image'),
        'href'  => esc_url($current),
        'meta'  => array(
            'title' => esc_attr__('Click the hero/LCP image on this page to set it as the manual priority target for this template.', 'fetchpriority-featured-image'),
        ),
    ));
}
add_action('admin_bar_menu', 'fpfi_picker_admin_bar', 1000);

/**
 * Load the picker overlay when ?fpfi_picker=1 and the user can manage options.
 */
function fpfi_picker_enqueue()
{
    if (is_admin() || !current_user_can('manage_options')) {
        return;
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only UI toggle.
    if (empty($_GET['fpfi_picker'])) {
        return;
    }

    $handle = 'fpfi-picker';
    wp_register_script(
        $handle,
        plugins_url('assets/js/picker.js', FPFI_PLUGIN_FILE),
        array(),
        FETCHPRIORITY_FEATURED_IMAGE_VERSION,
        true
    );
    wp_localize_script($handle, 'FPFI_PICKER', array(
        'ajax'     => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('fpfi_picker'),
        'template' => fpfi_template_key(),
        'label'    => fpfi_template_label(fpfi_template_key()),
    ));
    wp_enqueue_script($handle);
}
add_action('wp_enqueue_scripts', 'fpfi_picker_enqueue');

/**
 * AJAX: save the picked element as the manual LCP override.
 */
function fpfi_picker_save()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'fetchpriority-featured-image')), 403);
    }
    if (!isset($_REQUEST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['nonce'])), 'fpfi_picker')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'fetchpriority-featured-image')), 403);
    }

    $template = isset($_REQUEST['template']) ? sanitize_text_field(wp_unslash($_REQUEST['template'])) : '';
    $url      = isset($_REQUEST['url']) ? esc_url_raw(wp_unslash($_REQUEST['url'])) : '';
    $is_bg    = !empty($_REQUEST['is_bg']);
    $selector = isset($_REQUEST['selector']) ? sanitize_text_field(wp_unslash($_REQUEST['selector'])) : '';

    if ($template === '' || $url === '') {
        wp_send_json_error(array('message' => __('Could not read a usable image URL from that element.', 'fetchpriority-featured-image')));
    }

    fpfi_lcp_set_manual($template, array(
        'url'      => $url,
        'is_bg'    => $is_bg,
        'selector' => $selector,
    ));

    wp_send_json_success(array(
        'message'  => __('Saved as manual LCP target for this template.', 'fetchpriority-featured-image'),
        'url'      => $url,
        'template' => $template,
    ));
}
add_action('wp_ajax_fpfi_picker_save', 'fpfi_picker_save');

/* -------------------------------------------------------------------------
 * AJAX: per-template mode + reset (used by the settings table)
 * ---------------------------------------------------------------------- */

function fpfi_lcp_save_mode_ajax()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'fetchpriority-featured-image')), 403);
    }
    if (!isset($_REQUEST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['nonce'])), 'fpfi_lcp_admin')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'fetchpriority-featured-image')), 403);
    }
    $template = isset($_REQUEST['template']) ? sanitize_text_field(wp_unslash($_REQUEST['template'])) : '';
    $mode     = isset($_REQUEST['mode']) ? sanitize_text_field(wp_unslash($_REQUEST['mode'])) : 'auto';
    if ($template === '') {
        wp_send_json_error(array('message' => __('Missing template.', 'fetchpriority-featured-image')));
    }
    fpfi_lcp_set_mode($template, $mode);
    wp_send_json_success(array('mode' => $mode));
}
add_action('wp_ajax_fpfi_lcp_save_mode', 'fpfi_lcp_save_mode_ajax');

function fpfi_lcp_reset_ajax()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'fetchpriority-featured-image')), 403);
    }
    if (!isset($_REQUEST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['nonce'])), 'fpfi_lcp_admin')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'fetchpriority-featured-image')), 403);
    }
    $hard = !empty($_REQUEST['hard']);
    fpfi_lcp_reset_learned($hard);
    wp_send_json_success();
}
add_action('wp_ajax_fpfi_lcp_reset', 'fpfi_lcp_reset_ajax');
