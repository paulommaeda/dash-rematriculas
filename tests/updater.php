<?php
/** Execute somente em uma instalação descartável de testes do WordPress. */
if (PHP_SAPI !== 'cli' || getenv('DRJ_TEST_ENV') !== '1' || !getenv('DRJ_WP_BOOTSTRAP')) {
    exit("Defina DRJ_TEST_ENV=1 e DRJ_WP_BOOTSTRAP para um WordPress de testes.\n");
}
require getenv('DRJ_WP_BOOTSTRAP');
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
wp_set_current_user(1);
$total = 0;
function drj_assert($condition, $label) {
    global $total;
    if (!$condition) throw new RuntimeException('Falhou: ' . $label);
    $total++;
    echo 'OK ' . $label . PHP_EOL;
}
$release_root = dirname(__DIR__);
$original = json_decode(file_get_contents($release_root . '/update.json'), true);
$fixture = $original;
$version_parts = explode('.', DRJ_VERSION);
$next_version = $version_parts[0] . '.' . ((int) $version_parts[1] + 1) . '.0';
$fixture['version'] = $next_version;
$fixture['download_url'] = DRJ_Updater::RAW . 'dist/dashboard-rematriculas-' . $next_version . '.zip';
$package_path = $release_root . '/dist/dashboard-rematriculas-' . $original['version'] . '.zip';
$transport_error = false;
$corrupt = false;
$manifest_calls = 0;
$old_auto = get_site_option('auto_update_plugins', array());
$mock = function ($pre, $args, $url) use (&$fixture, &$manifest_calls, &$transport_error, &$corrupt, $package_path) {
    if ($url === DRJ_Updater::MANIFEST) {
        $manifest_calls++;
        if ($transport_error) return new WP_Error('offline', 'Rede indisponível');
        return array('response' => array('code' => 200, 'message' => 'OK'), 'headers' => array(), 'body' => wp_json_encode($fixture));
    }
    if (strpos($url, 'api.wordpress.org/plugins/update-check/') !== false) {
        return array('response' => array('code' => 200, 'message' => 'OK'), 'headers' => array(), 'body' => wp_json_encode(array('plugins' => array(), 'translations' => array(), 'no_update' => array())));
    }
    if (strpos($url, DRJ_Updater::RAW . 'dist/') === 0) {
        if (!empty($args['filename'])) {
            file_put_contents($args['filename'], $corrupt ? 'pacote incompleto' : file_get_contents($package_path));
        }
        return array('response' => array('code' => 200, 'message' => 'OK'), 'headers' => array(), 'body' => '');
    }
    return $pre;
};
add_filter('pre_http_request', $mock, 10, 3);
$plugin = plugin_basename(DRJ_FILE);
try {
    delete_site_transient(DRJ_Updater::CACHE_KEY);
    delete_site_transient('drj_update_backoff');
    drj_assert(DRJ_VERSION === $original['version'], 'cabeçalho e manifesto carregam a mesma versão');
    drj_assert(DRJ_Database::ready(), 'mudança de versão do plugin preserva versão do banco');
    $update = apply_filters('update_plugins_github.com', false, array(), $plugin, array('pt_BR'));
    drj_assert($update['version'] === $next_version && $update['package'] === $fixture['download_url'], 'hook nativo recebe nova versão e pacote');
    DRJ_Updater::manifest();
    drj_assert($manifest_calls === 1, 'consultas de versão usam cache');
    $foreign = array('version' => '9.9.9');
    drj_assert(DRJ_Updater::update($foreign, array(), 'outro/plugin.php', array()) === $foreign, 'hook não altera atualização de outro plugin');
    delete_site_transient('update_plugins');
    wp_update_plugins();
    $updates = get_site_transient('update_plugins');
    drj_assert(isset($updates->response[$plugin]) && $updates->response[$plugin]->new_version === $next_version, 'WordPress reconhece oferta em Painel Atualizações');
    $info = DRJ_Updater::plugin_info(false, 'plugin_information', (object) array('slug' => 'dashboard-rematriculas'));
    drj_assert($info->version === $next_version && $info->external, 'janela de detalhes apresenta versão do GitHub');
    drj_assert(DRJ_Updater::plugin_info('preservado', 'plugin_information', (object) array('slug' => 'outro')) === 'preservado', 'janela de detalhes de outro plugin permanece intacta');
    $downloaded = apply_filters('upgrader_pre_download', false, $fixture['download_url'], null, array('plugin' => $plugin));
    drj_assert(is_string($downloaded) && is_file($downloaded) && hash_file('sha256', $downloaded) === $fixture['sha256'], 'download válido passa pela verificação SHA-256');
    wp_delete_file($downloaded);
    $corrupt = true;
    $downloaded = DRJ_Updater::download(false, $fixture['download_url'], null, array('plugin' => $plugin));
    drj_assert(is_wp_error($downloaded) && $downloaded->get_error_code() === 'drj_checksum_mismatch', 'pacote alterado é recusado antes da instalação');
    $corrupt = false;
    drj_assert(is_wp_error(DRJ_Updater::download(false, 'https://example.test/pacote.zip', null, array('plugin' => $plugin))), 'origem externa não pode substituir pacote deste plugin');
    drj_assert(DRJ_Updater::download(false, 'https://example.test/pacote.zip', null, array('plugin' => 'outro/plugin.php')) === false, 'download de outro plugin não é interceptado');
    update_site_option('auto_update_plugins', array('outro/plugin.php'));
    DRJ_Updater::set_auto_updates(true);
    drj_assert(DRJ_Updater::auto_updates_enabled() && in_array('outro/plugin.php', get_site_option('auto_update_plugins'), true), 'ativar automático preserva preferências dos outros plugins');
    DRJ_Updater::set_auto_updates(false);
    drj_assert(!DRJ_Updater::auto_updates_enabled() && get_site_option('auto_update_plugins') === array('outro/plugin.php'), 'desativar automático altera somente este plugin');
    wp_set_current_user(0);
    DRJ_Updater::set_auto_updates(true);
    drj_assert(!DRJ_Updater::auto_updates_enabled(), 'usuário sem permissão não altera atualização automática');
    wp_set_current_user(1);
    $fixture['version'] = '4.0.0';
    $fixture['download_url'] = DRJ_Updater::RAW . 'dist/dashboard-rematriculas-4.0.0.zip';
    DRJ_Updater::manifest(true);
    delete_site_transient('update_plugins');
    wp_update_plugins();
    $updates = get_site_transient('update_plugins');
    drj_assert(!isset($updates->response[$plugin]) && isset($updates->no_update[$plugin]), 'WordPress não oferece downgrade');
    $fixture = $original;
    $fixture['download_url'] = 'https://example.test/arquivo.zip';
    drj_assert(DRJ_Updater::manifest(true) === false, 'manifesto com URL de terceiros é recusado');
    $fixture = $original;
    $fixture['version'] = '4.2.0-beta';
    drj_assert(DRJ_Updater::manifest(true) === false, 'pré-lançamento não é aceito no canal estável');
    $fixture = $original;
    $fixture['sha256'] = 'invalido';
    drj_assert(DRJ_Updater::manifest(true) === false, 'manifesto sem checksum válido é recusado');
    delete_site_transient(DRJ_Updater::CACHE_KEY);
    $transport_error = true;
    drj_assert(DRJ_Updater::manifest(true) === false, 'falha de rede não cria oferta de atualização');
    $calls = $manifest_calls;
    DRJ_Updater::manifest();
    drj_assert($manifest_calls === $calls, 'falha de rede aplica intervalo antes de tentar novamente');
    $transport_error = false;
    $fixture = $original;
    drj_assert(DRJ_Updater::manifest(true)['version'] === DRJ_VERSION, 'consulta manual recupera conexão depois da falha');
    $password_hash = get_option('drj_password_hash');
    $api_hash = get_option('drj_api_secret_hash');
    DRJ_Auth::migrate_credentials();
    drj_assert(get_option('drj_password_hash') === $password_hash && get_option('drj_api_secret_hash') === $api_hash, 'credenciais existentes são preservadas');
    $pending = get_option('drj_credentials_setup_required', array());
    try {
        delete_option('drj_password_hash');
        delete_option('drj_api_secret_hash');
        DRJ_Auth::migrate_credentials();
        drj_assert((bool) get_option('drj_password_hash') && (bool) get_option('drj_api_secret_hash') && !wp_check_password('senha-padrao', get_option('drj_password_hash')), 'instalação sem credenciais cria valores aleatórios');
        drj_assert(count(get_option('drj_credentials_setup_required')) === 2, 'instalação nova solicita configuração das credenciais');
    } finally {
        update_option('drj_password_hash', $password_hash, false);
        update_option('drj_api_secret_hash', $api_hash, false);
        update_option('drj_credentials_setup_required', $pending, false);
    }
    echo 'TOTAL: ' . $total . ' verificações do atualizador aprovadas.' . PHP_EOL;
} finally {
    update_site_option('auto_update_plugins', $old_auto);
    remove_filter('pre_http_request', $mock, 10);
    delete_site_transient(DRJ_Updater::CACHE_KEY);
    delete_site_transient('drj_update_backoff');
    delete_site_transient('update_plugins');
}
