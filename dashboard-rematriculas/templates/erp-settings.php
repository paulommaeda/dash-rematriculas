<?php defined('ABSPATH') || exit;
$erp = DRJ_ERP::settings();
$erp_error = get_transient('drj_erp_error_' . get_current_user_id());
delete_transient('drj_erp_error_' . get_current_user_id());
$erp_report = (array) get_option(DRJ_ERP::REPORT, array());
$erp_run = get_option(DRJ_ERP::RUN, false);
?>
<section id="integracao-erp" class="drj-erp-settings" style="max-width:850px;">
    <h2>API de rematrículas e consulta ao ERP</h2>
    <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=drj-integracao-logs')); ?>">Ver logs de integração</a> Acompanhe cada leitura, decisão e motivo, com os reenvios agrupados no mesmo requerimento.</p>
    <?php if ($erp_error) : ?><div class="notice notice-error inline"><p><?php echo esc_html($erp_error); ?></p></div><?php endif; ?>
    <?php if (isset($_GET['drj_erp_saved']) && $_GET['drj_erp_saved'] === '1') : ?><div class="notice notice-success inline"><p>Integração salva.</p></div><?php endif; ?>
    <h3>Receber de outro sistema</h3>
    <p>O sistema pode enviar um <strong>POST</strong> para <code><?php echo esc_html(rest_url('drj/v1/rematriculas')); ?></code> usando HTTPS, <code>Content-Type: application/json</code> e o cabeçalho <code>X-DRJ-Key</code> com a chave da API cadastrada em “Acesso e integração”.</p>
    <pre style="white-space:pre-wrap;">{"ra":"000123","data_rematricula":"2026-11-13T13:00:00Z"}</pre>
    <p class="description">Para identificar cada requerimento externo, envie também <code>id_requerimento</code> e, opcionalmente, <code>sistema_origem</code>. Reenvie sempre com o mesmo identificador. Sem identificador, o controle usa o RA. O ERP usa automaticamente coligada + ID do requerimento.</p>
    <p>Somente alunos já cadastrados são confirmados. Reenvios preservam a primeira confirmação. Alunos arquivados continuam ocultos. Valores, turma e conferências são preservados, como no Fluent Forms.</p>
    <p><a href="<?php echo esc_url(DRJ_URL . 'API-REMATRICULAS.md'); ?>" target="_blank" rel="noopener">Abrir documentação para o integrador</a></p>
    <h3>Buscar requerimentos no ERP 0.19.23 ou posterior</h3>
    <p>Use esta opção quando o ERP disponibilizar a API de consulta. No ERP, crie uma conta com o perfil <strong>Integração: leitura de requerimentos</strong>, autorize a coligada e gere uma <strong>Senha de aplicativo</strong>.</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="drj_save_erp">
        <?php wp_nonce_field('drj_save_erp'); ?>
        <label>Coligada autorizada<input type="text" name="drj_erp[company]" inputmode="numeric" pattern="[1-9][0-9]{0,17}" value="<?php echo esc_attr($erp['company']); ?>" placeholder="Ex.: 1"></label>
        <p class="description">Também é exigida para receber objetos completos do ERP pela API. O envio simples por RA usa a chave do dashboard.</p>
        <label>URL de consulta dos requerimentos<input type="url" name="drj_erp[url]" value="<?php echo esc_attr($erp['url']); ?>" placeholder="https://SEU-ERP/wp-json/erp-educacional/v1/integracao/requerimentos"></label>
        <label>Usuário da API no ERP<input type="text" name="drj_erp[username]" value="<?php echo esc_attr($erp['username']); ?>" autocomplete="off"></label>
        <label>Nova senha de aplicativo do ERP<input type="password" name="drj_erp[password]" value="" autocomplete="new-password"></label>
        <p class="description"><?php echo $erp['password'] !== '' ? 'Há uma senha cadastrada. Deixe vazio para mantê-la.' : 'Nenhuma senha de aplicativo cadastrada.'; ?> Alterar URL ou usuário exige informar a senha novamente.</p>
        <label><input type="checkbox" name="drj_erp[clear_password]" value="1"> Remover a senha de aplicativo armazenada</label>
        <label>Caminho do RA informado no formulário (opcional)<input type="text" name="drj_erp[ra_path]" value="<?php echo esc_attr($erp['ra_path']); ?>" placeholder="Ex.: dados.campos.ra_aluno"></label>
        <p class="description">O RA de <code>resultado.ra</code> tem prioridade após aprovação. Antes disso, este caminho permite localizar o RA no formulário. Não há associação por nome ou CPF.</p>
        <label>Considerar envios criados a partir de (opcional)<input type="date" name="drj_erp[created_since]" value="<?php echo esc_attr($erp['created_since']); ?>"></label>
        <p class="description">Data no fuso do WordPress. Informe o início da campanha para evitar confirmar o acompanhamento com requerimentos antigos.</p>
        <label><input type="checkbox" name="drj_erp[enabled]" value="1" <?php checked($erp['enabled']); ?>> Consultar automaticamente a cada 15 minutos</label>
        <p class="description">Depende do agendamento do WordPress e das visitas ao site. A consulta é somente de leitura; não aprova requerimentos nem envia e-mails. Pendentes e aprovados com RA são recebidos; rejeitados são ignorados.</p>
        <p><button class="button button-primary" type="submit">Salvar integração com ERP</button></p>
    </form>
    <div data-drj-erp data-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-nonce="<?php echo esc_attr(wp_create_nonce('drj_sync_erp')); ?>">
        <p><button class="button" type="button" data-erp-sync><?php echo $erp_run ? 'Continuar sincronização' : 'Sincronizar ERP agora'; ?></button> <button class="button" type="button" data-erp-full>Refazer leitura completa</button></p>
        <p data-erp-progress role="status" aria-live="polite"></p>
        <?php if (!empty($erp_report['time'])) : ?><p>Última tentativa: <?php echo esc_html(wp_date('d/m/Y H:i', (int) $erp_report['time'])); ?>. <?php echo !empty($erp_report['done']) ? 'Rodada concluída.' : 'Rodada incompleta; continue a sincronização.'; ?></p><?php endif; ?>
        <?php if (!empty($erp_report['error'])) : ?><p class="drj-error"><?php echo esc_html($erp_report['error']); ?></p><?php endif; ?>
        <?php if (!empty($erp_report['totals'])) : ?>
            <p><?php echo esc_html('Lidos: ' . $erp_report['totals']['lidos'] . ' · Confirmados: ' . $erp_report['totals']['confirmado'] . ' · Já confirmados: ' . $erp_report['totals']['ja_confirmado'] . ' · Arquivados: ' . $erp_report['totals']['arquivado'] . ' · RA não cadastrado: ' . $erp_report['totals']['nao_encontrado'] . ' · Sem RA: ' . $erp_report['totals']['sem_ra'] . ' · RA duplicado: ' . $erp_report['totals']['ra_duplicado'] . ' · Rejeitados: ' . $erp_report['totals']['rejeitado'] . ' · Anteriores ao período: ' . $erp_report['totals']['anterior_ao_periodo']); ?></p>
            <p><?php echo esc_html('Versões antigas ignoradas: ' . ($erp_report['totals']['versao_antiga'] ?? 0) . ' · Associação a outro RA bloqueada: ' . ($erp_report['totals']['conflito_requerimento'] ?? 0) . ' · Itens inválidos: ' . ($erp_report['totals']['invalidos'] ?? 0)); ?></p>
        <?php endif; ?>
        <?php if (!empty($erp_report['warnings'])) : ?>
            <p>Requerimentos para conferir (até 10 por rodada): <?php echo esc_html(implode(', ', array_map(function ($warning) { return '#' . $warning['idrequerimento'] . ' (' . $warning['situacao'] . ')'; }, $erp_report['warnings']))); ?>.</p>
        <?php endif; ?>
        <p class="description">As próximas rodadas retomam alterações recentes. Uma leitura completa diária revisita requerimentos sem correspondência; use “Refazer leitura completa” para tentar novamente após cadastrar ou corrigir um RA. Dados médicos, anexos e respostas do formulário não são armazenados no dashboard.</p>
    </div>
</section>
