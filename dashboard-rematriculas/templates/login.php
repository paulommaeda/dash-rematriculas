<?php defined('ABSPATH') || exit; ?>
<div class="drj-login">
    <div class="drj-login-icon" aria-hidden="true">🔒</div>
    <h2>Acesso restrito</h2>
    <p>Digite a senha da secretaria para acessar o Dashboard.</p>
    <?php if (DRJ_Auth::login_error()) : ?>
        <p class="drj-error" role="alert"><?php echo esc_html(DRJ_Auth::login_error()); ?></p>
    <?php endif; ?>
    <form method="post">
        <input type="hidden" name="drj_auth_action" value="login">
        <?php wp_nonce_field('drj_login', 'drj_login_nonce'); ?>
        <label for="drj-password">Senha do painel</label>
        <input id="drj-password" type="password" name="drj_senha" autocomplete="current-password" required>
        <button type="submit" class="drj-mod2-btn">Acessar Dashboard</button>
    </form>
</div>
