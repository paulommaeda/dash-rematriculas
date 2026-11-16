<?php defined('ABSPATH') || exit;
$labels = DRJ_Integration_Log::labels(); $origins = DRJ_Integration_Log::origins();
$base = array_merge(array('page' => 'drj-integracao-logs'), array_filter($filters, function ($value, $key) { return $key !== 'pagina' && $value !== ''; }, ARRAY_FILTER_USE_BOTH));
?>
<div class="wrap drj-integration-log">
    <h1>Logs de integração</h1>
    <p>Veja o que foi lido, aceito, ignorado ou rejeitado e o motivo. Cada requerimento aparece uma vez; releituras aumentam o contador e ficam no histórico. Falhas de conexão e requisições sem item identificável são agrupadas por hora.</p>
    <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=drj-configuracoes#integracao-erp')); ?>">Configurar ou sincronizar ERP</a> <a class="button" href="<?php echo esc_url(add_query_arg($base, admin_url('admin.php'))); ?>">Atualizar logs</a></p>
    <style>
        .drj-log-cards {display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin:20px 0}
        .drj-log-card {background:#fff;border:1px solid #cbd5e1;border-radius:8px;padding:16px}
        .drj-log-card strong {display:block;font-size:25px;line-height:1.5}
        .drj-log-filters {display:flex;flex-wrap:wrap;gap:12px;align-items:end;margin:20px 0}
        .drj-log-filters label {display:flex;flex-direction:column;gap:4px}
        .drj-log-badge {display:inline-block;padding:4px 7px;border-radius:4px;background:#e2e8f0;color:#0f172a;font-weight:600}
        .drj-log-aceito {background:#dcfce7;color:#14532d}.drj-log-duplicado {background:#dbeafe;color:#1e3a8a}
        .drj-log-rejeitado,.drj-log-erro {background:#fee2e2;color:#7f1d1d}.drj-log-ignorado {background:#fef3c7;color:#78350f}
        .drj-log-scroll {overflow-x:auto}.drj-log-scroll table {min-width:1050px}.drj-log-scroll td {overflow-wrap:anywhere;vertical-align:top}
        .drj-log-message {min-width:230px}.drj-log-pages {display:flex;gap:15px;align-items:center;margin:16px 0}
    </style>
    <form method="get" class="drj-log-filters">
        <input type="hidden" name="page" value="drj-integracao-logs">
        <label>Origem<select name="origem"><option value="">Todas</option><?php foreach ($origins as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($filters['origem'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
        <label>Último resultado<select name="resultado"><option value="">Todos</option><?php foreach ($labels as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($filters['resultado'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
        <label>RA, ID externo ou protocolo<input type="search" name="busca" value="<?php echo esc_attr($filters['busca']); ?>"></label>
        <label>Última leitura desde<input type="date" name="desde" value="<?php echo esc_attr($filters['desde']); ?>"></label>
        <label>Até<input type="date" name="ate" value="<?php echo esc_attr($filters['ate']); ?>"></label>
        <button type="submit" class="button button-primary">Filtrar</button>
        <a href="<?php echo esc_url(admin_url('admin.php?page=drj-integracao-logs')); ?>">Limpar filtros</a>
    </form>
    <?php if (is_wp_error($listing)) : ?>
        <div class="notice notice-error"><p><?php echo esc_html($listing->get_error_message()); ?></p></div>
    <?php else : $summary = $listing['summary']; ?>
        <div class="drj-log-cards">
            <?php foreach (array('lidos' => 'Registros únicos lidos', 'leituras' => 'Leituras / tentativas', 'aceitos' => 'Requerimentos aceitos', 'duplicados' => 'Reenvios evitados', 'pendencias' => 'Rejeitados / erros atuais') as $key => $label) : ?>
                <div class="drj-log-card"><?php echo esc_html($label); ?><strong><?php echo esc_html(number_format_i18n((int) $summary[$key])); ?></strong></div>
            <?php endforeach; ?>
        </div>
        <p class="description">Totais conforme os filtros. Um aceite anterior permanece contado mesmo se uma tentativa posterior for rejeitada. Reenvios são leituras que preservaram uma confirmação já existente.</p>
        <div class="drj-log-scroll"><table class="widefat striped">
            <thead><tr><th>Última leitura</th><th>Origem</th><th>Requerimento</th><th>RA / vínculo</th><th>Versão</th><th>Leituras</th><th>Último resultado</th><th>Motivo</th><th>Detalhes</th></tr></thead>
            <tbody>
            <?php if (!$listing['rows']) : ?><tr><td colspan="9">Nenhum registro encontrado. O histórico começa a ser gravado após a atualização; execute uma sincronização para acompanhar as leituras.</td></tr><?php endif; ?>
            <?php foreach ($listing['rows'] as $row) : ?>
                <tr>
                    <td><?php echo esc_html(DRJ_Integration_Log::date_label($row->ultima_leitura)); ?><br><small>Primeira: <?php echo esc_html(DRJ_Integration_Log::date_label($row->primeira_leitura)); ?></small></td>
                    <td><?php echo esc_html($origins[$row->origem] ?? $row->origem); ?><?php if ($row->sistema) : ?><br><small><?php echo esc_html($row->sistema); ?></small><?php endif; ?></td>
                    <td><?php echo esc_html($row->requerimento !== '' ? $row->requerimento : ($row->ra !== '' ? 'Sem ID externo · por RA' : 'Ocorrência geral')); ?><?php if ($row->coligada) : ?><br><small>Coligada <?php echo esc_html($row->coligada); ?></small><?php endif; ?><?php if ($row->protocolo) : ?><br><small><?php echo esc_html($row->protocolo); ?></small><?php endif; ?></td>
                    <td><?php echo esc_html($row->ra !== '' ? $row->ra : '—'); ?><?php if ($row->vinculo_ra && $row->vinculo_ra !== $row->ra) : ?><br><strong>Vinculado: <?php echo esc_html($row->vinculo_ra); ?></strong><?php endif; ?></td>
                    <td><?php echo esc_html($row->versao ?: '—'); ?></td>
                    <td><?php echo (int) $row->tentativas; ?><br><small>Reenvios: <?php echo (int) $row->duplicados; ?></small></td>
                    <td><span class="drj-log-badge drj-log-<?php echo esc_attr($row->resultado); ?>"><?php echo esc_html($labels[$row->resultado] ?? $row->resultado); ?></span><?php if ($row->aceito) : ?><br><small>Recebimento aceito em <?php echo esc_html($row->data_aceite ?: 'data anterior'); ?></small><?php endif; ?></td>
                    <td class="drj-log-message"><?php echo esc_html($row->mensagem); ?><br><code><?php echo esc_html($row->codigo); ?></code></td>
                    <td><a href="<?php echo esc_url(add_query_arg(array_merge($base, array('pagina' => $listing['page'], 'detalhes' => $row->id)), admin_url('admin.php')) . '#historico-requerimento'); ?>">Ver histórico #<?php echo (int) $row->id; ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <nav class="drj-log-pages" aria-label="Paginação dos logs">
            <?php if ($listing['page'] > 1) : ?><a class="button" href="<?php echo esc_url(add_query_arg(array_merge($base, array('pagina' => $listing['page'] - 1)), admin_url('admin.php'))); ?>">Anterior</a><?php endif; ?>
            <span>Página <?php echo (int) $listing['page']; ?> de <?php echo (int) $listing['pages']; ?> · até 50 registros por página</span>
            <?php if ($listing['page'] < $listing['pages']) : ?><a class="button" href="<?php echo esc_url(add_query_arg(array_merge($base, array('pagina' => $listing['page'] + 1)), admin_url('admin.php'))); ?>">Próxima</a><?php endif; ?>
        </nav>
    <?php endif; ?>
    <?php if ($details) : ?>
        <section id="historico-requerimento">
        <?php if (is_wp_error($details)) : ?><p><?php echo esc_html($details->get_error_message()); ?></p><?php else : ?>
            <h2>Histórico do registro #<?php echo (int) $details['row']->id; ?></h2>
            <p><?php echo esc_html(($origins[$details['row']->origem] ?? $details['row']->origem) . ' · Requerimento ' . ($details['row']->requerimento !== '' ? $details['row']->requerimento : 'sem ID externo') . ' · RA ' . ($details['row']->vinculo_ra !== '' ? $details['row']->vinculo_ra : $details['row']->ra)); ?></p>
            <div class="drj-log-scroll"><table class="widefat striped"><thead><tr><th>Leitura</th><th>Canal</th><th>RA recebido</th><th>Versão recebida</th><th>Resultado</th><th>Motivo</th></tr></thead><tbody>
            <?php foreach ($details['events'] as $event) : ?><tr><td><?php echo esc_html(DRJ_Integration_Log::date_label($event->criado_em)); ?></td><td><?php echo esc_html($event->canal); ?></td><td><?php echo esc_html($event->ra !== '' ? $event->ra : '—'); ?></td><td><?php echo esc_html($event->versao ?: '—'); ?></td><td><span class="drj-log-badge drj-log-<?php echo esc_attr($event->resultado); ?>"><?php echo esc_html($labels[$event->resultado] ?? $event->resultado); ?></span></td><td><?php echo esc_html($event->mensagem); ?><br><code><?php echo esc_html($event->codigo); ?></code></td></tr><?php endforeach; ?>
            <?php if (!$details['events']) : ?><tr><td colspan="6">Não há leituras detalhadas nos últimos 90 dias. O registro e o bloqueio de duplicidade foram preservados.</td></tr><?php endif; ?>
            </tbody></table></div>
            <p>Exibindo até 50 leituras mais recentes deste requerimento.</p>
        <?php endif; ?>
        </section>
    <?php endif; ?>
    <p class="description">Acesso exclusivo a administradores. Horários no fuso do WordPress. O histórico detalhado é mantido por 90 dias; a identidade do requerimento e o bloqueio de duplicidade permanecem. O log guarda apenas identificadores, RA, resultado e motivo. Senhas, chaves, dados médicos, anexos e o corpo completo dos formulários não são gravados.</p>
</div>
