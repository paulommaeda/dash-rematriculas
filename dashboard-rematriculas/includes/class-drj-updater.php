<?php
defined('ABSPATH') || exit;

/** Integra os pacotes publicados no GitHub ao atualizador nativo do WordPress. */
final class DRJ_Updater {
    const REPOSITORY = 'https://github.com/paulommaeda/dash-rematriculas';
    const RAW = 'https://raw.githubusercontent.com/paulommaeda/dash-rematriculas/main/';
    const MANIFEST = self::RAW . 'update.json';
    const CACHE_KEY = 'drj_github_update_manifest';

    public static function register() {
        add_filter('update_plugins_github.com', array(__CLASS__, 'update'), 10, 4);
        add_filter('plugins_api', array(__CLASS__, 'plugin_info'), 10, 3);
        add_filter('upgrader_pre_download', array(__CLASS__, 'download'), 10, 4);
        add_action('admin_post_drj_check_updates', array(__CLASS__, 'check_now'));
        add_action('upgrader_process_complete', array(__CLASS__, 'clear_after_upgrade'), 10, 2);
        add_filter('plugin_action_links_' . plugin_basename(DRJ_FILE), array(__CLASS__, 'links'));
    }

    public static function can_manage() {
        return current_user_can('update_plugins') && (!is_multisite() || current_user_can('manage_network_plugins'));
    }

    public static function auto_updates_enabled() {
        return in_array(plugin_basename(DRJ_FILE), (array) get_site_option('auto_update_plugins', array()), true);
    }

    public static function set_auto_updates($enabled) {
        if (!self::can_manage()) return;
        $plugin = plugin_basename(DRJ_FILE);
        $plugins = array_values(array_diff((array) get_site_option('auto_update_plugins', array()), array($plugin)));
        if ($enabled) $plugins[] = $plugin;
        update_site_option('auto_update_plugins', $plugins);
    }

