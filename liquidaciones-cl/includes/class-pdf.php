<?php
if ( ! defined('ABSPATH') ) { exit; }

final class CL_LIQ_PDF {

    public static function init() {
        add_action('admin_post_cl_liq_pdf', [__CLASS__, 'handle_pdf']);
    }

    /** Build PDF bytes for a given liquidacion ID (does not output headers). */
    public static function render_pdf(int $liq_id): string {
        $post = get_post($liq_id);
        if (!$post || $post->post_type !== 'cl_liquidacion') {
            return '';
        }

        $calc = get_post_meta($liq_id, 'cl_calc', true);
        if (!is_array($calc) || empty($calc)) {
            $calc = CL_LIQ_Calculator::calculate_liquidacion($liq_id);
        }
        if (!is_array($calc) || empty($calc)) {
            return '';
        }

        $empleado_id = (int) get_post_meta($liq_id, 'cl_empleado_id', true);
        $emp_title = $empleado_id ? get_the_title($empleado_id) : '';
        $emp_rut = $empleado_id ? (string) get_post_meta($empleado_id, 'cl_rut', true) : '';
        $afp = (string) ($calc['afp_nombre'] ?? '');
        $salud = (string) ($calc['salud_tipo'] ?? '');
        $tipo_contrato = (string) ($calc['tipo_contrato'] ?? '');
        $ym = (string) ($calc['ym'] ?? '');
        $period_label = $ym ? CL_LIQ_Helpers::ym_label($ym) : '';
        $generated_at = function_exists('wp_date') ? wp_date('Y-m-d H:i') : gmdate('Y-m-d H:i');

        $det = is_array($calc['haberes_detalle'] ?? null) ? $calc['haberes_detalle'] : [];

        $document = [
            'title' => 'Liquidacion de sueldo',
            'subtitle' => $period_label ? ('Periodo ' . $period_label . ($ym ? ' (' . $ym . ')' : '')) : 'Documento de liquidacion',
            'document_ref' => 'Liquidacion #' . $liq_id,
            'generated_at' => $generated_at,
            'meta' => [
                ['label' => 'Empleado', 'value' => $emp_title ?: '-'],
                ['label' => 'RUT', 'value' => $emp_rut ?: '-'],
                ['label' => 'Contrato', 'value' => $tipo_contrato ? self::label_contrato($tipo_contrato) : '-'],
                ['label' => 'AFP', 'value' => $afp ?: '-'],
                ['label' => 'Salud', 'value' => $salud ? strtoupper($salud) : '-'],
                ['label' => 'Periodo', 'value' => $period_label ?: ($ym ?: '-')],
            ],
            'sections' => [
                [
                    'title' => 'Haberes imponibles',
                    'rows' => self::build_rows($det, [
                        ['key' => 'sueldo_base', 'label' => 'Sueldo base', 'always' => true],
                        ['key' => 'gratificacion', 'label' => 'Gratificacion'],
                        ['key' => 'horas_extra', 'label' => 'Horas extra'],
                        ['key' => 'bonos_imponibles', 'label' => 'Bonos imponibles'],
                        ['key' => 'comisiones', 'label' => 'Comisiones'],
                        ['key' => 'otros_imponibles', 'label' => 'Otros imponibles'],
                    ]),
                    'total_label' => 'Total imponible',
                    'total_amount' => (int) ($calc['imponible_total'] ?? 0),
                ],
                [
                    'title' => 'Haberes no imponibles',
                    'rows' => self::build_rows($det, [
                        ['key' => 'colacion', 'label' => 'Colacion'],
                        ['key' => 'movilizacion', 'label' => 'Movilizacion'],
                        ['key' => 'viaticos', 'label' => 'Viaticos'],
                        ['key' => 'otros_no_imponibles', 'label' => 'Otros no imponibles'],
                        ['key' => 'asignacion_familiar', 'label' => 'Asignacion familiar'],
                    ]),
                    'total_label' => 'Total no imponible',
                    'total_amount' => (int) ($calc['no_imponible_total'] ?? 0),
                ],
                [
                    'title' => 'Descuentos',
                    'rows' => self::build_rows($calc, [
                        ['key' => 'afp_10', 'label' => 'AFP 10%', 'always' => true],
                        ['key' => 'afp_comision', 'label' => 'AFP comision', 'always' => true],
                        ['key' => 'salud', 'label' => (string) ($calc['salud_label'] ?? 'Salud'), 'always' => true],
                        ['key' => 'afc_trabajador', 'label' => 'AFC trabajador', 'always' => true],
                        ['key' => 'impuesto_unico', 'label' => 'Impuesto unico', 'always' => true],
                        ['key' => 'otros_descuentos_total', 'label' => 'Otros descuentos'],
                    ]),
                    'total_label' => 'Total descuentos',
                    'total_amount' => (int) ($calc['descuentos_total'] ?? 0),
                ],
            ],
            'summary' => [
                ['label' => 'Total haberes', 'amount' => (int) ($calc['haberes_total'] ?? 0), 'emphasis' => 'normal'],
                ['label' => 'Total descuentos', 'amount' => (int) ($calc['descuentos_total'] ?? 0), 'emphasis' => 'normal'],
                ['label' => 'Liquido a pagar', 'amount' => (int) ($calc['liquido'] ?? 0), 'emphasis' => 'strong'],
            ],
        ];

        $renderer = new CL_LIQ_PDF_Renderer([
            'title' => $document['title'],
            'subject' => $document['subtitle'],
            'author' => get_bloginfo('name') ?: 'WordPress',
            'creator' => 'Liquidaciones CL',
        ]);

        return $renderer->render($document);
    }

