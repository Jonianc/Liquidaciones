<?php
if ( ! defined('ABSPATH') ) { exit; }

final class CL_LIQ_Meta_Boxes {

    public static function init() {
        add_action('add_meta_boxes', [__CLASS__, 'add_boxes']);
        add_action('save_post', [__CLASS__, 'save_post'], 10, 2);

        // Column tweaks for list views
        add_filter('manage_cl_liquidacion_posts_columns', [__CLASS__, 'liq_columns']);
        add_action('manage_cl_liquidacion_posts_custom_column', [__CLASS__, 'liq_column_render'], 10, 2);
    }

    public static function add_boxes() {
        add_meta_box('cl_empleado_datos', 'Datos del empleado', [__CLASS__, 'render_empleado'], 'cl_empleado', 'normal', 'high');
        add_meta_box('cl_periodo_datos', 'Datos del período', [__CLASS__, 'render_periodo'], 'cl_periodo', 'normal', 'high');
        add_meta_box('cl_liq_datos', 'Datos de la liquidación', [__CLASS__, 'render_liquidacion'], 'cl_liquidacion', 'normal', 'high');
        add_meta_box('cl_liq_resumen', 'Resumen calculado', [__CLASS__, 'render_resumen'], 'cl_liquidacion', 'side', 'high');
    }

    private static function field($name, $value, $type='text', $attrs='') {
        return '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" ' . $attrs . ' />';
    }

