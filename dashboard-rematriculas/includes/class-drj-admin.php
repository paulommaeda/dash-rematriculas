<?php
defined('ABSPATH') || exit;

final class DRJ_Admin {
    public static function menu() {
        add_menu_page('Dashboard Rematrículas', 'Dash Rematrículas', 'manage_options', 'dashboard-rematriculas', array(__CLASS__, 'dashboard'), 'dashicons-chart-pie', 29);
        add_submenu_page('dashboard-rematriculas', 'Configurações', 'Configurações', 'manage_options', 'drj-configuracoes', array(__CLASS__, 'settings'));
    }

    public static function dashboard() {
        echo '<div class="wrap">' . DRJ_Dashboard::render() . '</div>';
    }

    public static function default_template() {
        return "Ação de Conferência: **[acao]**\nSetor: **[setor]**\n\nDados do Aluno:\n- **Nome:** [aluno]\n- **RA:** [ra]\n- **Turma:** [turma]";
    }

    public static function settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $saved = false;
        if (isset($_POST['drj_save_templates'])) {
            check_admin_referer('drj_salvar_config', 'drj_webhook_nonce');
            foreach (array('comercial', 'secretaria', 'financeiro', 'ti') as $sector) {
                $url = isset($_POST['webhook_' . $sector]) && is_string($_POST['webhook_' . $sector]) ? wp_unslash($_POST['webhook_' . $sector]) : '';
                $template = isset($_POST['tpl_' . $sector]) && is_string($_POST['tpl_' . $sector]) ? wp_unslash($_POST['tpl_' . $sector]) : '';
                update_option('drj_webhook_' . $sector, esc_url_raw($url, array('https', 'http')), false);
                update_option('drj_tpl_' . $sector, sanitize_textarea_field($template), false);
            }
            foreach (array('drj_password' => 'drj_password_hash', 'drj_api_secret' => 'drj_api_secret_hash') as $field => $option) {
                $constant = $field === 'drj_password' ? 'DRJ_DASHBOARD_PASSWORD' : 'DRJ_API_SECRET';
                if (!defined($constant) && isset($_POST[$field]) && is_string($_POST[$field]) && $_POST[$field] !== '') {
                    update_option($option, wp_hash_password(wp_unslash($_POST[$field])), false);
                    $pending = (array) get_option('drj_credentials_setup_required', array());
                    unset($pending[$field === 'drj_password' ? 'password' : 'api']);
                    update_option('drj_credentials_setup_required', $pending, false);
                }
            }
            if (DRJ_Updater::can_manage()) {
                DRJ_Updater::set_auto_updates(isset($_POST['drj_auto_updates']));
            }
            update_option('drj_ff_form_id', isset($_POST['drj_ff_form_id']) ? absint($_POST['drj_ff_form_id']) : 0, false);
            update_option('drj_ff_ra_field', isset($_POST['drj_ff_ra_field']) && is_string($_POST['drj_ff_ra_field']) ? sanitize_text_field(wp_unslash($_POST['drj_ff_ra_field'])) : '', false);
            $saved = true;
        }
        include DRJ_PATH . 'templates/settings.php';
    }
}
