<?php
defined('ABSPATH') || exit;

/** Projeção financeira, sem gravação de valores ou estados de matrícula. */
final class DRJ_Finance {
    const FIELDS = array('gross', 'scholarships', 'conditional', 'net', 'unconditional', 'referral', 'siblings');

    public static function register() {
        add_action('wp_ajax_drj_finance_report', array(__CLASS__, 'ajax'));
        add_action('wp_ajax_nopriv_drj_finance_report', array(__CLASS__, 'ajax'));
    }

    public static function assets() {
        wp_enqueue_style('drj-dashboard', DRJ_URL . 'assets/css/dashboard.css', array(), DRJ_VERSION);
        wp_enqueue_style('drj-finance', DRJ_URL . 'assets/css/finance.css', array('drj-dashboard'), DRJ_VERSION);
        wp_enqueue_script('drj-finance', DRJ_URL . 'assets/js/finance.js', array(), DRJ_VERSION, true);
    }

    public static function admin() {
        echo '<div class="wrap">' . self::render(false) . '</div>';
    }

    public static function shortcode() {
        DRJ_Auth::no_cache();
        self::assets();
        ob_start();
        if (!wp_style_is('drj-finance', 'done')) wp_print_styles(array('drj-dashboard', 'drj-finance'));
        if (!DRJ_Finance_Auth::can_access()) {
            include DRJ_PATH . 'templates/finance-login.php';
        } else {
            echo self::render(true);
        }
        return ob_get_clean();
    }

