<?php
defined('ABSPATH') || exit;

final class DRJ_Ajax {
    public static function register() {
        foreach (array('salvar_financeiro', 'salvar_check', 'salvar_modal', 'arquivar_aluno', 'historico_aluno', 'sincronizar_ff') as $action) {
            add_action('wp_ajax_drj_' . $action, array(__CLASS__, $action));
            // O frontend por senha é público no WordPress, mas exige sessão assinada E nonce.
            add_action('wp_ajax_nopriv_drj_' . $action, array(__CLASS__, $action));
        }
        add_action('wp_ajax_drj_receber_aluno_novo', array(__CLASS__, 'receber_aluno_novo'));
        add_action('wp_ajax_nopriv_drj_receber_aluno_novo', array(__CLASS__, 'receber_aluno_novo'));
    }

    private static function post($key, $default = '') {
        if (!isset($_POST[$key])) {
            return $default;
        }
        if (!is_scalar($_POST[$key])) {
            throw new InvalidArgumentException('Um dos campos enviados é inválido.');
        }
        return wp_unslash((string) $_POST[$key]);
    }

    private static function run($callback) {
        DRJ_Auth::authorize();
        try {
            wp_send_json_success(call_user_func($callback));
        } catch (InvalidArgumentException $error) {
            wp_send_json_error(array('msg' => $error->getMessage()), 400);
        } catch (Throwable $error) {
            wp_send_json_error(array('msg' => $error->getMessage()), 500);
        }
    }

    private static function student() {
        return DRJ_Students::find(absint(self::post('id')), sanitize_text_field(self::post('ra')));
    }

