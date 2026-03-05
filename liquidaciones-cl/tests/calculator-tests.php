<?php
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }
// Lightweight test runner for CL_LIQ_Calculator without full WP bootstrap.

$GLOBALS['cl_liq_meta'] = [];

function current_time($format) {
    if ($format === 'Y-m') return '2026-03';
    return time();
}
function get_post_meta($id, $key, $single = true) {
    return $GLOBALS['cl_liq_meta'][$id][$key] ?? '';
}
function update_post_meta($id, $key, $value) {
    $GLOBALS['cl_liq_meta'][$id][$key] = $value;
}

if (!class_exists('CL_LIQ_Settings')) {
    final class CL_LIQ_Settings {
        public static function get(): array {
            return [
                'topes' => [
                    'tope_uf_afp_salud' => 90.0,
                    'tope_uf_afc' => 135.2,
                ],
                'afp_commissions' => [
                    'Modelo' => 0.0058,
                ],
                'afc_employee_rates' => [
                    'indefinido' => 0.006,
                    'plazo_fijo' => 0.0,
                    'obra' => 0.0,
                    'casa_particular' => 0.0,
                ],
                'horas_extra' => [
                    'jornada_mensual_horas' => 180,
                    'recargo' => 0.5,
                ],
                'gratificacion' => [
                    'imm_clp' => 0,
                    'tope_factor' => 4.75,
                    'tope_divisor' => 12,
                ],
                'asignacion_familiar' => [
                    'tramos' => [
                        ['from' => 1, 'to' => 631976, 'amount' => 22007],
                        ['from' => 631977, 'to' => 923067, 'amount' => 13505],
                        ['from' => 923068, 'to' => 1439668, 'amount' => 4276],
                        ['from' => 1439669, 'to' => null, 'amount' => 0],
                    ],
                ],
                'tax_tables' => [
                    '2026-03' => [
                        ['from'=>0.0,        'to'=>943501.50,   'factor'=>0.0,   'rebate'=>0.0],
                        ['from'=>943501.51,  'to'=>2096670.00,  'factor'=>0.04,  'rebate'=>37740.06],
                        ['from'=>2096670.01, 'to'=>3494450.00,  'factor'=>0.08,  'rebate'=>121606.86],
                    ],
                ],
            ];
        }
    }
}

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/class-calculator.php';

function assert_same($expected, $actual, $msg): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$msg}. Expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function test_calc_impuesto_unico(): void {
    $table = [
        ['from' => 0.0, 'to' => 1000.0, 'factor' => 0.0, 'rebate' => 0.0],
        ['from' => 1000.01, 'to' => 2000.0, 'factor' => 0.04, 'rebate' => 40.0],
    ];

    assert_same(0, CL_LIQ_Calculator::calc_impuesto_unico(900.0, $table), 'tax bracket 0%');
    assert_same(20, CL_LIQ_Calculator::calc_impuesto_unico(1500.0, $table), 'tax bracket 4% with rebate');
    assert_same(0, CL_LIQ_Calculator::calc_impuesto_unico(0.0, $table), 'tax with rli 0');
}

function seed_base_case(): int {
    $liq_id = 100;
    $empleado_id = 10;
    $periodo_id = 20;

    $GLOBALS['cl_liq_meta'][$liq_id] = [
        'cl_empleado_id' => $empleado_id,
        'cl_periodo_id' => $periodo_id,
        'cl_sueldo_base' => 1000000,
        'cl_grat_tipo' => 'ninguna',
        'cl_grat_manual' => 0,
        'cl_he_horas' => 0,
        'cl_he_valor_hora' => 0,
        'cl_bonos_imponibles' => 0,
        'cl_comisiones' => 0,
        'cl_otros_imponibles' => 0,
        'cl_colacion' => 50000,
        'cl_movilizacion' => 30000,
        'cl_viaticos' => 0,
        'cl_otros_no_imponibles' => 0,
        'cl_asig_manual_monto' => 0,
        'cl_otros_descuentos' => 10000,
        'cl_anticipos' => 0,
        'cl_prestamos' => 0,
    ];

    $GLOBALS['cl_liq_meta'][$empleado_id] = [
        'cl_afp' => 'Modelo',
        'cl_salud_tipo' => 'FONASA',
        'cl_isapre_plan_clp' => 0,
        'cl_tipo_contrato' => 'indefinido',
        'cl_cargas' => 1,
        'cl_tramo_asig' => 'auto',
    ];

    $GLOBALS['cl_liq_meta'][$periodo_id] = [
        'cl_ym' => '2026-03',
        'cl_uf_value' => 38000,
    ];

    return $liq_id;
}

function test_calculate_liquidacion_base_case(): void {
    $liq_id = seed_base_case();
    $calc = CL_LIQ_Calculator::calculate_liquidacion($liq_id);

    assert_same(1000000, $calc['imponible_total'], 'imponible_total');
    assert_same(84276, $calc['no_imponible_total'], 'no_imponible_total');
    assert_same(1084276, $calc['haberes_total'], 'haberes_total');
    assert_same(100000, $calc['afp_10'], 'afp_10');
    assert_same(5800, $calc['afp_comision'], 'afp_comision');
    assert_same(70000, $calc['salud'], 'salud_fonasa');
    assert_same(6000, $calc['afc_trabajador'], 'afc_trabajador');
    assert_same(0, $calc['impuesto_unico'], 'impuesto_unico');
    assert_same(191800, $calc['descuentos_total'], 'descuentos_total');
    assert_same(892476, $calc['liquido'], 'liquido');
}

function run_all(): void {
    test_calc_impuesto_unico();
    test_calculate_liquidacion_base_case();
    echo "OK: calculator tests passed\n";
}

run_all();
