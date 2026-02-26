<?php
/**
 * Plugin Name: Liquidaciones MVP (FPDF)
 * Description: MVP para crear liquidaciones simples y generar PDF usando FPDF existente.
 * Version: 0.1.0
 * Author: Rocket Solutions
 */

if (!defined('ABSPATH')) exit;

define('LQM_VER', '0.1.0');
define('LQM_PATH', plugin_dir_path(__FILE__));
define('LQM_URL', plugin_dir_url(__FILE__));

require_once LQM_PATH . 'includes/class-lqm-fpdf.php';
require_once LQM_PATH . 'includes/class-lqm-cpt.php';
require_once LQM_PATH . 'includes/class-lqm-pdf.php';

add_action('plugins_loaded', function () {
    LQM_FPDF::init();
    LQM_CPT::init();
    LQM_PDF::init();
});
