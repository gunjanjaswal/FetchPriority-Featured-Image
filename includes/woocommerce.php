<?php
/**
 * WooCommerce integration.
 *
 * Shop archives and product-category pages already flow through the generic
 * archive path (their thumbnails render via wp_get_attachment_image), so the
 * existing first-N budget covers them. The one markup path that bypasses the
 * standard hooks is the single-product gallery, which WooCommerce builds with
 * its own template. This module tags that main gallery image as the hero.
 *
 * @package FetchPriority_Featured_Image
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Whether WooCommerce is active.
 *
 * @return bool
 */
function fpfi_wc_active()
{
    return class_exists('WooCommerce');
}

/**
 * Prioritise the main image in the single-product gallery.
 *
 * WooCommerce renders each gallery slide through this filter. Only the first
 * slide is the hero; it gets the same treatment a content hero would (respecting
 * the learned/manual LCP and the per-request high budget). Later slides can be
 * demoted to fetchpriority="low" when below-fold priority is enabled.
 *
 * @param string $html          Gallery image markup.
 * @param int    $attachment_id Attachment ID (unused, kept for the filter signature).
 * @return string
 */
function fpfi_wc_gallery_image($html, $attachment_id = 0)
{
    $s = fpfi_get_settings();
    if (empty($s['enable_woocommerce'])) {
        return $html;
    }
    if (!function_exists('is_product') || !is_product()) {
        return $html;
    }
    if (!fpfi_context_enabled()) {
        return $html;
    }
    if (strpos($html, 'fetchpriority=') !== false) {
        // WooCommerce (or the attachment filter) already decided this one.
        $GLOBALS['fpfi_wc_gallery_done'] = true;
        return $html;
    }
    if (fpfi_html_has_excluded_class($html) || fpfi_html_is_gravatar($html)) {
        return $html;
    }

    // Gallery slides after the hero: optionally defer.
    if (!empty($GLOBALS['fpfi_wc_gallery_done'])) {
        if (!empty($s['enable_low_below_fold'])) {
            $html = str_replace('<img ', '<img fetchpriority="low" ', $html);
            $html = fpfi_apply_loading_optim($html, 'low');
        }
        return $html;
    }
    $GLOBALS['fpfi_wc_gallery_done'] = true;

    // Measured/manual LCP wins; otherwise fall back to the standard budget.
    if (fpfi_lcp_matches_html($html)) {
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
add_filter('woocommerce_single_product_image_thumbnail_html', 'fpfi_wc_gallery_image', 10, 2);

/**
 * Friendlier labels for the WooCommerce template keys in the admin tables.
 *
 * @param string $label Resolved label.
 * @param string $key   Template key.
 * @return string
 */
function fpfi_wc_template_label($label, $key)
{
    if (!fpfi_wc_active()) {
        return $label;
    }
    $map = array(
        'single:product'       => __('Product pages', 'fetchpriority-featured-image'),
        'cpt_archive:product'  => __('Shop (product archive)', 'fetchpriority-featured-image'),
        'tax:product_cat'      => __('Product categories', 'fetchpriority-featured-image'),
        'tax:product_tag'      => __('Product tags', 'fetchpriority-featured-image'),
    );
    return isset($map[$key]) ? $map[$key] : $label;
}
add_filter('fpfi_template_label', 'fpfi_wc_template_label', 10, 2);
