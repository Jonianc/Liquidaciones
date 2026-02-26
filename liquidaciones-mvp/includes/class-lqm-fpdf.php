<?php
if (!defined('ABSPATH')) exit;

class LQM_FPDF {

    const TRANSIENT_KEY = 'lqm_fpdf_path';

    public static function init() {
        add_action('admin_notices', [__CLASS__, 'admin_notice_missing_fpdf']);
    }

    /**
     * Ensure FPDF class is loaded.
     */
    public static function ensure_loaded() {
        if (class_exists('FPDF')) return true;

        $path = get_transient(self::TRANSIENT_KEY);
        if (is_string($path) && $path && file_exists($path)) {
            require_once $path;
            return class_exists('FPDF');
        }

        $path = self::locate_fpdf();
        if ($path) {
            set_transient(self::TRANSIENT_KEY, $path, DAY_IN_SECONDS);
            require_once $path;
            return class_exists('FPDF');
        }

        return false;
    }

    /**
     * Locate an existing fpdf.php within wp-content/plugins.
     * We intentionally search only a few levels deep to avoid heavy scans.
     */
    private static function locate_fpdf() {
        if (!defined('WP_PLUGIN_DIR')) return '';

        $candidates = [];

        // Common paths in custom plugins
        $patterns = [
            WP_PLUGIN_DIR . '/*/lib/fpdf/fpdf.php',
            WP_PLUGIN_DIR . '/*/vendor/*/*/fpdf.php',
            WP_PLUGIN_DIR . '/*/vendor/*/*/*/fpdf.php',
            WP_PLUGIN_DIR . '/*/fpdf.php',
            WP_PLUGIN_DIR . '/*/includes/fpdf/fpdf.php',
            WP_PLUGIN_DIR . '/*/vendor/setasign/fpdf/fpdf.php',
            WP_PLUGIN_DIR . '/*/vendor/setasign/fpdf/src/Fpdf/Fpdf.php',
        ];

        foreach ($patterns as $p) {
            foreach (glob($p) as $hit) {
                if (is_readable($hit)) $candidates[] = $hit;
            }
        }

        // If still nothing, do a bounded directory walk (depth <= 3) for 'fpdf.php'
        if (empty($candidates)) {
            try {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(WP_PLUGIN_DIR, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($it as $file) {
                    /** @var SplFileInfo $file */
                    if ($it->getDepth() > 3) continue;
                    if ($file->isFile() && strtolower($file->getFilename()) === 'fpdf.php') {
                        $path = $file->getPathname();
                        if (is_readable($path)) {
                            $candidates[] = $path;
                            break;
                        }
                    }
                }
            } catch (Exception $e) {
                // ignore
            }
        }

        // Return first candidate
        return !empty($candidates) ? $candidates[0] : '';
    }

    public static function admin_notice_missing_fpdf() {
        if (!current_user_can('manage_options')) return;
        if (class_exists('FPDF')) return;

        // Try loading once, so we don't warn if it's actually available
        if (self::ensure_loaded()) return;

        echo '<div class="notice notice-warning"><p><strong>Liquidaciones MVP:</strong> No encontré <code>FPDF</code> en los plugins instalados. ' .
             'Deja tu FPDF donde ya lo usas (por ejemplo <code>tu-plugin/lib/fpdf/fpdf.php</code>) y recarga. ' .
             'Si cambias la ruta, borra el transient <code>' . esc_html(self::TRANSIENT_KEY) . '</code>.</p></div>';
    }
}