    public static function render($frontend = false) {
        if (!DRJ_Finance_Auth::can_access()) return '<p>Acesso financeiro não autorizado.</p>';
        if (!DRJ_Database::ready()) return '<p>Não foi possível preparar o banco do painel. Consulte o administrador.</p>';
        self::assets();
        try {
            $report = self::report(self::filters($_GET));
        } catch (Throwable $error) {
            return '<p>Não foi possível consultar o financeiro. Tente novamente.</p>';
        }
        $config = array('ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => DRJ_Finance_Auth::nonce(), 'report' => $report);
        ob_start();
        include DRJ_PATH . 'templates/finance.php';
        return ob_get_clean();
    }

    public static function filters($input) {
        $result = array();
        foreach (array('basis' => 'installment', 'scope' => 'confirmed', 'course' => '', 'class' => '') as $key => $default) {
            $value = $input['drj_fin_' . $key] ?? $default;
            $result[$key] = is_string($value) ? sanitize_text_field(wp_unslash($value)) : $default;
        }
        if (!in_array($result['basis'], array('installment', 'contract'), true)) $result['basis'] = 'installment';
        if (!in_array($result['scope'], array('confirmed', 'all', 'pending'), true)) $result['scope'] = 'confirmed';
        if ($result['course'] !== '' && !isset(self::course_labels()[$result['course']])) $result['course'] = '';
        return $result;
    }

    public static function course_labels() {
        return DRJ_Pricing::labels();
    }

    public static function course($class) {
        return DRJ_Pricing::course($class);
    }

    private static function cents($amount) {
        // Arredondamento por aluno antes da soma evita diferenças de centavos.
        return (int) round((float) $amount * 100, 0, PHP_ROUND_HALF_UP);
    }

    public static function empty_total() {
        return array_merge(array('students' => 0, 'scholarship_students' => 0, 'over_discount' => 0), array_fill_keys(self::FIELDS, 0));
    }

    private static function add(&$total, $values) {
        foreach ($values as $key => $value) $total[$key] += $value;
    }

    public static function report($filters) {
        global $wpdb;
        $table = DRJ_Database::table();
        // Apenas colunas financeiras necessárias; observações e conferências não são carregadas.
        $students = $wpdb->get_results("SELECT ra, turma, tipo_aluno, status_rematricula, parcelas, desc_incondicional, desc_indicacao, desc_irmaos FROM `$table` WHERE arquivado = 0");
        if ($wpdb->last_error) throw new RuntimeException('Não foi possível consultar os valores.');
        return self::calculate($students ?: array(), $filters);
    }

    public static function calculate($students, $filters) {
        $labels = self::course_labels();
        $summary = self::empty_total();
        $courses = array();
        $classes = array();
        $catalog_courses = array();
        $catalog_classes = array();
        foreach ($students as $student) {
            if (!empty($student->arquivado)) continue;
            $class = trim((string) $student->turma);
            $course = self::course($class);
            $catalog_courses[$course] = true;
            if ($filters['course'] === '' || $filters['course'] === $course) $catalog_classes[$class === '' ? '__sem_turma__' : $class] = true;
            $new = ($student->tipo_aluno ?? '') === 'novo' || (($student->tipo_aluno ?? '') === '' && strpos((string) $student->ra, 'NOV-') === 0);
            $confirmed = $new || (int) $student->status_rematricula === 1;
            if (($filters['scope'] === 'confirmed' && !$confirmed) || ($filters['scope'] === 'pending' && $confirmed)) continue;
            if ($filters['course'] !== '' && $filters['course'] !== $course) continue;
            if ($filters['class'] !== '' && ($filters['class'] === '__sem_turma__' ? '' : $filters['class']) !== $class) continue;
            $values = self::empty_total();
            $values['students'] = 1;
            $financial = DRJ_Pricing::financial($student);
            $factor = $filters['basis'] === 'contract' ? ((int) $student->parcelas > 0 ? (int) $student->parcelas : 12) : 1;
            $values['gross'] = self::cents($financial['valor_2027']) * $factor;
            $values['unconditional'] = self::cents($student->desc_incondicional ?? 0) * $factor;
            $values['referral'] = self::cents($student->desc_indicacao ?? 0) * $factor;
            $values['siblings'] = self::cents($student->desc_irmaos ?? 0) * $factor;
            $values['conditional'] = self::cents($financial['desc_condicional']) * $factor;
            $values['scholarships'] = $values['unconditional'] + $values['referral'] + $values['siblings'];
            $values['net'] = $values['gross'] - $values['scholarships'] - $values['conditional'];
            $values['scholarship_students'] = $values['unconditional'] > 0 ? 1 : 0;
            $values['over_discount'] = $values['net'] < 0 ? 1 : 0;
            self::add($summary, $values);
            if (!isset($courses[$course])) $courses[$course] = self::empty_total();
            self::add($courses[$course], $values);
            $key = $course . ':' . $class;
            if (!isset($classes[$key])) $classes[$key] = array_merge(self::empty_total(), array('class' => $class, 'course' => $course, 'course_label' => $labels[$course]));
            self::add($classes[$key], $values);
        }
        $course_rows = array();
        $catalog = array();
        foreach ($labels as $code => $label) {
            if (isset($courses[$code])) $course_rows[] = array_merge($courses[$code], array('course' => $code, 'label' => $label));
            if (isset($catalog_courses[$code])) $catalog[] = array('value' => $code, 'label' => $label);
        }
        $class_rows = array_values($classes);
        usort($class_rows, function ($a, $b) use ($labels) {
            $order = array_flip(array_keys($labels));
            return $order[$a['course']] <=> $order[$b['course']] ?: strnatcasecmp($a['class'], $b['class']);
        });
        $available_classes = array_keys($catalog_classes);
        natcasesort($available_classes);
        return array('filters' => $filters, 'summary' => $summary, 'courses' => $course_rows, 'classes' => $class_rows, 'catalog' => array('courses' => $catalog, 'classes' => array_values($available_classes)), 'generated_at' => wp_date('d/m/Y H:i:s'));
    }

    public static function money($cents) {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }

    public static function percentage($part, $whole) {
        return $whole > 0 ? number_format($part / $whole * 100, 1, ',', '.') . '%' : '—';
    }

    public static function ajax() {
        DRJ_Finance_Auth::authorize();
        try {
            wp_send_json_success(array('report' => self::report(self::filters($_POST))));
        } catch (Throwable $error) {
            wp_send_json_error(array('msg' => 'Não foi possível carregar os valores financeiros. Tente novamente.'), 500);
        }
    }
}
