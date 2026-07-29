<?php
/**
 * Core Web Vitals (CrUX) before/after badge.
 *
 * Pulls field data (LCP / INP / CLS p75) from the Chrome UX Report API and
 * stores a baseline + latest snapshot so users can see the plugin's impact.
 *
 * @package fetchpriority-featured-image
 */

if (!defined('WPINC')) {
    die;
}

function fpfi_crux_option_name()
{
    return 'fpfi_crux_snapshots';
}

/**
 * Rating thresholds (good/needs-improvement boundaries), p75.
 *
 * @return array
 */
function fpfi_crux_thresholds()
{
    return array(
        'lcp' => array(2500, 4000),  // ms
        'inp' => array(200, 500),    // ms
        'cls' => array(0.1, 0.25),   // unitless
    );
}

/**
 * Bucket a metric value into good|ni|poor.
 *
 * @param string $metric
 * @param float  $value
 * @return string
 */
function fpfi_crux_rating($metric, $value)
{
    $t = fpfi_crux_thresholds();
    if (!isset($t[$metric])) {
        return 'na';
    }
    if ($value <= $t[$metric][0]) {
        return 'good';
    }
    if ($value <= $t[$metric][1]) {
        return 'ni';
    }
    return 'poor';
}

/**
 * Call the CrUX API for the site origin.
 *
 * @param string $api_key
 * @return array|WP_Error  [lcp,inp,cls] p75 values, or error.
 */
function fpfi_crux_query($api_key)
{
    $api_key = trim($api_key);
    if ($api_key === '') {
        return new WP_Error('no_key', __('Add a CrUX API key first.', 'fetchpriority-featured-image'));
    }

    $endpoint = 'https://chromeuxreport.googleapis.com/v1/records:queryRecord?key=' . rawurlencode($api_key);
    $body = array(
        'origin'  => home_url('/'),
        'metrics' => array('largest_contentful_paint', 'interaction_to_next_paint', 'cumulative_layout_shift'),
    );

    $resp = wp_remote_post($endpoint, array(
        'timeout' => 15,
        'headers' => array(
            'Content-Type' => 'application/json',
            // Send the site URL as the referer so an API key restricted to this
            // domain works from the server (a server request carries no referer,
            // which Google rejects with "Requests from referer <empty> are blocked").
            'Referer'      => home_url('/'),
        ),
        'body'    => wp_json_encode($body),
    ));

    if (is_wp_error($resp)) {
        return $resp;
    }

    $code = wp_remote_retrieve_response_code($resp);
    $json = json_decode(wp_remote_retrieve_body($resp), true);

    if ($code === 404) {
        return new WP_Error('no_data', __('No CrUX field data for this origin yet (low traffic). Try again later.', 'fetchpriority-featured-image'));
    }
    if ($code !== 200 || empty($json['record']['metrics'])) {
        $msg = isset($json['error']['message']) ? $json['error']['message'] : __('CrUX API request failed.', 'fetchpriority-featured-image');
        return new WP_Error('crux_fail', $msg);
    }

    $m = $json['record']['metrics'];
    $get = function ($key) use ($m) {
        return isset($m[$key]['percentiles']['p75']) ? (float) $m[$key]['percentiles']['p75'] : null;
    };

    return array(
        'lcp'     => $get('largest_contentful_paint'),
        'inp'     => $get('interaction_to_next_paint'),
        'cls'     => $get('cumulative_layout_shift'),
        'ts'      => time(),
        'origin'  => home_url('/'),
    );
}

/**
 * AJAX: fetch CrUX and store snapshot (baseline first, then latest).
 */
function fpfi_crux_ajax_fetch()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'fetchpriority-featured-image')), 403);
    }
    if (!isset($_REQUEST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['nonce'])), 'fpfi_crux')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'fetchpriority-featured-image')), 403);
    }

    $s = fpfi_get_settings();
    $result = fpfi_crux_query(isset($s['crux_api_key']) ? $s['crux_api_key'] : '');

    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    $snaps = get_option(fpfi_crux_option_name(), array());
    if (!is_array($snaps)) {
        $snaps = array();
    }

    $set_baseline = !empty($_REQUEST['set_baseline']);
    if (empty($snaps['baseline']) || $set_baseline) {
        $snaps['baseline'] = $result;
    }
    $snaps['latest'] = $result;
    update_option(fpfi_crux_option_name(), $snaps, false);

    wp_send_json_success(array(
        'snapshots' => $snaps,
        'html'      => fpfi_crux_render_table($snaps),
    ));
}
add_action('wp_ajax_fpfi_crux_fetch', 'fpfi_crux_ajax_fetch');

