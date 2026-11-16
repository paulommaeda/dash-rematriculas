<?php
defined('ABSPATH') || exit;

final class DRJ_REST {
    const REST_NAMESPACE = 'drj/v1';
    const MAX_BODY = 1048576;
    private static $processed;

    public static function register() {
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_filter('rest_post_dispatch', array(__CLASS__, 'private_response'), 10, 3);
    }

    public static function routes() {
        register_rest_route(self::REST_NAMESPACE, '/rematriculas', array(
            'methods' => 'POST',
            'permission_callback' => array(__CLASS__, 'permission'),
            'callback' => array(__CLASS__, 'receive'),
        ));
    }

    public static function permission($request) {
        if (!is_ssl()) return new WP_Error('drj_https_obrigatorio', 'A API de recebimento exige HTTPS.', array('status' => 403));
        // Chaves nunca são aceitas na URL, em cookies ou nos campos do aluno.
        $key = $request->get_header('x-drj-key');
        if (!is_string($key) || $key === '' || strlen($key) > 512 || !DRJ_Auth::valid_api_secret($key)) {
            return new WP_Error('drj_nao_autorizado', 'Chave da integração ausente ou inválida.', array('status' => 401));
        }
        if (!DRJ_Database::ready()) return new WP_Error('drj_banco_pendente', 'O banco do painel precisa ser atualizado.', array('status' => 503));
        return true;
    }

    public static function receive($request) {
        if (!$request->is_json_content_type()) return new WP_Error('drj_json_obrigatorio', 'Envie Content-Type: application/json.', array('status' => 415));
        if (strlen($request->get_body()) > self::MAX_BODY) return new WP_Error('drj_limite_corpo', 'O corpo excede o limite de 1 MiB.', array('status' => 413));
        $body = $request->get_json_params();
        if (!is_array($body) || !$body) return new WP_Error('drj_corpo_invalido', 'Envie um objeto JSON com ra ou um requerimento do ERP.', array('status' => 422));
        if (isset($body['schema_version']) && $body['schema_version'] !== '1.0') return new WP_Error('drj_schema_invalido', 'Versão do contrato do ERP não suportada.', array('status' => 422));
        if (array_key_exists('items', $body)) {
            if ((string) $request->get_header('idempotency-key') !== '') return new WP_Error('drj_id_lote', 'Em lotes, informe id_requerimento em cada item; não use um Idempotency-Key para todo o lote.', array('status' => 422));
            $items = $body['items'];
            if (!is_array($items) || !$items || count($items) > 50 || array_keys($items) !== range(0, count($items) - 1)) return new WP_Error('drj_lote_invalido', 'Envie de 1 a 50 itens em items.', array('status' => 422));
            $results = array();
            $errors = 0;
            foreach ($items as $index => $item) {
                $result = self::process($item);
                if (is_wp_error($result)) {
                    $errors++;
                    $data = $result->get_error_data();
                    $result = array('sucesso' => false, 'codigo' => $result->get_error_code(), 'mensagem' => $result->get_error_message(), 'http_status' => $data['status'] ?? 500);
                    if (isset($data['log_id'])) $result['log_id'] = (int) $data['log_id'];
                } else {
                    $result['sucesso'] = true;
                }
                $result['indice'] = $index;
                $results[] = $result;
            }
            self::mark($request);
            return new WP_REST_Response(array('resultados' => $results, 'erros' => $errors), $errors ? 207 : 200);
        }
        $result = self::process(array_key_exists('item', $body) ? $body['item'] : $body, (string) $request->get_header('idempotency-key'));
        self::mark($request);
        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    private static function process($item, $header_id = '') {
        if (is_array($item) && array_key_exists('idrequerimento', $item)) {
            $settings = DRJ_ERP::settings();
            if ($settings['company'] === '') {
                $error = new WP_Error('drj_coligada_nao_configurada', 'Configure a coligada autorizada nas configurações do dashboard.', array('status' => 409));
                DRJ_Integration_Log::failure('api', $error->get_error_code(), $error->get_error_message(), 409);
                return $error;
            }
            return DRJ_Rematriculas::erp_item($item, $settings['company'], $settings['ra_path'], $settings['created_since'], 'api');
        }
        return DRJ_Rematriculas::api_item($item, $header_id);
    }

    private static function mark($request) {
        if (!self::$processed) self::$processed = new SplObjectStorage();
        self::$processed->attach($request);
    }

    public static function private_response($response, $server, $request) {
        if (strpos($request->get_route(), '/' . self::REST_NAMESPACE . '/') === 0) {
            $response->header('Cache-Control', 'private, no-store, max-age=0');
            if ($response->get_status() >= 400 && (!self::$processed || !self::$processed->contains($request))) {
                $data = $response->get_data();
                // Apenas códigos/mensagens conhecidos. Nunca guarda corpo ou cabeçalhos de falhas de autenticação.
                $code = is_array($data) && isset($data['code']) ? $data['code'] : 'drj_requisicao_recusada';
                $messages = array('drj_nao_autorizado' => 'Envio recusado: chave da integração ausente ou inválida.', 'drj_https_obrigatorio' => 'Envio recusado: a API exige HTTPS.',
                    'rest_invalid_json' => 'Envio recusado: JSON inválido.', 'drj_json_obrigatorio' => 'Envio recusado: Content-Type deve ser JSON.',
                    'drj_limite_corpo' => 'Envio recusado: corpo maior que 1 MiB.');
                DRJ_Integration_Log::failure('api', $code, $messages[$code] ?? 'Requisição recusada antes de processar os itens. Confira formato, rota, contrato e configuração.', $response->get_status());
                self::mark($request);
            }
        }
        return $response;
    }
}
