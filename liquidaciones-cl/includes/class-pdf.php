<?php
if ( ! defined('ABSPATH') ) { exit; }

final class CL_LIQ_PDF {

    public static function init() {
        add_action('admin_post_cl_liq_pdf', [__CLASS__, 'handle_pdf']);
    }

    /** Build PDF bytes for a given liquidación ID (does not output headers). */
    public static function render_pdf(int $liq_id): string {
        $post = get_post($liq_id);
        if (!$post || $post->post_type !== 'cl_liquidacion') return '';

        $calc = get_post_meta($liq_id, 'cl_calc', true);
        if (!is_array($calc) || empty($calc)) {
            $calc = CL_LIQ_Calculator::calculate_liquidacion($liq_id);
        }
        if (!is_array($calc) || empty($calc)) return '';

        $empleado_id = (int) get_post_meta($liq_id, 'cl_empleado_id', true);
        $emp_title = $empleado_id ? get_the_title($empleado_id) : '';
        $emp_rut = $empleado_id ? (string) get_post_meta($empleado_id, 'cl_rut', true) : '';
        $afp = $calc['afp_nombre'] ?? '';
        $salud = $calc['salud_tipo'] ?? '';
        $tipo_contrato = $calc['tipo_contrato'] ?? '';
        $ym = $calc['ym'] ?? '';
        $period_label = $ym ? CL_LIQ_Helpers::ym_label($ym) : '';

        $lines = [];
        $lines[] = 'LIQUIDACION DE SUELDO';
        $lines[] = 'Periodo: ' . $period_label . ' (' . $ym . ')';
        $lines[] = 'Empleado: ' . $emp_title;
        if ($emp_rut) $lines[] = 'RUT: ' . $emp_rut;
        if ($tipo_contrato) $lines[] = 'Contrato: ' . self::label_contrato($tipo_contrato);
        if ($afp) $lines[] = 'AFP: ' . $afp;
        if ($salud) $lines[] = 'Salud: ' . strtoupper($salud);
        $lines[] = str_repeat('-', 70);

        $det = $calc['haberes_detalle'] ?? [];
        $lines[] = 'HABERES IMPONIBLES';
        $lines[] = self::row('Sueldo base', (int)($det['sueldo_base'] ?? 0));
        if (!empty($det['gratificacion'])) $lines[] = self::row('Gratificacion', (int)$det['gratificacion']);
        if (!empty($det['horas_extra'])) $lines[] = self::row('Horas extra', (int)$det['horas_extra']);
        if (!empty($det['bonos_imponibles'])) $lines[] = self::row('Bonos imponibles', (int)$det['bonos_imponibles']);
        if (!empty($det['comisiones'])) $lines[] = self::row('Comisiones', (int)$det['comisiones']);
        if (!empty($det['otros_imponibles'])) $lines[] = self::row('Otros imponibles', (int)$det['otros_imponibles']);
        $lines[] = self::row('TOTAL IMPONIBLE', (int)($calc['imponible_total'] ?? 0));

        $lines[] = '';
        $lines[] = 'HABERES NO IMPONIBLES';
        if (!empty($det['colacion'])) $lines[] = self::row('Colacion', (int)$det['colacion']);
        if (!empty($det['movilizacion'])) $lines[] = self::row('Movilizacion', (int)$det['movilizacion']);
        if (!empty($det['viaticos'])) $lines[] = self::row('Viaticos', (int)$det['viaticos']);
        if (!empty($det['otros_no_imponibles'])) $lines[] = self::row('Otros no imponibles', (int)$det['otros_no_imponibles']);
        if (!empty($det['asignacion_familiar'])) $lines[] = self::row('Asignacion familiar', (int)$det['asignacion_familiar']);
        $lines[] = self::row('TOTAL NO IMPONIBLE', (int)($calc['no_imponible_total'] ?? 0));

        $lines[] = str_repeat('-', 70);
        $lines[] = self::row('TOTAL HABERES', (int)($calc['haberes_total'] ?? 0));
        $lines[] = '';

        $lines[] = 'DESCUENTOS';
        $lines[] = self::row('AFP 10%', (int)($calc['afp_10'] ?? 0));
        $lines[] = self::row('AFP comision', (int)($calc['afp_comision'] ?? 0));
        $lines[] = self::row($calc['salud_label'] ?? 'Salud', (int)($calc['salud'] ?? 0));
        $lines[] = self::row('AFC trabajador', (int)($calc['afc_trabajador'] ?? 0));
        $lines[] = self::row('Impuesto unico', (int)($calc['impuesto_unico'] ?? 0));
        if (!empty($calc['otros_descuentos_total'])) $lines[] = self::row('Otros descuentos', (int)$calc['otros_descuentos_total']);
        $lines[] = self::row('TOTAL DESCUENTOS', (int)($calc['descuentos_total'] ?? 0));

        $lines[] = str_repeat('-', 70);
        $lines[] = self::row('LIQUIDO A PAGAR', (int)($calc['liquido'] ?? 0));

        return self::build_simple_pdf($lines);
    }

