<?php defined('ABSPATH') || exit; ?>
<div class="wrap drj-settings">
    <h1>Configurações do Dashboard de Rematrículas</h1>
    <?php include DRJ_PATH . 'templates/pricing-settings.php'; ?>
    <?php if ($saved) : ?><div class="notice notice-success"><p>Configurações salvas.</p></div><?php endif; ?>
    <?php if (!empty($settings_error)) : ?><div class="notice notice-error"><p><?php echo esc_html($settings_error); ?></p></div><?php endif; ?>
    <form method="post">
        <?php wp_nonce_field('drj_salvar_config', 'drj_webhook_nonce'); ?>
        <section><h2>Acesso e integração</h2>
            <p>As credenciais já configuradas na versão 4.0 são preservadas. Em uma instalação nova ou atualização direta da versão 3.9, defina a senha e a chave da API. Deixe os campos vazios para manter configurações existentes.</p>
            <label>Nova senha do painel<input type="password" name="drj_password" autocomplete="new-password" <?php disabled(defined('DRJ_DASHBOARD_PASSWORD')); ?>></label>
            <p class="description">A troca invalida os acessos por senha em andamento. Administradores usam sua conta do WordPress.</p>
            <label>Nova chave da API<input type="password" name="drj_api_secret" autocomplete="new-password" <?php disabled(defined('DRJ_API_SECRET')); ?>></label>
            <p class="description">Se mudar a chave, atualize também o site de requerimentos que envia os alunos.</p>
        </section>
        <section><h2>Acesso exclusivo ao financeiro</h2>
            <input type="hidden" name="drj_finance_settings" value="1">
            <p>Essa autorização controla o dashboard financeiro consolidado. Os valores individuais continuam disponíveis no acompanhamento para validação comercial.</p>
            <p>O financeiro fica separado do acompanhamento. Administradores têm acesso. Autorize outras contas abaixo ou configure uma senha exclusiva para a página com <code>[dash_financeiro]</code>.</p>
            <?php $finance_users = get_users(array('orderby' => 'display_name', 'fields' => array('ID', 'display_name', 'user_login'))); $allowed_finance_users = array_map('intval', (array) get_option('drj_finance_allowed_users', array())); ?>
            <label>Contas WordPress autorizadas<select name="drj_finance_users[]" multiple size="7" style="width:100%; max-width:650px;">
                <?php foreach ($finance_users as $finance_user) : ?><option value="<?php echo (int) $finance_user->ID; ?>" <?php selected(in_array((int) $finance_user->ID, $allowed_finance_users, true)); ?>><?php echo esc_html($finance_user->display_name . ' (' . $finance_user->user_login . ')'); ?></option><?php endforeach; ?>
            </select></label>
            <p class="description">Use Ctrl/Cmd para selecionar mais de uma conta. Desmarcar uma conta retira o acesso financeiro dela. Administradores continuam autorizados.</p>
            <label><input type="checkbox" name="drj_finance_password_enabled" value="1" <?php checked(DRJ_Finance_Auth::password_enabled()); disabled(defined('DRJ_FINANCE_PASSWORD')); ?>> Permitir acesso pela senha exclusiva do financeiro</label>
            <label>Nova senha exclusiva do financeiro<input type="password" name="drj_new_finance_password" autocomplete="new-password" <?php disabled(defined('DRJ_FINANCE_PASSWORD')); ?>></label>
            <p class="description">Use uma senha diferente da senha de acompanhamento. Deixe o campo vazio para manter a senha atual. Desativar a opção acima bloqueia as sessões por senha. A troca de senha também invalida sessões financeiras anteriores.</p>
        </section>
        <section><h2>Sincronização com Fluent Forms</h2>
            <label>ID do formulário<input type="number" name="drj_ff_form_id" min="0" value="<?php echo esc_attr(get_option('drj_ff_form_id', 0)); ?>"></label>
            <p class="description">0 mantém a busca em todos os formulários, como na versão anterior.</p>
            <label>Nome do campo que contém o RA<input type="text" name="drj_ff_ra_field" value="<?php echo esc_attr(get_option('drj_ff_ra_field', '')); ?>" placeholder="Ex.: ra_aluno"></label>
            <p class="description">Use o nome interno do campo. Vazio mantém a compatibilidade com a leitura dos valores do formulário.</p>
        </section>
        <section><h2>Webhooks · MS Teams / Power Automate</h2>
            <p>Campos disponíveis: <code>[aluno]</code>, <code>[ra]</code>, <code>[turma]</code>, <code>[setor]</code> e <code>[acao]</code>. URL vazia desativa o envio daquele setor.</p>
            <?php foreach (array('comercial' => 'Comercial', 'secretaria' => 'Secretaria', 'financeiro' => 'Financeiro', 'ti' => 'Tecnologia (TI)') as $sector => $label) : ?>
                <h3><?php echo esc_html($label); ?></h3>
                <label>URL do webhook<input type="url" name="webhook_<?php echo esc_attr($sector); ?>" value="<?php echo esc_attr(get_option('drj_webhook_' . $sector, '')); ?>" placeholder="https://..."></label>
                <label>Texto da mensagem<textarea name="tpl_<?php echo esc_attr($sector); ?>" rows="5"><?php echo esc_textarea(get_option('drj_tpl_' . $sector, DRJ_Admin::default_template())); ?></textarea></label>
            <?php endforeach; ?>
        </section>
        <?php if (DRJ_Updater::can_manage()) : ?>
            <section id="atualizacoes"><h2>Atualizações pelo GitHub</h2>
                <p>Versão instalada: <strong><?php echo esc_html(DRJ_VERSION); ?></strong>. As novas versões aparecem em <strong>Plugins</strong> e em <strong>Painel → Atualizações</strong>.</p>
                <label><input type="checkbox" name="drj_auto_updates" value="1" <?php checked(DRJ_Updater::auto_updates_enabled()); ?>> Instalar automaticamente novas versões deste plugin</label>
                <p class="description">A mesma opção pode ser alterada na lista de plugins do WordPress. O agendamento segue as regras e permissões de atualização da hospedagem.</p>
            </section>
        <?php endif; ?>
        <p><button type="submit" name="drj_save_templates" class="button button-primary">Salvar configurações</button></p>
    </form>
    <?php if (DRJ_Updater::can_manage()) : $last_check = (array) get_site_option('drj_last_update_check', array()); ?>
        <section class="drj-update-check">
            <?php if (!empty($last_check['time'])) : ?><p>Última consulta: <?php echo esc_html(wp_date('d/m/Y H:i', (int) $last_check['time'])); ?>.</p><?php endif; ?>
            <?php if (!empty($last_check['error'])) : ?><p class="drj-error"><?php echo esc_html($last_check['error']); ?></p><?php endif; ?>
            <?php if (isset($_GET['drj_update_checked']) && empty($last_check['error'])) : ?><p>Consulta concluída. Confira as versões disponíveis em <a href="<?php echo esc_url(self_admin_url('update-core.php')); ?>">Atualizações</a>.</p><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="drj_check_updates">
                <?php wp_nonce_field('drj_check_updates'); ?>
                <button type="submit" class="button">Verificar atualizações agora</button>
            </form>
        </section>
    <?php endif; ?>
</div>
