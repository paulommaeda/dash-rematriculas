<?php
defined('ABSPATH') || exit;

/** Acesso financeiro independente da senha e da sessão do acompanhamento. */
final class DRJ_Finance_Auth {
    private static $error = '';

    public static function account_allowed() {
        $id = get_current_user_id();
        return $id && (current_user_can('manage_options') || in_array($id, array_map('intval', (array) get_option('drj_finance_allowed_users', array())), true));
    }

    public static function password_enabled() {
        if (defined('DRJ_FINANCE_PASSWORD')) return (string) DRJ_FINANCE_PASSWORD !== '';
        return (bool) get_option('drj_finance_password_enabled', false) && (bool) get_option('drj_finance_password_hash', '');
    }

    private static function credential_hash() {
        return defined('DRJ_FINANCE_PASSWORD') ? hash('sha256', (string) DRJ_FINANCE_PASSWORD) : (string) get_option('drj_finance_password_hash', '');
    }

    private static function revision() {
        return hash('sha256', self::credential_hash() . '|' . (string) get_option('drj_finance_auth_epoch', ''));
    }

    public static function matches_general($password) {
        return defined('DRJ_DASHBOARD_PASSWORD') ? hash_equals((string) DRJ_DASHBOARD_PASSWORD, $password) : wp_check_password($password, (string) get_option('drj_password_hash', ''));
    }

    public static function cookie_name() {
        return 'drj_finance_session_' . substr(hash('sha256', home_url('/')), 0, 12);
    }

    private static function session() {
        if (!self::password_enabled()) return false;
        $raw = isset($_COOKIE[self::cookie_name()]) && is_string($_COOKIE[self::cookie_name()]) ? wp_unslash($_COOKIE[self::cookie_name()]) : '';
        $parts = explode('.', $raw);
        if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', 'finance|' . $parts[0], wp_salt('secure_auth')), $parts[1])) return false;
        $data = json_decode(base64_decode($parts[0], true), true);
        if (!is_array($data) || !isset($data['expires'], $data['token'], $data['revision']) || !is_string($data['token']) || !is_string($data['revision']) || (int) $data['expires'] <= time()) return false;
        if (!hash_equals(self::revision(), $data['revision'])) return false;
        return get_transient('drj_finance_session_' . hash('sha256', $data['token'])) ? $data : false;
    }

    public static function has_password_session() {
        return (bool) self::session();
    }

    public static function can_access() {
        return self::account_allowed() || self::has_password_session();
    }

    private static function nonce_action() {
        $session = self::session();
        return 'drj_finance_' . ($session ? hash('sha256', $session['token']) : 'wordpress');
    }

    public static function nonce() {
        return wp_create_nonce(self::nonce_action());
    }

    public static function verify_nonce($nonce) {
        return is_string($nonce) && wp_verify_nonce($nonce, self::nonce_action());
    }

    public static function authorize() {
        if (!self::can_access()) wp_send_json_error(array('msg' => 'Acesso financeiro não autorizado. Entre com uma conta autorizada ou com a senha exclusiva do financeiro.'), 403);
        if (!isset($_POST['nonce']) || !self::verify_nonce(wp_unslash($_POST['nonce']))) wp_send_json_error(array('msg' => 'Atualize a página do financeiro e tente novamente.'), 403);
        if (!DRJ_Database::ready()) wp_send_json_error(array('msg' => 'O banco do painel precisa ser atualizado pelo administrador.'), 503);
    }

