<?php defined('ABSPATH') || exit; ?>
<div class="drj-app" data-drj-dashboard>
    <script type="application/json" class="drj-config"><?php echo wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <div class="drj-header">
        <div><h1>Dashboard de Rematrículas</h1><p>Valores salvos automaticamente. Clique no nome do aluno para ver detalhes e arquivar desistências.</p></div>
        <div class="drj-header-actions">
            <button type="button" class="drj-btn-sync" data-action="sync">🔄 Sincronizar Agora</button>
            <?php if ($frontend && !current_user_can('manage_options')) : ?>
                <form method="post"><input type="hidden" name="drj_auth_action" value="logout"><input type="hidden" name="nonce" value="<?php echo esc_attr(DRJ_Auth::nonce()); ?>"><button type="submit" class="drj-link">Sair do painel</button></form>
            <?php endif; ?>
        </div>
    </div>
    <nav class="drj-views" aria-label="Visualização dos alunos">
        <?php foreach (array('ativos' => 'Em acompanhamento', 'arquivados' => 'Desistências arquivadas (' . $archived_count . ')', 'todos' => 'Todos os registros') as $key => $label) : ?>
            <a href="<?php echo esc_url(add_query_arg('drj_visualizacao', $key)); ?>" class="<?php echo $view === $key ? 'is-current' : ''; ?>" <?php echo $view === $key ? 'aria-current="page"' : ''; ?>><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </nav>
    <p class="drj-view-help">Arquivados ficam fora das métricas e dos gráficos de acompanhamento. O histórico e todos os dados são preservados.</p>
    <div class="drj-feedback" role="status" aria-live="polite" hidden></div>
    <div class="drj-filtros-container">
        <h3>🔍 Filtros de visualização</h3>
        <div class="drj-filtros">
            <div><label>Busca rápida<input type="search" data-filter="name" placeholder="RA ou nome..."></label></div>
            <div><label>Status<select data-filter="status"><option value="">Todos</option><option value="1">Rematriculados (antigos)</option><option value="novo">Alunos novos</option><option value="0">Pendentes</option></select></label></div>
            <div><label>Nível de ensino<select data-filter="level"><option value="">Todos</option><option value="EF">Ensino Fundamental</option><option value="EM">Ensino Médio</option></select></label></div>
            <div><label>Turma<select data-filter="class"><option value="">Todas</option><?php foreach ($classes as $class) : ?><option value="<?php echo esc_attr($class); ?>"><?php echo esc_html($class); ?></option><?php endforeach; ?></select></label></div>
            <div><label>Data início<input type="date" data-filter="start"></label></div>
            <div><label>Data fim<input type="date" data-filter="end"></label></div>
        </div>
    </div>
    <div class="drj-grid-topo">
        <?php foreach (array('total' => array('Em acompanhamento', '#64748b'), 'remat' => array('Rematriculados', '#10b981'), 'novos' => array('Novos alunos', '#3b82f6'), 'pend' => array('Pendentes', '#f59e0b'), 'conv' => array('Conversão ativa', '#6366f1')) as $metric => $details) : ?>
            <div class="drj-card drj-metric" style="border-top-color:<?php echo esc_attr($details[1]); ?>"><h3><?php echo esc_html($details[0]); ?></h3><p data-metric="<?php echo esc_attr($metric); ?>">—</p></div>
        <?php endforeach; ?>
    </div>
    <div class="drj-grid-graficos">
        <div class="drj-card"><h3>Evolução diária</h3><div data-chart="evolution" class="drj-chart"></div></div>
        <div class="drj-card"><h3>Matrículas confirmadas · EF × EM</h3><div data-chart="levels" class="drj-chart"></div></div>
    </div>
    <div class="drj-card drj-class-chart"><h3>Lotação por turma · Capacidade de referência: 30</h3><div data-chart="classes" class="drj-chart"></div><p class="drj-muted">As vagas seguem a regra anterior: rematriculados, novos e pendentes reservam lugar. Arquivados não reservam vaga.</p></div>
    <p class="drj-results" aria-live="polite"></p>
    <div class="drj-table-wrapper">
        <table class="drj-table">
            <thead><tr><th>RA</th><th>Nome do aluno</th><th>Turma</th><th>Status</th><th>V. base</th><th>Cond.</th><th>Incond.</th><th>Indic.</th><th>Irmãos</th><th>Final</th><th>Venc.</th><th>Parc.</th><th>Conferência</th><th>Ações</th></tr></thead>
            <tbody>
                <?php foreach ($students as $student) : $row = DRJ_Students::view_data($student); include DRJ_PATH . 'templates/student-row.php'; endforeach; ?>
            </tbody>
        </table>
        <p class="drj-empty" hidden>Nenhum aluno encontrado nesta visualização.</p>
    </div>
    <noscript><p>Ative o JavaScript para usar os filtros, gráficos e ações do painel.</p></noscript>
    <div class="drj-modal-container"></div>
</div>
