<?php
defined('ABSPATH') || exit;

/** Consulta paginada e somente de leitura ao ERP 0.19.23+. */
final class DRJ_ERP {
    const OPTION = 'drj_erp_settings';
    const RUN = 'drj_erp_run';
    const REPORT = 'drj_erp_report';
    const CRON = 'drj_erp_sync';
    const CONTINUE_CRON = 'drj_erp_continue';
    const PAGE_SIZE = 20;

    public static function register() {
        add_filter('cron_schedules', array(__CLASS__, 'schedules'));
        add_action('init', array(__CLASS__, 'schedule'));
        add_action(self::CRON, array(__CLASS__, 'tick'));
        add_action(self::CONTINUE_CRON, array(__CLASS__, 'tick'));
        add_action('admin_post_drj_save_erp', array(__CLASS__, 'handle_save'));
        add_action('admin_post_nopriv_drj_save_erp', array(__CLASS__, 'handle_save'));
        add_action('wp_ajax_drj_sync_erp', array(__CLASS__, 'ajax_sync'));
        register_deactivation_hook(DRJ_FILE, array(__CLASS__, 'unschedule'));
    }

    public static function settings() {
        return array_merge(array('url' => '', 'username' => '', 'password' => '', 'company' => '', 'ra_path' => '', 'created_since' => '', 'enabled' => false), (array) get_option(self::OPTION, array()));
    }

    private static function lock_name() {
        return 'drj_erp_' . substr(hash('sha256', DRJ_Database::table()), 0, 40);
    }

