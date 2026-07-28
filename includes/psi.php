<?php
/**
 * PageSpeed Insights (Lighthouse lab) one-click audit.
 *
 * Cross-checks Google's own detected LCP element against the plugin's learned
 * LCP, and surfaces image-weight opportunities.
 *
 * @package fetchpriority-featured-image
 */

if (!defined('WPINC')) {
    die;
}

function fpfi_psi_option_name()
{
    return 'fpfi_psi_result';
}

/**
 * Run a PageSpeed Insights audit for a URL.
 *
 * @param string $url
 * @param string $strategy mobile|desktop
 * @param string $api_key  Optional Google API key (PSI works keyless at low volume).
 * @return array|WP_Error
 */
function fpfi_psi_query($url, $strategy = 'mobile', $api_key = '')
{
    $url = esc_url_raw($url);
    if (!$url) {
        return new WP_Error('bad_url', __('Invalid URL.', 'fetchpriority-featured-image'));
    }
    $strategy = ($strategy === 'desktop') ? 'desktop' : 'mobile';

    $base = array(
        'url'      => $url,
        'strategy' => $strategy,
        'category' => 'performance',
    );

    // Try the request, optionally with a key.
    $run = function ($with_key) use ($base, $api_key) {
        $query = $base;
        if ($with_key && trim($api_key) !== '') {
            $query['key'] = trim($api_key);
        }
        $endpoint = add_query_arg($query, 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed');
        return wp_remote_get($endpoint, array('timeout' => 60));
    };

    $has_key = trim($api_key) !== '';
    $resp    = $run($has_key);

    $code = is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp);
    $json = is_wp_error($resp) ? null : json_decode(wp_remote_retrieve_body($resp), true);

    // PSI is a lab test and works without a key at low volume. A key created only
    // for the Chrome UX Report (very common) gets rejected here, so if the keyed
    // request failed, fall back to a keyless one before giving up.
    if ($has_key && ($code !== 200 || empty($json['lighthouseResult']))) {
        $resp = $run(false);
        $code = is_wp_error($resp) ? 0 : wp_remote_retrieve_response_code($resp);
        $json = is_wp_error($resp) ? null : json_decode(wp_remote_retrieve_body($resp), true);
    }

    if (is_wp_error($resp)) {
        return $resp;
    }

    if ($code !== 200 || empty($json['lighthouseResult'])) {
        $msg = isset($json['error']['message']) ? $json['error']['message'] : __('PageSpeed Insights request failed (try again — the API is rate-limited without a key).', 'fetchpriority-featured-image');
        return new WP_Error('psi_fail', $msg);
    }

    $lh     = $json['lighthouseResult'];
    $audits = isset($lh['audits']) ? $lh['audits'] : array();

    $audit_num = function ($key) use ($audits) {
        return isset($audits[$key]['numericValue']) ? (float) $audits[$key]['numericValue'] : null;
    };
    $audit_disp = function ($key) use ($audits) {
        return isset($audits[$key]['displayValue']) ? (string) $audits[$key]['displayValue'] : '';
    };

    // Google's detected LCP element snippet.
    $lcp_element = '';
    if (!empty($audits['largest-contentful-paint-element']['details']['items'][0]['items'][0]['node']['snippet'])) {
        $lcp_element = $audits['largest-contentful-paint-element']['details']['items'][0]['items'][0]['node']['snippet'];
    }

    $savings = function ($key) use ($audits) {
        return isset($audits[$key]['details']['overallSavingsBytes']) ? (int) $audits[$key]['details']['overallSavingsBytes'] : 0;
    };

    return array(
        'url'           => $url,
        'strategy'      => $strategy,
        'score'         => isset($lh['categories']['performance']['score']) ? (int) round($lh['categories']['performance']['score'] * 100) : null,
        'lcp_ms'        => $audit_num('largest-contentful-paint'),
        'lcp_display'   => $audit_disp('largest-contentful-paint'),
        'lcp_element'   => $lcp_element,
        'total_bytes'   => $audit_disp('total-byte-weight'),
        'save_modern'   => $savings('modern-image-formats'),
        'save_responsive' => $savings('uses-responsive-images'),
        'save_optimized' => $savings('uses-optimized-images'),
        'ts'            => time(),
    );
}

/**
 * AJAX: run PSI and store the result.
 */
