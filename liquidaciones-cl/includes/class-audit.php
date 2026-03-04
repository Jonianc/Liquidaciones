<?php
if ( ! defined('ABSPATH') ) { exit; }

final class CL_LIQ_Audit {

    const OPT_LOG = 'cl_liq_audit_log';
    const SNAPSHOT_META = '_cl_liq_audit_snapshot';
    const MAX_LOG = 200;

    public static function get_log(int $limit = 20): array {
        $log = get_option(self::OPT_LOG, []);
        if (!is_array($log)) return [];
        return array_slice($log, 0, max(1, $limit));
    }

    public static function log_event(string $type, string $message, array $data = [], string $context = 'system'): void {
        $entry = [
            'ts' => time(),
            'user' => get_current_user_id(),
            'action' => sanitize_text_field($type),
            'entity_type' => 'event',
            'entity_id' => 0,
            'title' => '',
            'context' => sanitize_text_field($context),
            'message' => sanitize_text_field($message),
            'changes' => self::sanitize_data($data),
        ];

        self::push_log($entry);
    }

    public static function log_post_change(int $post_id, string $post_type = '', string $context = 'unknown'): void {
        $post = get_post($post_id);
        if (!$post) return;

        $post_type = $post_type ?: (string) $post->post_type;
        if (!in_array($post_type, ['cl_empleado', 'cl_periodo', 'cl_liquidacion'], true)) return;

        $current = self::snapshot_post($post_id, $post_type);
        if (empty($current)) return;

        $previous = get_post_meta($post_id, self::SNAPSHOT_META, true);
        if (!is_array($previous)) {
            $previous = [];
        }

        $action = empty($previous) ? 'created' : 'updated';
        $changes = empty($previous) ? $current : self::diff_snapshots($previous, $current);

        if ($action === 'updated' && empty($changes)) {
            return;
        }

        update_post_meta($post_id, self::SNAPSHOT_META, $current);

        $entry = [
            'ts' => time(),
            'user' => get_current_user_id(),
            'action' => $action,
            'entity_type' => $post_type,
            'entity_id' => $post_id,
            'title' => (string) get_the_title($post_id),
            'context' => sanitize_text_field($context),
            'message' => $action === 'created' ? 'Registro creado' : 'Registro actualizado',
            'changes' => self::sanitize_data($changes),
        ];

        self::push_log($entry);
    }

    private static function push_log(array $entry): void {
        $log = get_option(self::OPT_LOG, []);
        if (!is_array($log)) $log = [];

        array_unshift($log, $entry);
        $log = array_slice($log, 0, self::MAX_LOG);

        update_option(self::OPT_LOG, $log, false);
    }

    private static function snapshot_post(int $post_id, string $post_type): array {
        $base = [
            'post_title' => (string) get_the_title($post_id),
            'post_status' => (string) get_post_status($post_id),
        ];

        if ($post_type === 'cl_empleado') {
            $base['meta'] = [
                'cl_rut' => (string) get_post_meta($post_id, 'cl_rut', true),
                'cl_tipo_contrato' => (string) get_post_meta($post_id, 'cl_tipo_contrato', true),
                'cl_afp' => (string) get_post_meta($post_id, 'cl_afp', true),
                'cl_salud_tipo' => (string) get_post_meta($post_id, 'cl_salud_tipo', true),
                'cl_isapre_plan_clp' => (string) get_post_meta($post_id, 'cl_isapre_plan_clp', true),
                'cl_cargas' => (string) get_post_meta($post_id, 'cl_cargas', true),
                'cl_tramo_asig' => (string) get_post_meta($post_id, 'cl_tramo_asig', true),
            ];
            return $base;
        }

        if ($post_type === 'cl_periodo') {
            $base['meta'] = [
                'cl_ym' => (string) get_post_meta($post_id, 'cl_ym', true),
                'cl_uf_value' => (string) get_post_meta($post_id, 'cl_uf_value', true),
            ];
            return $base;
        }

        if ($post_type === 'cl_liquidacion') {
            $base['meta'] = [
                'cl_empleado_id' => (string) get_post_meta($post_id, 'cl_empleado_id', true),
                'cl_periodo_id' => (string) get_post_meta($post_id, 'cl_periodo_id', true),
                'cl_sueldo_base' => (string) get_post_meta($post_id, 'cl_sueldo_base', true),
                'cl_grat_tipo' => (string) get_post_meta($post_id, 'cl_grat_tipo', true),
                'cl_grat_manual' => (string) get_post_meta($post_id, 'cl_grat_manual', true),
                'cl_he_horas' => (string) get_post_meta($post_id, 'cl_he_horas', true),
                'cl_he_valor_hora' => (string) get_post_meta($post_id, 'cl_he_valor_hora', true),
                'cl_bonos_imponibles' => (string) get_post_meta($post_id, 'cl_bonos_imponibles', true),
                'cl_comisiones' => (string) get_post_meta($post_id, 'cl_comisiones', true),
                'cl_otros_imponibles' => (string) get_post_meta($post_id, 'cl_otros_imponibles', true),
                'cl_colacion' => (string) get_post_meta($post_id, 'cl_colacion', true),
                'cl_movilizacion' => (string) get_post_meta($post_id, 'cl_movilizacion', true),
                'cl_viaticos' => (string) get_post_meta($post_id, 'cl_viaticos', true),
                'cl_otros_no_imponibles' => (string) get_post_meta($post_id, 'cl_otros_no_imponibles', true),
                'cl_asig_manual_monto' => (string) get_post_meta($post_id, 'cl_asig_manual_monto', true),
                'cl_otros_descuentos' => (string) get_post_meta($post_id, 'cl_otros_descuentos', true),
                'cl_anticipos' => (string) get_post_meta($post_id, 'cl_anticipos', true),
                'cl_prestamos' => (string) get_post_meta($post_id, 'cl_prestamos', true),
                'cl_calc_liquido' => (string) self::calc_value($post_id, 'liquido'),
                'cl_calc_haberes_total' => (string) self::calc_value($post_id, 'haberes_total'),
                'cl_calc_descuentos_total' => (string) self::calc_value($post_id, 'descuentos_total'),
            ];
            return $base;
        }

        return [];
    }

    private static function calc_value(int $post_id, string $key): string {
        $calc = get_post_meta($post_id, 'cl_calc', true);
        if (!is_array($calc)) return '';
        return isset($calc[$key]) ? (string) $calc[$key] : '';
    }

    private static function diff_snapshots(array $before, array $after): array {
        $changes = [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($keys as $k) {
            $b = $before[$k] ?? null;
            $a = $after[$k] ?? null;

            if (is_array($b) || is_array($a)) {
                $b_arr = is_array($b) ? $b : [];
                $a_arr = is_array($a) ? $a : [];
                $nested = self::diff_snapshots($b_arr, $a_arr);
                if (!empty($nested)) {
                    $changes[$k] = $nested;
                }
                continue;
            }

            if ((string) $b !== (string) $a) {
                $changes[$k] = ['from' => $b, 'to' => $a];
            }
        }

        return $changes;
    }

    private static function sanitize_data(array $data): array {
        $clean = [];
        foreach ($data as $k => $v) {
            $key = sanitize_text_field((string) $k);
            if (is_array($v)) {
                $clean[$key] = self::sanitize_data($v);
            } else {
                $clean[$key] = is_scalar($v) || $v === null ? (string) $v : '';
            }
        }
        return $clean;
    }
}
