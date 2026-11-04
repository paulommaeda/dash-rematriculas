<?php
defined('ABSPATH') || exit;

final class DRJ_Database {
    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'lista_alunos_v4';
    }

    public static function maybe_upgrade() {
        if (get_option('drj_versao_banco') !== DRJ_DB_VERSION) {
            self::upgrade();
        }
    }

    public static function upgrade() {
        global $wpdb;
        if (get_option('drj_versao_banco') === DRJ_DB_VERSION) {
            return true;
        }
        $lock = (int) get_option('drj_migration_lock', 0);
        if ($lock && $lock < time() - 120) {
            delete_option('drj_migration_lock');
        }
        if (!add_option('drj_migration_lock', time(), '', false)) {
            return false;
        }
        $table = self::table();
        $history = $wpdb->prefix . 'drj_historico_rematriculas';
        $charset = $wpdb->get_charset_collate();
        $ok = true;
        try {
            // Não recria a tabela existente nem substitui dados financeiros já salvos.
            $ok = false !== $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                ra varchar(100) NOT NULL,
                nome_aluno varchar(255) NOT NULL DEFAULT '',
                turma varchar(100) NOT NULL DEFAULT '',
                PRIMARY KEY (id), KEY drj_ra (ra)
            ) $charset");
            $columns = $wpdb->get_col("SHOW COLUMNS FROM `$table`", 0);
            if (!$columns) {
                throw new RuntimeException('Não foi possível verificar a tabela de alunos.');
            }
            $definitions = array(
                'status_rematricula' => 'tinyint(1) NOT NULL DEFAULT 0',
                'data_rematricula' => 'datetime DEFAULT NULL',
                'valor_2027' => 'decimal(10,2) DEFAULT 0.00',
                'desc_condicional' => 'decimal(10,2) DEFAULT 103.00',
                'desc_incondicional' => 'decimal(10,2) DEFAULT 0.00',
                'desc_indicacao' => 'decimal(10,2) DEFAULT 0.00',
                'desc_irmaos' => 'decimal(10,2) DEFAULT 0.00',
                'vencimento' => "varchar(2) DEFAULT '05'",
                'parcelas' => 'smallint DEFAULT 12',
                'check_secretaria' => 'tinyint(1) DEFAULT 0',
                'check_comercial' => 'tinyint(1) DEFAULT 0',
                'check_financeiro' => 'tinyint(1) DEFAULT 0',
                'check_ti' => 'tinyint(1) DEFAULT 0',
                'check_atencao' => 'tinyint(1) DEFAULT 0',
                'observacao' => 'text DEFAULT NULL',
                'tipo_aluno' => "varchar(10) NOT NULL DEFAULT ''",
                'arquivado' => 'tinyint(1) NOT NULL DEFAULT 0',
                'motivo_arquivamento' => 'text DEFAULT NULL',
                'data_arquivamento' => 'datetime DEFAULT NULL',
                'arquivado_por' => 'bigint unsigned DEFAULT NULL',
            );
            foreach ($definitions as $column => $definition) {
                if (!in_array($column, $columns, true) && false === $wpdb->query("ALTER TABLE `$table` ADD `$column` $definition")) {
                    $ok = false;
                }
            }
            $indexes = $wpdb->get_col("SHOW INDEX FROM `$table`", 2);
            if (!in_array('drj_arquivado', $indexes, true) && false === $wpdb->query("ALTER TABLE `$table` ADD INDEX drj_arquivado (arquivado)")) {
                $ok = false;
            }
            if (false === $wpdb->query("UPDATE `$table` SET tipo_aluno = CASE WHEN LEFT(ra, 4) = 'NOV-' THEN 'novo' ELSE 'antigo' END WHERE tipo_aluno = ''")) {
                $ok = false;
            }
            if (false === $wpdb->query("CREATE TABLE IF NOT EXISTS `$history` (
                id bigint unsigned NOT NULL AUTO_INCREMENT,
                aluno_id bigint unsigned NOT NULL,
                ra varchar(100) NOT NULL,
                acao varchar(20) NOT NULL,
                motivo text DEFAULT NULL,
                usuario_id bigint unsigned NOT NULL DEFAULT 0,
                autor varchar(190) NOT NULL,
                criado_em datetime NOT NULL,
                PRIMARY KEY (id), KEY aluno_id (aluno_id)
            ) ENGINE=InnoDB $charset")) {
                $ok = false;
            }
            if (!$ok) {
                throw new RuntimeException('A atualização do banco não foi concluída. Verifique as permissões de criação e alteração de tabelas.');
            }
            DRJ_Auth::migrate_credentials();
            update_option('drj_versao_banco', DRJ_DB_VERSION, false);
            delete_option('drj_db_error');
        } catch (Throwable $error) {
            update_option('drj_db_error', $error->getMessage(), false);
            $ok = false;
        } finally {
            delete_option('drj_migration_lock');
        }
        return $ok;
    }

    public static function ready() {
        return get_option('drj_versao_banco') === DRJ_DB_VERSION;
    }

    public static function notice() {
        if (current_user_can('manage_options') && get_option('drj_db_error')) {
            echo '<div class="notice notice-error"><p>Dashboard de Rematrículas: ' . esc_html(get_option('drj_db_error')) . '</p></div>';
        }
    }
}