/**
 * Format a metric value for display.
 *
 * @param string     $metric
 * @param float|null $value
 * @return string
 */
function fpfi_crux_format($metric, $value)
{
    if ($value === null) {
        return '—';
    }
    if ($metric === 'cls') {
        return number_format((float) $value, 3);
    }
    return number_format((float) $value) . ' ms';
}

/**
 * Render the before/after comparison table.
 *
 * @param array $snaps
 * @return string
 */
function fpfi_crux_render_table($snaps)
{
    $baseline = isset($snaps['baseline']) ? $snaps['baseline'] : null;
    $latest   = isset($snaps['latest']) ? $snaps['latest'] : null;

    if (!$baseline && !$latest) {
        return '<p>' . esc_html__('No data yet. Click "Measure Core Web Vitals" to pull field data from the Chrome UX Report.', 'fetchpriority-featured-image') . '</p>';
    }

    $metrics = array(
        'lcp' => __('LCP (loading)', 'fetchpriority-featured-image'),
        'inp' => __('INP (interactivity)', 'fetchpriority-featured-image'),
        'cls' => __('CLS (stability)', 'fetchpriority-featured-image'),
    );

    $badge = function ($metric, $val) {
        if ($val === null) {
            return '<span class="fpfi-cwv-badge">—</span>';
        }
        $rating = fpfi_crux_rating($metric, $val);
        $colors = array('good' => '#0a7d28', 'ni' => '#bd7b00', 'poor' => '#c0341a', 'na' => '#787c82');
        $color = isset($colors[$rating]) ? $colors[$rating] : '#787c82';
        return '<span class="fpfi-cwv-badge" style="background:' . esc_attr($color) . ';color:#fff;padding:2px 8px;border-radius:10px;font-size:12px;">'
            . esc_html(fpfi_crux_format($metric, $val)) . '</span>';
    };

    $delta = function ($metric, $base, $now) {
        if ($base === null || $now === null) {
            return '';
        }
        $diff = $now - $base;
        if (abs($diff) < ($metric === 'cls' ? 0.001 : 1)) {
            return '<span style="color:#787c82;">±0</span>';
        }
        // Lower is better for all three; improvement = negative diff.
        $improved = $diff < 0;
        $arrow = $improved ? '▼' : '▲';
        $color = $improved ? '#0a7d28' : '#c0341a';
        $shown = $metric === 'cls' ? number_format(abs($diff), 3) : number_format(abs($diff)) . ' ms';
        return '<span style="color:' . esc_attr($color) . ';font-weight:600;">' . esc_html($arrow . ' ' . $shown) . '</span>';
    };

    ob_start();
    ?>
    <table class="widefat striped" style="max-width:680px;">
        <thead>
            <tr>
                <th><?php esc_html_e('Metric', 'fetchpriority-featured-image'); ?></th>
                <th><?php esc_html_e('Baseline', 'fetchpriority-featured-image'); ?></th>
                <th><?php esc_html_e('Latest', 'fetchpriority-featured-image'); ?></th>
                <th><?php esc_html_e('Change', 'fetchpriority-featured-image'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($metrics as $mkey => $mlabel) :
                $b = $baseline && isset($baseline[$mkey]) ? $baseline[$mkey] : null;
                $l = $latest && isset($latest[$mkey]) ? $latest[$mkey] : null;
                ?>
                <tr>
                    <td><strong><?php echo esc_html($mlabel); ?></strong></td>
                    <td><?php echo $badge($mkey, $b); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                    <td><?php echo $badge($mkey, $l); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                    <td><?php echo $delta($mkey, $b, $l); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    if ($baseline && isset($baseline['ts'])) {
        echo '<p class="description">' . sprintf(
            /* translators: %s: date */
            esc_html__('Baseline captured: %s', 'fetchpriority-featured-image'),
            esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $baseline['ts']))
        ) . '</p>';
    }
    return ob_get_clean();
}
