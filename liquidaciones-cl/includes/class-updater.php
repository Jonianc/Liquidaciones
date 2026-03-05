<?php
if ( ! defined('ABSPATH') ) { exit; }

/**
 * CL_LIQ_Updater
 * - Monthly-ish maintenance via WP-Cron (runs daily, applies once per month).
 * - Ensures period posts exist for selector window.
 * - Optionally propagates / fetches UF values.
 * - Can copy last known tax table into current month if missing.
 *
 * IMPORTANT: Any auto-updated values should be reviewed before issuing payroll.
 */
final class CL_LIQ_Updater {

    const CRON_HOOK = 'cl_liq_monthly_tick';
    const OPT_LOG = 'cl_liq_update_log';
    const OPT_SNAPSHOT = 'cl_liq_update_snapshot';
    const OPT_LAST_RUN_YM = 'cl_liq_last_run_ym';
    const OPT_LAST_RUN_TS = 'cl_liq_last_run_ts';

    public static function init() {
        add_action('init', [__CLASS__, 'maybe_schedule'], 9);
        add_action(self::CRON_HOOK, [__CLASS__, 'cron_tick']);
    }

    public static function activate() {
        self::maybe_schedule(true);
    }

    private static function auto_settings(): array {
        $s = CL_LIQ_Settings::get();
        $au = $s['auto_update'] ?? [];
        $au = wp_parse_args($au, [
            'enabled' => 0,
            'mode' => 'suggest', // suggest|apply
            'months_back' => 12,
            'months_forward' => 3,
            'uf_source' => 'copy_prev', // copy_prev|http
            'uf_http_url' => '', // template: https://.../{date} where {date}=YYYY-MM-DD
        ]);
        $au['enabled'] = (int) ($au['enabled'] ?? 0);
        $au['months_back'] = max(1, (int) ($au['months_back'] ?? 12));
        $au['months_forward'] = max(0, (int) ($au['months_forward'] ?? 3));
        $au['mode'] = ($au['mode'] === 'apply') ? 'apply' : 'suggest';
        $au['uf_source'] = ($au['uf_source'] === 'http') ? 'http' : 'copy_prev';
        $au['uf_http_url'] = (string) ($au['uf_http_url'] ?? '');
        return $au;
    }