    public static function handle_request() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['drj_finance_auth_action']) || !is_string($_POST['drj_finance_auth_action'])) return;
        DRJ_Auth::no_cache();
        $action = sanitize_key(wp_unslash($_POST['drj_finance_auth_action']));
        if ($action === 'logout') {
            if (isset($_POST['nonce']) && self::verify_nonce(wp_unslash($_POST['nonce']))) {
                $session = self::session();
                if ($session) delete_transient('drj_finance_session_' . hash('sha256', $session['token']));
                self::set_cookie('', time() - DAY_IN_SECONDS);
                self::redirect_back();
            }
            return;
        }
        if ($action !== 'login' || !self::password_enabled()) return;
        $nonce = isset($_POST['drj_finance_login_nonce']) && is_string($_POST['drj_finance_login_nonce']) ? wp_unslash($_POST['drj_finance_login_nonce']) : '';
        if (!wp_verify_nonce($nonce, 'drj_finance_login')) {
            self::$error = 'Atualize a página e tente novamente.';
            return;
        }
        $rate_key = 'drj_finance_login_' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'local');
        $attempts = (int) get_transient($rate_key);
        if ($attempts >= 10) {
            self::$error = 'Muitas tentativas. Aguarde 15 minutos e tente novamente.';
            return;
        }
        $password = isset($_POST['drj_finance_password']) && is_string($_POST['drj_finance_password']) ? wp_unslash($_POST['drj_finance_password']) : '';
        $valid = defined('DRJ_FINANCE_PASSWORD') ? hash_equals((string) DRJ_FINANCE_PASSWORD, $password) : wp_check_password($password, (string) get_option('drj_finance_password_hash', ''));
        if (!$valid || self::matches_general($password)) {
            set_transient($rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
            self::$error = 'Senha do financeiro incorreta. O acesso de acompanhamento não libera esta tela.';
            return;
        }
        delete_transient($rate_key);
        $duration = 8 * HOUR_IN_SECONDS;
        $data = array('expires' => time() + $duration, 'token' => wp_generate_password(40, false, false), 'revision' => self::revision());
        $payload = base64_encode(wp_json_encode($data));
        set_transient('drj_finance_session_' . hash('sha256', $data['token']), 1, $duration);
        self::set_cookie($payload . '.' . hash_hmac('sha256', 'finance|' . $payload, wp_salt('secure_auth')), $data['expires']);
        self::redirect_back();
    }

    private static function set_cookie($value, $expires) {
        setcookie(self::cookie_name(), $value, array('expires' => $expires, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax'));
    }

    private static function redirect_back() {
        $target = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : home_url('/');
        wp_safe_redirect($target);
        exit;
    }

    public static function login_error() {
        return self::$error;
    }

    public static function settings_error($post) {
        $new_finance = isset($post['drj_new_finance_password']) && is_string($post['drj_new_finance_password']) ? wp_unslash($post['drj_new_finance_password']) : '';
        $new_general = isset($post['drj_password']) && is_string($post['drj_password']) && !defined('DRJ_DASHBOARD_PASSWORD') ? wp_unslash($post['drj_password']) : '';
        if ($new_finance !== '' && (self::matches_general($new_finance) || ($new_general !== '' && hash_equals($new_general, $new_finance)))) return 'A senha do financeiro deve ser diferente da senha de acompanhamento.';
        $same_as_finance = defined('DRJ_FINANCE_PASSWORD') ? ($new_general !== '' && hash_equals((string) DRJ_FINANCE_PASSWORD, $new_general)) : ($new_general !== '' && wp_check_password($new_general, (string) get_option('drj_finance_password_hash', '')));
        if ($same_as_finance && $new_finance === '') return 'A senha de acompanhamento deve ser diferente da senha do financeiro.';
        if (isset($post['drj_finance_password_enabled']) && !defined('DRJ_FINANCE_PASSWORD') && $new_finance === '' && !get_option('drj_finance_password_hash')) return 'Defina uma senha exclusiva antes de ativar o acesso por senha ao financeiro.';
        return '';
    }

    public static function save_settings($post) {
        if (!current_user_can('manage_options') || !isset($post['drj_finance_settings'])) return;
        if (self::settings_error($post)) return;
        if (!defined('DRJ_FINANCE_PASSWORD')) {
            if (self::password_enabled() && !isset($post['drj_finance_password_enabled'])) update_option('drj_finance_auth_epoch', wp_generate_password(32, false, false), false);
            if (isset($post['drj_new_finance_password']) && is_string($post['drj_new_finance_password']) && $post['drj_new_finance_password'] !== '') update_option('drj_finance_password_hash', wp_hash_password(wp_unslash($post['drj_new_finance_password'])), false);
            update_option('drj_finance_password_enabled', isset($post['drj_finance_password_enabled']) ? 1 : 0, false);
        }
        $ids = array();
        foreach (isset($post['drj_finance_users']) && is_array($post['drj_finance_users']) ? $post['drj_finance_users'] : array() as $value) {
            if (is_scalar($value)) {
                $id = absint($value);
                if ($id && get_userdata($id)) $ids[] = $id;
            }
        }
        update_option('drj_finance_allowed_users', array_values(array_unique($ids)), false);
    }
}
