<?php
defined('ABSPATH') || exit;

final class DRJ_Dashboard {
    public static function assets() {
        wp_enqueue_style('drj-dashboard', DRJ_URL . 'assets/css/dashboard.css', array(), DRJ_VERSION);
        wp_enqueue_script('drj-dashboard', DRJ_URL . 'assets/js/dashboard.js', array(), DRJ_VERSION, true);
    }

    public static function frontend_assets() {
        global $post;
        if ($post && has_shortcode($post->post_content, 'dash_rematriculas')) {
            self::assets();
        }
    }

    public static function admin_assets($hook) {
        if (strpos($hook, 'dashboard-rematriculas') !== false || strpos($hook, 'drj-configuracoes') !== false) {
            self::assets();
        }
    }

    public static function shortcode() {
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
}
