<?php
defined('ABSPATH') || exit;

final class DRJ_REST {
    const REST_NAMESPACE = 'drj/v1';
    const MAX_BODY = 1048576;

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
                } else {
                    $result['sucesso'] = true;
                }
                $result['indice'] = $index;
                $results[] = $result;
            }
            return new WP_REST_Response(array('resultados' => $results, 'erros' => $errors), $errors ? 207 : 200);
        }
        $result = self::process(array_key_exists('item', $body) ? $body['item'] : $body);
        return is_wp_error($result) ? $result : new WP_REST_Response($result, 200);
    }

    private static function process($item) {
        if (!is_array($item)) return new WP_Error('drj_item_invalido', 'Cada requerimento deve ser um objeto JSON.', array('status' => 422));
        if (array_key_exists('idrequerimento', $item)) {
            $settings = DRJ_ERP::settings();
            if ($settings['company'] === '') return new WP_Error('drj_coligada_nao_configurada', 'Configure a coligada autorizada nas configurações do dashboard.', array('status' => 409));
            return DRJ_Rematriculas::erp_item($item, $settings['company'], $settings['ra_path'], $settings['created_since']);
        }
        // Campos financeiros, nome, turma, conferências e arquivamento são ignorados.
        $ra = DRJ_Rematriculas::ra($item['ra'] ?? null);
        if (is_wp_error($ra)) return $ra;
        if (array_key_exists('status', $item)) {
            if (!in_array($item['status'], array('pendente', 'aprovado', 'rejeitado'), true)) return new WP_Error('drj_status_invalido', 'Use pendente, aprovado ou rejeitado em status.', array('status' => 422));
            if ($item['status'] === 'rejeitado') return array('ra' => $ra, 'situacao' => 'rejeitado', 'alterado' => false);
        }
        $date = DRJ_Rematriculas::date($item['data_rematricula'] ?? null);
        return is_wp_error($date) ? $date : DRJ_Rematriculas::confirm($ra, $date);
    }

    public static function private_response($response, $server, $request) {
        if (strpos($request->get_route(), '/' . self::REST_NAMESPACE . '/') === 0) {
            $response->header('Cache-Control', 'private, no-store, max-age=0');
        }
        return $response;
    }
}
