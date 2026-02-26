<?php
if (!defined('ABSPATH')) exit;

class LQM_CPT {

    const CPT = 'lqm_liquidacion';

    public static function init() {
        add_action('init', [__CLASS__, 'register_cpt']);
        add_action('add_meta_boxes', [__CLASS__, 'add_metaboxes']);
        add_action('save_post_' . self::CPT, [__CLASS__, 'save_meta'], 10, 2);
        add_filter('post_row_actions', [__CLASS__, 'row_actions'], 10, 2);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    }

    public static function register_cpt() {
        register_post_type(self::CPT, [
            'labels' => [
                'name' => 'Liquidaciones',
                'singular_name' => 'Liquidación',
                'add_new' => 'Agregar nueva',
                'add_new_item' => 'Agregar Liquidación',
                'edit_item' => 'Editar Liquidación',
            ],
            'public' => false,
            'show_ui' => true,
            'menu_icon' => 'dashicons-media-document',
            'supports' => ['title'],
        ]);
    }

    public static function add_metaboxes() {
        add_meta_box('lqm_datos', 'Datos de Liquidación', [__CLASS__, 'metabox_html'], self::CPT, 'normal', 'high');
    }

    public static function metabox_html($post) {
        wp_nonce_field('lqm_save', 'lqm_nonce');

        $m = function($key, $default='') use ($post) {
            $v = get_post_meta($post->ID, $key, true);
            return $v === '' ? $default : $v;
        };

        $no_imp = get_post_meta($post->ID, '_lqm_no_imponible', true);
        if (!is_array($no_imp)) $no_imp = [];

        ?>

        <div class="lqm-grid">
            <div>
                <label>Periodo (ej: Febrero / 2026)</label>
                <input type="text" name="lqm_periodo" value="<?php echo esc_attr($m('_lqm_periodo')); ?>">
            </div>
            <div>
                <label>Nombre Trabajador</label>
                <input type="text" name="lqm_nombre" value="<?php echo esc_attr($m('_lqm_nombre')); ?>">
            </div>
            <div>
                <label>RUT</label>
                <input type="text" name="lqm_rut" value="<?php echo esc_attr($m('_lqm_rut')); ?>">
            </div>
            <div>
                <label>Relación Laboral</label>
                <input type="text" name="lqm_relacion" value="<?php echo esc_attr($m('_lqm_relacion')); ?>">
            </div>

            <div>
                <label>Fecha Inicio</label>
                <input type="text" name="lqm_inicio" value="<?php echo esc_attr($m('_lqm_inicio')); ?>">
            </div>
            <div>
                <label>Cargo</label>
                <input type="text" name="lqm_cargo" value="<?php echo esc_attr($m('_lqm_cargo')); ?>">
            </div>
            <div>
                <label>Días Trabajados</label>
                <input type="number" name="lqm_dias_trab" value="<?php echo esc_attr($m('_lqm_dias_trab', 30)); ?>">
            </div>
            <div>
                <label>Licencia / Inasistencias</label>
                <div class="lqm-row">
                    <input type="number" name="lqm_dias_lic" placeholder="Licencia" value="<?php echo esc_attr($m('_lqm_dias_lic', 0)); ?>">
                    <input type="number" name="lqm_dias_inas" placeholder="Inasist." value="<?php echo esc_attr($m('_lqm_dias_inas', 0)); ?>">
                </div>
            </div>

            <div>
                <label>Sueldo Base (Imponible)</label>
                <input type="number" name="lqm_sueldo_base" value="<?php echo esc_attr($m('_lqm_sueldo_base', 0)); ?>">
                <div class="lqm-small">MVP: Total Imponible = Sueldo Base</div>
            </div>

            <div>
                <label>Impuesto Único (manual, MVP)</label>
                <input type="number" name="lqm_impuesto_unico" value="<?php echo esc_attr($m('_lqm_impuesto_unico', 0)); ?>">
            </div>

            <div>
                <label>Otros Descuentos (manual)</label>
                <input type="number" name="lqm_otros_desc" value="<?php echo esc_attr($m('_lqm_otros_desc', 0)); ?>">
            </div>

            <div class="full">
                <h4>No Imponible (items)</h4>
                <table class="lqm-table" id="lqm-noimp-table">
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th>Monto</th>
                            <th style="width:80px">Quitar</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($no_imp)) : ?>
                        <tr>
                            <td><input type="text" name="lqm_noimp_nombre[]" value=""></td>
                            <td><input type="number" name="lqm_noimp_monto[]" value=""></td>
                            <td><button type="button" class="button lqm-del">X</button></td>
                        </tr>
                    <?php else : foreach ($no_imp as $row) : ?>
                        <tr>
                            <td><input type="text" name="lqm_noimp_nombre[]" value="<?php echo esc_attr($row['nombre'] ?? ''); ?>"></td>
                            <td><input type="number" name="lqm_noimp_monto[]" value="<?php echo esc_attr($row['monto'] ?? ''); ?>"></td>
                            <td><button type="button" class="button lqm-del">X</button></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>