    private static function lock() {
        global $wpdb;
        return (string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', self::lock_name())) === '1';
    }

    private static function unlock() {
        global $wpdb;
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::lock_name()));
    }

    /** A senha precisa ser recuperável para Basic Auth; só é armazenada cifrada. */
    private static function encrypt($password) {
        if (!function_exists('openssl_encrypt')) return new WP_Error('drj_cifra_indisponivel', 'A integração exige a extensão OpenSSL do PHP.');
        $iv = random_bytes(12);
        $tag = '';
        $data = openssl_encrypt($password, 'aes-256-gcm', hash('sha256', wp_salt('secure_auth') . '|drj-erp', true), OPENSSL_RAW_DATA, $iv, $tag);
        if ($data === false) return new WP_Error('drj_cifra_indisponivel', 'Não foi possível proteger a senha de aplicativo.');
        return base64_encode($iv) . '.' . base64_encode($tag) . '.' . base64_encode($data);
    }

    private static function decrypt($value) {
        if (!function_exists('openssl_decrypt') || !is_string($value)) return false;
        $parts = explode('.', $value);
        if (count($parts) !== 3) return false;
        $iv = base64_decode($parts[0], true); $tag = base64_decode($parts[1], true); $data = base64_decode($parts[2], true);
        if ($iv === false || strlen($iv) !== 12 || $tag === false || strlen($tag) !== 16 || $data === false) return false;
        return openssl_decrypt($data, 'aes-256-gcm', hash('sha256', wp_salt('secure_auth') . '|drj-erp', true), OPENSSL_RAW_DATA, $iv, $tag);
    }

    public static function save($input) {
        if (!current_user_can('manage_options')) return new WP_Error('drj_sem_permissao', 'Somente administradores podem configurar a integração.');
        if (!is_array($input)) return new WP_Error('drj_config_invalida', 'Configuração inválida.');
        $config = self::settings();
        $next = array();
        foreach (array('url', 'username', 'company', 'ra_path', 'created_since', 'password') as $key) {
            if (isset($input[$key]) && !is_string($input[$key])) return new WP_Error('drj_config_invalida', 'Preencha os campos da integração com texto.');
            $next[$key] = trim($input[$key] ?? '');
        }
        if ($next['company'] !== '' && !preg_match('/^[1-9][0-9]{0,17}$/D', $next['company'])) return new WP_Error('drj_config_invalida', 'Informe o código da coligada autorizada.');
        if ($next['ra_path'] !== '' && (!preg_match('/^[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)*$/D', $next['ra_path']) || strlen($next['ra_path']) > 200)) return new WP_Error('drj_config_invalida', 'Use o caminho do campo de RA, por exemplo dados.campos.ra_aluno.');
        if ($next['created_since'] !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $next['created_since']);
            if (!$date || $date->format('Y-m-d') !== $next['created_since']) return new WP_Error('drj_config_invalida', 'Informe uma data inicial válida.');
        }
        if (strlen($next['username']) > 190 || preg_match('/[:\x00-\x1f\x7f]/', $next['username'])) return new WP_Error('drj_config_invalida', 'Nome de usuário da API inválido.');
        if ($next['url'] !== '') {
            $url = wp_parse_url($next['url']);
            $query = array();
            if (!empty($url['query'])) parse_str($url['query'], $query);
            $path = isset($query['rest_route']) && is_string($query['rest_route']) ? $query['rest_route'] : ($url['path'] ?? '');
            if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['fragment']) || array_diff(array_keys($query), array('rest_route', 'codcoligada')) || !preg_match('~/erp-educacional/v1/integracao/requerimentos/?$~D', $path)) {
                return new WP_Error('drj_config_invalida', 'Cole a URL HTTPS de consulta /erp-educacional/v1/integracao/requerimentos, sem credenciais.');
            }
            $next['url'] = esc_url_raw(remove_query_arg('codcoligada', $next['url']), array('https'));
        }
        if (!empty($input['clear_password'])) {
            $next['password'] = '';
        } elseif ($next['password'] === '') {
            // Um novo destino/usuário exige nova credencial, evitando repassar a anterior.
            if ($next['url'] !== $config['url'] || $next['username'] !== $config['username']) $config['password'] = '';
            $next['password'] = $config['password'];
        } else {
            if (strlen($next['password']) > 512 || preg_match('/[\x00-\x1f\x7f]/', $next['password'])) return new WP_Error('drj_config_invalida', 'Senha de aplicativo inválida.');
            $encrypted = self::encrypt($next['password']);
            if (is_wp_error($encrypted)) return $encrypted;
            $next['password'] = $encrypted;
        }
        $next['enabled'] = !empty($input['enabled']);
        if ($next['enabled'] && (!$next['url'] || !$next['company'] || !$next['username'] || !$next['password'])) return new WP_Error('drj_config_invalida', 'Para ativar a consulta automática, informe URL, coligada, usuário e senha de aplicativo.');
        if (!self::lock()) return new WP_Error('drj_em_andamento', 'Há uma sincronização em andamento. Aguarde e tente salvar novamente.');
        try {
            if ($next !== self::settings()) {
                $saved = update_option(self::OPTION, $next, false);
                if (!$saved && get_option(self::OPTION) !== $next) return new WP_Error('drj_falha_configuracao', 'Não foi possível salvar a configuração.');
                delete_option(self::RUN);
                delete_option(self::REPORT);
                delete_option('drj_erp_checkpoint');
                delete_option('drj_erp_full_sync');
                self::unschedule();
            }
        } finally {
            self::unlock();
        }
        self::schedule();
        return true;
    }

    public static function handle_save() {
        if (!current_user_can('manage_options')) wp_die('Acesso restrito.', '', array('response' => 403));
        check_admin_referer('drj_save_erp');
        $input = isset($_POST['drj_erp']) && is_array($_POST['drj_erp']) ? wp_unslash($_POST['drj_erp']) : array();
        $result = self::save($input);
        if (is_wp_error($result)) set_transient('drj_erp_error_' . get_current_user_id(), $result->get_error_message(), 60);
        wp_safe_redirect(admin_url('admin.php?page=drj-configuracoes&drj_erp_saved=' . (is_wp_error($result) ? '0' : '1') . '#integracao-erp'));
        exit;
    }

    public static function schedules($schedules) {
        $schedules['drj_quarter_hour'] = array('interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'A cada 15 minutos (Rematrículas)');
        return $schedules;
    }

    public static function schedule() {
        $settings = self::settings();
        if ($settings['enabled'] && !wp_next_scheduled(self::CRON)) wp_schedule_event(time() + 60, 'drj_quarter_hour', self::CRON);
    }

    public static function unschedule() {
        wp_clear_scheduled_hook(self::CRON);
        wp_clear_scheduled_hook(self::CONTINUE_CRON);
    }

    public static function tick() {
        if (!self::settings()['enabled']) return;
        $result = self::sync_page();
        if (!is_wp_error($result) && !$result['done'] && !wp_next_scheduled(self::CONTINUE_CRON)) wp_schedule_single_event(time() + 15, self::CONTINUE_CRON);
    }

    public static function ajax_sync() {
        if (!current_user_can('manage_options')) wp_send_json_error(array('msg' => 'Acesso restrito.'), 403);
        check_ajax_referer('drj_sync_erp', 'nonce');
        $result = self::sync_page(isset($_POST['full']) && $_POST['full'] === '1');
        if (is_wp_error($result)) wp_send_json_error(array('msg' => $result->get_error_message()), 400);
        wp_send_json_success($result);
    }

    private static function counts() {
        return array_fill_keys(array('lidos', 'confirmado', 'ja_confirmado', 'arquivado', 'nao_encontrado', 'sem_ra', 'rejeitado', 'anterior_ao_periodo', 'ra_duplicado', 'versao_antiga', 'conflito_requerimento', 'invalidos'), 0);
    }

    private static function greater($a, $b) {
        return strlen($a) !== strlen($b) ? strlen($a) > strlen($b) : strcmp($a, $b) > 0;
    }

    public static function sync_page($full = false) {
        if (!DRJ_Database::ready()) return new WP_Error('drj_banco_pendente', 'Atualize o banco do painel antes de sincronizar.');
        if (!self::lock()) return new WP_Error('drj_em_andamento', 'Sincronização em andamento. Tente novamente em alguns segundos.');
        try {
            $settings = self::settings();
            $password = self::decrypt($settings['password']);
            if (!$settings['url'] || !$settings['company'] || !$settings['username'] || !$password) return self::failure('Configure a URL, coligada, usuário e senha de aplicativo do ERP. Se as chaves do WordPress mudaram, cadastre a senha novamente.', 'drj_erp_nao_configurado');
            $run = get_option(self::RUN, false);
            if ($full || !is_array($run)) {
                $checkpoint = (int) get_option('drj_erp_checkpoint', 0);
                $full = $full || !$checkpoint || (int) get_option('drj_erp_full_sync', 0) < time() - DAY_IN_SECONDS;
                $run = array('cursor' => '0', 'until' => gmdate('Y-m-d\TH:i:s\Z'), 'since' => $full ? '' : gmdate('Y-m-d\TH:i:s\Z', max(0, $checkpoint - 120)), 'full' => $full, 'totals' => self::counts(), 'warnings' => array());
            }
            $run['totals'] = array_merge(self::counts(), $run['totals']);
            $args = array('cursor' => $run['cursor'], 'limit' => self::PAGE_SIZE, 'status' => 'todos', 'codcoligada' => $settings['company'], 'atualizado_ate' => $run['until']);
            if ($run['since'] !== '') $args['atualizado_desde'] = $run['since'];
            $response = wp_safe_remote_get(add_query_arg($args, $settings['url']), array(
                'timeout' => 15, 'redirection' => 0, 'sslverify' => true, 'limit_response_size' => 4 * 1024 * 1024,
                'headers' => array('Accept' => 'application/json', 'Authorization' => 'Basic ' . base64_encode($settings['username'] . ':' . $password)),
            ));
            if (is_wp_error($response)) return self::failure('Não foi possível consultar o ERP por HTTPS. Verifique o endereço e a conexão.');
            $http = wp_remote_retrieve_response_code($response);
            if ($http !== 200) return self::failure('O ERP respondeu HTTP ' . (int) $http . '. Verifique a URL, a conta de leitura, a senha de aplicativo e a coligada autorizada.');
            $body = json_decode(wp_remote_retrieve_body($response), true, 64, JSON_BIGINT_AS_STRING);
            $validation = self::validate_page($body, $settings['company'], $run['cursor']);
            if (is_wp_error($validation)) return self::failure($validation->get_error_message());
            foreach ($body['items'] as $item) {
                $result = DRJ_Rematriculas::erp_item($item, $settings['company'], $settings['ra_path'], $settings['created_since']);
                $run['totals']['lidos']++;
                if (is_wp_error($result)) {
                    $code = $result->get_error_code();
                    $error_data = $result->get_error_data();
                    if (($error_data['status'] ?? 500) >= 500) return self::failure($result->get_error_message());
                    $outcome = array('drj_aluno_nao_encontrado' => 'nao_encontrado', 'drj_ra_duplicado' => 'ra_duplicado', 'drj_requerimento_ra_alterado' => 'conflito_requerimento')[$code] ?? 'invalidos';
                } else {
                    $outcome = $result['situacao'];
                }
                $run['totals'][$outcome]++;
                if (in_array($outcome, array('nao_encontrado', 'sem_ra', 'ra_duplicado', 'conflito_requerimento', 'invalidos'), true) && count($run['warnings']) < 10) {
                    $run['warnings'][] = array('idrequerimento' => $item['idrequerimento'], 'situacao' => $outcome);
                }
            }
            $done = $body['next_cursor'] === null;
            $report = array('time' => time(), 'done' => $done, 'totals' => $run['totals'], 'warnings' => $run['warnings'], 'error' => '');
            if ($done) {
                // Nunca avança a janela em falha de rede, contrato ou gravação.
                update_option('drj_erp_checkpoint', strtotime($run['until']), false);
                if ($run['full']) update_option('drj_erp_full_sync', time(), false);
                delete_option(self::RUN);
            } else {
                $run['cursor'] = $body['next_cursor'];
                update_option(self::RUN, $run, false);
            }
            update_option(self::REPORT, $report, false);
            return $report;
        } catch (Throwable $error) {
            // Não grava respostas, cabeçalhos, senha ou dados pessoais nos diagnósticos.
            return self::failure('Falha ao sincronizar. A rodada poderá ser repetida sem duplicar confirmações.');
        } finally {
            self::unlock();
        }
    }

    private static function failure($message, $code = 'drj_erp_falha') {
        $report = (array) get_option(self::REPORT, array());
        $report['time'] = time(); $report['error'] = $message;
        update_option(self::REPORT, $report, false);
        DRJ_Integration_Log::failure('erp', $code, $message, 503, self::settings()['company']);
        return new WP_Error('drj_erp_falha', $message);
    }

    private static function validate_page($body, $company, $cursor) {
        $error = new WP_Error('drj_erp_resposta_invalida', 'A resposta do ERP não corresponde ao contrato 1.0 da API 0.19.23 ou à coligada configurada.');
        if (!is_array($body) || ($body['schema_version'] ?? '') !== '1.0' || ($body['codcoligada'] ?? '') !== $company || !isset($body['items']) || !is_array($body['items']) || count($body['items']) > self::PAGE_SIZE || !array_key_exists('next_cursor', $body)) return $error;
        if ($body['items'] && array_keys($body['items']) !== range(0, count($body['items']) - 1)) return $error;
        $previous = $cursor;
        foreach ($body['items'] as $item) {
            if (!is_array($item) || ($item['codcoligada'] ?? '') !== $company || !isset($item['idrequerimento']) || !is_string($item['idrequerimento']) || !preg_match('/^[1-9][0-9]{0,17}$/D', $item['idrequerimento']) || !self::greater($item['idrequerimento'], $previous)) return $error;
            $previous = $item['idrequerimento'];
        }
        $next = $body['next_cursor'];
        if ($next !== null && (!is_string($next) || !$body['items'] || $next !== $previous)) return $error;
        return true;
    }
}
