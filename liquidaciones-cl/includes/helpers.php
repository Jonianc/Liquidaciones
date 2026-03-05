<?php
if ( ! defined('ABSPATH') ) { exit; }

final class CL_LIQ_Helpers {

    /** Parse CLP values like "1.234.567" or "$ 1 234 567" to int pesos */
    public static function parse_clp($value): int {
        if ($value === null) return 0;
        if (is_int($value)) return $value;
        if (is_float($value)) return (int) round($value);
        $s = (string) $value;
        $s = str_replace(["$", "CLP", "clp", " "], "", $s);
        // keep digits only
        $s = preg_replace('/[^0-9]/', '', $s);
        if ($s === '' ) return 0;
        return (int) $s;
    }

    /** Parse decimal like "1,23" or "1.23" to float */
    public static function parse_decimal($value): float {
        if ($value === null) return 0.0;
        if (is_float($value)) return $value;
        if (is_int($value)) return (float) $value;
        $s = trim((string)$value);
        $s = str_replace([' ', 'UF', 'uf'], '', $s);
        // If both separators exist, treat last as decimal
        if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
            // remove thousand separators: assume '.' thousands and ',' decimals
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }
        $s = preg_replace('/[^0-9\.-]/', '', $s);
        if ($s === '' || $s === '-' || $s === '.' ) return 0.0;
        return (float) $s;
    }

    public static function money($clp): string {
        $n = (int) round((float)$clp);
        return '$ ' . number_format($n, 0, ',', '.');
    }

    public static function esc_money($clp): string {
        return esc_html(self::money($clp));
    }

    public static function current_ym(): string {
        // Use WP timezone
        return (string) current_time('Y-m');
    }

    /** Add months to a YYYY-MM string (can be negative). */
    public static function ym_add(string $ym, int $months): string {
        $ym = trim($ym);
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $ym = self::current_ym();
        }

        try {
            $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
            $dt = new DateTime($ym . '-01 00:00:00', $tz);
            $dt->modify(($months >= 0 ? '+' : '') . $months . ' months');
            return $dt->format('Y-m');
        } catch (Exception $e) {
            return $ym;
        }
    }

    /** Returns last calendar day of a YYYY-MM period as Y-m-d. */
    public static function ym_last_day(string $ym): string {
        $ym = trim($ym);
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $ym = self::current_ym();
        }
        try {
            $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
            $dt = new DateTime($ym . '-01 00:00:00', $tz);
            $dt->modify('last day of this month');
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return $ym . '-28';
        }
    }



    public static function is_negative_number_input($value): bool {
        if ($value === null) return false;
        $s = trim((string) $value);
        if ($s === '') return false;
        return preg_match('/^\s*-/', $s) === 1;
    }

    public static function normalize_rut(string $rut): string {
        $rut = strtoupper(trim($rut));
        $rut = preg_replace('/[^0-9K]/', '', $rut);
        return (string) $rut;
    }

    public static function format_rut(string $rut): string {
        $rut = self::normalize_rut($rut);
        if (!preg_match('/^[0-9]{7,8}[0-9K]$/', $rut)) {
            return $rut;
        }

        $body = substr($rut, 0, -1);
        $dv = substr($rut, -1);

        $rev = strrev($body);
        $parts = str_split($rev, 3);
        $body_fmt = strrev(implode('.', $parts));

        return $body_fmt . '-' . $dv;
    }

    public static function validate_rut(string $rut): bool {
        $rut = self::normalize_rut($rut);
        if ($rut === '') return true;

        if (!preg_match('/^[0-9]{7,8}[0-9K]$/', $rut)) return false;

        $body = substr($rut, 0, -1);
        $dv = substr($rut, -1);

        $sum = 0;
        $mul = 2;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += ((int) $body[$i]) * $mul;
            $mul = ($mul === 7) ? 2 : $mul + 1;
        }

        $mod = 11 - ($sum % 11);
        if ($mod === 11) {
            $expected = '0';
        } elseif ($mod === 10) {
            $expected = 'K';
        } else {
            $expected = (string) $mod;
        }

        return $dv === $expected;
    }

    public static function period_exists(string $ym, int $exclude_id = 0): bool {
        $ym = trim($ym);
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) return false;

        $ids = get_posts([
            'post_type' => 'cl_periodo',
            'post_status' => 'any',
            'numberposts' => 10,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'cl_ym',
                    'value' => $ym,
                    'compare' => '=',
                ]
            ],
        ]);

        if (!$ids) return false;
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && $id !== $exclude_id) return true;
        }
        return false;
    }



    private static function log_priority(string $level): int {
        $map = [
            'error' => 0,
            'warning' => 1,
            'info' => 2,
            'debug' => 3,
        ];
        $level = strtolower(trim($level));
        return $map[$level] ?? 2;
    }

    public static function plugin_log(string $level, string $message, array $context = []): void {
        if (!function_exists('get_option')) return;

        $settings = get_option('cl_liq_settings', []);
        $cfg = is_array($settings) ? ($settings['logging'] ?? []) : [];
        $enabled = (int) ($cfg['enabled'] ?? 0) === 1;
        if (!$enabled) return;

        $threshold = (string) ($cfg['level'] ?? 'error');
        if (self::log_priority($level) > self::log_priority($threshold)) {
            return;
        }

        $safe = [];
        foreach ($context as $k => $v) {
            $key = sanitize_text_field((string) $k);
            if (is_scalar($v) || $v === null) {
                $safe[$key] = (string) $v;
            }
        }

        $line = '[Liquidaciones CL][' . strtoupper($level) . '] ' . sanitize_text_field($message);
        if (!empty($safe)) {
            $line .= ' ' . wp_json_encode($safe);
        }
        error_log($line);
    }

    public static function ym_label(string $ym): string {
        // ym = YYYY-MM
        $parts = explode('-', $ym);
        if (count($parts) !== 2) return $ym;
        $y = $parts[0];
        $m = (int)$parts[1];
        $months = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
        $mn = $months[$m] ?? $m;
        return $mn . ' ' . $y;
    }

}