    public static function pdf_url(int $liq_id): string {
        if ($liq_id <= 0) {
            return '';
        }
        $nonce = wp_create_nonce('cl_liq_pdf_' . $liq_id);
        return admin_url('admin-post.php?action=cl_liq_pdf&liq_id=' . $liq_id . '&_wpnonce=' . $nonce);
    }

    public static function handle_pdf() {
        $liq_id = isset($_GET['liq_id']) ? (int) $_GET['liq_id'] : 0;
        if ($liq_id <= 0) {
            wp_die('ID invalido.');
        }

        if ( ! is_user_logged_in() ) {
            wp_die('No autorizado.');
        }
        if ( ! current_user_can('edit_post', $liq_id) && ! current_user_can('manage_cl_liquidaciones') ) {
            wp_die('No autorizado.');
        }

        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
        if ( ! wp_verify_nonce($nonce, 'cl_liq_pdf_' . $liq_id) ) {
            wp_die('Nonce invalido.');
        }

        $pdf = self::render_pdf($liq_id);
        if (!$pdf) {
            wp_die('No fue posible generar el PDF.');
        }

        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="liquidacion-' . $liq_id . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    private static function label_contrato(string $key): string {
        $map = [
            'indefinido' => 'Indefinido',
            'plazo_fijo' => 'Plazo fijo',
            'obra' => 'Obra / Faena',
            'casa_particular' => 'Casa particular',
        ];
        return $map[$key] ?? $key;
    }

