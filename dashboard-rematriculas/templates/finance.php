<?php defined('ABSPATH') || exit;
$filters = $report['filters'];
$summary = $report['summary'];
$basis_help = $filters['basis'] === 'contract' ? 'Total dos contratos: cada aluno é multiplicado pelo seu número de parcelas.' : 'Por parcela: soma do valor de uma parcela de cada aluno selecionado.';
?>
<div class="drj-app drj-finance" data-drj-finance>
    <script type="application/json" class="drj-finance-config"><?php echo wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <?php DRJ_Dashboard::navigation('financeiro', $frontend); ?>
    <header class="drj-fin-hero">
        <div><span class="drj-fin-eyebrow">JOURNEY · GESTÃO FINANCEIRA</span><h1>Dashboard Financeiro</h1><p>Uma visão dos valores brutos, líquidos e das bolsas por curso e turma.</p></div>
        <div class="drj-fin-hero-actions">
            <button type="button" class="drj-fin-refresh" data-fin-refresh>↻ Atualizar dados</button>
            <?php if ($frontend && !current_user_can('manage_options')) : ?>
                <form method="post"><input type="hidden" name="drj_auth_action" value="logout"><input type="hidden" name="nonce" value="<?php echo esc_attr(DRJ_Auth::nonce()); ?>"><button type="submit" class="drj-fin-logout">Sair do painel</button></form>
            <?php endif; ?>
        </div>
    </header>
    <div class="drj-fin-feedback" role="status" aria-live="polite" hidden></div>
    <form method="get" class="drj-fin-filters">
        <?php foreach (array('page', 'page_id', 'p') as $query_key) : if (isset($_GET[$query_key]) && is_string($_GET[$query_key])) : ?>
            <input type="hidden" name="<?php echo esc_attr($query_key); ?>" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET[$query_key]))); ?>">
        <?php endif; endforeach; ?>
        <?php if ($frontend) : ?><input type="hidden" name="drj_painel" value="financeiro"><?php endif; ?>
        <label>Base dos valores<select name="drj_fin_basis" data-fin-filter="basis"><option value="installment" <?php selected($filters['basis'], 'installment'); ?>>Por parcela</option><option value="contract" <?php selected($filters['basis'], 'contract'); ?>>Total dos contratos</option></select></label>
        <label>Alunos considerados<select name="drj_fin_scope" data-fin-filter="scope"><option value="confirmed" <?php selected($filters['scope'], 'confirmed'); ?>>Rematriculados e novos</option><option value="all" <?php selected($filters['scope'], 'all'); ?>>Todos em acompanhamento</option><option value="pending" <?php selected($filters['scope'], 'pending'); ?>>Somente pendentes</option></select></label>
        <label>Curso<select name="drj_fin_course" data-fin-filter="course"><option value="">Todos os cursos</option><?php foreach ($report['catalog']['courses'] as $course) : ?><option value="<?php echo esc_attr($course['value']); ?>" <?php selected($filters['course'], $course['value']); ?>><?php echo esc_html($course['label']); ?></option><?php endforeach; ?></select></label>
        <label>Turma<select name="drj_fin_class" data-fin-filter="class"><option value="">Todas as turmas</option><?php foreach ($report['catalog']['classes'] as $class) : ?><option value="<?php echo esc_attr($class); ?>" <?php selected($filters['class'], $class); ?>><?php echo esc_html($class === '__sem_turma__' ? 'Sem turma informada' : $class); ?></option><?php endforeach; ?></select></label>
        <button type="submit" class="drj-fin-apply">Aplicar filtros</button>
    </form>
    <div class="drj-fin-context"><span data-fin-context><?php echo esc_html($basis_help); ?></span><span><strong data-fin-value="students"><?php echo (int) $summary['students']; ?></strong> aluno(s) · <strong data-fin-value="scholarship_students"><?php echo (int) $summary['scholarship_students']; ?></strong> com bolsa</span></div>
    <div class="drj-fin-cards">
        <?php foreach (array('gross' => array('Valor bruto', 'Antes de bolsas e descontos'), 'scholarships' => array('Bolsas', 'Incondicional + indicação + irmãos'), 'conditional' => array('Desconto condicional', 'Condição de desconto aplicada'), 'net' => array('Valor líquido', 'Após bolsas e desconto condicional')) as $key => $card) : ?>
            <article class="drj-fin-card drj-fin-card-<?php echo esc_attr($key); ?>"><h2><?php echo esc_html($card[0]); ?></h2><p data-fin-value="<?php echo esc_attr($key); ?>"><?php echo esc_html(self::money($summary[$key])); ?></p><span><?php echo esc_html($card[1]); ?></span></article>
        <?php endforeach; ?>
    </div>
    <div class="drj-fin-alert" role="status" <?php echo $summary['over_discount'] ? '' : 'hidden'; ?>><strong>Atenção aos valores:</strong> <span data-fin-warning><?php echo (int) $summary['over_discount']; ?></span> aluno(s) com descontos maiores que o valor bruto. Confira os cadastros no painel de rematrículas.</div>
    <div class="drj-fin-visuals">
        <section class="drj-card drj-fin-chart-card"><div class="drj-fin-section-heading"><div><span class="drj-fin-eyebrow">COMPARATIVO</span><h2>Bruto e líquido por curso</h2></div><div class="drj-fin-legend"><span><i class="is-gross"></i>Bruto</span><span><i class="is-net"></i>Líquido</span></div></div>
            <div data-fin-course-chart>
                <?php $chart_max = 1; foreach ($report['courses'] as $course) $chart_max = max($chart_max, $course['gross'], abs($course['net'])); ?>
                <?php if (!$report['courses']) : ?><p class="drj-fin-empty">Nenhum aluno encontrado para os filtros selecionados.</p><?php endif; ?>
                <?php foreach ($report['courses'] as $course) : ?>
                    <div class="drj-fin-course-bars"><strong><?php echo esc_html($course['label']); ?></strong>
                        <?php foreach (array('gross' => 'Bruto', 'net' => 'Líquido') as $key => $label) : ?><div class="drj-fin-bar-line"><span><?php echo esc_html($label); ?></span><div class="drj-fin-track"><div class="drj-fin-bar is-<?php echo esc_attr($key); ?> <?php echo $course[$key] < 0 ? 'is-negative' : ''; ?>" style="width:<?php echo esc_attr(number_format(abs($course[$key]) / $chart_max * 100, 3, '.', '')); ?>%"></div></div><b><?php echo esc_html(self::money($course[$key])); ?></b></div><?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <section class="drj-card drj-fin-chart-card"><div class="drj-fin-section-heading"><div><span class="drj-fin-eyebrow">COMPOSIÇÃO DAS BOLSAS</span><h2>Como as bolsas se distribuem</h2></div></div>
            <div class="drj-fin-scholarship-share"><span>Bolsas sobre o valor bruto</span><strong data-fin-share><?php echo esc_html(self::percentage($summary['scholarships'], $summary['gross'])); ?></strong></div>
            <div data-fin-scholarship-chart>
                <?php foreach (array('unconditional' => 'Incondicionais', 'referral' => 'Indicação', 'siblings' => 'Irmãos') as $key => $label) : ?>
                    <div class="drj-fin-scholarship-line"><div><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html(self::money($summary[$key])); ?></strong></div><div class="drj-fin-track"><div class="drj-fin-bar is-<?php echo esc_attr($key); ?>" style="width:<?php echo $summary['scholarships'] > 0 ? esc_attr(number_format(max(0, $summary[$key]) / $summary['scholarships'] * 100, 3, '.', '')) : '0'; ?>%"></div></div></div>
                <?php endforeach; ?>
            </div>
            <p class="drj-fin-note">O desconto condicional aparece separado e também é deduzido do líquido.</p>
        </section>
    </div>
    <?php foreach (array('courses' => array('RESUMO POR CURSO', 'Totais por curso'), 'classes' => array('DETALHAMENTO POR TURMA', 'Totais por turma')) as $group => $heading) : ?>
        <section class="drj-fin-table-section"><div class="drj-fin-section-heading"><div><span class="drj-fin-eyebrow"><?php echo esc_html($heading[0]); ?></span><h2><?php echo esc_html($heading[1]); ?></h2></div></div>
            <div class="drj-table-wrapper"><table class="drj-table drj-fin-table"><caption class="screen-reader-text"><?php echo esc_html($heading[1]); ?></caption><thead><tr><th scope="col"><?php echo $group === 'courses' ? 'Curso' : 'Turma'; ?></th><th scope="col">Alunos</th><th scope="col">Bruto</th><th scope="col">Bolsas</th><th scope="col">Desc. condicional</th><th scope="col">Líquido</th><th scope="col">% de bolsas</th></tr></thead><tbody data-fin-table="<?php echo esc_attr($group); ?>">
                <?php if (!$report[$group]) : ?><tr><td colspan="7" class="drj-fin-empty">Nenhum aluno encontrado para os filtros selecionados.</td></tr><?php endif; ?>
                <?php foreach ($report[$group] as $values) : ?>
                    <tr><th scope="row"><?php if ($group === 'classes') : echo esc_html($values['class'] ?: 'Sem turma informada'); ?><small><?php echo esc_html($values['course_label']); ?></small><?php else : echo esc_html($values['label']); endif; ?></th><td><?php echo (int) $values['students']; ?></td><?php foreach (array('gross', 'scholarships', 'conditional', 'net') as $key) : ?><td <?php echo $key === 'net' ? 'class="drj-fin-net-cell' . ($values[$key] < 0 ? ' is-negative' : '') . '"' : ''; ?>><?php echo esc_html(self::money($values[$key])); ?></td><?php endforeach; ?><td><?php echo esc_html(self::percentage($values['scholarships'], $values['gross'])); ?></td></tr>
                <?php endforeach; ?>
            </tbody><tfoot data-fin-total><tr><th scope="row">Total geral filtrado</th><td><?php echo (int) $summary['students']; ?></td><?php foreach (array('gross', 'scholarships', 'conditional', 'net') as $key) : ?><td><?php echo esc_html(self::money($summary[$key])); ?></td><?php endforeach; ?><td><?php echo esc_html(self::percentage($summary['scholarships'], $summary['gross'])); ?></td></tr></tfoot></table></div>
        </section>
    <?php endforeach; ?>
    <footer class="drj-fin-method"><div><strong>Como os valores são calculados</strong><p>Bruto − bolsas − desconto condicional = líquido. No total dos contratos, cada valor considera o número de parcelas do aluno. Os cursos são identificados pelas turmas.</p><p>Desistências arquivadas ficam fora do cálculo. A visão inicial inclui rematriculados e alunos novos; use o filtro para considerar pendentes.</p></div><span>Atualizado em <time data-fin-updated><?php echo esc_html($report['generated_at']); ?></time></span></footer>
    <noscript><p>Use “Aplicar filtros” para atualizar esta tela.</p></noscript>
</div>
