<?php
/**
 * WordPress dashboard widget.
 *
 * Surfaces the two most-used diagnostics — the Core Web Vitals (CrUX) snapshot
 * and the one-click PageSpeed audit — right on the main dashboard, so they are a
 * click away without opening the settings screen. Reuses the same renderers and
 * AJAX handlers as the settings page.
 *
 * @package FetchPriority_Featured_Image
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Register the dashboard widget (admins only — it uses the site API key).
 */
function fpfi_register_dashboard_widget()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    wp_add_dashboard_widget(
        'fpfi_dashboard_widget',
        __('FetchPriority — Core Web Vitals', 'fetchpriority-featured-image'),
        'fpfi_dashboard_widget_render'
    );
}
add_action('wp_dashboard_setup', 'fpfi_register_dashboard_widget');

/**
 * Render the dashboard widget body.
 */
function fpfi_dashboard_widget_render()
{
    $snaps = get_option(fpfi_crux_option_name(), array());
    $psi   = get_option(fpfi_psi_option_name(), array());
    ?>
    <div class="fpfi-dash">
        <h3 style="margin:0 0 8px;"><?php esc_html_e('Core Web Vitals', 'fetchpriority-featured-image'); ?>
            <span class="description" style="font-weight:400;">(<?php esc_html_e('Chrome UX Report', 'fetchpriority-featured-image'); ?>)</span>
        </h3>
        <p>
            <button type="button" class="button button-primary" id="fpfi-dash-crux-measure"><?php esc_html_e('Measure', 'fetchpriority-featured-image'); ?></button>
            <span id="fpfi-dash-crux-status" style="margin-left:8px;"></span>
        </p>
        <div id="fpfi-dash-crux-result"><?php
            echo fpfi_crux_render_table(is_array($snaps) ? $snaps : array()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?></div>

        <hr style="margin:16px 0;">

        <h3 style="margin:0 0 8px;"><?php esc_html_e('PageSpeed audit', 'fetchpriority-featured-image'); ?>
            <span class="description" style="font-weight:400;">(<?php esc_html_e('Lighthouse lab', 'fetchpriority-featured-image'); ?>)</span>
        </h3>
        <p>
            <input type="url" id="fpfi-dash-psi-url" class="regular-text" style="max-width:56%;" value="<?php echo esc_attr(home_url('/')); ?>">
            <select id="fpfi-dash-psi-strategy">
                <option value="mobile"><?php esc_html_e('Mobile', 'fetchpriority-featured-image'); ?></option>
                <option value="desktop"><?php esc_html_e('Desktop', 'fetchpriority-featured-image'); ?></option>
            </select>
            <button type="button" class="button button-primary" id="fpfi-dash-psi-run"><?php esc_html_e('Run audit', 'fetchpriority-featured-image'); ?></button>
            <span id="fpfi-dash-psi-status" style="margin-left:8px;"></span>
        </p>
        <div id="fpfi-dash-psi-result"><?php
            echo fpfi_psi_render(is_array($psi) ? $psi : array()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        ?></div>

        <p class="description" style="margin:12px 0 0;">
            <a href="<?php echo esc_url(admin_url('options-general.php?page=fetchpriority-featured-image')); ?>"><?php esc_html_e('Open FetchPriority settings →', 'fetchpriority-featured-image'); ?></a>
        </p>
    </div>
    <?php
}

/**
 * Enqueue the widget's script on the dashboard only.
 *
 * @param string $hook Current admin page.
 */
function fpfi_dashboard_assets($hook)
{
    if ('index.php' !== $hook || !current_user_can('manage_options')) {
        return;
    }

    wp_enqueue_script('jquery');
    $data = array(
        'ajax'      => admin_url('admin-ajax.php'),
        'cruxNonce' => wp_create_nonce('fpfi_crux'),
        'psiNonce'  => wp_create_nonce('fpfi_psi'),
        'measuring' => __('Measuring…', 'fetchpriority-featured-image'),
        'running'   => __('Running Lighthouse (up to a minute)…', 'fetchpriority-featured-image'),
        'error'     => __('Error.', 'fetchpriority-featured-image'),
    );

    // DOM-ready wrapped: the dashboard prints jQuery in the <head>, so binding
    // must wait for the widget markup to exist.
    $js = '
    jQuery(function($){
        var D = ' . wp_json_encode($data) . ';
        function notice($box, msg){
            $box.html($("<div class=\'notice notice-error inline\'><p></p></div>").find("p").text(msg).end());
        }
        $("#fpfi-dash-crux-measure").on("click", function(){
            var $s = $("#fpfi-dash-crux-status").text(D.measuring);
            $.post(D.ajax, {action:"fpfi_crux_fetch", nonce:D.cruxNonce})
            .done(function(r){
                $s.text("");
                if(r && r.success){ $("#fpfi-dash-crux-result").html(r.data.html); }
                else { notice($("#fpfi-dash-crux-result"), (r && r.data && r.data.message) ? r.data.message : D.error); }
            }).fail(function(){ $s.text(""); notice($("#fpfi-dash-crux-result"), D.error); });
        });
        $("#fpfi-dash-psi-run").on("click", function(){
            var $s = $("#fpfi-dash-psi-status").text(D.running);
            $.post(D.ajax, {action:"fpfi_psi_fetch", nonce:D.psiNonce, url:$("#fpfi-dash-psi-url").val(), strategy:$("#fpfi-dash-psi-strategy").val()})
            .done(function(r){
                $s.text("");
                if(r && r.success){ $("#fpfi-dash-psi-result").html(r.data.html); }
                else { notice($("#fpfi-dash-psi-result"), (r && r.data && r.data.message) ? r.data.message : D.error); }
            }).fail(function(){ $s.text(""); notice($("#fpfi-dash-psi-result"), D.error); });
        });
    });
    ';
    wp_add_inline_script('jquery', $js);
}
add_action('admin_enqueue_scripts', 'fpfi_dashboard_assets');
