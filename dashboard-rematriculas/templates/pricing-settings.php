<?php defined('ABSPATH') || exit; $pricing = DRJ_Pricing::get(); ?>
<section class="drj-pricing-settings">
    <h2>Valor base por curso e desconto condicional</h2>
    <p>Cadastre o valor base de cada curso e um desconto condicional único. Esses valores são aplicados a todos os alunos e aos cálculos dos dois dashboards.</p>
    <?php $pricing_error = get_transient('drj_pricing_error_' . get_current_user_id()); ?>
    <?php if ($pricing_error) : delete_transient('drj_pricing_error_' . get_current_user_id()); ?><div class="notice notice-error"><p><?php echo esc_html($pricing_error); ?></p></div><?php elseif (isset($_GET['drj_pricing_saved']) && $_GET['drj_pricing_saved'] === '1') : ?><div class="notice notice-success"><p>Valores gerais salvos e aplicados aos alunos. As conferências afetadas foram reiniciadas.</p></div><?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="drj_save_pricing"><?php wp_nonce_field('drj_save_pricing'); ?>
        <table class="widefat striped drj-pricing-table"><thead><tr><th>Curso</th><th>Valor base por parcela (R$)</th></tr></thead><tbody>
            <?php foreach (DRJ_Pricing::labels() as $course => $label) : ?><tr><th scope="row"><label for="drj-base-<?php echo esc_attr($course); ?>"><?php echo esc_html($label); ?></label></th><td><input type="number" id="drj-base-<?php echo esc_attr($course); ?>" name="bases[<?php echo esc_attr($course); ?>]" value="<?php echo esc_attr($pricing['bases'][$course]); ?>" min="0" max="99999999.99" step="0.01" required></td></tr><?php endforeach; ?>
        </tbody></table>
        <label for="drj-global-conditional">Desconto condicional por parcela, igual para todos (R$)</label><input type="number" id="drj-global-conditional" name="conditional" value="<?php echo esc_attr($pricing['conditional']); ?>" min="0" max="99999999.99" step="0.01" required>
        <p class="description">O valor base acompanha o curso identificado pela turma. Incondicional, indicação e irmãos continuam individuais. Alterar o valor base reinicia as quatro conferências dos alunos do curso afetado; alterar o condicional reinicia as conferências de todos. O primeiro cadastro confirma a regra central e reinicia as conferências.</p>
        <p><button type="submit" class="button button-primary">Salvar e aplicar a todos</button></p>
    </form>
</section>