    private static function build_rows(array $source, array $definitions): array {
        $rows = [];

        foreach ($definitions as $definition) {
            $key = (string) ($definition['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $amount = (int) ($source[$key] ?? 0);
            $always = !empty($definition['always']);
            if (!$always && $amount === 0) {
                continue;
            }

            $rows[] = [
                'label' => (string) ($definition['label'] ?? $key),
                'amount' => $amount,
            ];
        }

        return $rows;
    }
}

final class CL_LIQ_PDF_Renderer {

    private const PAGE_WIDTH = 595.28;
    private const PAGE_HEIGHT = 841.89;
    private const MARGIN_TOP = 46.0;
    private const MARGIN_RIGHT = 42.0;
    private const MARGIN_BOTTOM = 42.0;
    private const MARGIN_LEFT = 42.0;

    private array $meta;
    private array $pages = [];
    private int $currentPage = -1;
    private float $y = 0.0;

    public function __construct(array $meta = []) {
        $defaults = [
            'title' => 'Documento',
            'subject' => '',
            'author' => 'WordPress',
            'creator' => 'Liquidaciones CL',
        ];
        $this->meta = array_merge($defaults, $meta);
    }

    public function render(array $document): string {
        $this->pages = [];
        $this->startPage();
        $this->renderFirstPageHeader($document);
        $this->renderMetaGrid($document);

        $sections = is_array($document['sections'] ?? null) ? $document['sections'] : [];
        foreach ($sections as $section) {
            $this->renderTableSection($document, $section);
        }

        $summary = is_array($document['summary'] ?? null) ? $document['summary'] : [];
        if (!empty($summary)) {
            $this->renderSummaryBox($document, $summary);
        }

        $this->appendFooters($document);

        return $this->buildPdfBinary();
    }

    private function renderFirstPageHeader(array $document): void {
        $left = self::MARGIN_LEFT;
        $right = self::PAGE_WIDTH - self::MARGIN_RIGHT;
        $top = $this->y;

        $this->drawText($left, $top, (string) ($document['title'] ?? 'Documento'), 'F2', 19, [17, 24, 39]);
        $this->drawText($left, $top - 24, (string) ($document['subtitle'] ?? ''), 'F1', 10, [75, 85, 99]);

        $docRef = (string) ($document['document_ref'] ?? '');
        $generatedAt = (string) ($document['generated_at'] ?? '');
        if ($docRef !== '') {
            $this->drawTextRight($right, $top - 2, $docRef, 'F2', 10, [17, 24, 39]);
        }
        if ($generatedAt !== '') {
            $this->drawTextRight($right, $top - 18, 'Emitido: ' . $generatedAt, 'F1', 9, [107, 114, 128]);
        }

        $lineY = $top - 38;
        $this->drawLine($left, $lineY, $right, $lineY, [226, 232, 240], 1.0);
        $this->y = $lineY - 18;
    }

    private function renderMetaGrid(array $document): void {
        $items = is_array($document['meta'] ?? null) ? $document['meta'] : [];
        if (empty($items)) {
            return;
        }

        $columns = 2;
        $gap = 12.0;
        $columnWidth = (($this->contentWidth()) - $gap) / $columns;

        $rows = array_chunk($items, $columns);
        foreach ($rows as $row) {
            $heights = [];
            foreach ($row as $item) {
                $value = (string) ($item['value'] ?? '-');
                $lines = $this->wrapText($value, $columnWidth - 18.0, 'F1', 10);
                $lineCount = max(1, count($lines));
                $heights[] = 22.0 + ($lineCount * 12.0);
            }

            $rowHeight = max($heights ?: [34.0]);
            $this->ensureSpace($rowHeight + 10.0, $document, 'Datos del documento');

            foreach ($row as $index => $item) {
                $x = self::MARGIN_LEFT + (($columnWidth + $gap) * $index);
                $topY = $this->y;
                $this->drawRect($x, $topY, $columnWidth, $rowHeight, [248, 250, 252], [226, 232, 240], 0.8);
                $this->drawText($x + 9.0, $topY - 14.0, strtoupper((string) ($item['label'] ?? '')), 'F2', 8, [100, 116, 139]);
                $this->drawWrappedText($x + 9.0, $topY - 28.0, (string) ($item['value'] ?? '-'), $columnWidth - 18.0, 'F1', 10, [17, 24, 39], 12.0);
            }

            $this->y -= ($rowHeight + 10.0);
        }

        $this->y -= 6.0;
    }

    private function renderTableSection(array $document, array $section): void {
        $title = (string) ($section['title'] ?? 'Seccion');
        $rows = is_array($section['rows'] ?? null) ? $section['rows'] : [];
        $totalLabel = (string) ($section['total_label'] ?? 'Total');
        $totalAmount = (int) ($section['total_amount'] ?? 0);

        $this->ensureSpace(54.0, $document, $title);
        $this->drawText(self::MARGIN_LEFT, $this->y, $title, 'F2', 12, [17, 24, 39]);
        $this->y -= 16.0;
        $this->drawTableHeader();

        foreach ($rows as $row) {
            $label = (string) ($row['label'] ?? '');
            $amount = (int) ($row['amount'] ?? 0);
            $labelLines = $this->wrapText($label, $this->labelColumnWidth() - 18.0, 'F1', 10);
            $rowHeight = max(24.0, 12.0 + (count($labelLines) * 12.0));

            if (!$this->hasSpace($rowHeight + 8.0)) {
                $this->startContinuationPage($document, $title);
                $this->drawTableHeader();
            }

            $this->drawTableRow($labelLines, $amount, $rowHeight);
        }

        if (!$this->hasSpace(28.0)) {
            $this->startContinuationPage($document, $title);
            $this->drawTableHeader();
        }

        $this->drawTableTotal($totalLabel, $totalAmount);
        $this->y -= 16.0;
    }

    private function renderSummaryBox(array $document, array $summaryRows): void {
        $boxHeight = 18.0;
        foreach ($summaryRows as $row) {
            $boxHeight += (!empty($row['emphasis']) && $row['emphasis'] === 'strong') ? 22.0 : 16.0;
        }
        $boxHeight += 18.0;

        $this->ensureSpace($boxHeight, $document, 'Resumen');

        $left = self::MARGIN_LEFT;
        $top = $this->y;
        $width = $this->contentWidth();

        $this->drawRect($left, $top, $width, $boxHeight, [243, 244, 246], [203, 213, 225], 0.9);
        $this->drawText($left + 12.0, $top - 15.0, 'Resumen', 'F2', 11, [17, 24, 39]);

        $cursor = $top - 34.0;
        foreach ($summaryRows as $row) {
            $label = (string) ($row['label'] ?? '');
            $amount = (int) ($row['amount'] ?? 0);
            $strong = !empty($row['emphasis']) && $row['emphasis'] === 'strong';
            $font = $strong ? 'F2' : 'F1';
            $size = $strong ? 13.0 : 10.0;
            $color = $strong ? [17, 24, 39] : [55, 65, 81];
            $step = $strong ? 20.0 : 16.0;

            $this->drawText($left + 12.0, $cursor, $label, $font, $size, $color);
            $this->drawTextRight($left + $width - 12.0, $cursor, CL_LIQ_Helpers::money($amount), $font, $size, $color);
            $cursor -= $step;
        }

        $this->y -= ($boxHeight + 10.0);
    }

    private function drawTableHeader(): void {
        $left = self::MARGIN_LEFT;
        $top = $this->y;
        $width = $this->contentWidth();

        $this->drawRect($left, $top, $width, 24.0, [30, 41, 59], [30, 41, 59], 0.8);
        $this->drawText($left + 10.0, $top - 15.0, 'Concepto', 'F2', 9, [255, 255, 255]);
        $this->drawTextRight($left + $width - 10.0, $top - 15.0, 'Monto', 'F2', 9, [255, 255, 255]);
        $this->y -= 24.0;
    }

    private function drawTableRow(array $labelLines, int $amount, float $rowHeight): void {
        $left = self::MARGIN_LEFT;
        $top = $this->y;
        $width = $this->contentWidth();
        $labelX = $left + 10.0;
        $amountRight = $left + $width - 10.0;

        $this->drawRect($left, $top, $width, $rowHeight, [255, 255, 255], [226, 232, 240], 0.7);

        $textTop = $top - 15.0;
        foreach ($labelLines as $index => $line) {
            $this->drawText($labelX, $textTop - ($index * 12.0), $line, 'F1', 10, [17, 24, 39]);
        }

        $this->drawTextRight($amountRight, $top - 15.0, CL_LIQ_Helpers::money($amount), 'F1', 10, [17, 24, 39]);
        $this->y -= $rowHeight;
    }

    private function drawTableTotal(string $label, int $amount): void {
        $left = self::MARGIN_LEFT;
        $top = $this->y;
        $width = $this->contentWidth();

        $this->drawRect($left, $top, $width, 24.0, [239, 246, 255], [191, 219, 254], 0.8);
        $this->drawText($left + 10.0, $top - 15.0, $label, 'F2', 10, [30, 64, 175]);
        $this->drawTextRight($left + $width - 10.0, $top - 15.0, CL_LIQ_Helpers::money($amount), 'F2', 10, [30, 64, 175]);
        $this->y -= 24.0;
    }

    private function startPage(): void {
        $this->pages[] = '';
        $this->currentPage = count($this->pages) - 1;
        $this->y = self::PAGE_HEIGHT - self::MARGIN_TOP;
    }

    private function startContinuationPage(array $document, string $sectionTitle): void {
        $this->startPage();

        $left = self::MARGIN_LEFT;
        $right = self::PAGE_WIDTH - self::MARGIN_RIGHT;
        $top = $this->y;

        $this->drawText($left, $top, (string) ($document['title'] ?? 'Documento'), 'F2', 12, [17, 24, 39]);
        $this->drawTextRight($right, $top, 'Continuacion: ' . $sectionTitle, 'F1', 9, [75, 85, 99]);
        $lineY = $top - 14.0;
        $this->drawLine($left, $lineY, $right, $lineY, [226, 232, 240], 1.0);
        $this->y = $lineY - 18.0;
    }

    private function ensureSpace(float $height, array $document, string $continuationTitle): void {
        if (!$this->hasSpace($height)) {
            $this->startContinuationPage($document, $continuationTitle);
        }
    }

    private function hasSpace(float $height): bool {
        return ($this->y - $height) >= self::MARGIN_BOTTOM;
    }

    private function contentWidth(): float {
        return self::PAGE_WIDTH - self::MARGIN_LEFT - self::MARGIN_RIGHT;
    }

    private function labelColumnWidth(): float {
        return $this->contentWidth() * 0.72;
    }

    private function drawWrappedText(float $x, float $topY, string $text, float $maxWidth, string $font, float $fontSize, array $color, float $lineHeight): float {
        $lines = $this->wrapText($text, $maxWidth, $font, $fontSize);
        foreach ($lines as $index => $line) {
            $this->drawText($x, $topY - ($index * $lineHeight), $line, $font, $fontSize, $color);
        }
        return count($lines) * $lineHeight;
    }

    private function wrapText(string $text, float $maxWidth, string $font, float $fontSize): array {
        $normalized = preg_replace('/\s+/u', ' ', $text);
        $text = trim($normalized !== null ? $normalized : $text);
        if ($text === '') {
            return [''];
        }

        $words = preg_split('/\s+/u', $text);
        if (!is_array($words) || empty($words)) {
            return [$text];
        }

        $lines = [];
        $current = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $candidate = ($current === '') ? $word : ($current . ' ' . $word);
            if ($current === '' || $this->measureText($candidate, $font, $fontSize) <= $maxWidth) {
                if ($current === '' && $this->measureText($word, $font, $fontSize) > $maxWidth) {
                    $parts = $this->splitLongToken($word, $maxWidth, $font, $fontSize);
                    if (count($parts) === 1) {
                        $current = $parts[0];
                    } else {
                        $lines = array_merge($lines, array_slice($parts, 0, -1));
                        $current = (string) end($parts);
                    }
                    continue;
                }

                $current = $candidate;
                continue;
            }

            $lines[] = $current;
            if ($this->measureText($word, $font, $fontSize) <= $maxWidth) {
                $current = $word;
                continue;
            }

            $parts = $this->splitLongToken($word, $maxWidth, $font, $fontSize);
            $lines = array_merge($lines, array_slice($parts, 0, -1));
            $current = (string) end($parts);
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines ?: [''];
    }

    private function splitLongToken(string $word, float $maxWidth, string $font, float $fontSize): array {
        $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars) || empty($chars)) {
            return [$word];
        }

        $parts = [];
        $current = '';

        foreach ($chars as $char) {
            $candidate = $current . $char;
            if ($current === '' || $this->measureText($candidate, $font, $fontSize) <= $maxWidth) {
                $current = $candidate;
                continue;
            }

            $parts[] = $current;
            $current = $char;
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts ?: [$word];
    }

    private function drawText(float $x, float $y, string $text, string $font, float $fontSize, array $color): void {
        $escaped = $this->escapeLiteral($text);
        $command = 'BT '
            . '/' . $font . ' ' . $this->num($fontSize) . ' Tf '
            . $this->colorText($color)
            . ' 1 0 0 1 ' . $this->num($x) . ' ' . $this->num($y) . ' Tm '
            . '(' . $escaped . ') Tj ET';
        $this->appendCommand($command);
    }

    private function drawTextRight(float $rightX, float $y, string $text, string $font, float $fontSize, array $color): void {
        $width = $this->measureText($text, $font, $fontSize);
        $this->drawText($rightX - $width, $y, $text, $font, $fontSize, $color);
    }

    private function drawRect(float $x, float $topY, float $width, float $height, array $fillColor, array $strokeColor, float $lineWidth): void {
        $pdfY = $topY - $height;
        $command = 'q '
            . $this->num($lineWidth) . ' w '
            . $this->colorFill($fillColor)
            . ' '
            . $this->colorStroke($strokeColor)
            . ' '
            . $this->num($x) . ' ' . $this->num($pdfY) . ' ' . $this->num($width) . ' ' . $this->num($height) . ' re B Q';
        $this->appendCommand($command);
    }

    private function drawLine(float $x1, float $y1, float $x2, float $y2, array $strokeColor, float $lineWidth): void {
        $command = 'q '
            . $this->num($lineWidth) . ' w '
            . $this->colorStroke($strokeColor)
            . ' '
            . $this->num($x1) . ' ' . $this->num($y1) . ' m '
            . $this->num($x2) . ' ' . $this->num($y2) . ' l S Q';
        $this->appendCommand($command);
    }

    private function appendFooters(array $document): void {
        $pageCount = count($this->pages);
        foreach ($this->pages as $index => $_stream) {
            $pageNumber = $index + 1;
            $footerY = self::MARGIN_BOTTOM - 8.0;
            $left = self::MARGIN_LEFT;
            $right = self::PAGE_WIDTH - self::MARGIN_RIGHT;

            $this->currentPage = $index;
            $this->drawLine($left, $footerY + 10.0, $right, $footerY + 10.0, [226, 232, 240], 0.8);
            $this->drawText($left, $footerY, (string) ($document['document_ref'] ?? ''), 'F1', 8, [107, 114, 128]);
            $this->drawTextRight($right, $footerY, 'Pagina ' . $pageNumber . ' de ' . $pageCount, 'F1', 8, [107, 114, 128]);
        }
    }

    private function buildPdfBinary(): string {
        $objects = [];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids __KIDS__ /Count ' . count($this->pages) . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        $objects[5] = $this->buildInfoObject();

        $pageObjectNumbers = [];
        $nextObject = 6;

        foreach ($this->pages as $stream) {
            $pageObject = $nextObject++;
            $contentObject = $nextObject++;
            $pageObjectNumbers[] = $pageObject;

            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                . $this->num(self::PAGE_WIDTH) . ' ' . $this->num(self::PAGE_HEIGHT)
                . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';

            $objects[$contentObject] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        }

        $kidRefs = [];
        foreach ($pageObjectNumbers as $pageObjectNumber) {
            $kidRefs[] = $pageObjectNumber . ' 0 R';
        }
        $objects[2] = str_replace('__KIDS__', '[ ' . implode(' ', $kidRefs) . ' ]', $objects[2]);

        ksort($objects);
        $maxObject = max(array_keys($objects));

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        for ($i = 1; $i <= $maxObject; $i++) {
            $object = $objects[$i] ?? '';
            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($maxObject + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObject; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R /Info 5 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        return $pdf;
    }

    private function buildInfoObject(): string {
        $creationDate = gmdate('YmdHis');
        return '<< '
            . '/Title (' . $this->escapeLiteral((string) $this->meta['title']) . ') '
            . '/Subject (' . $this->escapeLiteral((string) $this->meta['subject']) . ') '
            . '/Author (' . $this->escapeLiteral((string) $this->meta['author']) . ') '
            . '/Creator (' . $this->escapeLiteral((string) $this->meta['creator']) . ') '
            . '/Producer (' . $this->escapeLiteral('Liquidaciones CL PDF Renderer') . ') '
            . '/CreationDate (D:' . $creationDate . ') >>';
    }

    private function appendCommand(string $command): void {
        if ($this->currentPage < 0) {
            $this->startPage();
        }

        $this->pages[$this->currentPage] .= $command . "\n";
    }

    private function escapeLiteral(string $text): string {
        $encoded = $this->encodeText($text);
        $encoded = str_replace('\\', '\\\\', $encoded);
        $encoded = str_replace('(', '\\(', $encoded);
        $encoded = str_replace(')', '\\)', $encoded);
        $encoded = str_replace("\r", '', $encoded);
        $encoded = str_replace("\n", ' ', $encoded);
        return $encoded;
    }

    private function encodeText(string $text): string {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                return (string) $converted;
            }
        }

        $fallback = preg_replace('/[^\x20-\x7E]/', '?', $text);
        return $fallback !== null ? $fallback : '';
    }

    private function measureText(string $text, string $font, float $fontSize): float {
        $encoded = $this->encodeText($text);
        $total = 0;

        $length = strlen($encoded);
        for ($i = 0; $i < $length; $i++) {
            $total += $this->glyphWidth($encoded[$i], $font);
        }

        return ($total / 1000) * $fontSize;
    }

    private function glyphWidth(string $char, string $font): int {
        static $widths = [
            ' ' => 278,
            '!' => 278,
            '"' => 355,
            '#' => 556,
            '$' => 556,
            '%' => 889,
            '&' => 667,
            "'" => 191,
            '(' => 333,
            ')' => 333,
            '*' => 389,
            '+' => 584,
            ',' => 278,
            '-' => 333,
            '.' => 278,
            '/' => 278,
            ':' => 278,
            ';' => 278,
            '<' => 584,
            '=' => 584,
            '>' => 584,
            '?' => 556,
            '@' => 1015,
            'I' => 278,
            'J' => 500,
            'M' => 833,
            'W' => 944,
            'f' => 278,
            'i' => 222,
            'j' => 222,
            'l' => 222,
            'm' => 833,
            'r' => 333,
            't' => 278,
            'w' => 722,
            '|' => 260,
        ];

        if (isset($widths[$char])) {
            $width = $widths[$char];
        } else {
            $ord = ord($char);
            if ($ord >= 48 && $ord <= 57) {
                $width = 556;
            } elseif ($ord >= 65 && $ord <= 90) {
                $width = 667;
            } elseif ($ord >= 97 && $ord <= 122) {
                $width = 500;
            } elseif ($ord >= 192) {
                $width = 556;
            } else {
                $width = 500;
            }
        }

        if ($font === 'F2') {
            return (int) round($width * 1.02);
        }

        return $width;
    }

    private function colorText(array $rgb): string {
        return $this->colorCommand($rgb, 'rg');
    }

    private function colorFill(array $rgb): string {
        return $this->colorCommand($rgb, 'rg');
    }

    private function colorStroke(array $rgb): string {
        return $this->colorCommand($rgb, 'RG');
    }

    private function colorCommand(array $rgb, string $operator): string {
        $r = isset($rgb[0]) ? max(0, min(255, (int) $rgb[0])) / 255 : 0;
        $g = isset($rgb[1]) ? max(0, min(255, (int) $rgb[1])) / 255 : 0;
        $b = isset($rgb[2]) ? max(0, min(255, (int) $rgb[2])) / 255 : 0;
        return $this->num($r) . ' ' . $this->num($g) . ' ' . $this->num($b) . ' ' . $operator;
    }

    private function num(float $value): string {
        return number_format($value, 2, '.', '');
    }
}