    public static function render_empleado($post) {
        wp_nonce_field('cl_liq_save_empleado', 'cl_liq_nonce');

        $rut = get_post_meta($post->ID, 'cl_rut', true);
        $tipo_contrato = get_post_meta($post->ID, 'cl_tipo_contrato', true) ?: 'indefinido';
        $afp = get_post_meta($post->ID, 'cl_afp', true) ?: 'Modelo';
        $salud_tipo = get_post_meta($post->ID, 'cl_salud_tipo', true) ?: 'FONASA';
        $isapre_plan = get_post_meta($post->ID, 'cl_isapre_plan_clp', true);
        $cargas = (int) get_post_meta($post->ID, 'cl_cargas', true);
        $tramo = get_post_meta($post->ID, 'cl_tramo_asig', true) ?: 'auto';

        $settings = CL_LIQ_Settings::get();
        $afps = array_keys($settings['afp_commissions']);

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">RUT</th><td>' . self::field('cl_empleado[cl_rut]', $rut, 'text', 'class="regular-text" placeholder="12.345.678-9"') . '</td></tr>';
        echo '<tr><th scope="row">Tipo de contrato</th><td><select name="cl_empleado[cl_tipo_contrato]">';
        $opts = [
            'indefinido' => 'Indefinido',
            'plazo_fijo' => 'Plazo fijo',
            'obra' => 'Obra / Faena',
            'casa_particular' => 'Casa particular',
        ];
        foreach ($opts as $k => $lbl) {
            echo '<option value="' . esc_attr($k) . '" ' . selected($tipo_contrato, $k, false) . '>' . esc_html($lbl) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row">AFP</th><td><select name="cl_empleado[cl_afp]">';
        foreach ($afps as $a) {
            echo '<option value="' . esc_attr($a) . '" ' . selected($afp, $a, false) . '>' . esc_html($a) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row">Salud</th><td><select name="cl_empleado[cl_salud_tipo]">';
        echo '<option value="FONASA" ' . selected($salud_tipo, 'FONASA', false) . '>FONASA (7%)</option>';
        echo '<option value="ISAPRE" ' . selected($salud_tipo, 'ISAPRE', false) . '>ISAPRE (plan)</option>';
        echo '</select></td></tr>';

        echo '<tr><th scope="row">Plan ISAPRE (CLP)</th><td>' . self::field('cl_empleado[cl_isapre_plan_clp]', $isapre_plan, 'text', 'class="regular-text" placeholder="Ej: 120000"') . '<p class="description">Si Salud = ISAPRE, se usa este monto (mínimo 7% del imponible topeado).</p></td></tr>';

        echo '<tr><th scope="row">Cargas familiares</th><td>' . self::field('cl_empleado[cl_cargas]', $cargas, 'number', 'min="0" step="1"') . '</td></tr>';

        echo '<tr><th scope="row">Tramo Asignación Familiar</th><td><select name="cl_empleado[cl_tramo_asig]">';
        echo '<option value="auto" ' . selected($tramo, 'auto', false) . '>Auto (según imponible del mes)</option>';
        echo '<option value="1" ' . selected($tramo, '1', false) . '>Tramo 1</option>';
        echo '<option value="2" ' . selected($tramo, '2', false) . '>Tramo 2</option>';
        echo '<option value="3" ' . selected($tramo, '3', false) . '>Tramo 3</option>';
        echo '<option value="4" ' . selected($tramo, '4', false) . '>Tramo 4</option>';
        echo '</select></td></tr>';

        echo '</tbody></table>';
    }

    public static function render_periodo($post) {
        wp_nonce_field('cl_liq_save_periodo', 'cl_liq_nonce');

        $ym = get_post_meta($post->ID, 'cl_ym', true) ?: CL_LIQ_Helpers::current_ym();
        $uf = get_post_meta($post->ID, 'cl_uf_value', true);

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">Período (YYYY-MM)</th><td>' . self::field('cl_periodo[cl_ym]', $ym, 'text', 'class="regular-text" placeholder="2026-03"') . '</td></tr>';
        echo '<tr><th scope="row">UF (CLP)</th><td>' . self::field('cl_periodo[cl_uf_value]', $uf, 'text', 'class="regular-text" placeholder="Ej: 38000.12"') . '<p class="description">Se usa para convertir topes UF a CLP.</p></td></tr>';
        echo '</tbody></table>';
    }

    public static function render_liquidacion($post) {
        wp_nonce_field('cl_liq_save_liq', 'cl_liq_nonce');

        $empleado_id = (int) get_post_meta($post->ID, 'cl_empleado_id', true);
        $periodo_id = (int) get_post_meta($post->ID, 'cl_periodo_id', true);

        $fields = [
            'sueldo_base','grat_tipo','grat_manual','he_horas','he_valor_hora',
            'bonos_imponibles','comisiones','otros_imponibles',
            'colacion','movilizacion','viaticos','otros_no_imponibles',
            'otros_descuentos','anticipos','prestamos',
            'asig_manual_monto'
        ];
        $v = [];
        foreach ($fields as $f) {
            $v[$f] = get_post_meta($post->ID, 'cl_' . $f, true);
        }
        $v['grat_tipo'] = $v['grat_tipo'] ?: 'ninguna';

        $empleados = get_posts(['post_type'=>'cl_empleado','numberposts'=>-1,'post_status'=>'any','orderby'=>'title','order'=>'ASC']);
        $periodos = get_posts(['post_type'=>'cl_periodo','numberposts'=>-1,'post_status'=>'any','orderby'=>'title','order'=>'DESC']);

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row">Empleado</th><td><select name="cl_liq[cl_empleado_id]" required>';
        echo '<option value="">— Selecciona —</option>';
        foreach ($empleados as $e) {
            $rut = get_post_meta($e->ID, 'cl_rut', true);
            $label = $e->post_title . ($rut ? ' (' . $rut . ')' : '');
            echo '<option value="' . esc_attr($e->ID) . '" ' . selected($empleado_id, $e->ID, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row">Período</th><td><select name="cl_liq[cl_periodo_id]" required>';
        echo '<option value="">— Selecciona —</option>';
        foreach ($periodos as $p) {
            $ym = get_post_meta($p->ID, 'cl_ym', true);
            $label = $ym ? CL_LIQ_Helpers::ym_label($ym) : $p->post_title;
            echo '<option value="' . esc_attr($p->ID) . '" ' . selected($periodo_id, $p->ID, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row">Sueldo base (CLP)</th><td>' . self::field('cl_liq[cl_sueldo_base]', $v['sueldo_base'], 'text', 'class="regular-text"') . '</td></tr>';

        echo '<tr><th scope="row">Gratificación</th><td><select name="cl_liq[cl_grat_tipo]">';
        echo '<option value="ninguna" ' . selected($v['grat_tipo'], 'ninguna', false) . '>Ninguna</option>';
        echo '<option value="legal" ' . selected($v['grat_tipo'], 'legal', false) . '>Legal (25% sueldo base con tope)</option>';
        echo '<option value="manual" ' . selected($v['grat_tipo'], 'manual', false) . '>Manual</option>';
        echo '</select> &nbsp; Monto manual: ' . self::field('cl_liq[cl_grat_manual]', $v['grat_manual'], 'text', 'style="width:160px"') . '</td></tr>';

        echo '<tr><th scope="row">Horas extra</th><td>Horas: ' . self::field('cl_liq[cl_he_horas]', $v['he_horas'], 'text', 'style="width:100px" placeholder="Ej: 10"') . ' &nbsp; Valor hora (opcional): ' . self::field('cl_liq[cl_he_valor_hora]', $v['he_valor_hora'], 'text', 'style="width:160px" placeholder="Ej: 3500"') . '<p class="description">Si no indicas valor hora, se calcula: sueldo_base / jornada_horas y se aplica recargo.</p></td></tr>';

        echo '<tr><th scope="row">Bonos imponibles</th><td>' . self::field('cl_liq[cl_bonos_imponibles]', $v['bonos_imponibles'], 'text', 'class="regular-text"') . '</td></tr>';
        echo '<tr><th scope="row">Comisiones</th><td>' . self::field('cl_liq[cl_comisiones]', $v['comisiones'], 'text', 'class="regular-text"') . '</td></tr>';
        echo '<tr><th scope="row">Otros imponibles</th><td>' . self::field('cl_liq[cl_otros_imponibles]', $v['otros_imponibles'], 'text', 'class="regular-text"') . '</td></tr>';

        echo '<tr><th scope="row">Colación (no imponible)</th><td>' . self::field('cl_liq[cl_colacion]', $v['colacion'], 'text', 'class="regular-text"') . '</td></tr>';
        echo '<tr><th scope="row">Movilización (no imponible)</th><td>' . self::field('cl_liq[cl_movilizacion]', $v['movilizacion'], 'text', 'class="regular-text"') . '</td></tr>';
        echo '<tr><th scope="row">Viáticos (no imponible)</th><td>' . self::field('cl_liq[cl_viaticos]', $v['viaticos'], 'text', 'class="regular-text"') . '</td></tr>';
        echo '<tr><th scope="row">Otros no imponibles</th><td>' . self::field('cl_liq[cl_otros_no_imponibles]', $v['otros_no_imponibles'], 'text', 'class="regular-text"') . '</td></tr>';

        echo '<tr><th scope="row">Asignación Familiar manual (CLP)</th><td>' . self::field('cl_liq[cl_asig_manual_monto]', $v['asig_manual_monto'], 'text', 'class="regular-text"') . '<p class="description">Si lo dejas vacío o 0, se calcula desde el empleado (cargas + tramo).</p></td></tr>';

        echo '<tr><th scope="row">Otros descuentos</th><td>' . self::field('cl_liq[cl_otros_descuentos]', $v['otros_descuentos'], 'text', 'class="regular-text"') . '</td></tr>';
        echo '<tr><th scope="row">Anticipos</th><td>' . self::field('cl_liq[cl_anticipos]', $v['anticipos'], 'text', 'class="regular-text"') . '</td></tr>';
        echo '<tr><th scope="row">Préstamos</th><td>' . self::field('cl_liq[cl_prestamos]', $v['prestamos'], 'text', 'class="regular-text"') . '</td></tr>';

        echo '</tbody></table>';

        echo '<p class="description">Al guardar, se recalculan AFP, Salud, AFC, Impuesto Único y el líquido.</p>';
    }

    public static function render_resumen($post) {
        $calc = get_post_meta($post->ID, 'cl_calc', true);
        if (!is_array($calc) || empty($calc)) {
            echo '<p>Aún no calculado. Guarda la liquidación.</p>';
            return;
        }

        echo '<p><strong>Haberes:</strong> ' . CL_LIQ_Helpers::esc_money($calc['haberes_total'] ?? 0) . '</p>';
        echo '<p><strong>Descuentos:</strong> ' . CL_LIQ_Helpers::esc_money($calc['descuentos_total'] ?? 0) . '</p>';
        echo '<p style="font-size:16px"><strong>Líquido:</strong> ' . CL_LIQ_Helpers::esc_money($calc['liquido'] ?? 0) . '</p>';

        $url = CL_LIQ_PDF::pdf_url($post->ID);
        if ($url) {
            echo '<p><a class="button button-primary" target="_blank" href="' . esc_url($url) . '">Ver PDF</a></p>';
        }
    }

    public static function save_post($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['cl_liq_nonce'])) return;

        // Determine nonce action per post type
        $pt = $post->post_type;
        $nonce = sanitize_text_field(wp_unslash($_POST['cl_liq_nonce'] ?? ''));

        if ($pt === 'cl_empleado') {
            if (!wp_verify_nonce($nonce, 'cl_liq_save_empleado')) return;
            if (!current_user_can('edit_post', $post_id)) return;
            $data = $_POST['cl_empleado'] ?? [];
            $rut = sanitize_text_field($data['cl_rut'] ?? '');
            if ($rut !== '' && !CL_LIQ_Helpers::validate_rut($rut)) return;
            update_post_meta($post_id, 'cl_rut', $rut);
            update_post_meta($post_id, 'cl_tipo_contrato', sanitize_text_field($data['cl_tipo_contrato'] ?? 'indefinido'));
            update_post_meta($post_id, 'cl_afp', sanitize_text_field($data['cl_afp'] ?? 'Modelo'));
            update_post_meta($post_id, 'cl_salud_tipo', sanitize_text_field($data['cl_salud_tipo'] ?? 'FONASA'));
            update_post_meta($post_id, 'cl_isapre_plan_clp', CL_LIQ_Helpers::parse_clp($data['cl_isapre_plan_clp'] ?? 0));
            update_post_meta($post_id, 'cl_cargas', max(0, (int) ($data['cl_cargas'] ?? 0)));
            update_post_meta($post_id, 'cl_tramo_asig', sanitize_text_field($data['cl_tramo_asig'] ?? 'auto'));
            if (class_exists('CL_LIQ_Audit')) {
                CL_LIQ_Audit::log_post_change($post_id, 'cl_empleado', 'admin_save');
            }
        }

        if ($pt === 'cl_periodo') {
            if (!wp_verify_nonce($nonce, 'cl_liq_save_periodo')) return;
            if (!current_user_can('edit_post', $post_id)) return;
            $data = $_POST['cl_periodo'] ?? [];
            $ym = sanitize_text_field($data['cl_ym'] ?? CL_LIQ_Helpers::current_ym());
            if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = CL_LIQ_Helpers::current_ym();
            if (CL_LIQ_Helpers::period_exists($ym, (int) $post_id)) return;
            if (CL_LIQ_Helpers::is_negative_number_input($data['cl_uf_value'] ?? '')) return;
            update_post_meta($post_id, 'cl_ym', $ym);
            update_post_meta($post_id, 'cl_uf_value', CL_LIQ_Helpers::parse_decimal($data['cl_uf_value'] ?? 0));
            if (class_exists('CL_LIQ_Audit')) {
                CL_LIQ_Audit::log_post_change($post_id, 'cl_periodo', 'admin_save');
            }
        }

        if ($pt === 'cl_liquidacion') {
            if (!wp_verify_nonce($nonce, 'cl_liq_save_liq')) return;
            if (!current_user_can('edit_post', $post_id)) return;

            $data = $_POST['cl_liq'] ?? [];

            $numeric_fields = [
                'cl_sueldo_base','cl_grat_manual','cl_he_horas','cl_he_valor_hora','cl_bonos_imponibles','cl_comisiones',
                'cl_otros_imponibles','cl_colacion','cl_movilizacion','cl_viaticos','cl_otros_no_imponibles',
                'cl_asig_manual_monto','cl_otros_descuentos','cl_anticipos','cl_prestamos'
            ];
            foreach ($numeric_fields as $nf) {
                if (CL_LIQ_Helpers::is_negative_number_input($data[$nf] ?? '')) return;
            }

            $empleado_id = (int) ($data['cl_empleado_id'] ?? 0);
            $periodo_id  = (int) ($data['cl_periodo_id'] ?? 0);
            update_post_meta($post_id, 'cl_empleado_id', $empleado_id);
            update_post_meta($post_id, 'cl_periodo_id', $periodo_id);

            $map = [
                'sueldo_base' => 'cl_sueldo_base',
                'grat_tipo' => 'cl_grat_tipo',
                'grat_manual' => 'cl_grat_manual',
                'he_horas' => 'cl_he_horas',
                'he_valor_hora' => 'cl_he_valor_hora',
                'bonos_imponibles' => 'cl_bonos_imponibles',
                'comisiones' => 'cl_comisiones',
                'otros_imponibles' => 'cl_otros_imponibles',
                'colacion' => 'cl_colacion',
                'movilizacion' => 'cl_movilizacion',
                'viaticos' => 'cl_viaticos',
                'otros_no_imponibles' => 'cl_otros_no_imponibles',
                'otros_descuentos' => 'cl_otros_descuentos',
                'anticipos' => 'cl_anticipos',
                'prestamos' => 'cl_prestamos',
                'asig_manual_monto' => 'cl_asig_manual_monto',
            ];

            // Save inputs
            foreach ($map as $k => $meta_key) {
                if ($k === 'grat_tipo') {
                    update_post_meta($post_id, $meta_key, sanitize_text_field($data[$meta_key] ?? 'ninguna'));
                    continue;
                }
                if ($k === 'he_horas') {
                    update_post_meta($post_id, $meta_key, CL_LIQ_Helpers::parse_decimal($data[$meta_key] ?? 0));
                    continue;
                }
                // numeric fields in CLP
                update_post_meta($post_id, $meta_key, CL_LIQ_Helpers::parse_clp($data[$meta_key] ?? 0));
            }

            // calculate + store
            $calc = CL_LIQ_Calculator::calculate_liquidacion($post_id);
            update_post_meta($post_id, 'cl_calc', $calc);

            if (class_exists('CL_LIQ_Audit')) {
                CL_LIQ_Audit::log_post_change($post_id, 'cl_liquidacion', 'admin_save');
            }

            // auto-title if empty
            $title = get_the_title($post_id);
            if (!$title && $empleado_id && $periodo_id) {
                $emp_title = get_the_title($empleado_id);
                $ym = get_post_meta($periodo_id, 'cl_ym', true);
                $new_title = trim($emp_title . ' - ' . ($ym ? $ym : ''));
                wp_update_post(['ID'=>$post_id, 'post_title'=>$new_title]);
            }
        }
    }

    public static function liq_columns($cols) {
        $cols['cl_liq_empleado'] = 'Empleado';
        $cols['cl_liq_periodo'] = 'Período';
        $cols['cl_liq_liquido'] = 'Líquido';
        return $cols;
    }

    public static function liq_column_render($col, $post_id) {
        if ($col === 'cl_liq_empleado') {
            $eid = (int) get_post_meta($post_id, 'cl_empleado_id', true);
            echo $eid ? esc_html(get_the_title($eid)) : '—';
        }
        if ($col === 'cl_liq_periodo') {
            $pid = (int) get_post_meta($post_id, 'cl_periodo_id', true);
            $ym = $pid ? get_post_meta($pid, 'cl_ym', true) : '';
            echo $ym ? esc_html(CL_LIQ_Helpers::ym_label($ym)) : '—';
        }
        if ($col === 'cl_liq_liquido') {
            $calc = get_post_meta($post_id, 'cl_calc', true);
            $liq = is_array($calc) ? ($calc['liquido'] ?? 0) : 0;
            echo esc_html(CL_LIQ_Helpers::money($liq));
        }
    }

}
