<?php
defined('ABSPATH') || exit;

final class DRJ_Integrations {
    public static function webhook($student, $sector, $value) {
        $url = get_option('drj_webhook_' . $sector, '');
        $template = get_option('drj_tpl_' . $sector, DRJ_Admin::default_template());
        if (!$url || !$template) {
            return '';
        }
        $action = $value ? 'APROVADO' : 'DESFEITO';
        $message = str_replace(
            array('[aluno]', '[ra]', '[turma]', '[setor]', '[acao]'),
            array($student->nome_aluno, $student->ra, $student->turma, strtoupper($sector), $action),
            $template
        );
        $result = wp_safe_remote_post($url, array(
            'timeout' => 5, 'redirection' => 3,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode(array('title' => 'Conferência ' . $action . ': ' . strtoupper($sector), 'text' => str_replace("\n", "\n\n", $message))),
        ));
        if (is_wp_error($result) || wp_remote_retrieve_response_code($result) < 200 || wp_remote_retrieve_response_code($result) >= 300) {
            return 'Conferência salva. O webhook não confirmou o recebimento; verifique a integração nas configurações.';
        }
        return '';
    }

    public static function sync($cursor, $until) {
        global $wpdb;
        $forms = $wpdb->prefix . 'fluentform_submissions';
        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($forms)))) {
            throw new InvalidArgumentException('A tabela do Fluent Forms não foi encontrada neste site.');
        }
        $form_id = (int) get_option('drj_ff_form_id', 0);
        $where = $form_id ? $wpdb->prepare(' AND form_id = %d', $form_id) : '';
        if (!$until) {
            $until = (int) $wpdb->get_var("SELECT MAX(id) FROM `$forms` WHERE 1=1 $where");
        }
        $batch = $wpdb->get_results($wpdb->prepare("SELECT id, response, created_at FROM `$forms` WHERE id > %d AND id <= %d $where ORDER BY id ASC LIMIT 200", $cursor, $until));
        if ($wpdb->last_error) {
            throw new RuntimeException('Não foi possível consultar o Fluent Forms.');
        }
        $table = DRJ_Database::table();
        $pending = $wpdb->get_results("SELECT id, ra FROM `$table` WHERE arquivado = 0 AND status_rematricula = 0");
        $map = array();
        foreach ($pending ?: array() as $student) {
            $map[(string) $student->ra] = (int) $student->id;
        }
        $field = get_option('drj_ff_ra_field', '');
        $updated = 0;
        foreach ($batch ?: array() as $submission) {
            $cursor = (int) $submission->id;
            $data = json_decode($submission->response, true);
            if (!is_array($data)) {
                continue;
            }
            $values = $field !== '' ? array($data[$field] ?? null) : array_values($data);
            foreach ($values as $value) {
                if (!is_string($value) && !is_int($value)) {
                    continue;
                }
                $ra = trim((string) $value);
                if (!isset($map[$ra])) {
                    continue;
                }
                $changed = $wpdb->update($table, array('status_rematricula' => 1, 'data_rematricula' => $submission->created_at), array('id' => $map[$ra], 'arquivado' => 0, 'status_rematricula' => 0));
                if ($changed === false) {
                    throw new RuntimeException('A sincronização foi interrompida ao salvar um aluno. Tente novamente.');
                }
                $updated += (int) $changed;
                unset($map[$ra]);
                break;
            }
        }
        return array('cursor' => $cursor, 'until' => $until, 'done' => count($batch ?: array()) < 200 || $cursor >= $until, 'updated' => $updated);
    }
}
