<?php
defined('ABSPATH') || exit;

/** Entrada comum ao Fluent Forms, REST e leitura do ERP. */
final class DRJ_Rematriculas {
    public static function ra($value) {
        if (!is_string($value) || trim($value) === '' || strlen(trim($value)) > 100 || preg_match('/[\x00-\x1f\x7f<>]/', $value)) {
            return new WP_Error('drj_ra_invalido', 'Informe o RA como texto, preservando os zeros iniciais.', array('status' => 422));
        }
        return trim($value);
    }

    public static function date($value) {
        if ($value === null || $value === '') return current_time('mysql');
        if (!is_string($value) || !preg_match('/^[1-9]\d{3}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D', $value)) {
            return new WP_Error('drj_data_invalida', 'Use uma data ISO 8601 com fuso, por exemplo 2026-11-13T13:00:00Z.', array('status' => 422));
        }
        try {
            $date = new DateTimeImmutable($value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors && ($errors['warning_count'] || $errors['error_count'])) throw new InvalidArgumentException();
            return $date->setTimezone(wp_timezone())->format('Y-m-d H:i:s');
        } catch (Throwable $error) {
            return new WP_Error('drj_data_invalida', 'Data de requerimento inválida.', array('status' => 422));
        }
    }

    /** Só altera status e data; a condição do UPDATE torna reenvios idempotentes. */
    public static function confirm($ra, $local_date) {
        global $wpdb;
        if (!DRJ_Database::ready()) return new WP_Error('drj_banco_pendente', 'O banco do painel precisa ser atualizado.', array('status' => 503));
        $ra = self::ra($ra);
        if (is_wp_error($ra)) return $ra;
        if (!is_string($local_date) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $local_date)) {
            return new WP_Error('drj_data_invalida', 'Data de requerimento inválida.', array('status' => 422));
        }
        $table = DRJ_Database::table();
        // Mantém a comparação exata do mapa de RAs do Fluent Forms, usando o índice legado.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, ra, arquivado, status_rematricula, data_rematricula FROM `$table` WHERE ra = %s AND BINARY ra = BINARY %s LIMIT 2", $ra, $ra));
        if ($wpdb->last_error) return self::database_error();
        if (!$rows) return new WP_Error('drj_aluno_nao_encontrado', 'RA não cadastrado no acompanhamento. Cadastre o aluno antes de reenviar.', array('status' => 404));
        if (count($rows) > 1) return new WP_Error('drj_ra_duplicado', 'Há mais de um cadastro com esse RA. Corrija a duplicidade no acompanhamento.', array('status' => 409));
        $student = $rows[0];
        if ((int) $student->arquivado) return self::result($student, 'arquivado', false);
        if ((int) $student->status_rematricula) return self::result($student, 'ja_confirmado', false);
        $changed = $wpdb->query($wpdb->prepare("UPDATE `$table` SET status_rematricula = 1, data_rematricula = %s WHERE id = %d AND arquivado = 0 AND status_rematricula = 0 AND ra = %s AND BINARY ra = BINARY %s", $local_date, $student->id, $ra, $ra));
        if ($changed === false) return self::database_error();
        if ($changed) {
            $student->data_rematricula = $local_date;
            return self::result($student, 'confirmado', true);
        }
        // Outro envio, arquivamento ou exclusão externa pode ter ocorrido durante a leitura.
        $student = $wpdb->get_row($wpdb->prepare("SELECT id, ra, arquivado, status_rematricula, data_rematricula FROM `$table` WHERE id = %d", $student->id));
        if ($wpdb->last_error) return self::database_error();
        if (!$student || (string) $student->ra !== $ra || (!(int) $student->arquivado && !(int) $student->status_rematricula)) return new WP_Error('drj_conflito', 'Cadastro alterado durante o recebimento. Tente novamente.', array('status' => 409));
        return self::result($student, (int) $student->arquivado ? 'arquivado' : 'ja_confirmado', false);
    }

    private static function database_error() {
        return new WP_Error('drj_falha_gravacao', 'Não foi possível registrar a rematrícula. Tente novamente.', array('status' => 503));
    }

    private static function result($student, $status, $changed) {
        return array('ra' => (string) $student->ra, 'situacao' => $status, 'alterado' => $changed, 'data_rematricula' => $student->data_rematricula ?: null);
    }

    /** Lê um caminho explícito; não tenta identificar o aluno por nome, CPF ou outros campos. */
    public static function field(array $item, $path) {
        $value = $item;
        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) return null;
            $value = $value[$key];
        }
        return $value;
    }

    /** Aceita o item documentado na API de requerimentos 0.19.23. */
    public static function erp_item($item, $company, $ra_path = '', $created_since = '') {
        if (!is_array($item) || !isset($item['codcoligada']) || !is_string($item['codcoligada']) || $item['codcoligada'] !== $company) {
            return new WP_Error('drj_coligada_invalida', 'O requerimento pertence a outra coligada ou não informa a coligada.', array('status' => 422));
        }
        foreach (array('idrequerimento', 'versao') as $key) {
            if (!isset($item[$key]) || !is_string($item[$key]) || !preg_match('/^[1-9][0-9]{0,17}$/D', $item[$key])) return new WP_Error('drj_item_invalido', 'Identificador ou versão do requerimento inválido.', array('status' => 422));
        }
        if (!isset($item['status']) || !in_array($item['status'], array('pendente', 'aprovado', 'rejeitado'), true)) return new WP_Error('drj_status_invalido', 'Situação do requerimento inválida.', array('status' => 422));
        if ($item['status'] === 'rejeitado') return array('situacao' => 'rejeitado', 'alterado' => false);
        if (!isset($item['criado_em']) || !is_string($item['criado_em'])) return new WP_Error('drj_data_invalida', 'O requerimento não informa criado_em.', array('status' => 422));
        $date = self::date($item['criado_em']);
        if (is_wp_error($date)) return $date;
        if ($created_since !== '' && substr($date, 0, 10) < $created_since) return array('situacao' => 'anterior_ao_periodo', 'alterado' => false);
        $ra = self::field($item, 'resultado.ra');
        if (($ra === null || $ra === '') && $ra_path !== '') $ra = self::field($item, $ra_path);
        if ($ra === null || $ra === '') return array('situacao' => 'sem_ra', 'alterado' => false);
        return self::confirm($ra, $date);
    }
}
