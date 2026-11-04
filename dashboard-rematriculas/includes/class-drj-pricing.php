<?php
defined('ABSPATH') || exit;

/** Fonte única do valor base por curso e do desconto condicional de todos. */
final class DRJ_Pricing {
    const OPTION = 'drj_pricing_values';

    public static function register() {
        add_action('admin_post_drj_save_pricing', array(__CLASS__, 'handle_save'));
        add_action('admin_post_nopriv_drj_save_pricing', array(__CLASS__, 'handle_save'));
    }

    public static function labels() {
        return array('EFI' => 'Ensino Fundamental I', 'EFII' => 'Ensino Fundamental II', 'EM' => 'Ensino Médio', 'EF' => 'Ensino Fundamental · ciclo não informado', 'OUTRO' => 'Não classificado');
    }

    public static function course($class) {
        $plain = strtoupper(remove_accents(trim((string) $class)));
        if (strpos($plain, 'SERIE') !== false) return 'EM';
        if (strpos($plain, 'ANO') !== false) {
            if (!preg_match('/(\d+)/', $plain, $match)) return 'EF';
            $year = (int) $match[1];
            if ($year >= 1 && $year <= 5) return 'EFI';
            if ($year >= 6 && $year <= 9) return 'EFII';
        }
        return 'OUTRO';
    }

    public static function defaults() {
        return array('bases' => array('EFI' => '1350.00', 'EFII' => '1290.00', 'EM' => '1520.00', 'EF' => '1290.00', 'OUTRO' => '0.00'), 'conditional' => '103.00');
    }

    private static function amount($raw) {
        if (!is_scalar($raw)) throw new InvalidArgumentException('Informe valores monetários válidos.');
        $value = trim((string) $raw);
        if (strpos($value, ',') !== false) $value = str_replace(array('.', ','), array('', '.'), $value);
        if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $value)) throw new InvalidArgumentException('Use valores a partir de zero, com até duas casas decimais.');
        return number_format((float) $value, 2, '.', '');
    }

    public static function get() {
        $result = self::defaults();
        $saved = get_option(self::OPTION, false);
        if (!is_array($saved)) return $result;
        foreach ($result['bases'] as $course => $default) {
            if (isset($saved['bases'][$course])) {
                try { $result['bases'][$course] = self::amount($saved['bases'][$course]); } catch (InvalidArgumentException $error) { /* Mantém a referência válida. */ }
            }
        }
        if (isset($saved['conditional'])) {
            try { $result['conditional'] = self::amount($saved['conditional']); } catch (InvalidArgumentException $error) { /* Mantém a referência válida. */ }
        }
        return $result;
    }

    public static function base($class) {
        return self::get()['bases'][self::course($class)];
    }

    public static function conditional() {
        return self::get()['conditional'];
    }

    public static function financial($student) {
        return array(
            'valor_2027' => (float) self::base($student->turma ?? ''),
            'desc_condicional' => (float) self::conditional(),
            'desc_incondicional' => (float) ($student->desc_incondicional ?? 0),
            'desc_indicacao' => (float) ($student->desc_indicacao ?? 0),
            'desc_irmaos' => (float) ($student->desc_irmaos ?? 0),
        );
    }

    public static function save($input) {
        if (!current_user_can('manage_options')) return new WP_Error('drj_pricing_forbidden', 'Somente administradores podem alterar os valores gerais.');
        if (!DRJ_Database::ready()) return new WP_Error('drj_pricing_database', 'Atualize o banco do plugin antes de salvar os valores gerais.');
        if (!is_array($input) || !isset($input['bases']) || !is_array($input['bases'])) return new WP_Error('drj_pricing_invalid', 'Preencha os valores base de todos os cursos.');
        try {
            $next = array('bases' => array(), 'conditional' => self::amount($input['conditional'] ?? ''));
            foreach (self::labels() as $course => $label) $next['bases'][$course] = self::amount($input['bases'][$course] ?? '');
        } catch (InvalidArgumentException $error) {
            return new WP_Error('drj_pricing_invalid', $error->getMessage());
        }
        $stored = get_option(self::OPTION, false);
        $previous = self::get();
        if ($stored !== false && $next === $previous) return array('changed' => false);
        global $wpdb;
        $table = DRJ_Database::table();
        $where = '';
        $changed_courses = array();
        foreach ($next['bases'] as $course => $value) if ($value !== $previous['bases'][$course]) $changed_courses[] = $course;
        if ($stored !== false && $next['conditional'] === $previous['conditional']) {
            $classes = $wpdb->get_col("SELECT DISTINCT turma FROM `$table`");
            if ($wpdb->last_error) return new WP_Error('drj_pricing_read', 'Não foi possível verificar os cursos dos alunos. Nenhum valor foi alterado.');
            $affected = array();
            $null_class = false;
            foreach ($classes ?: array() as $class) {
                if (in_array(self::course($class), $changed_courses, true)) {
                    if ($class === null) $null_class = true; else $affected[] = $class;
                }
            }
            if ($affected) $where = $wpdb->prepare('turma IN (' . implode(',', array_fill(0, count($affected), '%s')) . ')', $affected);
            if ($null_class) $where = $where ? '(' . $where . ' OR turma IS NULL)' : 'turma IS NULL';
            if (!$affected && !$null_class) $where = '1 = 0';
        }
        // Reinicia aprovações antes de publicar novos termos. Uma falha impede a gravação da configuração.
        $reset = $wpdb->query("UPDATE `$table` SET check_comercial = 0, check_secretaria = 0, check_financeiro = 0, check_ti = 0" . ($where ? ' WHERE ' . $where : ''));
        if ($reset === false) return new WP_Error('drj_pricing_reset', 'Não foi possível reiniciar as conferências. Os valores gerais não foram alterados.');
        if (!update_option(self::OPTION, $next, false)) return new WP_Error('drj_pricing_write', 'Não foi possível salvar os valores gerais. Tente novamente.');
        return array('changed' => true);
    }

    public static function handle_save() {
        if (!current_user_can('manage_options')) wp_die('Você não tem permissão para alterar os valores gerais.', '', array('response' => 403));
        check_admin_referer('drj_save_pricing');
        $result = self::save(wp_unslash($_POST));
        if (is_wp_error($result)) set_transient('drj_pricing_error_' . get_current_user_id(), $result->get_error_message(), MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=drj-configuracoes&drj_pricing_saved=' . (is_wp_error($result) ? '0' : '1')));
        exit;
    }
}