                <button type="button" class="button lqm-btn" id="lqm-add-noimp">+ Agregar item</button>

            </div>

            <div class="full">
                <?php
                $pdf_url = self::pdf_url($post->ID);
                if ($pdf_url) {
                    echo '<a class="button button-primary" target="_blank" href="'.esc_url($pdf_url).'">Ver PDF</a>';
                }
                ?>
            </div>
        </div>
        <?php
    }


    public static function enqueue_admin_assets($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') return;

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== self::CPT) return;

        wp_enqueue_style('lqm-admin', LQM_URL . 'assets/css/lqm-admin.css', [], LQM_VER);
        wp_enqueue_script('lqm-admin', LQM_URL . 'assets/js/lqm-admin.js', [], LQM_VER, true);
    }

    public static function save_meta($post_id, $post) {
        if (!isset($_POST['lqm_nonce']) || !wp_verify_nonce($_POST['lqm_nonce'], 'lqm_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $text_fields = [
            '_lqm_periodo' => 'lqm_periodo',
            '_lqm_nombre' => 'lqm_nombre',
            '_lqm_rut' => 'lqm_rut',
            '_lqm_relacion' => 'lqm_relacion',
            '_lqm_inicio' => 'lqm_inicio',
            '_lqm_cargo' => 'lqm_cargo',
        ];

        foreach ($text_fields as $meta => $post_key) {
            $val = isset($_POST[$post_key]) ? sanitize_text_field(wp_unslash($_POST[$post_key])) : '';
            update_post_meta($post_id, $meta, $val);
        }

        $numeric_fields = [
            '_lqm_dias_trab' => ['key' => 'lqm_dias_trab', 'default' => 30, 'min' => 0, 'max' => 31],
            '_lqm_dias_lic' => ['key' => 'lqm_dias_lic', 'default' => 0, 'min' => 0, 'max' => 31],
            '_lqm_dias_inas' => ['key' => 'lqm_dias_inas', 'default' => 0, 'min' => 0, 'max' => 31],
            '_lqm_sueldo_base' => ['key' => 'lqm_sueldo_base', 'default' => 0, 'min' => 0],
            '_lqm_impuesto_unico' => ['key' => 'lqm_impuesto_unico', 'default' => 0, 'min' => 0],
            '_lqm_otros_desc' => ['key' => 'lqm_otros_desc', 'default' => 0, 'min' => 0],
        ];

        foreach ($numeric_fields as $meta => $config) {
            $val = self::read_numeric_from_post($config['key'], $config['default'], $config['min'], $config['max'] ?? null);
            update_post_meta($post_id, $meta, $val);
        }

        $names = isset($_POST['lqm_noimp_nombre']) ? (array) $_POST['lqm_noimp_nombre'] : [];
        $montos = isset($_POST['lqm_noimp_monto']) ? (array) $_POST['lqm_noimp_monto'] : [];
        $rows = [];
        $n = max(count($names), count($montos));
        for ($i=0; $i<$n; $i++) {
            $nombre = sanitize_text_field(wp_unslash($names[$i] ?? ''));
            $monto = self::parse_non_negative_int($montos[$i] ?? 0);
            if ($nombre === '' || $monto <= 0) continue;
            $rows[] = [
                'nombre' => $nombre,
                'monto'  => $monto,
            ];
        }
        update_post_meta($post_id, '_lqm_no_imponible', $rows);

        // Clear cached FPDF path on save (in case plugins changed)
        delete_transient(LQM_FPDF::TRANSIENT_KEY);
    }

    private static function read_numeric_from_post($key, $default = 0, $min = 0, $max = null) {
        if (!isset($_POST[$key])) return (int) $default;

        $value = self::parse_non_negative_int($_POST[$key]);

        if ($value < (int) $min) {
            $value = (int) $min;
        }

        if ($max !== null && $value > (int) $max) {
            $value = (int) $max;
        }

        return $value;
    }

    private static function parse_non_negative_int($raw) {
        $value = is_scalar($raw) ? sanitize_text_field(wp_unslash((string) $raw)) : '';
        $value = preg_replace('/[^\d]/', '', (string) $value);

        if ($value === '') return 0;

        return absint($value);
    }

    public static function pdf_url($post_id) {
        if (!$post_id) return '';
        $nonce = wp_create_nonce('lqm_pdf_'.$post_id);
        return add_query_arg([
            'lqm_pdf' => $post_id,
            '_wpnonce' => $nonce
        ], home_url('/'));
    }

    public static function row_actions($actions, $post) {
        if ($post->post_type !== self::CPT) return $actions;
        $actions['lqm_pdf'] = '<a target="_blank" href="'.esc_url(self::pdf_url($post->ID)).'">Ver PDF</a>';
        return $actions;
    }
}
