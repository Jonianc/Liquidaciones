<?php
if (!defined('ABSPATH')) exit;

class LQM_PDF {

    public static function init() {
        add_action('init', [__CLASS__, 'maybe_output_pdf']);
    }

    public static function maybe_output_pdf() {
        if (!isset($_GET['lqm_pdf'])) return;

        $id = (int) $_GET['lqm_pdf'];
        if (!$id) wp_die('ID inválido');

        // Permisos MVP: quien pueda editar posts
        if (!current_user_can('edit_posts')) wp_die('Sin permisos');

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, 'lqm_pdf_'.$id)) wp_die('Nonce inválido');

        if (!LQM_FPDF::ensure_loaded()) wp_die('FPDF no disponible');

        $data = self::get_data($id);
        $calc = self::calculate($data);

        $pdf = new FPDF('P','mm','A4');
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 15);

        // Header
        $pdf->SetFont('Arial','B',14);
        $pdf->Cell(0, 8, utf8_decode('Liquidación ' . ($data['periodo'] ?: '')), 0, 1, 'C');
        $pdf->Ln(2);

        // Datos generales
        self::section_title($pdf, 'Datos Generales Trabajador');
        self::kv_row($pdf, 'Nombre Trabajador', $data['nombre'], 'Rut Trabajador', $data['rut']);
        self::kv_row($pdf, 'Relación Laboral', $data['relacion'], 'Fecha Inicio', $data['inicio']);
        self::kv_row($pdf, 'Cargo', $data['cargo'], 'Días Trabajados', (string)$data['dias_trab']);
        self::kv_row($pdf, 'Días Licencia Médica', (string)$data['dias_lic'], 'Días Inasistencias', (string)$data['dias_inas']);
        $pdf->Ln(2);

        // Imponible
        self::section_title($pdf, 'Imponible');
        self::line_item($pdf, 'Sueldo Base', $calc['sueldo_base']);
        self::line_item_total($pdf, 'Total Imponible', $calc['total_imponible']);
        $pdf->Ln(2);

        // No imponible
        self::section_title($pdf, 'No Imponible');
        if (!empty($calc['no_imponible_items'])) {
            foreach ($calc['no_imponible_items'] as $it) {
                self::line_item($pdf, $it['nombre'], $it['monto']);
            }
        } else {
            $pdf->SetFont('Arial','',10);
            $pdf->Cell(0, 6, utf8_decode('Sin Resultados'), 0, 1);
        }
        self::line_item_total($pdf, 'Total No Imponible', $calc['total_no_imponible']);
        $pdf->Ln(2);

        // Totales
        self::line_item_total($pdf, 'Total Sueldo Bruto', $calc['total_bruto']);
        $pdf->Ln(2);

        // Descuentos previsionales
        self::section_title($pdf, 'Descuentos Previsionales');
        $pdf->SetFont('Arial','',10);
        $pdf->Cell(0, 6, utf8_decode('Imponible a calcular Salud $' . self::fmt($calc['total_imponible'])), 0, 1);
        self::line_item_desc($pdf, 'Salud Fonasa 7%', $calc['fonasa_7']);
        self::line_item($pdf, 'Impuesto Único', $calc['impuesto_unico']);
        self::line_item_total_desc($pdf, 'Total Descuentos Previsionales', $calc['total_desc_previsionales']);

        $pdf->Ln(2);
        self::line_item_total($pdf, 'Total Sueldo Líquido', $calc['total_liquido']);

        // Otros descuentos
        $pdf->Ln(2);
        self::section_title($pdf, 'Otros Descuentos');
        if ($calc['otros_desc'] > 0) {
            self::line_item_desc($pdf, 'Otros Descuentos', $calc['otros_desc']);
        } else {
            $pdf->SetFont('Arial','',10);
            $pdf->Cell(0, 6, utf8_decode('Sin Resultados'), 0, 1);
        }

        $pdf->Ln(2);
        self::line_item_total($pdf, 'Total A Pagar', $calc['total_a_pagar']);

        // Firmas (simple)
        $pdf->Ln(8);
        $pdf->SetFont('Arial','',9);
        $pdf->MultiCell(0, 5, utf8_decode('Recibí conforme el alcance líquido, sin tener cargo o cobro alguno por otro concepto.'), 0, 'L');
        $pdf->Ln(12);

        $w = 90;
        $pdf->Cell($w, 6, '____________________________', 0, 0, 'C');
        $pdf->Cell(0, 6, '____________________________', 0, 1, 'C');
        $pdf->Cell($w, 6, utf8_decode('Firma Empleador'), 0, 0, 'C');
        $pdf->Cell(0, 6, utf8_decode('Firma Trabajador'), 0, 1, 'C');

        // Output
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="liquidacion-'.$id.'.pdf"');

        echo $pdf->Output('S');
        exit;
    }

    private static function get_data($id) {
        $g = function($k, $d='') use ($id) {
            $v = get_post_meta($id, $k, true);
            return $v === '' ? $d : $v;
        };

        $no_imp = get_post_meta($id, '_lqm_no_imponible', true);
        if (!is_array($no_imp)) $no_imp = [];

        return [
            'periodo' => $g('_lqm_periodo'),
            'nombre' => $g('_lqm_nombre'),
            'rut' => $g('_lqm_rut'),
            'relacion' => $g('_lqm_relacion'),
            'inicio' => $g('_lqm_inicio'),
            'cargo' => $g('_lqm_cargo'),
            'dias_trab' => (int)$g('_lqm_dias_trab', 30),
            'dias_lic' => (int)$g('_lqm_dias_lic', 0),
            'dias_inas' => (int)$g('_lqm_dias_inas', 0),
            'sueldo_base' => (int)$g('_lqm_sueldo_base', 0),
            'impuesto_unico' => (int)$g('_lqm_impuesto_unico', 0),
            'otros_desc' => (int)$g('_lqm_otros_desc', 0),
            'no_imponible' => $no_imp,
        ];
    }

    private static function calculate($d) {
        $sueldo_base = max(0, (int)$d['sueldo_base']);
        $total_imponible = $sueldo_base; // MVP

        $items = [];
        $sum_no = 0;
        foreach ((array)$d['no_imponible'] as $row) {
            $nombre = trim((string)($row['nombre'] ?? ''));
            $monto  = (int)($row['monto'] ?? 0);
            if ($nombre === '' || $monto <= 0) continue;
            $items[] = ['nombre' => $nombre, 'monto' => $monto];
            $sum_no += $monto;
        }

        $total_bruto = $total_imponible + $sum_no;

        // Coincide con tus PDFs: redondeo a peso
        $fonasa_7 = (int) round($total_imponible * 0.07);

        $impuesto_unico = max(0, (int)$d['impuesto_unico']);
        $total_desc_pre = $fonasa_7 + $impuesto_unico;

        $total_liquido = $total_bruto - $total_desc_pre;

        $otros_desc = max(0, (int)$d['otros_desc']);
        $total_a_pagar = $total_liquido - $otros_desc;

        return [
            'sueldo_base' => $sueldo_base,
            'total_imponible' => $total_imponible,
            'no_imponible_items' => $items,
            'total_no_imponible' => $sum_no,
            'total_bruto' => $total_bruto,
            'fonasa_7' => $fonasa_7,
            'impuesto_unico' => $impuesto_unico,
            'total_desc_previsionales' => $total_desc_pre,
            'total_liquido' => $total_liquido,
            'otros_desc' => $otros_desc,
            'total_a_pagar' => $total_a_pagar,
        ];
    }

    // Helpers PDF
    private static function section_title($pdf, $t) {
        $pdf->SetFont('Arial','B',11);
        $pdf->SetFillColor(240,240,240);
        $pdf->Cell(0, 7, utf8_decode($t), 0, 1, 'L', true);
        $pdf->SetFillColor(255,255,255);
    }

    private static function kv_row($pdf, $k1, $v1, $k2, $v2) {
        $pdf->SetFont('Arial','B',9);
        $pdf->Cell(45, 6, utf8_decode($k1), 0, 0);
        $pdf->SetFont('Arial','',9);
        $pdf->Cell(55, 6, utf8_decode((string)$v1), 0, 0);

        $pdf->SetFont('Arial','B',9);
        $pdf->Cell(45, 6, utf8_decode($k2), 0, 0);
        $pdf->SetFont('Arial','',9);
        $pdf->Cell(0, 6, utf8_decode((string)$v2), 0, 1);
    }

    private static function line_item($pdf, $label, $amount) {
        $pdf->SetFont('Arial','',10);
        $pdf->Cell(140, 6, utf8_decode($label), 0, 0);
        $pdf->Cell(0, 6, '$' . self::fmt((int)$amount), 0, 1, 'R');
    }

    private static function line_item_total($pdf, $label, $amount) {
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(140, 7, utf8_decode($label), 0, 0);
        $pdf->Cell(0, 7, '$' . self::fmt((int)$amount), 0, 1, 'R');
        $pdf->SetFont('Arial','',10);
    }

    private static function line_item_desc($pdf, $label, $amount) {
        $pdf->SetFont('Arial','',10);
        $pdf->Cell(140, 6, utf8_decode($label), 0, 0);
        $pdf->Cell(0, 6, '$-' . self::fmt((int)$amount), 0, 1, 'R');
    }

    private static function line_item_total_desc($pdf, $label, $amount) {
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(140, 7, utf8_decode($label), 0, 0);
        $pdf->Cell(0, 7, '$-' . self::fmt((int)$amount), 0, 1, 'R');
        $pdf->SetFont('Arial','',10);
    }

    private static function fmt($n) {
        return number_format((int)$n, 0, ',', '.');
    }
}
