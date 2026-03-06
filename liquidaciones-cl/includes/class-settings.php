<?php
if ( ! defined('ABSPATH') ) { exit; }

final class CL_LIQ_Settings {

    const OPTION_KEY = 'cl_liq_settings';
    const VERSION_OPTION = 'cl_liq_plugin_version';

    private static function can_manage(): bool {
        return current_user_can('manage_cl_liquidaciones') || current_user_can('manage_options');
    }

    private static function frontend_base_url(): string {
        $slug = apply_filters('cl_liq_front_slug', class_exists('CL_LIQ_Frontend') ? CL_LIQ_Frontend::SLUG_DEFAULT : 'liquidaciones-cl');
        $slug = sanitize_title_with_dashes((string) $slug);
        if (!$slug) $slug = 'liquidaciones-cl';
        return trailingslashit(home_url('/' . $slug . '/'));
    }

    private static function t(string $text): string {
        return esc_html__($text, 'liquidaciones-cl');
    }

    private static function quick_links(string $base): array {
        return [
            ['label' => __('Frontend: Listado de liquidaciones', 'liquidaciones-cl'), 'url' => $base, 'blank' => true],
            ['label' => __('Frontend: Nueva liquidación', 'liquidaciones-cl'), 'url' => $base . 'nueva/', 'blank' => true],
            ['label' => __('Frontend: Empleados', 'liquidaciones-cl'), 'url' => $base . 'empleados/', 'blank' => true],
            ['label' => __('Frontend: Nuevo empleado', 'liquidaciones-cl'), 'url' => $base . 'empleados/nuevo/', 'blank' => true],
            ['label' => __('Frontend: Períodos', 'liquidaciones-cl'), 'url' => $base . 'periodos/', 'blank' => true],
            ['label' => __('Frontend: Nuevo período', 'liquidaciones-cl'), 'url' => $base . 'periodos/nuevo/', 'blank' => true],
            ['label' => __('Admin: Empleados', 'liquidaciones-cl'), 'url' => admin_url('edit.php?post_type=cl_empleado'), 'blank' => false],
            ['label' => __('Admin: Períodos', 'liquidaciones-cl'), 'url' => admin_url('edit.php?post_type=cl_periodo'), 'blank' => false],
            ['label' => __('Admin: Liquidaciones', 'liquidaciones-cl'), 'url' => admin_url('edit.php?post_type=cl_liquidacion'), 'blank' => false],
        ];
    }

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('init', [__CLASS__, 'maybe_upgrade'], 9);
        add_action('admin_post_cl_liq_run_update', [__CLASS__, 'handle_run_update']);
        add_action('admin_post_cl_liq_rollback', [__CLASS__, 'handle_rollback']);
    }

    public static function activate() {
        $existing = get_option(self::OPTION_KEY, null);
        if ($existing === null) {
            update_option(self::OPTION_KEY, self::defaults(), false);
        } else {
            // merge missing keys safely
            $merged = wp_parse_args($existing, self::defaults());
            update_option(self::OPTION_KEY, $merged, false);
        }

        update_option(self::VERSION_OPTION, CL_LIQ_VERSION, false);
    }

    /** One-time upgrade tasks on version change (kept lightweight). */
    public static function maybe_upgrade() {
        $stored = (string) get_option(self::VERSION_OPTION, '');
        if ($stored === CL_LIQ_VERSION) return;

        // ensure defaults are present
        self::activate();

        // ensure frontend rewrite rules exist after updates
        if (class_exists('CL_LIQ_Frontend')) {
            CL_LIQ_Frontend::activate();
        }
    }

    public static function defaults(): array {
        return [
            'topes' => [
                'tope_uf_afp_salud' => 90.0,
                'tope_uf_afc'       => 135.2,
            ],
            'afp_commissions' => [
                'Capital'  => 0.0144,
                'Cuprum'   => 0.0144,
                'Habitat'  => 0.0127,
                'Modelo'   => 0.0058,
                'Planvital'=> 0.0116,
                'Provida'  => 0.0145,
                'Uno'      => 0.0046,
            ],
            'afc_employee_rates' => [
                'indefinido' => 0.006,
                'plazo_fijo' => 0.0,
                'obra'       => 0.0,
                'casa_particular' => 0.0,
            ],
            'horas_extra' => [
                'jornada_mensual_horas' => 180,
                'recargo' => 0.5, // 50% => multiplicador 1.5
            ],
            'gratificacion' => [
                'imm_clp' => 0, // ingreso mínimo mensual (configurable)
                'tope_factor' => 4.75, // 4.75 IMM anual
                'tope_divisor' => 12,
            ],
            'asignacion_familiar' => [
                // thresholds and amounts (CLP) - editable
                'tramos' => [
                    ['from' => 1,       'to' => 631976,  'amount' => 22007],
                    ['from' => 631977,  'to' => 923067,  'amount' => 13505],
                    ['from' => 923068,  'to' => 1439668, 'amount' => 4276],
                    ['from' => 1439669, 'to' => null,    'amount' => 0],
                ],
            ],
            'tax_tables' => [
                // Estructura: YYYY-MM => [ {from,to,factor,rebate} ... ] (valores en CLP, mensual)
                '2026-03' => [
                    ['from'=>0.0,        'to'=>943501.50,     'factor'=>0.0,   'rebate'=>0.0],
                    ['from'=>943501.51,  'to'=>2096670.00,    'factor'=>0.04,  'rebate'=>37740.06],
                    ['from'=>2096670.01, 'to'=>3494450.00,    'factor'=>0.08,  'rebate'=>121606.86],
                    ['from'=>3494450.01, 'to'=>4892230.00,    'factor'=>0.135, 'rebate'=>313801.61],
                    ['from'=>4892230.01, 'to'=>6290010.00,    'factor'=>0.23,  'rebate'=>778563.46],
                    ['from'=>6290010.01, 'to'=>8386680.00,    'factor'=>0.304, 'rebate'=>1244024.20],
                    ['from'=>8386680.01, 'to'=>21665590.00,   'factor'=>0.35,  'rebate'=>1629811.48],
                    ['from'=>21665590.01,'to'=>null,          'factor'=>0.4,   'rebate'=>2713090.98],
                ],
            ],
            'auto_update' => [
                'enabled' => 0,
                'mode' => 'suggest', // suggest|apply
                'months_back' => 12,
                'months_forward' => 3,
                'uf_source' => 'copy_prev', // copy_prev|http
                'uf_http_url' => '', // template with {date} placeholder
            ],
            'logging' => [
                'enabled' => 0,
                'level' => 'error', // error|warning|info|debug
            ],
        ];
    }

    public static function get(): array {
        $opt = get_option(self::OPTION_KEY, []);
        return wp_parse_args($opt, self::defaults());
    }

    public static function admin_menu() {
        add_menu_page(
            __('Liquidaciones CL', 'liquidaciones-cl'),
            __('Liquidaciones CL', 'liquidaciones-cl'),
            'manage_cl_liquidaciones',
            'cl-liquidaciones',
            [__CLASS__, 'render_home'],
            'dashicons-media-spreadsheet',
            26
        );

        add_submenu_page('cl-liquidaciones', __('Empleados', 'liquidaciones-cl'), __('Empleados', 'liquidaciones-cl'), 'edit_cl_empleados', 'edit.php?post_type=cl_empleado');
        add_submenu_page('cl-liquidaciones', __('Períodos', 'liquidaciones-cl'), __('Períodos', 'liquidaciones-cl'), 'edit_cl_periodos', 'edit.php?post_type=cl_periodo');
        add_submenu_page('cl-liquidaciones', __('Liquidaciones', 'liquidaciones-cl'), __('Liquidaciones', 'liquidaciones-cl'), 'edit_cl_liquidaciones', 'edit.php?post_type=cl_liquidacion');
        add_submenu_page('cl-liquidaciones', __('Parámetros', 'liquidaciones-cl'), __('Parámetros', 'liquidaciones-cl'), 'manage_cl_liquidaciones', 'cl-liquidaciones-settings', [__CLASS__, 'render_settings']);
        add_submenu_page('cl-liquidaciones', __('Ayuda', 'liquidaciones-cl'), __('Ayuda', 'liquidaciones-cl'), 'manage_cl_liquidaciones', 'cl-liquidaciones-help', [__CLASS__, 'render_help']);
    }

    public static function render_home() {
        if ( ! self::can_manage() ) return;
        echo '<div class="wrap">';
        echo '<h1>Liquidaciones CL</h1>';
        echo '<p>Flujo rápido:</p>';
        echo '<ol>';
        echo '<li><a href="' . esc_url(admin_url('post-new.php?post_type=cl_empleado')) . '">Crear empleado</a></li>';
        echo '<li><a href="' . esc_url(admin_url('post-new.php?post_type=cl_periodo')) . '">Crear período (mes)</a></li>';
        echo '<li><a href="' . esc_url(admin_url('post-new.php?post_type=cl_liquidacion')) . '">Crear liquidación</a></li>';
        echo '</ol>';
        echo '<p>Revisa <a href="' . esc_url(admin_url('admin.php?page=cl-liquidaciones-settings')) . '">Parámetros</a> para topes, comisiones AFP, AFC, Asignación Familiar y Tabla SII.</p>';
        echo '</div>';
    }

    public static function render_help() {
        if ( ! self::can_manage() ) return;
        echo '<div class="wrap">';
        echo '<h1>Ayuda</h1>';
        echo '<p><strong>Notas:</strong></p>';
        echo '<ul>';
        echo '<li>Los parámetros (topes UF, comisiones AFP, tabla SII, etc.) son editables.</li>';
        echo '<li>Asignación Familiar: por defecto se calcula por tramo del empleado, o puedes fijarla por liquidación.</li>';
        echo '<li>Este plugin es un generador administrativo. Revisa siempre el resultado antes de emitir a un trabajador.</li>';
        echo '</ul>';
        echo '</div>';
    }

    private static function sanitize_settings(array $in): array {
        $d = self::get(); // for structure

        // Topes UF
        $d['topes']['tope_uf_afp_salud'] = CL_LIQ_Helpers::parse_decimal($in['topes']['tope_uf_afp_salud'] ?? $d['topes']['tope_uf_afp_salud']);
        $d['topes']['tope_uf_afc']       = CL_LIQ_Helpers::parse_decimal($in['topes']['tope_uf_afc'] ?? $d['topes']['tope_uf_afc']);

        // AFP commissions
        if (isset($in['afp_commissions']) && is_array($in['afp_commissions'])) {
            foreach ($in['afp_commissions'] as $name => $rate) {
                $name = sanitize_text_field($name);
                $r = CL_LIQ_Helpers::parse_decimal($rate);
                if ($r > 1) { $r = $r / 100.0; } // allow percent input
                $d['afp_commissions'][$name] = max(0.0, min(0.2, $r));
            }
        }

        // AFC employee rates
        if (isset($in['afc_employee_rates']) && is_array($in['afc_employee_rates'])) {
            foreach ($d['afc_employee_rates'] as $k => $_) {
                $r = CL_LIQ_Helpers::parse_decimal($in['afc_employee_rates'][$k] ?? $d['afc_employee_rates'][$k]);
                if ($r > 1) { $r = $r / 100.0; }
                $d['afc_employee_rates'][$k] = max(0.0, min(0.1, $r));
            }
        }

        // Horas extra
        $d['horas_extra']['jornada_mensual_horas'] = max(1, (int) CL_LIQ_Helpers::parse_clp($in['horas_extra']['jornada_mensual_horas'] ?? $d['horas_extra']['jornada_mensual_horas']));
        $d['horas_extra']['recargo'] = max(0.0, min(2.0, CL_LIQ_Helpers::parse_decimal($in['horas_extra']['recargo'] ?? $d['horas_extra']['recargo'])));

        // Gratificación
        $d['gratificacion']['imm_clp'] = CL_LIQ_Helpers::parse_clp($in['gratificacion']['imm_clp'] ?? $d['gratificacion']['imm_clp']);
        $d['gratificacion']['tope_factor'] = max(0.0, CL_LIQ_Helpers::parse_decimal($in['gratificacion']['tope_factor'] ?? $d['gratificacion']['tope_factor']));
        $d['gratificacion']['tope_divisor'] = max(1, (int) CL_LIQ_Helpers::parse_clp($in['gratificacion']['tope_divisor'] ?? $d['gratificacion']['tope_divisor']));

        // Asignación familiar (JSON textarea)
        if (!empty($in['asignacion_json'])) {
            $json = json_decode(wp_unslash($in['asignacion_json']), true);
            if (is_array($json) && isset($json['tramos']) && is_array($json['tramos'])) {
                $tramos = [];
                foreach ($json['tramos'] as $t) {
                    $tramos[] = [
                        'from' => isset($t['from']) ? (int)$t['from'] : 0,
                        'to' => (isset($t['to']) && $t['to'] !== null) ? (int)$t['to'] : null,
                        'amount' => isset($t['amount']) ? (int)$t['amount'] : 0,
                    ];
                }
                $d['asignacion_familiar']['tramos'] = $tramos;
            }
        }

        // Auto-update
        $enabled = isset($in['auto_update']['enabled']) ? 1 : 0;
        $mode = sanitize_text_field($in['auto_update']['mode'] ?? 'suggest');
        $mode = ($mode === 'apply') ? 'apply' : 'suggest';
        $months_back = max(1, (int) ($in['auto_update']['months_back'] ?? ($d['auto_update']['months_back'] ?? 12)));
        $months_forward = max(0, (int) ($in['auto_update']['months_forward'] ?? ($d['auto_update']['months_forward'] ?? 3)));
        $uf_source = sanitize_text_field($in['auto_update']['uf_source'] ?? ($d['auto_update']['uf_source'] ?? 'copy_prev'));
        $uf_source = ($uf_source === 'http') ? 'http' : 'copy_prev';
        $uf_http_url = esc_url_raw($in['auto_update']['uf_http_url'] ?? ($d['auto_update']['uf_http_url'] ?? ''));

        $d['auto_update'] = [
            'enabled' => $enabled,
            'mode' => $mode,
            'months_back' => $months_back,
            'months_forward' => $months_forward,
            'uf_source' => $uf_source,
            'uf_http_url' => $uf_http_url,
        ];

        // Logging / observabilidad
        $log_enabled = isset($in['logging']['enabled']) ? 1 : 0;
        $log_level = sanitize_text_field($in['logging']['level'] ?? ($d['logging']['level'] ?? 'error'));
        if (!in_array($log_level, ['error', 'warning', 'info', 'debug'], true)) {
            $log_level = 'error';
        }
        $d['logging'] = [
            'enabled' => $log_enabled,
            'level' => $log_level,
        ];

        // Tax tables (JSON textarea)
        if (!empty($in['tax_json'])) {
            $json = json_decode(wp_unslash($in['tax_json']), true);
            if (is_array($json)) {
                $tables = [];
                foreach ($json as $ym => $rows) {
                    $ym = sanitize_text_field($ym);
                    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) continue;
                    if (!is_array($rows)) continue;
                    $clean = [];
                    foreach ($rows as $r) {
                        $clean[] = [
                            'from' => isset($r['from']) ? (float)$r['from'] : 0.0,
                            'to' => (array_key_exists('to', $r) && $r['to'] !== null) ? (float)$r['to'] : null,
                            'factor' => isset($r['factor']) ? (float)$r['factor'] : 0.0,
                            'rebate' => isset($r['rebate']) ? (float)$r['rebate'] : 0.0,
                        ];
                    }
                    if ($clean) $tables[$ym] = $clean;
                }
                if ($tables) $d['tax_tables'] = $tables;
            }
        }

        return $d;
    }

    public static function render_settings() {
        if ( ! self::can_manage() ) return;

        $settings = self::get();

        if (isset($_POST['cl_liq_save_settings'])) {
            check_admin_referer('cl_liq_save_settings');
            $new = self::sanitize_settings($_POST['cl_liq'] ?? []);
            update_option(self::OPTION_KEY, $new, false);
            $settings = $new;
            echo '<div class="notice notice-success"><p>Parámetros guardados.</p></div>';
        }

        $asig_json = wp_json_encode($settings['asignacion_familiar'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $tax_json = wp_json_encode($settings['tax_tables'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        echo '<div class="wrap">';
        echo '<h1>Parámetros</h1>';

        $fe_base = self::frontend_base_url();
        echo '<div style="margin:12px 0 18px;padding:12px;border:1px solid #e5e7eb;background:#fff;border-radius:8px">';
        echo '<h2 style="margin-top:0">' . self::t('Rutas de acceso rápido') . '</h2>'; 
        echo '<p class="description">' . self::t('Accesos directos al frontend del plugin y a pantallas admin.') . '</p>'; 
        echo '<ul style="margin:8px 0 0 18px;line-height:1.8">';
        foreach (self::quick_links($fe_base) as $link) {
            $target = !empty($link['blank']) ? ' target="_blank"' : '';
            echo '<li><a' . $target . ' href="' . esc_url((string) $link['url']) . '">' . esc_html((string) $link['label']) . '</a></li>';
        }
        echo '</ul>';
        echo '</div>';
        // Notices from updater actions
        $auto_msg = sanitize_text_field(wp_unslash($_GET['auto_msg'] ?? ''));
        if ($auto_msg) {
            echo '<div class="notice notice-info"><p>' . esc_html($auto_msg) . '</p></div>';
        }

        // Updater status
        $last_run_ym = class_exists('CL_LIQ_Updater') ? (string) get_option(CL_LIQ_Updater::OPT_LAST_RUN_YM, '') : '';
        $last_run_ts = class_exists('CL_LIQ_Updater') ? (int) get_option(CL_LIQ_Updater::OPT_LAST_RUN_TS, 0) : 0;
        $has_snap = class_exists('CL_LIQ_Updater') ? get_option(CL_LIQ_Updater::OPT_SNAPSHOT, null) : null;
        $log = class_exists('CL_LIQ_Updater') ? CL_LIQ_Updater::get_log(8) : [];

        echo '<div style="margin:12px 0 18px;padding:12px;border:1px solid #e5e7eb;background:#fff;border-radius:8px">';
        echo '<h2 style="margin-top:0">Actualización automática</h2>';
        echo '<p class="description">Recomendación: revisa parámetros antes de emitir una liquidación. El modo automático ayuda a preparar períodos/tablas, pero no reemplaza verificación.</p>';
        if ($last_run_ym) {
            echo '<p><strong>Última ejecución:</strong> ' . esc_html($last_run_ym) . ($last_run_ts ? ' (' . esc_html(wp_date('Y-m-d H:i', $last_run_ts)) . ')' : '') . '</p>';
        } else {
            echo '<p><strong>Última ejecución:</strong> —</p>';
        }

        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="cl_liq_run_update">';
        wp_nonce_field('cl_liq_run_update');
        echo '<button class="button button-primary" type="submit">Actualizar ahora</button>';
        echo '</form>';

        if (is_array($has_snap) && !empty($has_snap)) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="cl_liq_rollback">';
            wp_nonce_field('cl_liq_rollback');
            echo '<button class="button" type="submit">Rollback último snapshot</button>';
            echo '</form>';
        }

        echo '</div>';

        if (!empty($log)) {
            echo '<details><summary><strong>Log (últimos eventos)</strong></summary>';
            echo '<table class="widefat striped" style="margin-top:10px"><thead><tr><th>Fecha</th><th>Tipo</th><th>Mensaje</th><th>Datos</th></tr></thead><tbody>';
            foreach ($log as $row) {
                $ts = (int)($row['ts'] ?? 0);
                $type = (string)($row['type'] ?? '');
                $msg2 = (string)($row['message'] ?? '');
                $data = $row['data'] ?? [];
                echo '<tr>';
                echo '<td>' . esc_html($ts ? wp_date('Y-m-d H:i', $ts) : '—') . '</td>';
                echo '<td>' . esc_html($type) . '</td>';
                echo '<td>' . esc_html($msg2) . '</td>';
                echo '<td><code>' . esc_html(wp_json_encode($data)) . '</code></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</details>';
        }

        echo '</div>';

        $audit_log = class_exists('CL_LIQ_Audit') ? CL_LIQ_Audit::get_log(20) : [];
        echo '<div style="margin:12px 0 18px;padding:12px;border:1px solid #e5e7eb;background:#fff;border-radius:8px">';
        echo '<h2 style="margin-top:0">' . self::t('Auditoría operativa') . '</h2>'; 
        echo '<p class="description">Registra quién creó/actualizó entidades y acciones del updater. Se guardan hasta 200 eventos.</p>';
        if (!empty($audit_log)) {
            echo '<table class="widefat striped"><thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>ID</th><th>Contexto</th><th>Cambios</th></tr></thead><tbody>';
            foreach ($audit_log as $row) {
                $ts = (int) ($row['ts'] ?? 0);
                $uid = (int) ($row['user'] ?? 0);
                $user = $uid > 0 ? get_userdata($uid) : null;
                $uname = $user ? $user->user_login : 'sistema';
                $action = (string) ($row['action'] ?? '');
                $entity = (string) ($row['entity_type'] ?? '');
                $eid = (int) ($row['entity_id'] ?? 0);
                $ctx = (string) ($row['context'] ?? '');
                $changes = $row['changes'] ?? [];
                echo '<tr>';
                echo '<td>' . esc_html($ts ? wp_date('Y-m-d H:i', $ts) : '—') . '</td>';
                echo '<td>' . esc_html($uname) . '</td>';
                echo '<td>' . esc_html($action) . '</td>';
                echo '<td>' . esc_html($entity) . '</td>';
                echo '<td>' . esc_html($eid ? (string) $eid : '—') . '</td>';
                echo '<td>' . esc_html($ctx ?: '—') . '</td>';
                echo '<td><code>' . esc_html(wp_json_encode($changes)) . '</code></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . self::t('No hay eventos de auditoría todavía.') . '</p>'; 
        }
        echo '</div>';

        echo '<form method="post">';
        wp_nonce_field('cl_liq_save_settings');


        echo '<h2>Auto-update (mensual)</h2>';
        $au = $settings['auto_update'] ?? [];
        $au = wp_parse_args($au, [
            'enabled' => 0,
            'mode' => 'suggest',
            'months_back' => 12,
            'months_forward' => 3,
            'uf_source' => 'copy_prev',
            'uf_http_url' => '',
        ]);

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">Activar</th><td><label><input type="checkbox" name="cl_liq[auto_update][enabled]" value="1" ' . checked((int)$au['enabled'], 1, false) . '> Habilitar tareas automáticas (WP-Cron)</label></td></tr>';
        echo '<tr><th scope="row">Modo</th><td><select name="cl_liq[auto_update][mode]">';
        echo '<option value="suggest" ' . selected($au['mode'], 'suggest', false) . '>Sugerir / preparar (recomendado)</option>';
        echo '<option value="apply" ' . selected($au['mode'], 'apply', false) . '>Aplicar automático</option>';
        echo '</select><p class="description">En v1.3.0 ambos modos preparan períodos y copian tabla si falta. La diferencia es que en futuras versiones el modo apply puede pisar valores existentes.</p></td></tr>';
        echo '<tr><th scope="row">Ventana de períodos</th><td><input name="cl_liq[auto_update][months_back]" type="number" min="1" step="1" value="' . esc_attr((int)$au['months_back']) . '" style="width:90px"> meses hacia atrás + <input name="cl_liq[auto_update][months_forward]" type="number" min="0" step="1" value="' . esc_attr((int)$au['months_forward']) . '" style="width:90px"> meses hacia adelante</td></tr>';
        echo '<tr><th scope="row">UF (relleno)</th><td><select name="cl_liq[auto_update][uf_source]">';
        echo '<option value="copy_prev" ' . selected($au['uf_source'], 'copy_prev', false) . '>Copiar UF del último período existente</option>';
        echo '<option value="http" ' . selected($au['uf_source'], 'http', false) . '>HTTP (JSON) desde URL template</option>';
        echo '</select>';
        echo '<p class="description">Si eliges HTTP, ingresa una URL template con {date}. Ejemplo: https://ejemplo/api/uf/{date} (donde {date}=YYYY-MM-DD).</p>';
        echo '<input name="cl_liq[auto_update][uf_http_url]" type="text" value="' . esc_attr((string)$au['uf_http_url']) . '" class="regular-text" placeholder="https://.../{date}">';
        echo '</td></tr>';
        echo '</tbody></table>';

        echo '<h2>Logging / Observabilidad</h2>';
        $lg = $settings['logging'] ?? [];
        $lg = wp_parse_args($lg, ['enabled' => 0, 'level' => 'error']);
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">Activar logging</th><td><label><input type="checkbox" name="cl_liq[logging][enabled]" value="1" ' . checked((int)$lg['enabled'], 1, false) . '> Escribir eventos en error_log según nivel seleccionado</label></td></tr>';
        echo '<tr><th scope="row">Nivel mínimo</th><td><select name="cl_liq[logging][level]">';
        echo '<option value="error" ' . selected($lg['level'], 'error', false) . '>Error</option>';
        echo '<option value="warning" ' . selected($lg['level'], 'warning', false) . '>Warning</option>';
        echo '<option value="info" ' . selected($lg['level'], 'info', false) . '>Info</option>';
        echo '<option value="debug" ' . selected($lg['level'], 'debug', false) . '>Debug</option>';
        echo '</select><p class="description">Se registran eventos del plugin como validaciones rechazadas y fallos HTTP del updater.</p></td></tr>';
        echo '</tbody></table>';

        echo '<h2>Topes imponibles (UF)</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">Tope AFP/Salud</th><td><input name="cl_liq[topes][tope_uf_afp_salud]" type="text" value="' . esc_attr($settings['topes']['tope_uf_afp_salud']) . '" class="regular-text"></td></tr>';
        echo '<tr><th scope="row">Tope Seguro Cesantía (AFC)</th><td><input name="cl_liq[topes][tope_uf_afc]" type="text" value="' . esc_attr($settings['topes']['tope_uf_afc']) . '" class="regular-text"></td></tr>';
        echo '</tbody></table>';

        echo '<h2>Comisiones AFP (mensual, % sobre imponible)</h2>';
        echo '<table class="widefat striped"><thead><tr><th>AFP</th><th>Comisión (%)</th></tr></thead><tbody>';
        foreach ($settings['afp_commissions'] as $name => $rate) {
            echo '<tr><td>' . esc_html($name) . '</td><td><input name="cl_liq[afp_commissions][' . esc_attr($name) . ']" type="text" value="' . esc_attr($rate * 100.0) . '" style="width:120px"></td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2>AFC (aporte trabajador)</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Tipo contrato</th><th>Tasa trabajador (%)</th></tr></thead><tbody>';
        $labels = [
            'indefinido' => 'Indefinido',
            'plazo_fijo' => 'Plazo fijo',
            'obra' => 'Obra / Faena',
            'casa_particular' => 'Casa particular',
        ];
        foreach ($settings['afc_employee_rates'] as $k => $r) {
            echo '<tr><td>' . esc_html($labels[$k] ?? $k) . '</td><td><input name="cl_liq[afc_employee_rates][' . esc_attr($k) . ']" type="text" value="' . esc_attr($r * 100.0) . '" style="width:120px"></td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2>Horas extra</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">Jornada mensual (horas)</th><td><input name="cl_liq[horas_extra][jornada_mensual_horas]" type="text" value="' . esc_attr($settings['horas_extra']['jornada_mensual_horas']) . '" class="small-text"></td></tr>';
        echo '<tr><th scope="row">Recargo (ej: 0.5 = +50%)</th><td><input name="cl_liq[horas_extra][recargo]" type="text" value="' . esc_attr($settings['horas_extra']['recargo']) . '" class="small-text"></td></tr>';
        echo '</tbody></table>';

        echo '<h2>Gratificación legal (opcional)</h2>';
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row">IMM (CLP)</th><td><input name="cl_liq[gratificacion][imm_clp]" type="text" value="' . esc_attr($settings['gratificacion']['imm_clp']) . '" class="regular-text"> <p class="description">Si es 0, el tope no se aplica.</p></td></tr>';
        echo '<tr><th scope="row">Factor tope</th><td><input name="cl_liq[gratificacion][tope_factor]" type="text" value="' . esc_attr($settings['gratificacion']['tope_factor']) . '" class="small-text"> / <input name="cl_liq[gratificacion][tope_divisor]" type="text" value="' . esc_attr($settings['gratificacion']['tope_divisor']) . '" class="small-text"></td></tr>';
        echo '</tbody></table>';

        echo '<h2>Asignación Familiar (JSON)</h2>';
        echo '<p class="description">Estructura: {"tramos":[{"from":1,"to":631976,"amount":22007}, ... ]}</p>';
        echo '<textarea name="cl_liq[asignacion_json]" rows="10" style="width:100%">' . esc_textarea($asig_json) . '</textarea>';

        echo '<h2>Tabla Impuesto Único (JSON)</h2>';
        echo '<p class="description">Estructura: {"YYYY-MM":[{"from":0,"to":943501.50,"factor":0,"rebate":0}, ...]}</p>';
        echo '<textarea name="cl_liq[tax_json]" rows="14" style="width:100%">' . esc_textarea($tax_json) . '</textarea>';

        echo '<p><button type="submit" name="cl_liq_save_settings" class="button button-primary">Guardar</button></p>';
        echo '</form>';
        echo '</div>';
    }


    public static function handle_run_update() {
        if ( ! self::can_manage() ) {
            wp_die('No autorizado.');
        }
        check_admin_referer('cl_liq_run_update');

        if ( ! class_exists('CL_LIQ_Updater') ) {
            wp_die('Updater no disponible.');
        }

        $res = CL_LIQ_Updater::run_monthly_update(true);
        $msg = (string) ($res['msg'] ?? '');
        if (!empty($res['changes']) && is_array($res['changes'])) {
            $parts = [];
            foreach ($res['changes'] as $k => $v) {
                $parts[] = $k . ':' . (string) $v;
            }
            if ($parts) {
                $msg .= ' [' . implode(', ', $parts) . ']';
            }
        }

        $url = admin_url('admin.php?page=cl-liquidaciones-settings&auto_msg=' . rawurlencode($msg));
        wp_safe_redirect($url);
        exit;
    }

    public static function handle_rollback() {
        if ( ! self::can_manage() ) {
            wp_die('No autorizado.');
        }
        check_admin_referer('cl_liq_rollback');

        if ( ! class_exists('CL_LIQ_Updater') ) {
            wp_die('Updater no disponible.');
        }

        $res = CL_LIQ_Updater::rollback_last();
        $msg = (string) ($res['msg'] ?? '');

        $url = admin_url('admin.php?page=cl-liquidaciones-settings&auto_msg=' . rawurlencode($msg));
        wp_safe_redirect($url);
        exit;
    }

}
