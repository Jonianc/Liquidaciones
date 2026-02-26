<?php
if (!defined('ABSPATH')) exit;

class LQM_CPT {

    const CPT = 'lqm_liquidacion';

    const CAPS = [
        'edit_post' => 'edit_lqm_liquidacion',
        'read_post' => 'read_lqm_liquidacion',
        'delete_post' => 'delete_lqm_liquidacion',
        'edit_posts' => 'edit_lqm_liquidaciones',
        'edit_others_posts' => 'edit_others_lqm_liquidaciones',
        'publish_posts' => 'publish_lqm_liquidaciones',
        'read_private_posts' => 'read_private_lqm_liquidaciones',
        'delete_posts' => 'delete_lqm_liquidaciones',
        'delete_private_posts' => 'delete_private_lqm_liquidaciones',
        'delete_published_posts' => 'delete_published_lqm_liquidaciones',
        'delete_others_posts' => 'delete_others_lqm_liquidaciones',
        'edit_private_posts' => 'edit_private_lqm_liquidaciones',
        'edit_published_posts' => 'edit_published_lqm_liquidaciones',
        'create_posts' => 'create_lqm_liquidaciones',
    ];

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
            'map_meta_cap' => true,
            'capability_type' => ['lqm_liquidacion', 'lqm_liquidaciones'],
            'capabilities' => self::CAPS,
        ]);
    }

    public static function activate() {
        self::register_cpt();
        self::grant_caps_to_roles();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    private static function grant_caps_to_roles() {
        $roles = ['administrator'];

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if (!$role) continue;

            foreach (self::CAPS as $cap) {
                $role->add_cap($cap);
            }
        }
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

        <div class="lqm-form" data-lqm-form="liquidacion">
        <div class="lqm-grid">
            <div class="lqm-field">
                <label for="lqm_periodo">Periodo (ej: Febrero / 2026)</label>
                <input type="text" id="lqm_periodo" name="lqm_periodo" required value="<?php echo esc_attr($m('_lqm_periodo')); ?>">
                <p class="lqm-error" id="lqm_periodo_error" aria-live="polite"></p>
            </div>
            <div class="lqm-field">
                <label for="lqm_nombre">Nombre Trabajador</label>
                <input type="text" id="lqm_nombre" name="lqm_nombre" required value="<?php echo esc_attr($m('_lqm_nombre')); ?>">
                <p class="lqm-error" id="lqm_nombre_error" aria-live="polite"></p>
            </div>
            <div class="lqm-field">
                <label for="lqm_rut">RUT</label>
                <input type="text" id="lqm_rut" name="lqm_rut" required aria-describedby="lqm_rut_help lqm_rut_error" value="<?php echo esc_attr($m('_lqm_rut')); ?>">
                <div class="lqm-small" id="lqm_rut_help">Formato sugerido: 12.345.678-5</div>
                <p class="lqm-error" id="lqm_rut_error" aria-live="polite"></p>
            </div>
            <div class="lqm-field">
                <label for="lqm_relacion">Relación Laboral</label>
                <input type="text" id="lqm_relacion" name="lqm_relacion" value="<?php echo esc_attr($m('_lqm_relacion')); ?>">
            </div>

            <div class="lqm-field">
                <label for="lqm_inicio">Fecha Inicio</label>
                <input type="text" id="lqm_inicio" name="lqm_inicio" placeholder="YYYY-MM-DD o texto libre" value="<?php echo esc_attr($m('_lqm_inicio')); ?>">
                <div class="lqm-small">Compatibilidad legacy: mantiene fechas históricas no-ISO sin borrarlas al re-guardar.</div>
            </div>
            <div class="lqm-field">
                <label for="lqm_cargo">Cargo</label>
                <input type="text" id="lqm_cargo" name="lqm_cargo" value="<?php echo esc_attr($m('_lqm_cargo')); ?>">
            </div>
            <div class="lqm-field">
                <label for="lqm_dias_trab">Días Trabajados</label>
                <input type="number" id="lqm_dias_trab" name="lqm_dias_trab" min="0" max="31" step="1" value="<?php echo esc_attr($m('_lqm_dias_trab', 30)); ?>">
                <p class="lqm-error" id="lqm_dias_trab_error" aria-live="polite"></p>
            </div>
            <div class="lqm-field">
                <label>Licencia / Inasistencias</label>
                <div class="lqm-row">
                    <input type="number" id="lqm_dias_lic" name="lqm_dias_lic" min="0" max="31" step="1" placeholder="Licencia" value="<?php echo esc_attr($m('_lqm_dias_lic', 0)); ?>">
                    <input type="number" id="lqm_dias_inas" name="lqm_dias_inas" min="0" max="31" step="1" placeholder="Inasist." value="<?php echo esc_attr($m('_lqm_dias_inas', 0)); ?>">
                </div>
                <p class="lqm-error" id="lqm_dias_error" aria-live="polite"></p>
            </div>

            <div class="lqm-field">
                <label for="lqm_sueldo_base">Sueldo Base (Imponible)</label>
                <input type="number" id="lqm_sueldo_base" name="lqm_sueldo_base" min="0" step="1" value="<?php echo esc_attr($m('_lqm_sueldo_base', 0)); ?>">
                <div class="lqm-small">MVP: Total Imponible = Sueldo Base</div>
                <p class="lqm-error" id="lqm_sueldo_base_error" aria-live="polite"></p>
            </div>

            <div class="lqm-field">
                <label for="lqm_impuesto_unico">Impuesto Único (manual, MVP)</label>
                <input type="number" id="lqm_impuesto_unico" name="lqm_impuesto_unico" min="0" step="1" value="<?php echo esc_attr($m('_lqm_impuesto_unico', 0)); ?>">
            </div>

            <div class="lqm-field">
                <label for="lqm_otros_desc">Otros Descuentos (manual)</label>
                <input type="number" id="lqm_otros_desc" name="lqm_otros_desc" min="0" step="1" value="<?php echo esc_attr($m('_lqm_otros_desc', 0)); ?>">
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
                            <td><input type="number" name="lqm_noimp_monto[]" min="0" step="1" value=""></td>
                            <td><button type="button" class="button lqm-del" aria-label="Quitar item no imponible">Quitar</button></td>
                        </tr>
                    <?php else : foreach ($no_imp as $row) : ?>
                        <tr>
                            <td><input type="text" name="lqm_noimp_nombre[]" value="<?php echo esc_attr($row['nombre'] ?? ''); ?>"></td>
                            <td><input type="number" name="lqm_noimp_monto[]" min="0" step="1" value="<?php echo esc_attr($row['monto'] ?? ''); ?>"></td>
                            <td><button type="button" class="button lqm-del" aria-label="Quitar item no imponible">Quitar</button></td>
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

        if (preg_match('/^\s*-/', (string) $value)) {
            return 0;
        }

        $value = preg_replace('/[^\d]/', '', (string) $value);

        if ($value === '') return 0;

        return absint($value);
    }

    public static function pdf_url($post_id) {
        if (!$post_id) return '';

        $nonce = wp_create_nonce('lqm_pdf_'.$post_id);

        return add_query_arg([
            'action' => 'lqm_pdf',
            'lqm_pdf' => $post_id,
            '_wpnonce' => $nonce,
        ], admin_url('admin-post.php'));
    }

    public static function row_actions($actions, $post) {
        if ($post->post_type !== self::CPT) return $actions;
        $actions['lqm_pdf'] = '<a target="_blank" href="'.esc_url(self::pdf_url($post->ID)).'">Ver PDF</a>';
        return $actions;
    }
}
