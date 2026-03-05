<?php
if ( ! defined('ABSPATH') ) { exit; }

final class CL_LIQ_Frontend {

    const SLUG_DEFAULT = 'liquidaciones-cl';

    public static function init() {
        add_action('init', [__CLASS__, 'add_rewrite_rules']);
        add_filter('query_vars', [__CLASS__, 'query_vars']);
        add_action('template_redirect', [__CLASS__, 'template_redirect']);
    }

    public static function activate() {
        self::add_caps();
        // Ensure rules exist before flushing
        self::add_rewrite_rules();
        flush_rewrite_rules();
    }

    private static function add_caps() {
        $role = get_role('administrator');
        if (!$role) return;

        $caps = ['manage_cl_liquidaciones'];
        if (class_exists('CL_LIQ_CPT')) {
            $caps = array_merge($caps, CL_LIQ_CPT::all_caps());
        }

        foreach (array_unique($caps) as $cap) {
            if (!$role->has_cap($cap)) {
                $role->add_cap($cap);
            }
        }
    }

    public static function query_vars($vars) {
        $vars[] = 'cl_liq_fe';
        $vars[] = 'cl_liq_view';
        $vars[] = 'cl_liq_id';
        return $vars;
    }

    private static function slug(): string {
        $slug = apply_filters('cl_liq_front_slug', self::SLUG_DEFAULT);
        $slug = sanitize_title_with_dashes((string)$slug);
        return $slug ?: self::SLUG_DEFAULT;
    }

    public static function add_rewrite_rules() {
        $slug = self::slug();

        // Liquidaciones
        add_rewrite_rule('^' . $slug . '/?$', 'index.php?cl_liq_fe=1&cl_liq_view=list', 'top');
        add_rewrite_rule('^' . $slug . '/nueva/?$', 'index.php?cl_liq_fe=1&cl_liq_view=new', 'top');
        add_rewrite_rule('^' . $slug . '/editar/([0-9]+)/?$', 'index.php?cl_liq_fe=1&cl_liq_view=edit&cl_liq_id=$matches[1]', 'top');
        add_rewrite_rule('^' . $slug . '/pdf/([0-9]+)/?$', 'index.php?cl_liq_fe=1&cl_liq_view=pdf&cl_liq_id=$matches[1]', 'top');

        // Empleados
        add_rewrite_rule('^' . $slug . '/empleados/?$', 'index.php?cl_liq_fe=1&cl_liq_view=employees', 'top');
        add_rewrite_rule('^' . $slug . '/empleados/nuevo/?$', 'index.php?cl_liq_fe=1&cl_liq_view=emp_new', 'top');
        add_rewrite_rule('^' . $slug . '/empleados/editar/([0-9]+)/?$', 'index.php?cl_liq_fe=1&cl_liq_view=emp_edit&cl_liq_id=$matches[1]', 'top');

        // Períodos
        add_rewrite_rule('^' . $slug . '/periodos/?$', 'index.php?cl_liq_fe=1&cl_liq_view=periods', 'top');
        add_rewrite_rule('^' . $slug . '/periodos/nuevo/?$', 'index.php?cl_liq_fe=1&cl_liq_view=per_new', 'top');
        add_rewrite_rule('^' . $slug . '/periodos/editar/([0-9]+)/?$', 'index.php?cl_liq_fe=1&cl_liq_view=per_edit&cl_liq_id=$matches[1]', 'top');
    }

    private static function require_auth() {
        if (!is_user_logged_in()) {
            wp_redirect(wp_login_url(self::current_url()));
            exit;
        }
        if (!current_user_can('manage_cl_liquidaciones') && !current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }
    }

    public static function template_redirect() {
        $is = (int) get_query_var('cl_liq_fe');
        if ($is !== 1) return;

        self::require_auth();

        $view = (string) get_query_var('cl_liq_view');
        $id = (int) get_query_var('cl_liq_id');

        switch ($view) {
            case 'pdf':
                self::render_pdf($id);
                exit;
            case 'new':
                self::render_liq_form(0);
                exit;
            case 'edit':
                self::render_liq_form($id);
                exit;
            case 'employees':
                self::render_employees_list();
                exit;
            case 'emp_new':
                self::render_employee_form(0);
                exit;
            case 'emp_edit':
                self::render_employee_form($id);
                exit;
            case 'periods':
                self::render_periods_list();
                exit;
            case 'per_new':
                self::render_period_form(0);
                exit;
            case 'per_edit':
                self::render_period_form($id);
                exit;
            default:
                self::render_liq_list();
                exit;
        }
    }

    private static function base_url(): string {
        return trailingslashit(home_url('/' . self::slug() . '/'));
    }

