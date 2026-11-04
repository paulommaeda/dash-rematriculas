<?php
defined('ABSPATH') || exit;

final class DRJ_Auth {
    private static $login_error = '';

    public static function migrate_credentials() {
        // Preserva configurações da 4.0. Instalações sem credenciais devem
        // configurá-las pelo administrador; não há senha padrão no código público.
        $pending = (array) get_option('drj_credentials_setup_required', array());
        foreach (array('drj_password_hash' => 'password', 'drj_api_secret_hash' => 'api') as $option => $key) {
            if (!get_option($option)) {
                add_option($option, wp_hash_password(wp_generate_password(64, true, true)), '', false);
                $pending[$key] = true;
            }
        }
        if ($pending) {
            update_option('drj_credentials_setup_required', $pending, false);
        }
    }

    public static function setup_notice() {
        $pending = (array) get_option('drj_credentials_setup_required', array());
        if (defined('DRJ_DASHBOARD_PASSWORD')) unset($pending['password']);
        if (defined('DRJ_API_SECRET')) unset($pending['api']);
        if (current_user_can('manage_options') && $pending) {
            echo '<div class="notice notice-warning"><p>Dashboard de Rematrículas: configure a senha do painel e a chave da API em <a href="' . esc_url(admin_url('admin.php?page=drj-configuracoes')) . '">Configurações</a>. O acesso pela conta de administrador continua disponível.</p></div>';
        }
    }

    public static function cookie_name() {
        return 'drj_session_' . substr(hash('sha256', home_url('/')), 0, 12);
    }

    private static function credential_hash() {
        return defined('DRJ_DASHBOARD_PASSWORD') ? hash('sha256', DRJ_DASHBOARD_PASSWORD) : (string) get_option('drj_password_hash', '');
    }

    private static function session() {
        $raw = isset($_COOKIE[self::cookie_name()]) && is_string($_COOKIE[self::cookie_name()]) ? wp_unslash($_COOKIE[self::cookie_name()]) : '';
        $parts = explode('.', $raw);
        if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], wp_salt('auth')), $parts[1])) {
            return false;
        }
        $data = json_decode(base64_decode($parts[0], true), true);
        if (!is_array($data) || !isset($data['expires'], $data['token'], $data['revision']) || !is_string($data['token']) || !is_string($data['revision']) || (int) $data['expires'] <= time()) {
            return false;
        }
        if (!hash_equals(hash('sha256', self::credential_hash()), $data['revision'])) {
            return false;
        }
        return get_transient('drj_session_' . hash('sha256', $data['token'])) ? $data : false;
    }

    public static function can_access() {
        return current_user_can(apply_filters('drj_dashboard_capability', 'manage_options')) || (bool) self::session();
    }

    private static function nonce_action() {
        $session = self::session();
        return 'drj_dashboard_' . ($session ? hash('sha256', $session['token']) : 'wordpress');
    }

    public static function nonce() {
        return wp_create_nonce(self::nonce_action());
    }

    public static function verify_nonce($nonce) {
        return is_string($nonce) && wp_verify_nonce($nonce, self::nonce_action());
    }

    public static function authorize() {
        if (!self::can_access()) {
            wp_send_json_error(array('msg' => 'Sua sessão expirou. Acesse o painel novamente.'), 403);
        }
        if (!isset($_POST['nonce']) || !self::verify_nonce(wp_unslash($_POST['nonce']))) {
            wp_send_json_error(array('msg' => 'Atualize a página e tente novamente.'), 403);
        }
        if (!DRJ_Database::ready()) {
            wp_send_json_error(array('msg' => 'O banco do painel precisa ser atualizado pelo administrador.'), 503);
        }
    }

    public static function handle_request() {
        global $post;
        if ($post && has_shortcode($post->post_content, 'dash_rematriculas')) {
            self::no_cache();
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['drj_auth_action']) || !is_string($_POST['drj_auth_action'])) {
            return;
        }
        self::no_cache();
        $action = sanitize_key(wp_unslash($_POST['drj_auth_action']));
        if ($action === 'logout') {
            if (isset($_POST['nonce']) && self::verify_nonce(wp_unslash($_POST['nonce']))) {
                $session = self::session();
                if ($session) {
                    delete_transient('drj_session_' . hash('sha256', $session['token']));
                }
                self::set_cookie('', time() - DAY_IN_SECONDS);
                self::redirect_back();
            }
            return;
        }
        if ($action !== 'login') {
            return;
        }
        $nonce = isset($_POST['drj_login_nonce']) && is_string($_POST['drj_login_nonce']) ? wp_unslash($_POST['drj_login_nonce']) : '';
        if (!wp_verify_nonce($nonce, 'drj_login')) {
            self::$login_error = 'Atualize a página e tente acessar novamente.';
            return;
        }
        $rate_key = 'drj_login_' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local');
        $attempts = (int) get_transient($rate_key);
        if ($attempts >= 10) {
            self::$login_error = 'Muitas tentativas. Aguarde 15 minutos e tente novamente.';
            return;
        }
        $password = isset($_POST['drj_senha']) && is_string($_POST['drj_senha']) ? wp_unslash($_POST['drj_senha']) : '';
        $valid = defined('DRJ_DASHBOARD_PASSWORD') ? hash_equals((string) DRJ_DASHBOARD_PASSWORD, $password) : wp_check_password($password, (string) get_option('drj_password_hash', ''));
        if (!$valid) {
            set_transient($rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
            self::$login_error = 'Senha incorreta. Tente novamente.';
            return;
        }
        delete_transient($rate_key);
        $expires = time() + DAY_IN_SECONDS;
        $data = array('expires' => $expires, 'token' => wp_generate_password(40, false, false), 'revision' => hash('sha256', self::credential_hash()));
        $payload = base64_encode(wp_json_encode($data));
        set_transient('drj_session_' . hash('sha256', $data['token']), 1, DAY_IN_SECONDS);
        self::set_cookie($payload . '.' . hash_hmac('sha256', $payload, wp_salt('auth')), $expires);
        self::redirect_back();
    }

    private static function set_cookie($value, $expires) {
        setcookie(self::cookie_name(), $value, array('expires' => $expires, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax'));
    }

    private static function redirect_back() {
        wp_safe_redirect(wp_get_referer() ?: home_url('/'));
        exit;
    }

    public static function no_cache() {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        if (!headers_sent()) {
            nocache_headers();
        }
    }

    public static function login_error() {
        return self::$login_error;
    }

    public static function valid_api_secret($secret) {
        return defined('DRJ_API_SECRET') ? hash_equals((string) DRJ_API_SECRET, $secret) : wp_check_password($secret, (string) get_option('drj_api_secret_hash', ''));
    }

    public static function actor() {
        $user = wp_get_current_user();
        return $user->ID ? $user->display_name : 'Acesso pela senha do painel';
    }
}
