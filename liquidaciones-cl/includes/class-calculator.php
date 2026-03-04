<?php
if ( ! defined('ABSPATH') ) { exit; }

final class CL_LIQ_Calculator {

    public static function calculate_liquidacion(int $liq_id): array {
        $settings = CL_LIQ_Settings::get();

        $empleado_id = (int) get_post_meta($liq_id, 'cl_empleado_id', true);
        $periodo_id  = (int) get_post_meta($liq_id, 'cl_periodo_id', true);

        if (!$empleado_id || !$periodo_id) {
            return [];
        }

        // Period data
        $ym = (string) get_post_meta($periodo_id, 'cl_ym', true);
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = CL_LIQ_Helpers::current_ym();
        $uf = (float) get_post_meta($periodo_id, 'cl_uf_value', true);

        // Employee data
        $afp_name = (string) get_post_meta($empleado_id, 'cl_afp', true);
        $salud_tipo = (string) get_post_meta($empleado_id, 'cl_salud_tipo', true);
        $isapre_plan = (int) get_post_meta($empleado_id, 'cl_isapre_plan_clp', true);
        $tipo_contrato = (string) get_post_meta($empleado_id, 'cl_tipo_contrato', true);
        $cargas = (int) get_post_meta($empleado_id, 'cl_cargas', true);
        $tramo_asig = (string) get_post_meta($empleado_id, 'cl_tramo_asig', true);

        // Inputs (CLP)
        $sueldo_base = (int) get_post_meta($liq_id, 'cl_sueldo_base', true);
        $grat_tipo = (string) get_post_meta($liq_id, 'cl_grat_tipo', true);
        $grat_manual = (int) get_post_meta($liq_id, 'cl_grat_manual', true);

        $he_horas = (float) get_post_meta($liq_id, 'cl_he_horas', true);
        $he_valor_hora = (int) get_post_meta($liq_id, 'cl_he_valor_hora', true);

        $bonos_imponibles = (int) get_post_meta($liq_id, 'cl_bonos_imponibles', true);
        $comisiones = (int) get_post_meta($liq_id, 'cl_comisiones', true);
        $otros_imponibles = (int) get_post_meta($liq_id, 'cl_otros_imponibles', true);

        $colacion = (int) get_post_meta($liq_id, 'cl_colacion', true);
        $movilizacion = (int) get_post_meta($liq_id, 'cl_movilizacion', true);
        $viaticos = (int) get_post_meta($liq_id, 'cl_viaticos', true);
        $otros_no_imponibles = (int) get_post_meta($liq_id, 'cl_otros_no_imponibles', true);

        $asig_manual_monto = (int) get_post_meta($liq_id, 'cl_asig_manual_monto', true);

        $otros_descuentos = (int) get_post_meta($liq_id, 'cl_otros_descuentos', true);
        $anticipos = (int) get_post_meta($liq_id, 'cl_anticipos', true);
        $prestamos = (int) get_post_meta($liq_id, 'cl_prestamos', true);

        // Gratificación
        $gratificacion = 0;
        if ($grat_tipo === 'manual') {
            $gratificacion = max(0, $grat_manual);
        } elseif ($grat_tipo === 'legal') {
            $imm = (int) ($settings['gratificacion']['imm_clp'] ?? 0);
            $tope = PHP_INT_MAX;
            if ($imm > 0) {
                $tope = (int) round(((float)$settings['gratificacion']['tope_factor'] * $imm) / max(1, (int)$settings['gratificacion']['tope_divisor']));
            }
            $gratificacion = (int) round($sueldo_base * 0.25);
            $gratificacion = min($gratificacion, $tope);
        }

        // Horas extra
        $he_total = 0;
        if ($he_horas > 0) {
            $jornada = (int) ($settings['horas_extra']['jornada_mensual_horas'] ?? 180);
            $recargo = (float) ($settings['horas_extra']['recargo'] ?? 0.5);
            $mult = 1.0 + $recargo;

            $valor_hora = $he_valor_hora > 0 ? (float)$he_valor_hora : ((float)$sueldo_base / max(1, $jornada));
            $he_total = (int) round($he_horas * $valor_hora * $mult);
        }

        // Imponibles / No imponibles
        $imponible_total = max(0, $sueldo_base + $gratificacion + $he_total + $bonos_imponibles + $comisiones + $otros_imponibles);

        // Asignación familiar
        $asig_total = 0;
        if ($asig_manual_monto > 0) {
            $asig_total = $asig_manual_monto;
        } else {
            $asig_unit = self::asignacion_unitaria($settings, $tramo_asig, $imponible_total);
            $asig_total = max(0, $cargas) * (int) $asig_unit;
        }

        $no_imponible_total = max(0, $colacion + $movilizacion + $viaticos + $otros_no_imponibles + $asig_total);

        $haberes_total = $imponible_total + $no_imponible_total;

        // Topes en CLP (requiere UF del período)
        $tope_afp_salud_clp = PHP_INT_MAX;
        $tope_afc_clp = PHP_INT_MAX;
        if ($uf > 0) {
            $tope_afp_salud_clp = (int) round(((float)$settings['topes']['tope_uf_afp_salud']) * $uf);
            $tope_afc_clp = (int) round(((float)$settings['topes']['tope_uf_afc']) * $uf);
        }

        $base_afp_salud = min($imponible_total, $tope_afp_salud_clp);
        $base_afc = min($imponible_total, $tope_afc_clp);

        // AFP
        $afp_10 = (int) round($base_afp_salud * 0.10);
        $com_rate = (float) ($settings['afp_commissions'][$afp_name] ?? 0.0);
        $afp_comision = (int) round($base_afp_salud * $com_rate);

        // Salud
        $salud_7 = (int) round($base_afp_salud * 0.07);
        $salud = $salud_7;
        $salud_label = 'Salud (7%)';
        if (strtoupper($salud_tipo) === 'ISAPRE') {
            $plan = max(0, (int)$isapre_plan);
            if ($plan > 0) {
                $salud = max($salud_7, $plan);
                $salud_label = 'ISAPRE (plan)';
            }
        }

        // AFC trabajador
        $rate_afc_emp = (float) ($settings['afc_employee_rates'][$tipo_contrato] ?? 0.0);
        $afc_trabajador = (int) round($base_afc * $rate_afc_emp);

        // Renta líquida imponible para impuesto (aprox)
        $rli = max(0, $imponible_total - ($afp_10 + $afp_comision + $salud + $afc_trabajador));

        // Impuesto Único
        $tax_table = self::tax_table_for_ym($settings, $ym);
        $impuesto_unico = self::calc_impuesto_unico($rli, $tax_table);

        // Otros descuentos
        $otros_desc = max(0, $otros_descuentos + $anticipos + $prestamos);

        $descuentos_legales = $afp_10 + $afp_comision + $salud + $afc_trabajador + $impuesto_unico;
        $descuentos_total = $descuentos_legales + $otros_desc;

        $liquido = $haberes_total - $descuentos_total;

        return [
            'ym' => $ym,
            'uf' => $uf,
            'imponible_total' => $imponible_total,
            'no_imponible_total' => $no_imponible_total,
            'asignacion_familiar' => $asig_total,
            'haberes_total' => $haberes_total,

            'base_afp_salud' => (int)$base_afp_salud,
            'base_afc' => (int)$base_afc,

            'afp_10' => $afp_10,
            'afp_comision' => $afp_comision,
            'afp_nombre' => $afp_name,

            'salud' => $salud,
            'salud_label' => $salud_label,
            'salud_tipo' => $salud_tipo,

            'afc_trabajador' => $afc_trabajador,
            'tipo_contrato' => $tipo_contrato,

            'rli' => $rli,
            'impuesto_unico' => $impuesto_unico,

            'otros_descuentos_total' => $otros_desc,
            'descuentos_legales' => $descuentos_legales,
            'descuentos_total' => $descuentos_total,

            'liquido' => $liquido,

            // detalle haberes
            'haberes_detalle' => [
                'sueldo_base' => $sueldo_base,
                'gratificacion' => $gratificacion,
                'horas_extra' => $he_total,
                'bonos_imponibles' => $bonos_imponibles,
                'comisiones' => $comisiones,
                'otros_imponibles' => $otros_imponibles,
                'colacion' => $colacion,
                'movilizacion' => $movilizacion,
                'viaticos' => $viaticos,
                'otros_no_imponibles' => $otros_no_imponibles,
                'asignacion_familiar' => $asig_total,
            ],
        ];
    }

