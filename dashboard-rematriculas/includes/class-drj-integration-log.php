<?php
defined('ABSPATH') || exit;

/** Um registro por requerimento; as leituras e decisões ficam no histórico separado. */
final class DRJ_Integration_Log {
    const CLEANUP = 'drj_integration_log_cleanup';

    public static function table($events = false) {
        global $wpdb;
        return $wpdb->prefix . ($events ? 'drj_integracao_eventos' : 'drj_integracao_requerimentos');
    }

    public static function install() {
        global $wpdb;
        $table = self::table(); $events = self::table(true); $charset = $wpdb->get_charset_collate();
        $sql = array(
            "CREATE TABLE IF NOT EXISTS `$table` (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                chave char(64) NOT NULL,
                origem varchar(16) NOT NULL,
                sistema varchar(64) NOT NULL DEFAULT '',
                coligada varchar(18) NOT NULL DEFAULT '',
                requerimento varchar(100) NOT NULL DEFAULT '',
                protocolo varchar(100) NOT NULL DEFAULT '',
                ra varchar(100) NOT NULL DEFAULT '',
                versao varchar(18) NOT NULL DEFAULT '',
                vinculo_ra varchar(100) NOT NULL DEFAULT '',
                aceito tinyint(1) NOT NULL DEFAULT 0,
                data_aceite datetime DEFAULT NULL,
                tentativas bigint unsigned NOT NULL DEFAULT 0,
                duplicados bigint unsigned NOT NULL DEFAULT 0,
                resultado varchar(20) NOT NULL DEFAULT 'lido',
                codigo varchar(80) NOT NULL DEFAULT '',
                mensagem varchar(255) NOT NULL DEFAULT '',
                primeira_leitura datetime NOT NULL,
                ultima_leitura datetime NOT NULL,
                PRIMARY KEY (id), UNIQUE KEY drj_chave (chave),
                KEY drj_ultima (ultima_leitura, id), KEY drj_resultado (origem, resultado)
            ) ENGINE=InnoDB $charset",
            "CREATE TABLE IF NOT EXISTS `$events` (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                requerimento_id bigint unsigned NOT NULL,
                canal varchar(20) NOT NULL DEFAULT '',
                ra varchar(100) NOT NULL DEFAULT '',
                versao varchar(18) NOT NULL DEFAULT '',
                resultado varchar(20) NOT NULL DEFAULT 'lido',
                codigo varchar(80) NOT NULL DEFAULT '',
                mensagem varchar(255) NOT NULL DEFAULT '',
                criado_em datetime NOT NULL,
                PRIMARY KEY (id), KEY drj_requerimento (requerimento_id, id), KEY drj_data (criado_em)
            ) ENGINE=InnoDB $charset",
        );
        foreach ($sql as $query) if ($wpdb->query($query) === false) return false;
        return true;
    }

    public static function register() {
        add_action('init', array(__CLASS__, 'schedule'), 30);
        add_action(self::CLEANUP, array(__CLASS__, 'cleanup'));
        register_deactivation_hook(DRJ_FILE, array(__CLASS__, 'unschedule'));
    }

    public static function schedule() {
        if (DRJ_Database::ready() && !wp_next_scheduled(self::CLEANUP)) wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', self::CLEANUP);
    }

    public static function unschedule() { wp_clear_scheduled_hook(self::CLEANUP); }

    public static function cleanup() {
        global $wpdb;
        $events = self::table(true);
        // A retenção atinge somente o histórico detalhado; nunca apaga a chave antirreenvio.
        $wpdb->query($wpdb->prepare("DELETE FROM `$events` WHERE criado_em < %s ORDER BY id LIMIT 5000", gmdate('Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS)));
    }

    private static function text($value, $length) {
        return is_string($value) ? mb_substr(sanitize_text_field($value), 0, $length, 'UTF-8') : '';
    }

    private static function context(array $input) {
        $context = array();
        foreach (array('origem' => 16, 'sistema' => 64, 'coligada' => 18, 'requerimento' => 100, 'protocolo' => 100, 'ra' => 100, 'versao' => 18, 'canal' => 20) as $key => $length) {
            $context[$key] = self::text($input[$key] ?? '', $length);
        }
        if (isset($input['ra']) && is_string($input['ra'])) $context['ra'] = mb_substr(trim($input['ra']), 0, 100, 'UTF-8');
        $context['processar'] = !empty($input['processar']);
        // Os componentes brutos não são truncados antes de gerar a identidade.
        if (isset($input['identidade']) && is_array($input['identidade'])) {
            $identity = $input['identidade'];
        } elseif ($context['requerimento'] !== '') {
            $identity = array($context['origem'], $context['origem'] === 'api' ? ($input['sistema'] ?? '') : '', $input['coligada'] ?? '', $input['requerimento'] ?? '');
        } else {
            $identity = array($context['origem'], $input['sistema'] ?? '', 'ra', isset($input['ra']) && is_string($input['ra']) ? trim($input['ra']) : '');
        }
        $context['chave'] = hash('sha256', wp_json_encode($identity));
        return $context;
    }

    public static function compare_version($a, $b) {
        if ($a === $b) return 0;
        return strlen($a) !== strlen($b) ? (strlen($a) > strlen($b) ? 1 : -1) : (strcmp($a, $b) > 0 ? 1 : -1);
    }

    private static function unavailable() {
        return new WP_Error('drj_log_indisponivel', 'Não foi possível registrar a leitura da integração. Tente novamente; a confirmação não será duplicada.', array('status' => 503));
    }

    /** Grava a leitura antes de executar; serializa a mesma identidade entre ERP e API. */
    public static function process(array $input, $callback, $validation_error = null) {
        global $wpdb;
        if (!DRJ_Database::ready()) return self::unavailable();
        $context = self::context($input);
        $table = self::table(); $events = self::table(true);
        $lock = 'drj_req_' . substr(hash('sha256', $table . $context['chave']), 0, 40);
        if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $lock)) !== '1') return new WP_Error('drj_requerimento_ocupado', 'Este requerimento está sendo processado. Tente novamente.', array('status' => 503));
        try {
            $now = gmdate('Y-m-d H:i:s');
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table` WHERE chave = %s", $context['chave']));
            if ($wpdb->last_error) return self::unavailable();
            if (!$row) {
                $data = array_intersect_key($context, array_flip(array('chave', 'origem', 'sistema', 'coligada', 'requerimento', 'protocolo', 'ra')));
                $data['primeira_leitura'] = $now; $data['ultima_leitura'] = $now;
                if ($wpdb->insert($table, $data) === false) return self::unavailable();
                $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table` WHERE chave = %s", $context['chave']));
                if (!$row || $wpdb->last_error) return self::unavailable();
            }
            $result = null;
            if (is_wp_error($validation_error)) {
                $result = $validation_error;
            } elseif ($context['versao'] !== '' && $row->versao !== '' && self::compare_version($context['versao'], $row->versao) < 0) {
                $result = array('situacao' => 'versao_antiga', 'alterado' => false);
            } elseif ($context['processar'] && $row->vinculo_ra !== '' && $row->vinculo_ra !== $context['ra']) {
                $result = new WP_Error('drj_requerimento_ra_alterado', 'Este requerimento já foi vinculado a outro RA. A segunda associação foi bloqueada; confira o histórico.', array('status' => 409));
            } elseif ($context['processar'] && (int) $row->aceito) {
                $students = DRJ_Database::table();
                $archived = $wpdb->get_var($wpdb->prepare("SELECT arquivado FROM `$students` WHERE ra = %s AND BINARY ra = BINARY %s LIMIT 1", $row->vinculo_ra, $row->vinculo_ra));
                $result = $wpdb->last_error ? self::unavailable() : array('ra' => $row->vinculo_ra, 'situacao' => (int) $archived ? 'arquivado' : 'ja_confirmado', 'alterado' => false, 'data_rematricula' => $row->data_aceite, 'duplicado' => true);
            }
            $update = array('ultima_leitura' => $now, 'tentativas' => (int) $row->tentativas + 1, 'ra' => $context['ra'] !== '' ? $context['ra'] : $row->ra,
                'resultado' => 'lido', 'codigo' => 'processando', 'mensagem' => 'Leitura registrada. Aguardando a conclusão do processamento.');
            if ($context['protocolo'] !== '') $update['protocolo'] = $context['protocolo'];
            if (!is_wp_error($validation_error) && $context['versao'] !== '' && ($row->versao === '' || self::compare_version($context['versao'], $row->versao) > 0)) $update['versao'] = $context['versao'];
            // A reserva persiste mesmo em queda entre confirmar o aluno e finalizar o log.
            if ($result === null && $context['processar'] && $context['ra'] !== '' && $row->vinculo_ra === '') $update['vinculo_ra'] = $context['ra'];
            if ($wpdb->update($table, $update, array('id' => $row->id)) === false) return self::unavailable();
            $attempt = array('requerimento_id' => $row->id, 'canal' => $context['canal'], 'ra' => $context['ra'], 'versao' => $context['versao'], 'resultado' => 'lido', 'codigo' => 'processando', 'mensagem' => 'Leitura registrada. Aguardando a conclusão do processamento.', 'criado_em' => $now);
            if ($wpdb->insert($events, $attempt) === false) return self::unavailable();
            $event_id = $wpdb->insert_id;
            if ($result === null) {
                try { $result = call_user_func($callback); }
                catch (Throwable $error) { $result = new WP_Error('drj_falha_processamento', 'Falha ao processar o requerimento. Reenvie para tentar novamente.', array('status' => 503)); }
            }
            $decision = self::decision($result);
            $final = array('resultado' => $decision['resultado'], 'codigo' => $decision['codigo'], 'mensagem' => $decision['mensagem']);
            $accepted = !is_wp_error($result) && in_array($result['situacao'] ?? '', array('confirmado', 'ja_confirmado'), true);
            if ($accepted) {
                $final['aceito'] = 1;
                $final['vinculo_ra'] = $result['ra'];
                $final['data_aceite'] = $row->data_aceite ?: ($result['data_rematricula'] ?? null);
            } elseif (is_wp_error($result) && !(int) $row->aceito && $row->vinculo_ra === '' && $decision['resultado'] === 'rejeitado' && $result->get_error_code() !== 'drj_requerimento_ra_alterado') {
                $final['vinculo_ra'] = '';
            }
            if ($decision['resultado'] === 'duplicado') $final['duplicados'] = (int) $row->duplicados + 1;
            if ($wpdb->update($table, $final, array('id' => $row->id)) === false || $wpdb->update($events, array_intersect_key($final, array_flip(array('resultado', 'codigo', 'mensagem'))), array('id' => $event_id)) === false) return self::unavailable();
            if (!is_wp_error($result)) $result['log_id'] = (int) $row->id;
            else $result->add_data(array_merge((array) $result->get_error_data(), array('log_id' => (int) $row->id)));
            return $result;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    private static function decision($result) {
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            return array('resultado' => ($data['status'] ?? 500) >= 500 ? 'erro' : 'rejeitado', 'codigo' => self::text($result->get_error_code(), 80), 'mensagem' => self::text($result->get_error_message(), 255));
        }
        $code = $result['situacao'] ?? 'sem_resultado';
        $messages = array(
            'confirmado' => 'Aceito. O requerimento confirmou a rematrícula; os demais dados foram preservados.',
            'ja_confirmado' => 'Reenvio evitado. A rematrícula já estava confirmada; a primeira data foi preservada.',
            'arquivado' => 'Ignorado. Aluno arquivado; nenhuma restauração ou alteração foi feita.',
            'rejeitado' => 'Rejeitado na origem. O requerimento não confirmou nem desfez a rematrícula.',
            'sem_ra' => 'Ignorado. Não foi encontrado RA no caminho configurado nem no resultado do ERP.',
            'sem_correspondencia' => 'Ignorado. Nenhum valor do formulário corresponde a um RA cadastrado.',
            'anterior_ao_periodo' => 'Ignorado. Requerimento anterior ao início da campanha configurada.',
            'versao_antiga' => 'Ignorado. Uma versão mais recente deste requerimento já foi lida.',
        );
        $outcome = $code === 'confirmado' ? 'aceito' : ($code === 'ja_confirmado' || !empty($result['duplicado']) ? 'duplicado' : ($code === 'rejeitado' ? 'rejeitado' : 'ignorado'));
        return array('resultado' => $outcome, 'codigo' => self::text($code, 80), 'mensagem' => $messages[$code] ?? 'Leitura concluída sem alteração no acompanhamento.');
    }

    /** Erros de conexão/corpo/autenticação agrupados por hora, sem ler ou guardar o payload. */
    public static function failure($origin, $code, $message, $status = 503, $company = '') {
        if (!DRJ_Database::ready()) return;
        if (in_array($code, array('drj_nao_autorizado', 'drj_https_obrigatorio'), true)) {
            $rate_key = 'drj_log_failure_' . hash('sha256', $origin . $code);
            if (get_transient($rate_key)) return;
            set_transient($rate_key, 1, MINUTE_IN_SECONDS);
        }
        $error = new WP_Error($code, $message, array('status' => $status));
        self::process(array('origem' => $origin, 'canal' => $origin === 'erp' ? 'consulta' : 'api', 'coligada' => $company,
            'identidade' => array('falha', $origin, $company, $code, gmdate('Y-m-d-H'))), function () use ($error) { return $error; }, $error);
    }

    public static function labels() { return array('lido' => 'Lido / em processamento', 'aceito' => 'Aceito', 'duplicado' => 'Reenvio evitado', 'ignorado' => 'Ignorado', 'rejeitado' => 'Rejeitado', 'erro' => 'Erro'); }
    public static function origins() { return array('erp' => 'ERP', 'api' => 'API', 'fluent_forms' => 'Fluent Forms'); }

    public static function filters($input) {
        $filters = array();
        foreach (array('origem', 'resultado', 'busca', 'desde', 'ate') as $key) $filters[$key] = isset($input[$key]) && is_string($input[$key]) ? self::text(wp_unslash($input[$key]), 100) : '';
        if (!isset(self::origins()[$filters['origem']])) $filters['origem'] = '';
        if (!isset(self::labels()[$filters['resultado']])) $filters['resultado'] = '';
        foreach (array('desde', 'ate') as $key) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key]);
            if (!$date || $date->format('Y-m-d') !== $filters[$key]) $filters[$key] = '';
        }
        $filters['pagina'] = isset($input['pagina']) && is_scalar($input['pagina']) ? max(1, min(1000000, absint($input['pagina']))) : 1;
        return $filters;
    }

    public static function listing(array $filters) {
        global $wpdb;
        if (!current_user_can('manage_options')) return new WP_Error('drj_log_sem_acesso', 'Acesso restrito.', array('status' => 403));
        if (!DRJ_Database::ready()) return self::unavailable();
        $where = array('1=1');
        foreach (array('origem', 'resultado') as $key) if ($filters[$key] !== '') $where[] = $wpdb->prepare("`$key` = %s", $filters[$key]);
        if ($filters['busca'] !== '') {
            $like = '%' . $wpdb->esc_like($filters['busca']) . '%';
            $where[] = $wpdb->prepare('(ra LIKE %s OR requerimento LIKE %s OR protocolo LIKE %s OR vinculo_ra LIKE %s)', $like, $like, $like, $like);
        }
        foreach (array('desde', 'ate') as $key) if ($filters[$key] !== '') {
            $date = new DateTimeImmutable($filters[$key] . ' 00:00:00', wp_timezone());
            if ($key === 'ate') $date = $date->modify('+1 day');
            $utc = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $where[] = $wpdb->prepare('ultima_leitura ' . ($key === 'desde' ? '>=' : '<') . ' %s', $utc);
        }
        $table = self::table(); $where = implode(' AND ', $where);
        $summary = $wpdb->get_row("SELECT COUNT(*) AS lidos, COALESCE(SUM(aceito),0) AS aceitos, COALESCE(SUM(tentativas),0) AS leituras, COALESCE(SUM(duplicados),0) AS duplicados, COALESCE(SUM(resultado IN ('rejeitado','erro')),0) AS pendencias FROM `$table` WHERE $where", ARRAY_A);
        if ($wpdb->last_error) return self::unavailable();
        $pages = max(1, (int) ceil((int) $summary['lidos'] / 50)); $page = min($pages, $filters['pagina']);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM `$table` WHERE $where ORDER BY ultima_leitura DESC, id DESC LIMIT 50 OFFSET %d", ($page - 1) * 50));
        if ($wpdb->last_error) return self::unavailable();
        return array('summary' => $summary, 'rows' => $rows ?: array(), 'page' => $page, 'pages' => $pages);
    }

    public static function details($id) {
        global $wpdb;
        if (!current_user_can('manage_options')) return new WP_Error('drj_log_sem_acesso', 'Acesso restrito.', array('status' => 403));
        $table = self::table(); $events = self::table(true);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table` WHERE id = %d", $id));
        if (!$row) return new WP_Error('drj_log_nao_encontrado', 'Registro não encontrado.');
        return array('row' => $row, 'events' => $wpdb->get_results($wpdb->prepare("SELECT * FROM `$events` WHERE requerimento_id = %d ORDER BY id DESC LIMIT 50", $id)) ?: array());
    }

    public static function date_label($utc) { return $utc ? wp_date('d/m/Y H:i:s', strtotime($utc . ' UTC')) : '—'; }

    public static function admin() {
        if (!current_user_can('manage_options')) wp_die('Acesso restrito.', '', array('response' => 403));
        $filters = self::filters($_GET);
        $listing = self::listing($filters);
        $details = isset($_GET['detalhes']) && is_scalar($_GET['detalhes']) && DRJ_Database::ready() ? self::details(absint($_GET['detalhes'])) : null;
        include DRJ_PATH . 'templates/integration-log.php';
    }
}