    public static function manifest($force = false) {
        if (!$force) {
            $cached = get_site_transient(self::CACHE_KEY);
            if (is_array($cached)) return $cached;
            if (get_site_transient('drj_update_backoff')) return false;
        }
        $response = wp_safe_remote_get(self::MANIFEST, array(
            'timeout' => 10, 'redirection' => 3, 'limit_response_size' => 65536,
            'headers' => array('Accept' => 'application/json', 'User-Agent' => 'Dashboard-Rematriculas/' . DRJ_VERSION),
        ));
        $error = '';
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $error = 'Não foi possível consultar o GitHub. Tente novamente mais tarde.';
        } else {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!self::valid_manifest($data)) {
                $error = 'Os dados da versão publicada são inválidos. Nenhuma atualização foi oferecida.';
            }
        }
        update_site_option('drj_last_update_check', array('time' => time(), 'error' => $error));
        if ($error) {
            set_site_transient('drj_update_backoff', 1, 5 * MINUTE_IN_SECONDS);
            return false;
        }
        delete_site_transient('drj_update_backoff');
        set_site_transient(self::CACHE_KEY, $data, 6 * HOUR_IN_SECONDS);
        self::remember_package($data);
        return $data;
    }

    private static function valid_manifest($data) {
        if (!is_array($data)) return false;
        foreach (array('version', 'download_url', 'sha256', 'requires', 'requires_php', 'tested') as $key) {
            if (!isset($data[$key]) || !is_string($data[$key])) return false;
        }
        // Somente versões estáveis e o pacote exato deste repositório são aceitos.
        if (!preg_match('/^\d+\.\d+\.\d+$/D', $data['version']) || !preg_match('/^[a-f0-9]{64}$/D', $data['sha256'])) return false;
        if ($data['download_url'] !== self::RAW . 'dist/dashboard-rematriculas-' . $data['version'] . '.zip') return false;
        foreach (array('requires', 'requires_php', 'tested') as $key) {
            if (!preg_match('/^\d+\.\d+(?:\.\d+)?$/D', $data[$key])) return false;
        }
        return true;
    }

    private static function remember_package($data) {
        set_site_transient('drj_package_' . hash('sha256', $data['download_url']), array('sha256' => $data['sha256'], 'version' => $data['version']), 7 * DAY_IN_SECONDS);
    }

    public static function update($update, $plugin_data, $plugin_file, $locales) {
        if ($plugin_file !== plugin_basename(DRJ_FILE)) return $update;
        $data = self::manifest();
        if (!$data) return $update;
        self::remember_package($data);
        return array(
            'id' => self::REPOSITORY, 'slug' => 'dashboard-rematriculas',
            'version' => $data['version'], 'url' => self::REPOSITORY,
            'package' => $data['download_url'], 'requires' => $data['requires'],
            'requires_php' => $data['requires_php'], 'tested' => $data['tested'],
            // A decisão automática permanece na opção nativa escolhida pelo administrador.
        );
    }

    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'dashboard-rematriculas') return $result;
        $data = self::manifest();
        if (!$data) return $result;
        return (object) array(
            'name' => 'Dashboard de Rematrículas Journey', 'slug' => 'dashboard-rematriculas',
            'version' => $data['version'], 'author' => 'Paulo Maeda',
            'homepage' => self::REPOSITORY, 'requires' => $data['requires'],
            'requires_php' => $data['requires_php'], 'tested' => $data['tested'],
            'download_link' => $data['download_url'], 'external' => true,
            'sections' => array('description' => 'Painel de rematrículas, conferências e arquivamento reversível de desistências.', 'changelog' => wpautop(esc_html(isset($data['changelog']) && is_string($data['changelog']) ? $data['changelog'] : 'Consulte o histórico de versões no repositório.'))),
        );
    }

    public static function download($reply, $package, $upgrader, $hook_extra) {
        $plugin = $hook_extra['plugin'] ?? '';
        $ours = strpos((string) $package, self::RAW . 'dist/dashboard-rematriculas-') === 0;
        if ($plugin && $plugin !== plugin_basename(DRJ_FILE)) return $reply;
        if (!$ours) {
            return $plugin === plugin_basename(DRJ_FILE) ? new WP_Error('drj_invalid_package', 'O pacote não pertence ao repositório de atualizações do plugin.') : $reply;
        }
        if (is_wp_error($reply)) return $reply;
        $expected = get_site_transient('drj_package_' . hash('sha256', $package));
        if (!is_array($expected)) {
            $data = self::manifest(true);
            if ($data && $data['download_url'] === $package) {
                $expected = array('sha256' => $data['sha256']);
            }
        }
        if (!is_array($expected) || empty($expected['sha256'])) {
            return new WP_Error('drj_missing_checksum', 'Verifique novamente as atualizações antes de instalar esta versão.');
        }
        if (!function_exists('download_url')) require_once ABSPATH . 'wp-admin/includes/file.php';
        $file = $reply === false ? download_url($package, 120) : $reply;
        if (is_wp_error($file)) return $file;
        $checksum = is_string($file) && is_file($file) ? hash_file('sha256', $file) : false;
        if (!$checksum || !hash_equals($expected['sha256'], $checksum)) {
            if (is_string($file) && is_file($file)) wp_delete_file($file);
            return new WP_Error('drj_checksum_mismatch', 'O arquivo recebido está incompleto ou foi alterado. A atualização foi cancelada.');
        }
        return $file;
    }

    public static function check_now() {
        if (!self::can_manage()) wp_die('Você não tem permissão para verificar atualizações.');
        check_admin_referer('drj_check_updates');
        delete_site_transient(self::CACHE_KEY);
        delete_site_transient('drj_update_backoff');
        self::manifest(true);
        delete_site_transient('update_plugins');
        wp_update_plugins();
        wp_safe_redirect(admin_url('admin.php?page=drj-configuracoes&drj_update_checked=1'));
        exit;
    }

    public static function clear_after_upgrade($upgrader, $options) {
        if (($options['type'] ?? '') !== 'plugin' || ($options['action'] ?? '') !== 'update') return;
        $plugins = isset($options['plugins']) ? (array) $options['plugins'] : array($options['plugin'] ?? '');
        if (in_array(plugin_basename(DRJ_FILE), $plugins, true)) {
            delete_site_transient(self::CACHE_KEY);
            delete_site_transient('drj_update_backoff');
        }
    }

    public static function links($links) {
        array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=drj-configuracoes')) . '">Configurações</a>');
        return $links;
    }
}
