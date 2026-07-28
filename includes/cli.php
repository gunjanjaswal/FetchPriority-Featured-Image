<?php
/**
 * WP-CLI commands: `wp fpfi ...`
 *
 * Read and reset the learned LCP data, and export/import/reset settings from the
 * command line — useful when managing many sites or scripting deployments.
 *
 * @package FetchPriority_Featured_Image
 */

if (!defined('WPINC')) {
    die;
}

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * Manage FetchPriority Featured Image from the command line.
 */
class FPFI_CLI_Command
{

    /**
     * List the learned / manual LCP target for each template.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. One of table, csv, json, yaml. Default: table.
     *
     * ## EXAMPLES
     *
     *     wp fpfi lcp list
     *     wp fpfi lcp list --format=json
     *
     * @subcommand lcp-list
     *
     * @param array $args       Positional args (unused).
     * @param array $assoc_args Flags.
     */
    public function lcp_list($args, $assoc_args)
    {
        $all  = fpfi_lcp_get_all();
        $rows = array();

        foreach ($all as $key => $rec) {
            $learned = isset($rec['learned']) ? $rec['learned'] : array();
            $font    = isset($rec['font']) ? $rec['font'] : array();
            $avg_ms  = function_exists('fpfi_lcp_avg_ms') ? fpfi_lcp_avg_ms($rec) : 0;
            $rows[] = array(
                'template'  => $key,
                'mode'      => isset($rec['mode']) ? $rec['mode'] : 'auto',
                'lcp_url'   => !empty($learned['url']) ? $learned['url'] : '—',
                'is_bg'     => !empty($learned['is_bg']) ? 'yes' : 'no',
                'font'      => !empty($font['url']) ? $font['url'] : '—',
                'samples'   => !empty($learned['samples']) ? (int) $learned['samples'] : 0,
                'avg_ms'    => $avg_ms ? (int) $avg_ms : 0,
            );
        }

        if (empty($rows)) {
            WP_CLI::log('No learned LCP data yet. It builds up as real visitors load your pages.');
            return;
        }

        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'table';
        WP_CLI\Utils\format_items(
            $format,
            $rows,
            array('template', 'mode', 'lcp_url', 'is_bg', 'font', 'samples', 'avg_ms')
        );
    }

    /**
     * Clear the learned LCP data.
     *
     * ## OPTIONS
     *
     * [--hard]
     * : Also clear manual picks and per-template modes, not just the measured data.
     *
     * ## EXAMPLES
     *
     *     wp fpfi lcp reset
     *     wp fpfi lcp reset --hard
     *
     * @subcommand lcp-reset
     *
     * @param array $args       Positional args (unused).
     * @param array $assoc_args Flags.
     */
    public function lcp_reset($args, $assoc_args)
    {
        $hard = isset($assoc_args['hard']);
        fpfi_lcp_reset_learned($hard);
        WP_CLI::success($hard ? 'Cleared all learned LCP data, manual picks and modes.' : 'Cleared learned LCP data. Manual picks kept.');
    }

    /**
     * Export settings as JSON.
     *
     * ## OPTIONS
     *
     * [--file=<path>]
     * : Write to this file instead of printing to STDOUT.
     *
     * ## EXAMPLES
     *
     *     wp fpfi settings-export
     *     wp fpfi settings-export --file=fpfi.json
     *
     * @subcommand settings-export
     *
     * @param array $args       Positional args (unused).
     * @param array $assoc_args Flags.
     */
    public function settings_export($args, $assoc_args)
    {
        $json = wp_json_encode(fpfi_build_export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (!empty($assoc_args['file'])) {
            $ok = file_put_contents($assoc_args['file'], $json); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            if ($ok === false) {
                WP_CLI::error('Could not write to ' . $assoc_args['file']);
            }
            WP_CLI::success('Settings written to ' . $assoc_args['file']);
            return;
        }

        WP_CLI::line($json);
    }

    /**
     * Import settings from a JSON file produced by export.
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to the JSON file.
     *
     * ## EXAMPLES
     *
     *     wp fpfi settings-import fpfi.json
     *
     * @subcommand settings-import
     *
     * @param array $args       Positional args: file path.
     * @param array $assoc_args Flags (unused).
     */
    public function settings_import($args, $assoc_args)
    {
        if (empty($args[0]) || !file_exists($args[0])) {
            WP_CLI::error('File not found. Pass the path to an exported JSON file.');
        }
        $raw = file_get_contents($args[0]); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $bundle = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            WP_CLI::error('That file is not valid JSON.');
        }
        if (!fpfi_apply_import($bundle)) {
            WP_CLI::error('That file is not a FetchPriority settings export.');
        }
        WP_CLI::success('Settings imported.');
    }

    /**
     * Reset all settings to their defaults (keeps the saved API key).
     *
     * ## EXAMPLES
     *
     *     wp fpfi settings-reset
     *
     * @subcommand settings-reset
     */
    public function settings_reset()
    {
        $current  = fpfi_get_settings();
        $defaults = fpfi_default_settings();
        foreach (fpfi_porting_excluded_keys() as $key) {
            if (isset($current[$key])) {
                $defaults[$key] = $current[$key];
            }
        }
        update_option('fpfi_settings', $defaults);
        WP_CLI::success('Settings reset to defaults.');
    }
}

WP_CLI::add_command('fpfi', 'FPFI_CLI_Command');
