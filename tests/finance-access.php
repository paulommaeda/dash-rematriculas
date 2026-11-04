<?php
/** Execute somente em WordPress descartável com DRJ_TEST_ENV=1. */
if (PHP_SAPI !== 'cli' || getenv('DRJ_TEST_ENV') !== '1' || !getenv('DRJ_WP_BOOTSTRAP')) exit("Defina DRJ_TEST_ENV=1 e DRJ_WP_BOOTSTRAP.\n");
require getenv('DRJ_WP_BOOTSTRAP');
$total = 0;
function drj_access_check($valid, $label) {
    global $total;
    if (!$valid) throw new RuntimeException('Falhou: ' . $label);
    $total++;
    echo 'OK ' . $label . PHP_EOL;
}
$keys = array('drj_finance_allowed_users', 'drj_finance_password_enabled', 'drj_finance_password_hash', 'drj_finance_auth_epoch');
$backup = array();
foreach ($keys as $key) $backup[$key] = get_option($key);
$original_cookies = $_COOKIE;
$original_get = $_GET;
$general_token = wp_generate_password(32, false, false);
$existing = get_user_by('login', 'drj_finance_access_test');
$reader_id = $existing ? $existing->ID : wp_create_user('drj_finance_access_test', wp_generate_password(32, true), 'finance-access@example.test');
try {
    update_option('drj_finance_allowed_users', array(), false);
    update_option('drj_finance_password_enabled', 0, false);
    delete_option('drj_finance_password_hash');
    wp_set_current_user(1);
    drj_access_check(DRJ_Finance_Auth::can_access(), 'administrador mantém acesso financeiro');
    wp_set_current_user($reader_id);
    drj_access_check(!DRJ_Finance_Auth::can_access(), 'conta comum não possui acesso financeiro');
    update_option('drj_finance_allowed_users', array((int) $reader_id), false);
    drj_access_check(DRJ_Finance_Auth::account_allowed(), 'conta explicitamente autorizada recebe acesso');
    update_option('drj_finance_allowed_users', array(), false);
    drj_access_check(!DRJ_Finance_Auth::can_access(), 'remoção da conta revoga acesso imediatamente');
    wp_set_current_user(0);
    $data = array('expires' => time() + HOUR_IN_SECONDS, 'token' => $general_token, 'revision' => hash('sha256', (string) get_option('drj_password_hash')));
    $payload = base64_encode(wp_json_encode($data));
    $_COOKIE[DRJ_Auth::cookie_name()] = $payload . '.' . hash_hmac('sha256', $payload, wp_salt('auth'));
    set_transient('drj_session_' . hash('sha256', $general_token), 1, HOUR_IN_SECONDS);
    drj_access_check(DRJ_Auth::can_access(), 'sessão válida libera acompanhamento');
    drj_access_check(!DRJ_Finance_Auth::can_access(), 'mesma sessão de acompanhamento não libera financeiro');
    drj_access_check(!DRJ_Finance_Auth::verify_nonce(DRJ_Auth::nonce()), 'token de acompanhamento não vale como token financeiro');
    $_COOKIE[DRJ_Finance_Auth::cookie_name()] = $_COOKIE[DRJ_Auth::cookie_name()];
    drj_access_check(!DRJ_Finance_Auth::can_access(), 'copiar cookie de acompanhamento não cria sessão financeira');
    $_GET = array('drj_painel' => 'financeiro');
    $html = DRJ_Dashboard::shortcode();
    drj_access_check(strpos($html, 'data-drj-finance') === false && strpos($html, 'drj-panel-nav') === false, 'acompanhamento não expõe aba ou atalho de URL para financeiro');
    drj_access_check(strpos($html, 'data-financial=') === false && strpos($html, '"financeiro":{') === false, 'valores financeiros não são enviados no HTML e JSON do acompanhamento');
    $student = DRJ_Students::find(1);
    drj_access_check(!isset(DRJ_Students::dashboard_data($student)['financeiro']), 'resposta de cadastro de aluno é filtrada para acesso comum');
    drj_access_check(strpos(DRJ_Finance::shortcode(), 'drj-finance-login') !== false, 'página financeira pede autorização própria');
    drj_access_check(strpos(DRJ_Finance::render(), 'drj-finance-config') === false, 'renderizador financeiro bloqueia sessão comum');
    wp_set_current_user(1);
    drj_access_check(isset(DRJ_Students::dashboard_data($student)['financeiro']), 'conta financeira autorizada mantém edição individual');
    drj_access_check(DRJ_Finance_Auth::verify_nonce(DRJ_Finance_Auth::nonce()) !== false, 'token próprio do financeiro é válido');
    drj_access_check(!DRJ_Finance_Auth::verify_nonce(DRJ_Auth::nonce()), 'tokens também ficam separados para administrador');
    drj_access_check((bool) DRJ_Finance_Auth::settings_error(array('drj_finance_password_enabled' => 1)), 'ativar senha financeira sem definir credencial é recusado');
    $post = array('drj_finance_settings' => 1, 'drj_new_finance_password' => 'senha-financeira-somente-teste', 'drj_finance_password_enabled' => 1, 'drj_finance_users' => array($reader_id));
    DRJ_Finance_Auth::save_settings($post);
    drj_access_check(DRJ_Finance_Auth::password_enabled(), 'administrador pode ativar senha exclusiva');
    drj_access_check(wp_check_password('senha-financeira-somente-teste', get_option('drj_finance_password_hash')), 'senha financeira é armazenada como hash');
    drj_access_check((bool) DRJ_Finance_Auth::settings_error(array('drj_password' => 'senha-financeira-somente-teste')), 'senha comum não pode reutilizar senha financeira atual');
    drj_access_check((bool) DRJ_Finance_Auth::settings_error(array('drj_password' => 'iguais-somente-teste', 'drj_new_finance_password' => 'iguais-somente-teste')), 'duas senhas iguais não podem ser configuradas juntas');
    wp_set_current_user($reader_id);
    DRJ_Finance_Auth::save_settings(array('drj_finance_settings' => 1));
    drj_access_check(DRJ_Finance_Auth::password_enabled(), 'conta sem permissão não altera configurações de acesso');
    wp_set_current_user(1);
    $before = get_option('drj_finance_allowed_users');
    DRJ_Finance_Auth::save_settings(array());
    drj_access_check(get_option('drj_finance_allowed_users') === $before, 'formulário antigo não apaga autorizações financeiras');
    DRJ_Finance_Auth::save_settings(array('drj_finance_settings' => 1));
    drj_access_check(!DRJ_Finance_Auth::password_enabled() && get_option('drj_finance_auth_epoch') !== false, 'desativar senha bloqueia acesso e muda geração das sessões');
    drj_access_check(get_option('drj_finance_allowed_users') === array(), 'administrador pode retirar contas autorizadas');
    echo 'TOTAL: ' . $total . ' verificações de acesso financeiro aprovadas.' . PHP_EOL;
} finally {
    foreach ($backup as $key => $value) {
        if ($value === false) delete_option($key); else update_option($key, $value, false);
    }
    delete_transient('drj_session_' . hash('sha256', $general_token));
    $_COOKIE = $original_cookies;
    $_GET = $original_get;
}
