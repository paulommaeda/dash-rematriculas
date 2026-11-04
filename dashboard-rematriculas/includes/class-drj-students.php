<?php
defined('ABSPATH') || exit;

final class DRJ_Students {
    public static function all($view = 'ativos') {
        global $wpdb;
        $table = DRJ_Database::table();
        $where = $view === 'arquivados' ? 'WHERE arquivado = 1' : ($view === 'todos' ? '' : 'WHERE arquivado = 0');
        $rows = $wpdb->get_results("SELECT * FROM `$table` $where ORDER BY nome_aluno ASC, id ASC");
        if ($wpdb->last_error) {
            throw new RuntimeException('Não foi possível carregar os alunos.');
        }
        return $rows ?: array();
    }

    public static function archived_count() {
        global $wpdb;
        $table = DRJ_Database::table();
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE arquivado = 1");
    }

    public static function find($id = 0, $ra = '', $lock = false) {
        global $wpdb;
        $table = DRJ_Database::table();
        $query = $id ? $wpdb->prepare("SELECT * FROM `$table` WHERE id = %d", $id) : $wpdb->prepare("SELECT * FROM `$table` WHERE ra = %s", $ra);
        $rows = $wpdb->get_results($query . ' LIMIT 2' . ($lock ? ' FOR UPDATE' : ''));
        if (count($rows ?: array()) > 1) {
            throw new InvalidArgumentException('Há mais de um aluno com esse RA. Utilize o identificador do aluno.');
        }
        if (!$rows) {
            throw new InvalidArgumentException('Aluno não encontrado. Atualize o painel.');
        }
        return $rows[0];
    }

    public static function ensure_active($student) {
        if ((int) $student->arquivado === 1) {
            throw new InvalidArgumentException('Este aluno está arquivado. Restaure-o para editar os dados.');
        }
    }

    public static function update($student, array $data) {
        global $wpdb;
        $where = array('id' => (int) $student->id);
        if (!array_key_exists('arquivado', $data)) {
            self::ensure_active($student);
            $where['arquivado'] = 0;
        }
        $result = $wpdb->update(DRJ_Database::table(), $data, $where);
        if ($result === false) {
            throw new RuntimeException('Não foi possível salvar os dados no banco.');
        }
        if ($result === 0 && !array_key_exists('arquivado', $data)) {
            self::ensure_active(self::find((int) $student->id));
        }
        return $result;
    }

    public static function archive($id, $archive, $reason) {
        global $wpdb;
        if ($archive && trim($reason) === '') {
            throw new InvalidArgumentException('Informe o motivo da desistência antes de arquivar.');
        }
        // Não assume que uma tabela legada suporta rollback. Evita confirmar uma
        // desistência sem conseguir garantir a gravação conjunta do histórico.
        foreach (array(DRJ_Database::table(), $wpdb->prefix . 'drj_historico_rematriculas') as $table) {
            $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $wpdb->esc_like($table)));
            if (!$status || strcasecmp((string) $status->Engine, 'InnoDB') !== 0) {
                throw new RuntimeException('O arquivamento com histórico requer tabelas InnoDB. Solicite ao administrador a conversão da tabela antes de arquivar.');
            }
        }
        if (false === $wpdb->query('START TRANSACTION')) {
            throw new RuntimeException('Não foi possível iniciar o arquivamento.');
        }
        try {
            $student = self::find($id, '', true);
            if ((int) $student->arquivado === (int) $archive) {
                $wpdb->query('ROLLBACK');
                return;
            }
            self::update($student, array(
                'arquivado' => $archive ? 1 : 0,
                'motivo_arquivamento' => $archive ? $reason : null,
                'data_arquivamento' => $archive ? current_time('mysql') : null,
                'arquivado_por' => $archive ? get_current_user_id() : null,
            ));
            $result = $wpdb->insert($wpdb->prefix . 'drj_historico_rematriculas', array(
                'aluno_id' => (int) $student->id,
                'ra' => $student->ra,
                'acao' => $archive ? 'arquivado' : 'restaurado',
                'motivo' => $reason,
                'usuario_id' => get_current_user_id(),
                'autor' => DRJ_Auth::actor(),
                'criado_em' => current_time('mysql'),
            ));
            if ($result === false || false === $wpdb->query('COMMIT')) {
                throw new RuntimeException('Não foi possível registrar o histórico. Tente novamente.');
            }
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }

    public static function history($id) {
        global $wpdb;
        $table = $wpdb->prefix . 'drj_historico_rematriculas';
        return $wpdb->get_results($wpdb->prepare("SELECT acao, motivo, autor, criado_em FROM `$table` WHERE aluno_id = %d ORDER BY id DESC LIMIT 50", $id)) ?: array();
    }

    public static function level($class) {
        $course = DRJ_Pricing::course($class);
        return array('level' => in_array($course, array('EFI', 'EFII', 'EF'), true) ? 'EF' : ($course === 'EM' ? 'EM' : 'OUTRO'), 'base' => (float) DRJ_Pricing::base($class));
    }

    public static function view_data($student) {
        $level = self::level($student->turma);
        $financial = DRJ_Pricing::financial($student);
        return array(
            'id' => (int) $student->id,
            'ra' => (string) $student->ra,
            'nome' => (string) $student->nome_aluno,
            'turma' => (string) $student->turma,
            'nivel' => $level['level'],
            'novo' => $student->tipo_aluno === 'novo' || ($student->tipo_aluno === '' && strpos($student->ra, 'NOV-') === 0),
            'status' => (int) $student->status_rematricula,
            'data' => $student->data_rematricula && strtotime($student->data_rematricula) ? substr($student->data_rematricula, 0, 10) : '',
            'financeiro' => $financial,
            'vencimento' => $student->vencimento ?: '05',
            'parcelas' => (int) $student->parcelas ?: 12,
            'observacao' => (string) $student->observacao,
            'atencao' => (int) $student->check_atencao,
            'arquivado' => (int) $student->arquivado,
            'motivo' => (string) $student->motivo_arquivamento,
            'data_arquivamento' => (string) $student->data_arquivamento,
        );
    }

    public static function dashboard_data($student) {
        $data = self::view_data($student);
        if (!DRJ_Finance_Auth::can_access()) unset($data['financeiro'], $data['vencimento'], $data['parcelas']);
        return $data;
    }
}
