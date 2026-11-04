<?php defined('ABSPATH') || exit; ?>
<div class="drj-login drj-finance-login">
    <div class="drj-login-icon" aria-hidden="true">🔒</div><h2>Acesso ao financeiro</h2>
    <p>Esta área exige autorização própria. Entre com uma conta WordPress autorizada<?php echo DRJ_Finance_Auth::password_enabled() ? ' ou com a senha exclusiva do financeiro' : ''; ?>.</p>
    <?php if (DRJ_Finance_Auth::login_error()) : ?><p class="drj-error" role="alert"><?php echo esc_html(DRJ_Finance_Auth::login_error()); ?></p><?php endif; ?>
    <?php if (DRJ_Finance_Auth::password_enabled()) : ?>
        <form method="post"><input type="hidden" name="drj_finance_auth_action" value="login">
            <?php wp_nonce_field('drj_finance_login', 'drj_finance_login_nonce'); ?>
            <label for="drj-finance-password">Senha exclusiva do financeiro</label><input id="drj-finance-password" type="password" name="drj_finance_password" autocomplete="current-password" required>
            <button type="submit" class="drj-mod2-btn">Acessar financeiro</button>
        </form>
    <?php endif; ?>
    <p><a href="<?php echo esc_url(wp_login_url(get_permalink() ?: home_url('/'))); ?>">Entrar com minha conta WordPress</a></p>
</div>