    private static function decimal($key) {
        $value = trim(self::post($key));
        if (strpos($value, ',') !== false) {
            $value = str_replace(array('.', ','), array('', '.'), $value);
        }
        if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('Informe valores financeiros válidos, com até duas casas decimais.');
        }
        return $value;
    }

    public static function salvar_financeiro() {
        self::run(function () {
            $student = self::student();
            $data = array();
            foreach (array('valor_2027', 'desc_condicional', 'desc_incondicional', 'desc_indicacao', 'desc_irmaos') as $key) {
                $data[$key] = self::decimal($key);
            }
            $day_text = self::post('vencimento');
            $installments_text = self::post('parcelas');
            $day = ctype_digit($day_text) ? (int) $day_text : 0;
            $installments = ctype_digit($installments_text) ? (int) $installments_text : 0;
            if ($day < 1 || $day > 31 || $installments < 1 || $installments > 12) {
                throw new InvalidArgumentException('Use vencimento entre 1 e 31 e parcelamento entre 1 e 12.');
            }
            $data['vencimento'] = str_pad((string) $day, 2, '0', STR_PAD_LEFT);
            $data['parcelas'] = $installments;
            foreach (array('secretaria', 'comercial', 'financeiro', 'ti') as $sector) {
                $data['check_' . $sector] = 0;
            }
            DRJ_Students::update($student, $data);
            return array('msg' => 'Valores salvos. As conferências foram reiniciadas.');
        });
    }

    public static function salvar_check() {
        self::run(function () {
            $student = self::student();
            $type = sanitize_key(self::post('tipo'));
            $raw_value = self::post('valor');
            if (!in_array($type, array('secretaria', 'comercial', 'financeiro', 'ti'), true) || !in_array($raw_value, array('0', '1'), true)) {
                throw new InvalidArgumentException('Conferência inválida.');
            }
            $value = (int) $raw_value;
            $changed = DRJ_Students::update($student, array('check_' . $type => $value));
            $warning = $changed ? DRJ_Integrations::webhook($student, $type, $value) : '';
            return array('msg' => 'Conferência salva.', 'warning' => $warning);
        });
    }

    public static function salvar_modal() {
        self::run(function () {
            global $wpdb;
            $student = self::student();
            $ra = sanitize_text_field(self::post('novo_ra'));
            $class = sanitize_text_field(self::post('turma'));
            if ($ra === '' || strlen($ra) > 100 || $class === '' || mb_strlen($class) > 100) {
                throw new InvalidArgumentException('Preencha um RA e uma turma válidos.');
            }
            $table = DRJ_Database::table();
            if ($wpdb->get_var($wpdb->prepare("SELECT id FROM `$table` WHERE ra = %s AND id <> %d LIMIT 1", $ra, $student->id))) {
                throw new InvalidArgumentException('Este RA já está cadastrado para outro aluno.');
            }
            DRJ_Students::update($student, array(
                'ra' => $ra, 'turma' => $class,
                'observacao' => sanitize_textarea_field(self::post('observacao')),
                'check_atencao' => self::post('check_atencao') === '1' ? 1 : 0,
            ));
            return array('msg' => 'Alterações salvas.', 'student' => DRJ_Students::view_data(DRJ_Students::find((int) $student->id)));
        });
    }

    public static function arquivar_aluno() {
        self::run(function () {
            $student = self::student();
            $value = self::post('arquivar');
            if (!in_array($value, array('0', '1'), true)) {
                throw new InvalidArgumentException('Ação inválida.');
            }
            DRJ_Students::archive((int) $student->id, $value === '1', sanitize_textarea_field(self::post('motivo')));
            return array('msg' => $value === '1' ? 'Desistência arquivada. Os dados foram preservados.' : 'Aluno restaurado para o acompanhamento.');
        });
    }

    public static function historico_aluno() {
        self::run(function () {
            $student = self::student();
            return array('history' => DRJ_Students::history((int) $student->id));
        });
    }

    public static function sincronizar_ff() {
        self::run(function () {
            return DRJ_Integrations::sync(absint(self::post('cursor')), absint(self::post('until')));
        });
    }

    public static function receber_aluno_novo() {
        try {
            global $wpdb;
            if (!DRJ_Auth::valid_api_secret(self::post('secret'))) {
                wp_send_json_error(array('msg' => 'Acesso não autorizado à API Journey.'), 403);
            }
            if (!DRJ_Database::ready()) {
                wp_send_json_error(array('msg' => 'O banco do painel precisa ser atualizado.'), 503);
            }
            $ra = sanitize_text_field(self::post('ra'));
            $name = sanitize_text_field(self::post('nome'));
            $class = sanitize_text_field(self::post('turma'));
            if ($ra === '' || $name === '' || $class === '' || strlen($ra) > 100 || mb_strlen($name) > 255 || mb_strlen($class) > 100) {
                throw new InvalidArgumentException('Informe RA, nome e turma válidos.');
            }
            $table = DRJ_Database::table();
            if ($wpdb->get_var($wpdb->prepare("SELECT id FROM `$table` WHERE ra = %s LIMIT 1", $ra))) {
                wp_send_json_success(array('msg' => 'Aluno já cadastrado. Dados e arquivamento preservados.'));
            }
            // Serializa cadastros deste endpoint para impedir duplicação em reenvios simultâneos.
            $lock = 'drj_api_' . substr(hash('sha256', $table . $ra), 0, 40);
            if ((string) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $lock)) !== '1') {
                wp_send_json_error(array('msg' => 'Cadastro em processamento. Tente novamente.'), 409);
            }
            try {
                if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM `$table` WHERE ra = %s LIMIT 1", $ra))) {
                    $inserted = $wpdb->insert($table, array('ra' => $ra, 'nome_aluno' => mb_strtoupper($name, 'UTF-8'), 'turma' => $class, 'tipo_aluno' => 'novo', 'status_rematricula' => 1, 'data_rematricula' => current_time('mysql'), 'desc_condicional' => 103.00, 'vencimento' => '05', 'parcelas' => 12));
                    if (!$inserted) {
                        throw new RuntimeException('Não foi possível cadastrar o aluno.');
                    }
                }
            } finally {
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
            }
            wp_send_json_success();
        } catch (InvalidArgumentException $error) {
            wp_send_json_error(array('msg' => $error->getMessage()), 400);
        } catch (Throwable $error) {
            wp_send_json_error(array('msg' => 'Não foi possível concluir o cadastro. Tente novamente.'), 500);
        }
    }
}
