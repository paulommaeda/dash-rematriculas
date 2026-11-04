<?php
defined('ABSPATH') || exit;

final class DRJ_Dashboard {
    public static function assets() {
        wp_enqueue_style('drj-dashboard', DRJ_URL . 'assets/css/dashboard.css', array(), DRJ_VERSION);
        wp_enqueue_script('drj-dashboard', DRJ_URL . 'assets/js/dashboard.js', array(), DRJ_VERSION, true);
    }

    public static function frontend_assets() {
        global $post;
        if ($post && ((has_shortcode($post->post_content, 'dash_financeiro') && DRJ_Finance::requested(true)) || (has_shortcode($post->post_content, 'dash_rematriculas') && DRJ_Finance::requested()))) {
            DRJ_Finance::assets();
        } elseif ($post && (has_shortcode($post->post_content, 'dash_rematriculas') || has_shortcode($post->post_content, 'dash_financeiro'))) {
            self::assets();
        }
    }

    public static function admin_assets($hook) {
        if (strpos($hook, 'drj-financeiro') !== false) {
            DRJ_Finance::assets();
            return;
        }
        if (strpos($hook, 'dashboard-rematriculas') !== false || strpos($hook, 'drj-configuracoes') !== false) {
            self::assets();
        }
    }

    public static function shortcode() {
        if (DRJ_Finance::requested()) return DRJ_Finance::shortcode();
        DRJ_Auth::no_cache();
        self::assets();
        // Builders podem renderizar shortcodes depois de wp_head().
        ob_start();
        if (!wp_style_is('drj-dashboard', 'done')) {
            wp_print_styles('drj-dashboard');
        }
        if (!DRJ_Auth::can_access()) {
            include DRJ_PATH . 'templates/login.php';
        } else {
            echo self::render(true); // A saída é escapada pelos templates.
        }
        return ob_get_clean();
    }

    public static function render($frontend = false) {
        if (!DRJ_Auth::can_access()) {
            return '<p>Acesso restrito ao painel.</p>';
        }
        if (!DRJ_Database::ready()) {
            return '<p>Não foi possível preparar o banco do painel. Consulte o administrador.</p>';
        }
        self::assets();
        $view = isset($_GET['drj_visualizacao']) && is_string($_GET['drj_visualizacao']) ? sanitize_key(wp_unslash($_GET['drj_visualizacao'])) : 'ativos';
        if (!in_array($view, array('ativos', 'arquivados', 'todos'), true)) {
            $view = 'ativos';
        }
        try {
            $students = DRJ_Students::all($view);
            $archived_count = DRJ_Students::archived_count();
        } catch (Throwable $error) {
            return '<p>' . esc_html($error->getMessage()) . '</p>';
        }
        $classes = array();
        $data = array();
        foreach ($students as $student) {
            $classes[$student->turma] = true;
            $data[] = DRJ_Students::view_data($student);
        }
        $classes = array_keys($classes);
        sort($classes, SORT_NATURAL | SORT_FLAG_CASE);
        $config = array('ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => DRJ_Auth::nonce(), 'students' => $data, 'classes' => $classes, 'view' => $view);
        ob_start();
        include DRJ_PATH . 'templates/dashboard.php';
        return ob_get_clean();
    }

    public static function navigation($current, $frontend = false) {
        if ($frontend) {
            $base = remove_query_arg(array('drj_painel', 'drj_visualizacao', 'drj_fin_basis', 'drj_fin_scope', 'drj_fin_course', 'drj_fin_class'));
            $urls = array('rematriculas' => add_query_arg('drj_painel', 'rematriculas', $base), 'financeiro' => add_query_arg('drj_painel', 'financeiro', $base));
        } else {
            $urls = array('rematriculas' => admin_url('admin.php?page=dashboard-rematriculas'), 'financeiro' => admin_url('admin.php?page=drj-financeiro'));
        }
        echo '<nav class="drj-panel-nav" aria-label="Painéis de acompanhamento">';
        foreach (array('rematriculas' => 'Rematrículas', 'financeiro' => 'Financeiro') as $key => $label) {
            echo '<a href="' . esc_url($urls[$key]) . '"' . ($current === $key ? ' class="is-current" aria-current="page"' : '') . '>' . esc_html($label) . '</a>';
        }
        echo '</nav>';
    }
}