    private static function current_url(): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'] ?? ''));
        $uri  = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'));
        if (!$host) return home_url('/');
        return $scheme . '://' . $host . $uri;
    }

    private static function safe_return_url(string $url, string $fallback): string {
        $url = trim($url);
        if (!$url) return $fallback;
        $validated = wp_validate_redirect($url, false);
        if (!$validated) return $fallback;
        return $validated;
    }

    private static function html_head(string $title) {
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        echo '<!doctype html><html><head>';
        echo '<meta charset="' . esc_attr(get_bloginfo('charset')) . '">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>' . esc_html($title) . '</title>';
        echo '<style>';
        echo 'body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:0;background:#f5f6f8;color:#111;}';
        echo '.wrap{max-width:1100px;margin:24px auto;padding:0 16px;}';
        echo '.card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.04);padding:16px;}';
        echo 'h1{font-size:22px;margin:0 0 12px;}';
        echo 'h2{font-size:16px;margin:18px 0 8px;}';
        echo 'a{color:#0f62fe;text-decoration:none} a:hover{text-decoration:underline}';
        echo '.skip-link{position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden}';
        echo '.skip-link:focus{left:16px;top:12px;width:auto;height:auto;z-index:10000;background:#111827;color:#fff;padding:8px 10px;border-radius:8px}';
        echo '.topbar{display:flex;gap:10px;align-items:flex-start;justify-content:space-between;margin-bottom:12px}';
        echo '.nav{display:flex;gap:8px;flex-wrap:wrap;margin:6px 0 0}';
        echo '.nav a{display:inline-block;padding:6px 10px;border-radius:999px;background:#fff;border:1px solid #e5e7eb;color:#111;font-weight:700;font-size:13px}';
        echo '.nav a.active{background:#111827;color:#fff;border-color:#111827}';
        echo '.btn{display:inline-block;background:#0f62fe;color:#fff;padding:10px 12px;border-radius:10px;font-weight:600;border:0;cursor:pointer}';
        echo '.btn.secondary{background:#111827}';
        echo '.btn.ghost{background:#fff;color:#111;border:1px solid #d1d5db}';
        echo '.btn.small{padding:6px 10px;border-radius:8px;font-size:13px}';
        echo 'table{width:100%;border-collapse:collapse} th,td{padding:10px;border-bottom:1px solid #eef2f7;text-align:left;font-size:14px;vertical-align:top}';
        echo 'th{color:#374151;font-weight:700;background:#fafafa;position:sticky;top:0}';
        echo '.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}';
        echo '.grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}';
        echo 'label{font-size:13px;color:#374151;font-weight:600;display:block;margin:0 0 6px}';
        echo 'input,select{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:10px;font-size:14px}';
        echo 'a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible{outline:3px solid #2563eb;outline-offset:2px}';
        echo '.sr-only{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;white-space:nowrap!important;border:0!important}';
        echo '.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}';
        echo '.note{font-size:13px;color:#6b7280;margin-top:6px}';
        echo '.msg{padding:10px 12px;border-radius:10px;background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;margin:0 0 12px}';
        echo '.err{padding:10px 12px;border-radius:10px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;margin:0 0 12px}';
        echo '.muted{color:#6b7280}';
        echo '@media(max-width:820px){.grid,.grid3{grid-template-columns:1fr}.topbar{flex-direction:column;align-items:stretch}}';
        echo '</style>';
        echo '</head><body>';
        echo '<a class="skip-link" href="#cl-main">' . esc_html__('Saltar al contenido principal', 'liquidaciones-cl') . '</a>';
        echo '<div class="wrap"><main id="cl-main" role="main">';
    }

    private static function html_foot() {
        echo '</main></div></body></html>';
    }

    private static function render_nav(string $active) {
        $base = self::base_url();
        $tabs = [
            'liq' => ['Liquidaciones', $base],
            'emp' => ['Empleados', $base . 'empleados/'],
            'per' => ['Períodos', $base . 'periodos/'],
        ];
        echo '<div class="nav">';
        foreach ($tabs as $k => $t) {
            $cls = ($k === $active) ? 'active' : '';
            echo '<a class="' . esc_attr($cls) . '" href="' . esc_url($t[1]) . '">' . esc_html($t[0]) . '</a>';
        }
        echo '<a href="' . esc_url(admin_url('admin.php?page=cl-liquidaciones-settings')) . '">Parámetros</a>';
        echo '<a href="' . esc_url(admin_url()) . '">WP Admin</a>';
        echo '</div>';
    }

    // ----------------------------
    // Liquidaciones
    // ----------------------------

    private static function render_liq_list() {
        $base = self::base_url();

        $periodo_id  = isset($_GET['periodo_id']) ? (int) $_GET['periodo_id'] : 0;
        $empleado_id = isset($_GET['empleado_id']) ? (int) $_GET['empleado_id'] : 0;
        $q_term      = sanitize_text_field(wp_unslash($_GET['q'] ?? ''));
        $msg         = sanitize_text_field(wp_unslash($_GET['msg'] ?? ''));

        $args = [
            'post_type'      => 'cl_liquidacion',
            'post_status'    => ['private','publish','draft'],
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $meta_query = ['relation' => 'AND'];

        if ($periodo_id > 0) {
            $meta_query[] = [
                'key'     => 'cl_periodo_id',
                'value'   => $periodo_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
        }

        if ($empleado_id > 0) {
            $meta_query[] = [
                'key'     => 'cl_empleado_id',
                'value'   => $empleado_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
        }

        if ($q_term !== '') {
            if (preg_match('/^\d{4}-\d{2}$/', $q_term)) {
                $pids = self::find_period_ids_by_ym($q_term);
                $meta_query[] = [
                    'key'     => 'cl_periodo_id',
                    'value'   => $pids ? $pids : [-1],
                    'compare' => 'IN',
                    'type'    => 'NUMERIC',
                ];
            } else {
                $eids = self::find_employee_ids_by_q($q_term);
                $meta_query[] = [
                    'key'     => 'cl_empleado_id',
                    'value'   => $eids ? $eids : [-1],
                    'compare' => 'IN',
                    'type'    => 'NUMERIC',
                ];
            }
        }

        if (count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }

        $q = new WP_Query($args);

        $period_items = class_exists('CL_LIQ_Updater') ? CL_LIQ_Updater::get_periods_for_selector($periodo_id) : [];
        $periodos = [];
        if (empty($period_items)) {
            $periodos = get_posts(['post_type'=>'cl_periodo','numberposts'=>-1,'post_status'=>'any','orderby'=>'title','order'=>'DESC']);
        }
        $empleados = get_posts(['post_type'=>'cl_empleado','numberposts'=>500,'post_status'=>'any','orderby'=>'title','order'=>'ASC']);

        self::html_head('Liquidaciones');

        echo '<div class="topbar">';
        echo '<div>';
        echo '<h1>Gestión de Liquidaciones</h1>';
        echo '<div class="note">Ruta: <code>' . esc_html(parse_url($base, PHP_URL_PATH) ?: '/') . '</code></div>';
        self::render_nav('liq');
        echo '</div>';
        echo '<div class="row">';
        echo '<a class="btn" href="' . esc_url($base . 'nueva/') . '">+ Nueva</a>';
        echo '<a class="btn ghost" href="' . esc_url(admin_url('edit.php?post_type=cl_liquidacion')) . '">Admin</a>';
        echo '</div>';
        echo '</div>';

        if ($msg === 'saved') {
            echo '<div class="msg" role="status" aria-live="polite">Guardado.</div>';
        }

        echo '<div class="card">';

        echo '<form method="get" class="row" style="margin:0 0 12px">';
        echo '<input type="hidden" name="cl_liq_dummy" value="1">';

        echo '<label style="min-width:260px">Empleado'
            . '<select name="empleado_id" onchange="this.form.submit()">'
            . '<option value="0">Todos</option>';
        foreach ($empleados as $e) {
            $rut = get_post_meta($e->ID, 'cl_rut', true);
            $label = $e->post_title . ($rut ? ' (' . $rut . ')' : '');
            echo '<option value="' . esc_attr($e->ID) . '" ' . selected($empleado_id, $e->ID, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';

        echo '<label style="min-width:240px">Período'
            . '<select name="periodo_id" onchange="this.form.submit()">'
            . '<option value="0">Todos</option>';
        if (!empty($period_items)) {
            foreach ($period_items as $it) {
                $pid = (int) ($it['id'] ?? 0);
                $ym = (string) ($it['ym'] ?? '');
                $label = (string) ($it['label'] ?? $ym);
                $uf = (string) ($it['uf'] ?? '');
                $suffix = $uf ? (' · UF ' . $uf) : '';
                echo '<option value="' . esc_attr($pid) . '" ' . selected($periodo_id, $pid, false) . '>' . esc_html($label . $suffix) . '</option>';
            }
        } else {
            foreach ($periodos as $p) {
                $ym = get_post_meta($p->ID, 'cl_ym', true);
                $label = $ym ? CL_LIQ_Helpers::ym_label($ym) : $p->post_title;
                echo '<option value="' . esc_attr($p->ID) . '" ' . selected($periodo_id, $p->ID, false) . '>' . esc_html($label) . '</option>';
            }
        }
        echo '</select></label>';

        echo '<label style="min-width:260px">Búsqueda (empleado/RUT o YYYY-MM)'
            . '<input type="text" name="q" value="' . esc_attr($q_term) . '" placeholder="Ej: 12.345.678-9 o 2026-03">'
            . '</label>';

        echo '<button class="btn small" type="submit">Buscar</button>';
        echo '<a class="btn small ghost" href="' . esc_url($base) . '">Limpiar</a>';
        echo '</form>';

        echo '<div style="overflow:auto">';
        echo '<table><caption class="sr-only">Listado de liquidaciones</caption>';
        echo '<thead><tr><th>ID</th><th>Empleado</th><th>Período</th><th>Líquido</th><th>Acciones</th></tr></thead><tbody>';

        if ($q->have_posts()) {
            while ($q->have_posts()) {
                $q->the_post();
                $id = get_the_ID();
                $eid = (int) get_post_meta($id, 'cl_empleado_id', true);
                $pid = (int) get_post_meta($id, 'cl_periodo_id', true);
                $emp = $eid ? get_the_title($eid) : '—';
                $ym = $pid ? (string) get_post_meta($pid, 'cl_ym', true) : '';
                $per = $ym ? CL_LIQ_Helpers::ym_label($ym) : '—';
                $calc = get_post_meta($id, 'cl_calc', true);
                $liq = is_array($calc) ? (int) ($calc['liquido'] ?? 0) : 0;

                $pdf_nonce = wp_create_nonce('cl_liq_pdf_' . $id);
                $pdf_url = $base . 'pdf/' . $id . '/?_wpnonce=' . rawurlencode($pdf_nonce);

                echo '<tr>';
                echo '<td>' . esc_html((string)$id) . '</td>';
                echo '<td>' . esc_html($emp) . '</td>';
                echo '<td>' . esc_html($per) . '</td>';
                echo '<td><strong>' . esc_html(CL_LIQ_Helpers::money($liq)) . '</strong></td>';
                echo '<td class="row">';
                echo '<a class="btn small secondary" href="' . esc_url($base . 'editar/' . $id . '/') . '">Editar</a>';
                echo '<a class="btn small" target="_blank" href="' . esc_url($pdf_url) . '">PDF</a>';
                echo '</td>';
                echo '</tr>';
            }
            wp_reset_postdata();
        } else {
            echo '<tr><td colspan="5">No hay resultados.</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';

        echo '</div>';

        self::html_foot();
    }

    private static function render_liq_form(int $liq_id) {
        $base = self::base_url();
        $is_edit = $liq_id > 0;

        if ($is_edit) {
            $post = get_post($liq_id);
            if (!$post || $post->post_type !== 'cl_liquidacion') {
                wp_die('Liquidación no encontrada.');
            }
            if (!current_user_can('edit_post', $liq_id) && !current_user_can('manage_cl_liquidaciones')) {
                wp_die('No autorizado.');
            }
        }

        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cl_liq_fe_save'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['cl_liq_nonce'] ?? ''));
            if (!wp_verify_nonce($nonce, 'cl_liq_fe_save')) {
                $error = 'Nonce inválido.';
            } else {
                $data = $_POST['cl_liq'] ?? [];

                $empleado_id = (int) ($data['cl_empleado_id'] ?? 0);
                $periodo_id  = (int) ($data['cl_periodo_id'] ?? 0);

                $numeric_fields = [
                    'cl_sueldo_base','cl_grat_manual','cl_he_horas','cl_he_valor_hora','cl_bonos_imponibles','cl_comisiones',
                    'cl_otros_imponibles','cl_colacion','cl_movilizacion','cl_viaticos','cl_otros_no_imponibles',
                    'cl_asig_manual_monto','cl_otros_descuentos','cl_anticipos','cl_prestamos'
                ];
                foreach ($numeric_fields as $nf) {
                    if (CL_LIQ_Helpers::is_negative_number_input($data[$nf] ?? '')) {
                        $error = __('No se permiten valores negativos en el formulario.', 'liquidaciones-cl');
                        break;
                    }
                }

                if (!$error && ($empleado_id <= 0 || $periodo_id <= 0)) {
                    $error = 'Debes seleccionar empleado y período.';
                } else {
                    if (!$is_edit) {
                        $title = trim(get_the_title($empleado_id) . ' - ' . (string) get_post_meta($periodo_id, 'cl_ym', true));
                        $new_id = wp_insert_post([
                            'post_type'   => 'cl_liquidacion',
                            'post_status' => 'private',
                            'post_title'  => $title ?: 'Liquidación',
                        ], true);

                        if (is_wp_error($new_id)) {
                            $error = 'No se pudo crear la liquidación.';
                        } else {
                            $liq_id = (int) $new_id;
                            $is_edit = true;
                        }
                    }

                    if ($is_edit && !$error) {
                        update_post_meta($liq_id, 'cl_empleado_id', $empleado_id);
                        update_post_meta($liq_id, 'cl_periodo_id', $periodo_id);

                        // map fields
                        $map = [
                            'cl_sueldo_base' => 'clp',
                            'cl_grat_tipo' => 'text',
                            'cl_grat_manual' => 'clp',
                            'cl_he_horas' => 'decimal',
                            'cl_he_valor_hora' => 'clp',
                            'cl_bonos_imponibles' => 'clp',
                            'cl_comisiones' => 'clp',
                            'cl_otros_imponibles' => 'clp',
                            'cl_colacion' => 'clp',
                            'cl_movilizacion' => 'clp',
                            'cl_viaticos' => 'clp',
                            'cl_otros_no_imponibles' => 'clp',
                            'cl_asig_manual_monto' => 'clp',
                            'cl_otros_descuentos' => 'clp',
                            'cl_anticipos' => 'clp',
                            'cl_prestamos' => 'clp',
                        ];

                        foreach ($map as $k => $type) {
                            $val = $data[$k] ?? 0;
                            if ($type === 'text') {
                                update_post_meta($liq_id, $k, sanitize_text_field($val));
                            } elseif ($type === 'decimal') {
                                update_post_meta($liq_id, $k, CL_LIQ_Helpers::parse_decimal($val));
                            } else {
                                update_post_meta($liq_id, $k, CL_LIQ_Helpers::parse_clp($val));
                            }
                        }

                        // Defaults
                        $grat_tipo = sanitize_text_field($data['cl_grat_tipo'] ?? 'ninguna');
                        if (!$grat_tipo) $grat_tipo = 'ninguna';
                        update_post_meta($liq_id, 'cl_grat_tipo', $grat_tipo);

                        // calculate + store
                        $calc = CL_LIQ_Calculator::calculate_liquidacion($liq_id);
                        update_post_meta($liq_id, 'cl_calc', $calc);

                        // keep a clean title
                        $ym = (string) get_post_meta($periodo_id, 'cl_ym', true);
                        $title = trim(get_the_title($empleado_id) . ' - ' . $ym);
                        if ($title) {
                            wp_update_post(['ID' => $liq_id, 'post_title' => $title]);
                        }

                        if (class_exists('CL_LIQ_Audit')) {
                            CL_LIQ_Audit::log_post_change($liq_id, 'cl_liquidacion', 'frontend_save');
                        }

                        wp_redirect($base . 'editar/' . $liq_id . '/?msg=saved');
                        exit;
                    }
                }
            }
        }

        $empleado_id = $is_edit ? (int) get_post_meta($liq_id, 'cl_empleado_id', true) : 0;
        
        $periodo_id  = $is_edit ? (int) get_post_meta($liq_id, 'cl_periodo_id', true) : 0;

        // Default período = mes actual (auto-crea si falta)
        if (!$is_edit && $periodo_id <= 0 && class_exists('CL_LIQ_Updater')) {
            $ym_now = CL_LIQ_Helpers::current_ym();
            $pid_now = CL_LIQ_Updater::ensure_period($ym_now);
            if ($pid_now > 0) $periodo_id = $pid_now;
        }


        $fields = [
            'cl_sueldo_base','cl_grat_tipo','cl_grat_manual','cl_he_horas','cl_he_valor_hora',
            'cl_bonos_imponibles','cl_comisiones','cl_otros_imponibles',
            'cl_colacion','cl_movilizacion','cl_viaticos','cl_otros_no_imponibles',
            'cl_otros_descuentos','cl_anticipos','cl_prestamos','cl_asig_manual_monto'
        ];
        $v = [];
        foreach ($fields as $f) {
            $v[$f] = $is_edit ? get_post_meta($liq_id, $f, true) : '';
        }
        $v['cl_grat_tipo'] = $v['cl_grat_tipo'] ?: 'ninguna';

        $empleados = get_posts(['post_type'=>'cl_empleado','numberposts'=>500,'post_status'=>'any','orderby'=>'title','order'=>'ASC']);
        $period_items = class_exists('CL_LIQ_Updater') ? CL_LIQ_Updater::get_periods_for_selector($periodo_id) : [];
        $periodos = [];
        if (empty($period_items)) {
            $periodos = get_posts(['post_type'=>'cl_periodo','numberposts'=>-1,'post_status'=>'any','orderby'=>'title','order'=>'DESC']);
        }

        $msg = sanitize_text_field(wp_unslash($_GET['msg'] ?? ''));

        $return_here = $base . ($is_edit ? 'editar/' . $liq_id . '/' : 'nueva/');
        $emp_new_url = $base . 'empleados/nuevo/?return=' . rawurlencode($return_here);
        $per_new_url = $base . 'periodos/nuevo/?return=' . rawurlencode($return_here);
        $emp_edit_url = ($empleado_id > 0)
            ? ($base . 'empleados/editar/' . $empleado_id . '/?return=' . rawurlencode($return_here))
            : '';
        $per_edit_url = ($periodo_id > 0)
            ? ($base . 'periodos/editar/' . $periodo_id . '/?return=' . rawurlencode($return_here))
            : '';

        self::html_head($is_edit ? 'Editar liquidación' : 'Nueva liquidación');

        echo '<div class="topbar">';
        echo '<div><h1>' . ($is_edit ? 'Editar liquidación #' . esc_html((string)$liq_id) : 'Nueva liquidación') . '</h1>';
        echo '<div class="note"><a href="' . esc_url($base) . '">← Volver al listado</a></div>';
        self::render_nav('liq');
        echo '</div>';
        echo '<div class="row">';
        if ($is_edit) {
            $pdf_nonce = wp_create_nonce('cl_liq_pdf_' . $liq_id);
            $pdf_url = $base . 'pdf/' . $liq_id . '/?_wpnonce=' . rawurlencode($pdf_nonce);
            echo '<a class="btn" target="_blank" href="' . esc_url($pdf_url) . '">Ver PDF</a>';
        }
        echo '</div>';
        echo '</div>';

        if ($msg === 'saved') {
            echo '<div class="msg" role="status" aria-live="polite">Liquidación guardada y recalculada.</div>';
        }
        if ($error) {
            echo '<div class="err" role="alert" aria-live="assertive">' . esc_html($error) . '</div>';
        }

        echo '<div class="card">';
        echo '<form method="post">';
        echo '<input type="hidden" name="cl_liq_fe_save" value="1">';
        wp_nonce_field('cl_liq_fe_save', 'cl_liq_nonce');

        echo '<div class="grid">';

        // Selector rápido empleado (filtro)
        echo '<div>';
        echo '<label>Buscar empleado (rápido)</label>';
        echo '<input type="text" id="clEmpFilter" placeholder="Escribe nombre o RUT…" autocomplete="off">';
        echo '<div class="note">Filtra el selector de abajo. <a href="' . esc_url($emp_new_url) . '">Crear empleado</a>';
        if ($emp_edit_url) {
            echo ' · <a href="' . esc_url($emp_edit_url) . '">Editar empleado</a>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div>';
        echo '<label>Empleado</label>';
        echo '<select id="clEmpSelect" name="cl_liq[cl_empleado_id]" required>';
        echo '<option value="">— Selecciona —</option>';
        foreach ($empleados as $e) {
            $rut = get_post_meta($e->ID, 'cl_rut', true);
            $label = $e->post_title . ($rut ? ' (' . $rut . ')' : '');
            echo '<option value="' . esc_attr($e->ID) . '" ' . selected($empleado_id, $e->ID, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '</div>';

        echo '<div class="grid" style="margin-top:12px">';
        echo '<div>';
        echo '<label>Período</label><select name="cl_liq[cl_periodo_id]" required>';
        echo '<option value="">— Selecciona —</option>';
        foreach ($periodos as $p) {
            $ym = get_post_meta($p->ID, 'cl_ym', true);
            $label = $ym ? CL_LIQ_Helpers::ym_label($ym) : $p->post_title;
            echo '<option value="' . esc_attr($p->ID) . '" ' . selected($periodo_id, $p->ID, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<div class="note"><a href="' . esc_url($per_new_url) . '">Crear período</a> <span class="muted">(el selector muestra ventana automática)</span>';
        if ($per_edit_url) {
            echo ' · <a href="' . esc_url($per_edit_url) . '">Editar período</a>';
        }
        echo '</div>';
        echo '</div>';
        echo '<div></div>';
        echo '</div>';

        echo '<script>';
        echo '(function(){\n'
            . 'var inp=document.getElementById("clEmpFilter");\n'
            . 'var sel=document.getElementById("clEmpSelect");\n'
            . 'if(!inp||!sel) return;\n'
            . 'function norm(s){return (s||"").toString().toLowerCase();}\n'
            . 'inp.addEventListener("input", function(){\n'
            . '  var q=norm(inp.value);\n'
            . '  for(var i=0;i<sel.options.length;i++){\n'
            . '    var opt=sel.options[i];\n'
            . '    if(i===0){opt.hidden=false;continue;}\n'
            . '    opt.hidden = q && norm(opt.text).indexOf(q)===-1;\n'
            . '  }\n'
            . '});\n'
            . '})();';
        echo '</script>';

        echo '<h2>Haberes</h2>';
        echo '<div class="grid3">';
        echo '<div><label>Sueldo base (CLP)</label><input name="cl_liq[cl_sueldo_base]" type="text" value="' . esc_attr($v['cl_sueldo_base']) . '"></div>';
        echo '<div><label>Gratificación</label><select name="cl_liq[cl_grat_tipo]">';
        echo '<option value="ninguna" ' . selected($v['cl_grat_tipo'], 'ninguna', false) . '>Ninguna</option>';
        echo '<option value="legal" ' . selected($v['cl_grat_tipo'], 'legal', false) . '>Legal (25% con tope)</option>';
        echo '<option value="manual" ' . selected($v['cl_grat_tipo'], 'manual', false) . '>Manual</option>';
        echo '</select></div>';
        echo '<div><label>Grat. manual (CLP)</label><input name="cl_liq[cl_grat_manual]" type="text" value="' . esc_attr($v['cl_grat_manual']) . '"></div>';
        echo '</div>';

        echo '<div class="grid3" style="margin-top:12px">';
        echo '<div><label>Horas extra (horas)</label><input name="cl_liq[cl_he_horas]" type="text" value="' . esc_attr($v['cl_he_horas']) . '" placeholder="Ej: 10"></div>';
        echo '<div><label>Valor hora (opcional)</label><input name="cl_liq[cl_he_valor_hora]" type="text" value="' . esc_attr($v['cl_he_valor_hora']) . '" placeholder="Ej: 3500"></div>';
        echo '<div><label>Bonos imponibles</label><input name="cl_liq[cl_bonos_imponibles]" type="text" value="' . esc_attr($v['cl_bonos_imponibles']) . '"></div>';
        echo '</div>';

        echo '<div class="grid3" style="margin-top:12px">';
        echo '<div><label>Comisiones</label><input name="cl_liq[cl_comisiones]" type="text" value="' . esc_attr($v['cl_comisiones']) . '"></div>';
        echo '<div><label>Otros imponibles</label><input name="cl_liq[cl_otros_imponibles]" type="text" value="' . esc_attr($v['cl_otros_imponibles']) . '"></div>';
        echo '<div><label>Asignación fam. manual</label><input name="cl_liq[cl_asig_manual_monto]" type="text" value="' . esc_attr($v['cl_asig_manual_monto']) . '"><div class="note">Si es 0/vacío, se calcula desde el empleado.</div></div>';
        echo '</div>';

        echo '<h2>No imponibles</h2>';
        echo '<div class="grid3">';
        echo '<div><label>Colación</label><input name="cl_liq[cl_colacion]" type="text" value="' . esc_attr($v['cl_colacion']) . '"></div>';
        echo '<div><label>Movilización</label><input name="cl_liq[cl_movilizacion]" type="text" value="' . esc_attr($v['cl_movilizacion']) . '"></div>';
        echo '<div><label>Viáticos</label><input name="cl_liq[cl_viaticos]" type="text" value="' . esc_attr($v['cl_viaticos']) . '"></div>';
        echo '</div>';
        echo '<div class="grid" style="margin-top:12px">';
        echo '<div><label>Otros no imponibles</label><input name="cl_liq[cl_otros_no_imponibles]" type="text" value="' . esc_attr($v['cl_otros_no_imponibles']) . '"></div>';
        echo '<div></div>';
        echo '</div>';

        echo '<h2>Descuentos</h2>';
        echo '<div class="grid3">';
        echo '<div><label>Otros descuentos</label><input name="cl_liq[cl_otros_descuentos]" type="text" value="' . esc_attr($v['cl_otros_descuentos']) . '"></div>';
        echo '<div><label>Anticipos</label><input name="cl_liq[cl_anticipos]" type="text" value="' . esc_attr($v['cl_anticipos']) . '"></div>';
        echo '<div><label>Préstamos</label><input name="cl_liq[cl_prestamos]" type="text" value="' . esc_attr($v['cl_prestamos']) . '"></div>';
        echo '</div>';

        echo '<div class="row" style="margin-top:16px">';
        echo '<button class="btn" type="submit">Guardar & recalcular</button>';
        echo '<a class="btn ghost" href="' . esc_url($base) . '">Cancelar</a>';
        echo '</div>';
        echo '<div class="note" style="margin-top:10px">Los cálculos se recalculan al guardar (AFP, Salud, AFC, Impuesto Único y líquido).</div>';

        echo '</form>';
        echo '</div>';

        if ($is_edit) {
            $calc = get_post_meta($liq_id, 'cl_calc', true);
            if (is_array($calc) && !empty($calc)) {
                echo '<div style="height:12px"></div>';
                echo '<div class="card">';
                echo '<h2>Resumen calculado</h2>';
                echo '<div class="grid3">';
                echo '<div><div class="note">Haberes</div><div style="font-size:18px;font-weight:800">' . esc_html(CL_LIQ_Helpers::money((int)($calc['haberes_total'] ?? 0))) . '</div></div>';
                echo '<div><div class="note">Descuentos</div><div style="font-size:18px;font-weight:800">' . esc_html(CL_LIQ_Helpers::money((int)($calc['descuentos_total'] ?? 0))) . '</div></div>';
                echo '<div><div class="note">Líquido</div><div style="font-size:18px;font-weight:900">' . esc_html(CL_LIQ_Helpers::money((int)($calc['liquido'] ?? 0))) . '</div></div>';
                echo '</div>';
                echo '</div>';
            }
        }

        self::html_foot();
    }

    private static function render_pdf(int $liq_id) {
        if ($liq_id <= 0) wp_die('ID inválido.');

        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'cl_liq_pdf_' . $liq_id)) {
            wp_die('Nonce inválido.');
        }

        if (!current_user_can('edit_post', $liq_id) && !current_user_can('manage_cl_liquidaciones')) {
            wp_die('No autorizado.');
        }

        $pdf = CL_LIQ_PDF::render_pdf($liq_id);
        if (!$pdf) wp_die('No fue posible generar el PDF.');

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="liquidacion-' . $liq_id . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    // ----------------------------
    // Empleados
    // ----------------------------

    private static function render_employees_list() {
        $base = self::base_url();
        $q_term = sanitize_text_field(wp_unslash($_GET['q'] ?? ''));
        $msg = sanitize_text_field(wp_unslash($_GET['msg'] ?? ''));

        $ids = [];
        if ($q_term !== '') {
            $ids = self::find_employee_ids_by_q($q_term);
            if (!$ids) $ids = [-1];
        }

        $args = [
            'post_type'      => 'cl_empleado',
            'post_status'    => ['publish','private','draft'],
            'posts_per_page' => 100,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        if ($q_term !== '') {
            $args['post__in'] = $ids;
        }

        $q = new WP_Query($args);

        self::html_head('Empleados');

        echo '<div class="topbar">';
        echo '<div><h1>Empleados</h1>';
        echo '<div class="note"><a href="' . esc_url($base) . '">← Volver a Liquidaciones</a></div>';
        self::render_nav('emp');
        echo '</div>';
        echo '<div class="row">';
        echo '<a class="btn" href="' . esc_url($base . 'empleados/nuevo/') . '">+ Nuevo empleado</a>';
        echo '</div>';
        echo '</div>';

        if ($msg === 'saved') {
            echo '<div class="msg" role="status" aria-live="polite">Empleado guardado.</div>';
        }

        echo '<div class="card">';

        echo '<form method="get" class="row" style="margin:0 0 12px">';
        echo '<label style="min-width:320px">Búsqueda (nombre o RUT)'
            . '<input type="text" name="q" value="' . esc_attr($q_term) . '" placeholder="Ej: Juan o 12.345.678-9">'
            . '</label>';
        echo '<button class="btn small" type="submit">Buscar</button>';
        echo '<a class="btn small ghost" href="' . esc_url($base . 'empleados/') . '">Limpiar</a>';
        echo '</form>';

        echo '<div style="overflow:auto">';
        echo '<table><caption class="sr-only">Listado de empleados</caption>';
        echo '<thead><tr><th>ID</th><th>Nombre</th><th>RUT</th><th>AFP</th><th>Salud</th><th>Acciones</th></tr></thead><tbody>';

        if ($q->have_posts()) {
            while ($q->have_posts()) {
                $q->the_post();
                $id = get_the_ID();
                $rut = (string) get_post_meta($id, 'cl_rut', true);
                $afp = (string) get_post_meta($id, 'cl_afp', true);
                $salud = (string) get_post_meta($id, 'cl_salud_tipo', true);

                echo '<tr>';
                echo '<td>' . esc_html((string)$id) . '</td>';
                echo '<td><strong>' . esc_html(get_the_title()) . '</strong></td>';
                echo '<td>' . esc_html($rut ?: '—') . '</td>';
                echo '<td>' . esc_html($afp ?: '—') . '</td>';
                echo '<td>' . esc_html($salud ?: '—') . '</td>';
                echo '<td class="row">';
                echo '<a class="btn small secondary" href="' . esc_url($base . 'empleados/editar/' . $id . '/') . '">Editar</a>';
                echo '</td>';
                echo '</tr>';
            }
            wp_reset_postdata();
        } else {
            echo '<tr><td colspan="6">No hay resultados.</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';

        echo '</div>';

        self::html_foot();
    }

    private static function render_employee_form(int $emp_id) {
        $base = self::base_url();
        $is_edit = $emp_id > 0;
        $error = '';

        $return = self::safe_return_url((string) sanitize_text_field(wp_unslash($_GET['return'] ?? '')), $base . 'empleados/');

        if ($is_edit) {
            $post = get_post($emp_id);
            if (!$post || $post->post_type !== 'cl_empleado') wp_die('Empleado no encontrado.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cl_liq_fe_emp_save'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['cl_liq_nonce'] ?? ''));
            if (!wp_verify_nonce($nonce, 'cl_liq_fe_emp_save')) {
                $error = 'Nonce inválido.';
            } else {
                $name = sanitize_text_field(wp_unslash($_POST['cl_emp_name'] ?? ''));
                if ($name === '') {
                    $error = 'Debes indicar el nombre.';
                } else {
                    if (!$is_edit) {
                        $new_id = wp_insert_post([
                            'post_type'   => 'cl_empleado',
                            'post_status' => 'publish',
                            'post_title'  => $name,
                        ], true);
                        if (is_wp_error($new_id)) {
                            $error = 'No se pudo crear el empleado.';
                        } else {
                            $emp_id = (int) $new_id;
                            $is_edit = true;
                        }
                    } else {
                        wp_update_post(['ID' => $emp_id, 'post_title' => $name]);
                    }

                    if ($is_edit && !$error) {
                        $rut_raw = sanitize_text_field(wp_unslash($_POST['cl_rut'] ?? ''));
                        if ($rut_raw !== '' && !CL_LIQ_Helpers::validate_rut($rut_raw)) {
                            $error = __('RUT inválido. Verifica formato y dígito verificador.', 'liquidaciones-cl');
                        }
                        $rut = CL_LIQ_Helpers::format_rut($rut_raw);

                        $tipo_contrato = sanitize_text_field(wp_unslash($_POST['cl_tipo_contrato'] ?? 'indefinido'));
                        $afp = sanitize_text_field(wp_unslash($_POST['cl_afp'] ?? 'Modelo'));
                        $salud_tipo = sanitize_text_field(wp_unslash($_POST['cl_salud_tipo'] ?? 'FONASA'));
                        $isapre_plan = CL_LIQ_Helpers::parse_clp($_POST['cl_isapre_plan_clp'] ?? 0);
                        $cargas = max(0, (int) ($_POST['cl_cargas'] ?? 0));
                        $tramo = sanitize_text_field(wp_unslash($_POST['cl_tramo_asig'] ?? 'auto'));

                        if (!$error) {
                            update_post_meta($emp_id, 'cl_rut', $rut);
                            update_post_meta($emp_id, 'cl_tipo_contrato', $tipo_contrato);
                            update_post_meta($emp_id, 'cl_afp', $afp);
                            update_post_meta($emp_id, 'cl_salud_tipo', $salud_tipo);
                            update_post_meta($emp_id, 'cl_isapre_plan_clp', $isapre_plan);
                            update_post_meta($emp_id, 'cl_cargas', $cargas);
                            update_post_meta($emp_id, 'cl_tramo_asig', $tramo);

                            if (class_exists('CL_LIQ_Audit')) {
                                CL_LIQ_Audit::log_post_change($emp_id, 'cl_empleado', 'frontend_save');
                            }

                            $redir = $return;
                            $sep = (strpos($redir, '?') === false) ? '?' : '&';
                            wp_redirect($redir . $sep . 'msg=saved');
                            exit;
                        }
                    }
                }
            }
        }

        $settings = CL_LIQ_Settings::get();
        $afps = array_keys($settings['afp_commissions']);

        $name = $is_edit ? get_the_title($emp_id) : '';
        $rut = $is_edit ? (string) get_post_meta($emp_id, 'cl_rut', true) : '';
        $tipo_contrato = $is_edit ? ((string) get_post_meta($emp_id, 'cl_tipo_contrato', true) ?: 'indefinido') : 'indefinido';
        $afp = $is_edit ? ((string) get_post_meta($emp_id, 'cl_afp', true) ?: 'Modelo') : 'Modelo';
        $salud_tipo = $is_edit ? ((string) get_post_meta($emp_id, 'cl_salud_tipo', true) ?: 'FONASA') : 'FONASA';
        $isapre_plan = $is_edit ? (string) get_post_meta($emp_id, 'cl_isapre_plan_clp', true) : '';
        $cargas = $is_edit ? (int) get_post_meta($emp_id, 'cl_cargas', true) : 0;
        $tramo = $is_edit ? ((string) get_post_meta($emp_id, 'cl_tramo_asig', true) ?: 'auto') : 'auto';

        self::html_head($is_edit ? 'Editar empleado' : 'Nuevo empleado');

        echo '<div class="topbar">';
        echo '<div><h1>' . ($is_edit ? 'Editar empleado #' . esc_html((string)$emp_id) : 'Nuevo empleado') . '</h1>';
        echo '<div class="note"><a href="' . esc_url($return) . '">← Volver</a></div>';
        self::render_nav('emp');
        echo '</div>';
        echo '<div class="row"></div>';
        echo '</div>';

        if ($error) {
            echo '<div class="err" role="alert" aria-live="assertive">' . esc_html($error) . '</div>';
        }

        echo '<div class="card">';
        echo '<form method="post">';
        echo '<input type="hidden" name="cl_liq_fe_emp_save" value="1">';
        wp_nonce_field('cl_liq_fe_emp_save', 'cl_liq_nonce');

        echo '<div class="grid">';
        echo '<div><label>Nombre</label><input name="cl_emp_name" type="text" value="' . esc_attr($name) . '" required></div>';
        echo '<div><label>RUT</label><input id="clRutInput" name="cl_rut" type="text" value="' . esc_attr($rut) . '" placeholder="12.345.678-9"></div>';
        echo '</div>';

        echo '<div class="grid3" style="margin-top:12px">';
        echo '<div><label>Tipo de contrato</label><select name="cl_tipo_contrato">';
        $opts = [
            'indefinido' => 'Indefinido',
            'plazo_fijo' => 'Plazo fijo',
            'obra' => 'Obra / Faena',
            'casa_particular' => 'Casa particular',
        ];
        foreach ($opts as $k => $lbl) {
            echo '<option value="' . esc_attr($k) . '" ' . selected($tipo_contrato, $k, false) . '>' . esc_html($lbl) . '</option>';
        }
        echo '</select></div>';

        echo '<div><label>AFP</label><select name="cl_afp">';
        foreach ($afps as $a) {
            echo '<option value="' . esc_attr($a) . '" ' . selected($afp, $a, false) . '>' . esc_html($a) . '</option>';
        }
        echo '</select></div>';

        echo '<div><label>Salud</label><select name="cl_salud_tipo">';
        echo '<option value="FONASA" ' . selected($salud_tipo, 'FONASA', false) . '>FONASA (7%)</option>';
        echo '<option value="ISAPRE" ' . selected($salud_tipo, 'ISAPRE', false) . '>ISAPRE (plan)</option>';
        echo '</select></div>';
        echo '</div>';

        echo '<div class="grid3" style="margin-top:12px">';
        echo '<div><label>Plan ISAPRE (CLP)</label><input name="cl_isapre_plan_clp" type="text" value="' . esc_attr($isapre_plan) . '" placeholder="Ej: 120000"><div class="note">Si Salud = ISAPRE, se usa este monto (mínimo 7% del imponible topeado).</div></div>';
        echo '<div><label>Cargas familiares</label><input name="cl_cargas" type="number" min="0" step="1" value="' . esc_attr((string)$cargas) . '"></div>';
        echo '<div><label>Tramo Asig. Familiar</label><select name="cl_tramo_asig">';
        echo '<option value="auto" ' . selected($tramo, 'auto', false) . '>Auto (según imponible)</option>';
        echo '<option value="1" ' . selected($tramo, '1', false) . '>Tramo 1</option>';
        echo '<option value="2" ' . selected($tramo, '2', false) . '>Tramo 2</option>';
        echo '<option value="3" ' . selected($tramo, '3', false) . '>Tramo 3</option>';
        echo '<option value="4" ' . selected($tramo, '4', false) . '>Tramo 4</option>';
        echo '</select></div>';
        echo '</div>';

        echo '<div class="row" style="margin-top:16px">';
        echo '<button class="btn" type="submit">Guardar</button>';
        echo '<a class="btn ghost" href="' . esc_url($return) . '">Cancelar</a>';
        echo '</div>';

        echo '<script>(function(){var i=document.getElementById("clRutInput");if(!i)return;function f(v){v=(v||"").toUpperCase().replace(/[^0-9K]/g,"");if(v.length<2)return v;var b=v.slice(0,-1),d=v.slice(-1);var out="",c=0;for(var x=b.length-1;x>=0;x--){out=b.charAt(x)+out;c++;if(c%3===0&&x!==0)out="."+out;}return out+"-"+d;}i.addEventListener("blur",function(){i.value=f(i.value);});})();</script>';

        echo '</form>';
        echo '</div>';

        self::html_foot();
    }

    // ----------------------------
    // Períodos
    // ----------------------------

    private static function render_periods_list() {
        $base = self::base_url();
        $q_term = sanitize_text_field(wp_unslash($_GET['q'] ?? ''));
        $msg = sanitize_text_field(wp_unslash($_GET['msg'] ?? ''));

        $args = [
            'post_type'      => 'cl_periodo',
            'post_status'    => ['publish','private','draft'],
            'posts_per_page' => 120,
            'orderby'        => 'title',
            'order'          => 'DESC',
        ];

        $q = new WP_Query($args);

        self::html_head('Períodos');

        echo '<div class="topbar">';
        echo '<div><h1>Períodos</h1>';
        echo '<div class="note"><a href="' . esc_url($base) . '">← Volver a Liquidaciones</a></div>';
        self::render_nav('per');
        echo '</div>';
        echo '<div class="row">';
        echo '<a class="btn" href="' . esc_url($base . 'periodos/nuevo/') . '">+ Nuevo período</a>';
        echo '</div>';
        echo '</div>';

        if ($msg === 'saved') {
            echo '<div class="msg" role="status" aria-live="polite">Período guardado.</div>';
        }

        echo '<div class="card">';

        echo '<form method="get" class="row" style="margin:0 0 12px">';
        echo '<label style="min-width:320px">Búsqueda (YYYY-MM)'
            . '<input type="text" name="q" value="' . esc_attr($q_term) . '" placeholder="Ej: 2026-03">'
            . '</label>';
        echo '<button class="btn small" type="submit">Buscar</button>';
        echo '<a class="btn small ghost" href="' . esc_url($base . 'periodos/') . '">Limpiar</a>';
        echo '</form>';

        echo '<div style="overflow:auto">';
        echo '<table><caption class="sr-only">Listado de períodos</caption>';
        echo '<thead><tr><th>ID</th><th>Período</th><th>UF</th><th>Acciones</th></tr></thead><tbody>';

        $shown = 0;
        if ($q->have_posts()) {
            while ($q->have_posts()) {
                $q->the_post();
                $id = get_the_ID();
                $ym = (string) get_post_meta($id, 'cl_ym', true);
                $uf = (string) get_post_meta($id, 'cl_uf_value', true);

                if ($q_term !== '' && $ym && stripos($ym, $q_term) === false) {
                    continue;
                }

                $shown++;
                echo '<tr>';
                echo '<td>' . esc_html((string)$id) . '</td>';
                echo '<td><strong>' . esc_html($ym ? CL_LIQ_Helpers::ym_label($ym) : get_the_title()) . '</strong><div class="note muted">' . esc_html($ym ?: '') . '</div></td>';
                echo '<td>' . esc_html($uf ?: '—') . '</td>';
                echo '<td class="row">';
                echo '<a class="btn small secondary" href="' . esc_url($base . 'periodos/editar/' . $id . '/') . '">Editar</a>';
                echo '</td>';
                echo '</tr>';
            }
            wp_reset_postdata();
        }

        if ($shown === 0) {
            echo '<tr><td colspan="4">No hay resultados.</td></tr>';
        }

        echo '</tbody></table>';
        echo '</div>';

        echo '</div>';

        self::html_foot();
    }

    private static function render_period_form(int $per_id) {
        $base = self::base_url();
        $is_edit = $per_id > 0;
        $error = '';

        $return = self::safe_return_url((string) sanitize_text_field(wp_unslash($_GET['return'] ?? '')), $base . 'periodos/');

        if ($is_edit) {
            $post = get_post($per_id);
            if (!$post || $post->post_type !== 'cl_periodo') wp_die('Período no encontrado.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cl_liq_fe_per_save'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['cl_liq_nonce'] ?? ''));
            if (!wp_verify_nonce($nonce, 'cl_liq_fe_per_save')) {
                $error = 'Nonce inválido.';
            } else {
                $ym = sanitize_text_field(wp_unslash($_POST['cl_ym'] ?? CL_LIQ_Helpers::current_ym()));
                if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
                    $error = __('Período inválido (usa YYYY-MM).', 'liquidaciones-cl');
                } elseif (CL_LIQ_Helpers::period_exists($ym, $is_edit ? $per_id : 0)) {
                    $error = __('Ya existe un período con ese YYYY-MM.', 'liquidaciones-cl');
                } elseif (CL_LIQ_Helpers::is_negative_number_input($_POST['cl_uf_value'] ?? '')) {
                    $error = __('UF inválida: no se permiten valores negativos.', 'liquidaciones-cl');
                } else {
                    $uf = CL_LIQ_Helpers::parse_decimal($_POST['cl_uf_value'] ?? 0);

                    if (!$is_edit) {
                        $new_id = wp_insert_post([
                            'post_type'   => 'cl_periodo',
                            'post_status' => 'publish',
                            'post_title'  => $ym,
                        ], true);
                        if (is_wp_error($new_id)) {
                            $error = 'No se pudo crear el período.';
                        } else {
                            $per_id = (int) $new_id;
                            $is_edit = true;
                        }
                    } else {
                        wp_update_post(['ID' => $per_id, 'post_title' => $ym]);
                    }

                    if ($is_edit && !$error) {
                        update_post_meta($per_id, 'cl_ym', $ym);
                        update_post_meta($per_id, 'cl_uf_value', $uf);

                        if (class_exists('CL_LIQ_Audit')) {
                            CL_LIQ_Audit::log_post_change($per_id, 'cl_periodo', 'frontend_save');
                        }

                        $redir = $return;
                        $sep = (strpos($redir, '?') === false) ? '?' : '&';
                        wp_redirect($redir . $sep . 'msg=saved');
                        exit;
                    }
                }
            }
        }

        $ym = $is_edit ? ((string) get_post_meta($per_id, 'cl_ym', true) ?: CL_LIQ_Helpers::current_ym()) : CL_LIQ_Helpers::current_ym();
        $uf = $is_edit ? (string) get_post_meta($per_id, 'cl_uf_value', true) : '';
        if (!$is_edit && ($uf === '' || (float)CL_LIQ_Helpers::parse_decimal($uf) <= 0) && class_exists('CL_LIQ_Updater')) {
            $uf_guess = CL_LIQ_Updater::guess_uf_for_ym($ym);
            if ($uf_guess > 0) $uf = (string) $uf_guess;
        }

        self::html_head($is_edit ? 'Editar período' : 'Nuevo período');

        echo '<div class="topbar">';
        echo '<div><h1>' . ($is_edit ? 'Editar período #' . esc_html((string)$per_id) : 'Nuevo período') . '</h1>';
        echo '<div class="note"><a href="' . esc_url($return) . '">← Volver</a></div>';
        self::render_nav('per');
        echo '</div>';
        echo '<div class="row"></div>';
        echo '</div>';

        if ($error) {
            echo '<div class="err" role="alert" aria-live="assertive">' . esc_html($error) . '</div>';
        }

        echo '<div class="card">';
        echo '<form method="post">';
        echo '<input type="hidden" name="cl_liq_fe_per_save" value="1">';
        wp_nonce_field('cl_liq_fe_per_save', 'cl_liq_nonce');

        echo '<div class="grid">';
        echo '<div><label>Período (YYYY-MM)</label><input name="cl_ym" type="text" value="' . esc_attr($ym) . '" placeholder="2026-03" required></div>';
        echo '<div><label>UF (CLP)</label><input name="cl_uf_value" type="text" value="' . esc_attr($uf) . '" placeholder="Ej: 38000.12"><div class="note">Se usa para convertir topes UF a CLP.</div></div>';
        echo '</div>';

        echo '<div class="row" style="margin-top:16px">';
        echo '<button class="btn" type="submit">Guardar</button>';
        echo '<a class="btn ghost" href="' . esc_url($return) . '">Cancelar</a>';
        echo '</div>';

        echo '</form>';
        echo '</div>';

        self::html_foot();
    }

    // ----------------------------
    // Helpers (search)
    // ----------------------------

    private static function find_employee_ids_by_q(string $q): array {
        $q = trim($q);
        if ($q === '') return [];

        $ids = [];

        // Search by title
        $by_title = get_posts([
            'post_type' => 'cl_empleado',
            'post_status' => 'any',
            'numberposts' => 50,
            's' => $q,
            'fields' => 'ids',
        ]);
        if ($by_title) $ids = array_merge($ids, $by_title);

        // Search by RUT meta (LIKE)
        $by_rut = get_posts([
            'post_type' => 'cl_empleado',
            'post_status' => 'any',
            'numberposts' => 50,
            'meta_query' => [
                [
                    'key' => 'cl_rut',
                    'value' => $q,
                    'compare' => 'LIKE',
                ]
            ],
            'fields' => 'ids',
        ]);
        if ($by_rut) $ids = array_merge($ids, $by_rut);

        $ids = array_values(array_unique(array_map('intval', $ids)));
        return $ids;
    }

    private static function find_period_ids_by_ym(string $ym): array {
        $ym = trim($ym);
        if ($ym === '') return [];

        $ids = get_posts([
            'post_type' => 'cl_periodo',
            'post_status' => 'any',
            'numberposts' => 50,
            'meta_query' => [
                [
                    'key' => 'cl_ym',
                    'value' => $ym,
                    'compare' => '=',
                ]
            ],
            'fields' => 'ids',
        ]);

        return array_values(array_unique(array_map('intval', $ids ?: [])));
    }
}