    private static function tax_table_for_ym(array $settings, string $ym): array {
        $tables = $settings['tax_tables'] ?? [];
        if (isset($tables[$ym]) && is_array($tables[$ym])) {
            return $tables[$ym];
        }
        // fallback: most recent key
        if (is_array($tables) && !empty($tables)) {
            $keys = array_keys($tables);
            rsort($keys);
            $k = $keys[0];
            if (isset($tables[$k]) && is_array($tables[$k])) return $tables[$k];
        }
        return [];
    }

    public static function calc_impuesto_unico(float $rli, array $table): int {
        if ($rli <= 0 || empty($table)) return 0;

        foreach ($table as $row) {
            $from = (float) ($row['from'] ?? 0);
            $to = array_key_exists('to', $row) ? $row['to'] : null;
            $to = ($to === null) ? null : (float)$to;

            $in = ($rli >= $from) && ($to === null || $rli <= $to);
            if (!$in) continue;

            $factor = (float) ($row['factor'] ?? 0);
            $rebate = (float) ($row['rebate'] ?? 0);

            if ($factor <= 0) return 0;

            $tax = ($rli * $factor) - $rebate;
            $tax = (int) round($tax);
            return max(0, $tax);
        }

        return 0;
    }

    private static function asignacion_unitaria(array $settings, string $tramo, int $imponible): int {
        $tramos = $settings['asignacion_familiar']['tramos'] ?? [];
        if (!is_array($tramos) || empty($tramos)) return 0;

        if ($tramo !== 'auto' && in_array($tramo, ['1','2','3','4'], true)) {
            $idx = (int)$tramo - 1;
            if (isset($tramos[$idx]['amount'])) return (int)$tramos[$idx]['amount'];
        }

        // auto: selecciona por imponible
        foreach ($tramos as $t) {
            $from = (int) ($t['from'] ?? 0);
            $to = array_key_exists('to', $t) ? $t['to'] : null;
            $to = ($to === null) ? null : (int)$to;
            if ($imponible >= $from && ($to === null || $imponible <= $to)) {
                return (int) ($t['amount'] ?? 0);
            }
        }
        return 0;
    }

}
