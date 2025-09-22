<?php
/**
 * Plugin Name: FetchPriority Featured Image
 * Plugin URI: https://github.com/gunjanjaswal/FetchPriority-Featured-Image
 * Description: Adds fetchpriority="high" attribute to featured images to improve page loading performance.
 * Version: 1.0.0
 * Author: Gunjan Jaswaal
 * Author URI: https://gunjanjaswal.me
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fetchpriority-featured-image
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * Tested up to: 6.8
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Current plugin version.
 */
define( 'FETCHPRIORITY_FEATURED_IMAGE_VERSION', '1.0.0' );

/**
 * Plugin activation hook.
 */
function fpfi_activate() {
    // Reset the coffee notice option on activation
    delete_option( 'fpfi_coffee_notice_dismissed' );
}
register_activation_hook( __FILE__, 'fpfi_activate' );

/**
 * Add fetchpriority="high" attribute to featured images.
 *
 * @param string $html The post thumbnail HTML.
 * @param int    $post_id The post ID.
 * @param int    $post_thumbnail_id The post thumbnail ID.
 * @param string $size The post thumbnail size.
 * @param array  $attr Query arguments.
 * @return string Modified HTML with fetchpriority attribute.
 */
function fpfi_add_fetchpriority_to_featured_image( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
    // Only add fetchpriority to featured images on single posts/pages or the first post on archive pages
    if ( is_singular() || ( is_home() || is_archive() || is_search() ) && ! isset( $GLOBALS['fpfi_first_post_processed'] ) ) {
        // Mark that we've processed the first post on archive pages
        if ( ! is_singular() ) {
            $GLOBALS['fpfi_first_post_processed'] = true;
        }
        
        // Add fetchpriority="high" attribute if it doesn't already exist
        if ( strpos( $html, 'fetchpriority=' ) === false ) {
            $html = str_replace( '<img ', '<img fetchpriority="high" ', $html );
        }
    }
    
    return $html;
}
add_filter( 'post_thumbnail_html', 'fpfi_add_fetchpriority_to_featured_image', 10, 5 );

/**
 * Reset the first post processed flag on each page load.
 */
function fpfi_reset_first_post_flag() {
    unset( $GLOBALS['fpfi_first_post_processed'] );
}
add_action( 'template_redirect', 'fpfi_reset_first_post_flag' );

/**
 * Add plugin action links.
 *
 * @param array $links Array of plugin action links.
 * @return array Modified array of plugin action links.
 */
function fpfi_plugin_action_links( $links ) {
    $coffee_link = '<a href="https://www.buymeacoffee.com/gunjanjaswal" target="_blank" style="color: #d9534f; font-weight: bold;">' . __( 'Buy Me a Coffee', 'fetchpriority-featured-image' ) . '</a>';
    array_unshift( $links, $coffee_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'fpfi_plugin_action_links' );

/**
 * Add plugin meta links.
 *
 * @param array  $links Array of plugin meta links.
 * @param string $file  Plugin file.
 * @return array Modified array of plugin meta links.
 */
function fpfi_plugin_meta_links( $links, $file ) {
    if ( plugin_basename( __FILE__ ) === $file ) {
        $links[] = '<a href="mailto:hello@gunjanjaswal.me">' . __( 'Contact Developer', 'fetchpriority-featured-image' ) . '</a>';
    }
    return $links;
}
add_filter( 'plugin_row_meta', 'fpfi_plugin_meta_links', 10, 2 );

/**
 * Display admin notice for Buy Me a Coffee.
 * Shows only once after plugin activation.
 */
function fpfi_admin_notice() {
    // Check if notice has been dismissed or shown
    if ( get_option( 'fpfi_coffee_notice_dismissed' ) ) {
        return;
    }
    
    // Only show on plugins page
    $screen = get_current_screen();
    if ( $screen->id !== 'plugins' ) {
        return;
    }
    
    // Display the notice
    ?>
    <div class="notice notice-info is-dismissible fpfi-coffee-notice">
        <p><?php echo wp_kses( 
            sprintf( 
                /* translators: %s: Link to Buy Me a Coffee */
                __( 'Thank you for using FetchPriority Featured Image! If you find it useful, please consider %s.', 'fetchpriority-featured-image' ),
                '<a href="https://www.buymeacoffee.com/gunjanjaswal" target="_blank">buying me a coffee</a>'
            ),
            array(
                'a' => array(
                    'href' => array(),
                    'target' => array()
                )
            )
        ); ?></p>
    </div>
    <script>
        jQuery(document).ready(function($) {
            $(document).on('click', '.fpfi-coffee-notice .notice-dismiss', function() {
                $.ajax({
                    url: ajaxurl,
                    data: {
                        action: 'fpfi_dismiss_coffee_notice'
                    }
                });
            });
        });
    </script>
    <?php
    
    // Mark notice as shown
    update_option( 'fpfi_coffee_notice_dismissed', true );
}
add_action( 'admin_notices', 'fpfi_admin_notice' );

/**
 * AJAX handler to dismiss the coffee notice.
 */
function fpfi_dismiss_coffee_notice() {
    update_option( 'fpfi_coffee_notice_dismissed', true );
    wp_die();
}
add_action( 'wp_ajax_fpfi_dismiss_coffee_notice', 'fpfi_dismiss_coffee_notice' );
