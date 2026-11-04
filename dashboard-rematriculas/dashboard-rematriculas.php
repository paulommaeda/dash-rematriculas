<?php
/**
 * Plugin Name: Dashboard de Rematrículas Journey
 * Plugin URI: https://github.com/paulommaeda/dash-rematriculas
 * Description: Painéis de rematrículas e financeiro, conferências, integração e arquivamento reversível de desistências.
 * Version: 4.4.1
 * Author: Paulo Maeda
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: dashboard-rematriculas
 * Update URI: https://github.com/paulommaeda/dash-rematriculas
 */

defined('ABSPATH') || exit;

define('DRJ_VERSION', '4.4.1');
define('DRJ_DB_VERSION', '4.0.0');
define('DRJ_FILE', __FILE__);
define('DRJ_PATH', plugin_dir_path(__FILE__));
define('DRJ_URL', plugin_dir_url(__FILE__));

foreach (array('database', 'auth', 'finance-auth', 'pricing', 'students', 'integrations', 'ajax', 'dashboard', 'finance', 'admin', 'updater') as $module) {
    require_once DRJ_PATH . 'includes/class-drj-' . $module . '.php';
}

register_activation_hook(__FILE__, array('DRJ_Database', 'upgrade'));
add_action('init', array('DRJ_Database', 'maybe_upgrade'), 5);
add_action('template_redirect', array('DRJ_Auth', 'handle_request'), 1);
add_action('template_redirect', array('DRJ_Finance_Auth', 'handle_request'), 2);
add_action('admin_menu', array('DRJ_Admin', 'menu'));
add_action('admin_notices', array('DRJ_Database', 'notice'));
add_action('wp_enqueue_scripts', array('DRJ_Dashboard', 'frontend_assets'));
add_action('admin_enqueue_scripts', array('DRJ_Dashboard', 'admin_assets'));
add_shortcode('dash_rematriculas', array('DRJ_Dashboard', 'shortcode'));
add_shortcode('dash_financeiro', array('DRJ_Finance', 'shortcode'));
DRJ_Ajax::register();
DRJ_Finance::register();
DRJ_Pricing::register();
DRJ_Updater::register();
add_action('admin_notices', array('DRJ_Auth', 'setup_notice'));

// Compatibilidade para chamadas PHP ao renderizador da versão anterior.
function drj_gerar_html_dashboard() {
    return DRJ_Dashboard::render();
}