function fpfi_psi_ajax_fetch()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Permission denied.', 'fetchpriority-featured-image')), 403);
    }
    if (!isset($_REQUEST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['nonce'])), 'fpfi_psi')) {
        wp_send_json_error(array('message' => __('Security check failed.', 'fetchpriority-featured-image')), 403);
    }

    $s        = fpfi_get_settings();
    $url      = isset($_REQUEST['url']) && $_REQUEST['url'] !== '' ? esc_url_raw(wp_unslash($_REQUEST['url'])) : home_url('/');
    $strategy = isset($_REQUEST['strategy']) ? sanitize_text_field(wp_unslash($_REQUEST['strategy'])) : 'mobile';
    $key      = isset($s['crux_api_key']) ? $s['crux_api_key'] : '';

    $result = fpfi_psi_query($url, $strategy, $key);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    }

    update_option(fpfi_psi_option_name(), $result, false);
    wp_send_json_success(array('html' => fpfi_psi_render($result)));
}
add_action('wp_ajax_fpfi_psi_fetch', 'fpfi_psi_ajax_fetch');

/**
 * Human-readable byte savings.
 *
 * @param int $bytes
 * @return string
 */
function fpfi_psi_bytes($bytes)
{
    if ($bytes <= 0) {
        return '—';
    }
    return size_format($bytes, ($bytes >= 1048576 ? 1 : 0));
}

/**
 * Render the PSI result panel.
 *
 * @param array $r
 * @return string
 */
function fpfi_psi_render($r)
{
    if (empty($r) || !is_array($r)) {
        return '<p>' . esc_html__('No audit yet. Click "Run PageSpeed audit" to test a URL with Google Lighthouse.', 'fetchpriority-featured-image') . '</p>';
    }

    $score = isset($r['score']) ? (int) $r['score'] : null;
    $score_color = '#787c82';
    if ($score !== null) {
        $score_color = $score >= 90 ? '#0a7d28' : ($score >= 50 ? '#bd7b00' : '#c0341a');
    }

    ob_start();
    ?>
    <div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;margin:10px 0;">
        <div style="text-align:center;">
            <div style="width:64px;height:64px;border-radius:50%;border:6px solid <?php echo esc_attr($score_color); ?>;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:<?php echo esc_attr($score_color); ?>;">
                <?php echo $score === null ? '—' : esc_html($score); ?>
            </div>
            <div style="font-size:11px;margin-top:4px;"><?php esc_html_e('Performance', 'fetchpriority-featured-image'); ?></div>
        </div>
        <div>
            <strong><?php esc_html_e('LCP:', 'fetchpriority-featured-image'); ?></strong>
            <?php echo esc_html($r['lcp_display'] !== '' ? $r['lcp_display'] : '—'); ?>
            &nbsp;·&nbsp;<strong><?php esc_html_e('Page weight:', 'fetchpriority-featured-image'); ?></strong>
            <?php echo esc_html($r['total_bytes'] !== '' ? $r['total_bytes'] : '—'); ?>
            <br>
            <span class="description" style="font-size:11px;">
                <?php
                printf(
                    /* translators: 1: strategy, 2: url */
                    esc_html__('%1$s · %2$s', 'fetchpriority-featured-image'),
                    esc_html(ucfirst($r['strategy'])),
                    esc_html($r['url'])
                );
                ?>
            </span>
        </div>
    </div>

    <?php if (!empty($r['lcp_element'])) : ?>
        <p style="margin:6px 0;">
            <strong><?php esc_html_e("Google's detected LCP element:", 'fetchpriority-featured-image'); ?></strong>
            <code style="display:inline-block;max-width:100%;overflow:auto;font-size:11px;background:#f6f7f7;padding:2px 6px;"><?php echo esc_html($r['lcp_element']); ?></code>
        </p>
    <?php endif; ?>

    <table class="widefat striped" style="max-width:520px;">
        <tbody>
            <tr>
                <td><?php esc_html_e('Savings from modern formats (AVIF/WebP)', 'fetchpriority-featured-image'); ?></td>
                <td><strong><?php echo esc_html(fpfi_psi_bytes((int) $r['save_modern'])); ?></strong></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Savings from properly-sized images', 'fetchpriority-featured-image'); ?></td>
                <td><strong><?php echo esc_html(fpfi_psi_bytes((int) $r['save_responsive'])); ?></strong></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Savings from image compression', 'fetchpriority-featured-image'); ?></td>
                <td><strong><?php echo esc_html(fpfi_psi_bytes((int) $r['save_optimized'])); ?></strong></td>
            </tr>
        </tbody>
    </table>
    <p class="description">
        <?php
        echo esc_html(sprintf(
            /* translators: %s: date */
            __('Last run: %s', 'fetchpriority-featured-image'),
            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $r['ts'])
        ));
        ?>
    </p>
    <?php
    return ob_get_clean();
}