    public static function pdf_url(int $liq_id): string {
        if ($liq_id <= 0) return '';
        $nonce = wp_create_nonce('cl_liq_pdf_' . $liq_id);
        return admin_url('admin-post.php?action=cl_liq_pdf&liq_id=' . $liq_id . '&_wpnonce=' . $nonce);
    }

    public static function handle_pdf() {
        $liq_id = isset($_GET['liq_id']) ? (int) $_GET['liq_id'] : 0;
        if ($liq_id <= 0) wp_die('ID inválido.');

        if ( ! is_user_logged_in() ) wp_die('No autorizado.');
        if ( ! current_user_can('edit_post', $liq_id) && ! current_user_can('manage_cl_liquidaciones') ) wp_die('No autorizado.');

        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
        if ( ! wp_verify_nonce($nonce, 'cl_liq_pdf_' . $liq_id) ) wp_die('Nonce inválido.');

        $pdf = self::render_pdf($liq_id);
        if (!$pdf) wp_die('No fue posible generar el PDF.');

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="liquidacion-' . $liq_id . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    private static function label_contrato(string $k): string {
        $map = [
            'indefinido' => 'Indefinido',
            'plazo_fijo' => 'Plazo fijo',
            'obra' => 'Obra / Faena',
            'casa_particular' => 'Casa particular',
        ];
        return $map[$k] ?? $k;
    }

    private static function row(string $label, int $amount): string {
        $label = trim($label);
        $money = CL_LIQ_Helpers::money($amount);
        // crude alignment for PDF text
        $pad = max(1, 52 - strlen($label));
        return $label . str_repeat(' ', $pad) . $money;
    }

    private static function pdf_escape(string $s): string {
        // convert to windows-1252 for basic PDF WinAnsi
        $s = (string) @iconv('UTF-8', 'windows-1252//TRANSLIT', $s);
        $s = str_replace('\\', '\\\\', $s);
        $s = str_replace('(', '\\(', $s);
        $s = str_replace(')', '\\)', $s);
        $s = str_replace("\r", '', $s);
        $s = str_replace("\n", '', $s);
        return $s;
    }

    /**
     * Minimal single-page PDF for lines of text using Helvetica.
     */
    private static function build_simple_pdf(array $lines): string {
        $w = 595.28; $h = 841.89; // A4 points
        $x = 40; $y = 800; $lh = 13;

        $stream = "";
        $stream .= "BT\n";
        $stream .= "/F1 10 Tf\n";
        foreach ($lines as $i => $line) {
            $yy = $y - ($i * $lh);
            if ($yy < 40) break; // stop if out of page
            $line = self::pdf_escape((string)$line);
            $stream .= sprintf("%.2f %.2f Td (%s) Tj\n", $x, $yy, $line);
            // reset text matrix each line by ending/starting text object to keep absolute positions
            $stream .= "ET\nBT\n/F1 10 Tf\n";
        }
        $stream .= "ET\n";

        $objects = [];

        $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$w} {$h}] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
        $objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $len = strlen($stream);
        $objects[] = "5 0 obj\n<< /Length {$len} >>\nstream\n{$stream}endstream\nendobj\n";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= $obj;
        }
        $xref_pos = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects)+1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i=1; $i<=count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects)+1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xref_pos}\n%%EOF";

        return $pdf;
    }

}
