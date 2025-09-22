<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package FetchPriority_Featured_Image
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Clean up plugin options
delete_option( 'fpfi_coffee_notice_dismissed' );