    public static function maybe_schedule(bool $force = false) {
        $au = self::auto_settings();
        if (!$force && (int)$au['enabled'] !== 1) {
            return;
        }

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // schedule daily (we will gate to run once per month)
            $ts = (int) current_time('timestamp');
            $next = $ts + 300; // 5 minutes from now
            wp_schedule_event($next, 'daily', self::CRON_HOOK);
        }
    }

    public static function cron_tick() {
        $au = self::auto_settings();
        if ((int)$au['enabled'] !== 1) return;

        $ym = CL_LIQ_Helpers::current_ym();
        $last = (string) get_option(self::OPT_LAST_RUN_YM, '');
        if ($last === $ym) return;

        self::run_monthly_update(false);
    }

    /**
     * Run monthly update. If $manual=true, runs regardless of month gate.
     * Returns array summary.
     */
    public static function run_monthly_update(bool $manual = false): array {
        $au = self::auto_settings();
        if ((int)$au['enabled'] !== 1 && !$manual) {
            return ['ok' => false, 'msg' => 'Auto-update desactivado.'];
        }

        $ym = CL_LIQ_Helpers::current_ym();
        if (!$manual) {
            $last = (string) get_option(self::OPT_LAST_RUN_YM, '');
            if ($last === $ym) return ['ok' => true, 'msg' => 'Ya ejecutado este mes.'];
        }

        $changes = [
            'periodos_creados' => 0,
            'periodos_actualizados_uf' => 0,
            'tax_table_copiada' => 0,
        ];

        // Snapshot for rollback (last snapshot only)
        self::snapshot();

        // 1) Ensure periods exist for selector window
        $period_map = self::ensure_periods_window((int)$au['months_back'], (int)$au['months_forward']);
        $changes['periodos_creados'] = (int) ($period_map['_created'] ?? 0);

        // 2) UF propagation/fetch
        $updated_uf = self::update_uf_for_periods($period_map, $au);
        $changes['periodos_actualizados_uf'] = $updated_uf;

        // 3) If current month tax table missing, copy last known
        $tax_copied = self::ensure_tax_table_for_month($ym);
        $changes['tax_table_copiada'] = $tax_copied ? 1 : 0;

        update_option(self::OPT_LAST_RUN_YM, $ym, false);
        update_option(self::OPT_LAST_RUN_TS, time(), false);

        self::log('run', 'Actualización mensual ejecutada', $changes);
        CL_LIQ_Helpers::plugin_log('info', 'Updater mensual ejecutado', $changes);
        if (class_exists('CL_LIQ_Audit')) {
            CL_LIQ_Audit::log_event('updater_run', 'Actualización mensual ejecutada', $changes, 'updater');
        }

        return ['ok' => true, 'msg' => 'Actualización ejecutada', 'changes' => $changes];
    }

    private static function snapshot() {
        $settings = CL_LIQ_Settings::get();
        $au = self::auto_settings();

        // snapshot period UF values for window (and current existing)
        $window = self::compute_window_yms((int)$au['months_back'], (int)$au['months_forward']);
        $periods = [];
        foreach ($window as $ym) {
            $pid = self::get_period_id_by_ym($ym);
            if ($pid) {
                $periods[$pid] = [
                    'ym' => (string) get_post_meta($pid, 'cl_ym', true),
                    'uf' => (string) get_post_meta($pid, 'cl_uf_value', true),
                ];
            }
        }

        $snap = [
            'ts' => time(),
            'settings' => $settings,
            'periods' => $periods,
        ];
        update_option(self::OPT_SNAPSHOT, $snap, false);
    }

    public static function rollback_last(): array {
        $snap = get_option(self::OPT_SNAPSHOT, null);
        if (!is_array($snap) || empty($snap['settings'])) {
            return ['ok' => false, 'msg' => 'No hay snapshot para rollback.'];
        }

        update_option(CL_LIQ_Settings::OPTION_KEY, $snap['settings'], false);

        if (!empty($snap['periods']) && is_array($snap['periods'])) {
            foreach ($snap['periods'] as $pid => $row) {
                $pid = (int) $pid;
                if ($pid <= 0) continue;
                if (!get_post($pid)) continue;
                update_post_meta($pid, 'cl_ym', sanitize_text_field((string)($row['ym'] ?? '')));
                update_post_meta($pid, 'cl_uf_value', CL_LIQ_Helpers::parse_decimal($row['uf'] ?? 0));
            }
        }

        self::log('rollback', 'Rollback aplicado (snapshot restaurado)', ['ts' => (int)($snap['ts'] ?? 0)]);
        CL_LIQ_Helpers::plugin_log('warning', 'Updater rollback aplicado', ['snapshot_ts' => (int)($snap['ts'] ?? 0)]);
        if (class_exists('CL_LIQ_Audit')) {
            CL_LIQ_Audit::log_event('updater_rollback', 'Rollback aplicado', ['snapshot_ts' => (int)($snap['ts'] ?? 0)], 'updater');
        }
        return ['ok' => true, 'msg' => 'Rollback aplicado.'];
    }

    private static function log(string $type, string $message, array $data = []) {
        $log = get_option(self::OPT_LOG, []);
        if (!is_array($log)) $log = [];

        array_unshift($log, [
            'ts' => time(),
            'type' => sanitize_text_field($type),
            'message' => sanitize_text_field($message),
            'data' => $data,
            'user' => get_current_user_id(),
        ]);

        // keep last 50
        $log = array_slice($log, 0, 50);
        update_option(self::OPT_LOG, $log, false);
    }

    public static function get_log(int $limit = 10): array {
        $log = get_option(self::OPT_LOG, []);
        if (!is_array($log)) return [];
        return array_slice($log, 0, max(1, $limit));
    }

    // ----------------------------
    // Period helpers
    // ----------------------------

    private static function compute_window_yms(int $months_back, int $months_forward): array {
        $months_back = max(1, $months_back);
        $months_forward = max(0, $months_forward);

        $current = CL_LIQ_Helpers::current_ym();

        // include current month, so back = months_back-1
        $yms = [];
        for ($i = ($months_back - 1) * -1; $i <= $months_forward; $i++) {
            $yms[] = CL_LIQ_Helpers::ym_add($current, $i);
        }
        // Unique and keep order
        $out = [];
        foreach ($yms as $ym) {
            if (!in_array($ym, $out, true)) $out[] = $ym;
        }
        return $out;
    }

    public static function ensure_periods_window(int $months_back = 12, int $months_forward = 3): array {
        $yms = self::compute_window_yms($months_back, $months_forward);
        $created = 0;

        $map = [];
        foreach ($yms as $ym) {
            $pid = self::ensure_period($ym);
            if ($pid && !isset($map[$ym])) $map[$ym] = $pid;
            if ($pid && (int) get_post_meta($pid, 'cl_uf_auto_created', true) === 1) {
                // marker used only on creation; keep counting once
                delete_post_meta($pid, 'cl_uf_auto_created');
                $created++;
            }
        }

        $map['_created'] = $created;
        return $map;
    }

    public static function get_period_id_by_ym(string $ym): int {
        $ym = trim($ym);
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) return 0;

        $ids = get_posts([
            'post_type' => 'cl_periodo',
            'post_status' => 'any',
            'numberposts' => 1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'cl_ym',
                    'value' => $ym,
                    'compare' => '=',
                ]
            ],
        ]);

        if ($ids) return (int) $ids[0];
        return 0;
    }

    public static function ensure_period(string $ym): int {
        $ym = trim($ym);
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) return 0;

        $existing = self::get_period_id_by_ym($ym);
        if ($existing) return $existing;

        $pid = wp_insert_post([
            'post_type' => 'cl_periodo',
            'post_status' => 'publish',
            'post_title' => $ym,
        ], true);

        if (is_wp_error($pid)) return 0;

        $pid = (int) $pid;
        update_post_meta($pid, 'cl_ym', $ym);
        // UF will be filled by update routine; mark created
        update_post_meta($pid, 'cl_uf_auto_created', 1);

        return $pid;
    }

    public static function get_periods_for_selector(int $selected_id = 0): array {
        $au = self::auto_settings();
        $map = self::ensure_periods_window((int)$au['months_back'], (int)$au['months_forward']);
        unset($map['_created']);

        // If selected is outside window, include it as extra
        if ($selected_id > 0 && get_post($selected_id)) {
            $ym_sel = (string) get_post_meta($selected_id, 'cl_ym', true);
            if ($ym_sel && !isset($map[$ym_sel])) {
                $map[$ym_sel] = $selected_id;
            }
        }

        // Order by ym descending (most recent first), but keep window ordering as "current +-"
        $items = [];
        foreach ($map as $ym => $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) continue;
            $uf = (string) get_post_meta($pid, 'cl_uf_value', true);
            $items[] = [
                'id' => $pid,
                'ym' => $ym,
                'label' => CL_LIQ_Helpers::ym_label($ym),
                'uf' => $uf,
            ];
        }

        usort($items, function($a, $b) {
            return strcmp($b['ym'], $a['ym']);
        });

        return $items;
    }

    public static function guess_uf_for_ym(string $ym): float {
        // default: copy UF from most recent existing period
        $latest = get_posts([
            'post_type' => 'cl_periodo',
            'post_status' => 'any',
            'numberposts' => 1,
            'orderby' => 'meta_value',
            'meta_key' => 'cl_ym',
            'order' => 'DESC',
        ]);
        if ($latest) {
            $uf = (float) CL_LIQ_Helpers::parse_decimal(get_post_meta($latest[0]->ID, 'cl_uf_value', true));
            if ($uf > 0) return $uf;
        }
        return 0.0;
    }

    private static function update_uf_for_periods(array $period_map, array $au): int {
        $updated = 0;

        // Determine fallback UF (copy from latest)
        $fallback_uf = self::guess_uf_for_ym(CL_LIQ_Helpers::current_ym());

        foreach ($period_map as $ym => $pid) {
            if ($ym === '_created') continue;
            $pid = (int) $pid;
            if ($pid <= 0) continue;

            $current_uf = (float) CL_LIQ_Helpers::parse_decimal(get_post_meta($pid, 'cl_uf_value', true));
            if ($current_uf > 0) continue; // don't overwrite

            $new_uf = 0.0;

            if ($au['uf_source'] === 'http' && !empty($au['uf_http_url'])) {
                $new_uf = self::fetch_uf_http($au['uf_http_url'], $ym);
            }

            if ($new_uf <= 0) {
                $new_uf = $fallback_uf;
            }

            if ($new_uf > 0) {
                update_post_meta($pid, 'cl_uf_value', $new_uf);
                update_post_meta($pid, 'cl_uf_auto', 1);
                $updated++;
            }
        }

        return $updated;
    }

    private static function fetch_uf_http(string $template, string $ym): float {
        // Template uses {date} placeholder, where {date} = last day of month (YYYY-MM-DD)
        $date = CL_LIQ_Helpers::ym_last_day($ym);
        $url = str_replace('{date}', rawurlencode($date), $template);
        $url = esc_url_raw($url);
        if (!$url) {
            CL_LIQ_Helpers::plugin_log('warning', 'UF HTTP URL inválida', ['ym' => $ym]);
            return 0.0;
        }

        $resp = wp_remote_get($url, ['timeout' => 8]);
        if (is_wp_error($resp)) {
            CL_LIQ_Helpers::plugin_log('warning', 'UF HTTP request error', ['ym' => $ym, 'url' => $url, 'error' => $resp->get_error_message()]);
            return 0.0;
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) {
            CL_LIQ_Helpers::plugin_log('warning', 'UF HTTP status inválido', ['ym' => $ym, 'url' => $url, 'status' => $code]);
            return 0.0;
        }

        $body = (string) wp_remote_retrieve_body($resp);
        if (!$body) {
            CL_LIQ_Helpers::plugin_log('warning', 'UF HTTP respuesta vacía', ['ym' => $ym, 'url' => $url]);
            return 0.0;
        }

        // Expect JSON with a "valor" field (common in several indicator APIs), but keep it flexible:
        $json = json_decode($body, true);
        if (!is_array($json)) {
            CL_LIQ_Helpers::plugin_log('warning', 'UF HTTP JSON inválido', ['ym' => $ym, 'url' => $url]);
            return 0.0;
        }

        // Try common paths
        if (isset($json['serie'][0]['valor'])) {
            $val = (float) CL_LIQ_Helpers::parse_decimal($json['serie'][0]['valor']);
            CL_LIQ_Helpers::plugin_log('debug', 'UF HTTP valor obtenido (serie)', ['ym' => $ym, 'value' => $val]);
            return $val;
        }
        if (isset($json['valor'])) {
            $val = (float) CL_LIQ_Helpers::parse_decimal($json['valor']);
            CL_LIQ_Helpers::plugin_log('debug', 'UF HTTP valor obtenido (valor)', ['ym' => $ym, 'value' => $val]);
            return $val;
        }
        if (isset($json['UF'])) {
            $val = (float) CL_LIQ_Helpers::parse_decimal($json['UF']);
            CL_LIQ_Helpers::plugin_log('debug', 'UF HTTP valor obtenido (UF)', ['ym' => $ym, 'value' => $val]);
            return $val;
        }

        return 0.0;
    }

    private static function ensure_tax_table_for_month(string $ym): bool {
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) return false;

        $settings = CL_LIQ_Settings::get();
        $tables = $settings['tax_tables'] ?? [];
        if (!is_array($tables)) $tables = [];

        if (isset($tables[$ym]) && is_array($tables[$ym]) && !empty($tables[$ym])) {
            return false;
        }

        // Find latest existing table key
        $keys = array_keys($tables);
        $keys = array_filter($keys, function($k){ return preg_match('/^\d{4}-\d{2}$/', (string)$k); });
        if (!$keys) return false;

        rsort($keys);
        $latest = $keys[0];

        if (empty($tables[$latest]) || !is_array($tables[$latest])) return false;

        $tables[$ym] = $tables[$latest];

        // If mode is suggest, still save but mark as copied (review needed)
        $settings['tax_tables'] = $tables;
        update_option(CL_LIQ_Settings::OPTION_KEY, $settings, false);

        return true;
    }
}
