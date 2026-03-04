<?php
/**
 * Plugin Name: Liquidaciones CL
 * Description: Genera liquidaciones de sueldo (Chile) con cálculos automáticos y PDF. Incluye empleados, períodos y liquidaciones.
 * Version: 1.3.1
 * Author: Rocket Solutions
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * Text Domain: liquidaciones-cl
 */

if ( ! defined('ABSPATH') ) { exit; }

define('CL_LIQ_VERSION', '1.3.1');
define('CL_LIQ_PATH', plugin_dir_path(__FILE__));
define('CL_LIQ_URL', plugin_dir_url(__FILE__));

require_once CL_LIQ_PATH . 'includes/helpers.php';
require_once CL_LIQ_PATH . 'includes/class-settings.php';
require_once CL_LIQ_PATH . 'includes/class-cpt.php';
require_once CL_LIQ_PATH . 'includes/class-meta-boxes.php';
require_once CL_LIQ_PATH . 'includes/class-calculator.php';
require_once CL_LIQ_PATH . 'includes/class-pdf.php';
require_once CL_LIQ_PATH . 'includes/class-updater.php';
require_once CL_LIQ_PATH . 'includes/class-frontend.php';

register_activation_hook(__FILE__, function() {
    CL_LIQ_Settings::activate();
    CL_LIQ_Frontend::activate();
    CL_LIQ_Updater::activate();
});

add_action('plugins_loaded', function() {
    CL_LIQ_Settings::init();
    CL_LIQ_CPT::init();
    CL_LIQ_Meta_Boxes::init();
    CL_LIQ_PDF::init();
    CL_LIQ_Updater::init();
    CL_LIQ_Frontend::init();
});

