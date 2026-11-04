<?php
defined('ABSPATH') || exit;
$complete = $student->check_comercial && $student->check_secretaria && $student->check_financeiro && $student->check_ti;
$row_class = 'linha-aluno' . ($complete ? ' drj-linha-ok' : '') . ($row['atencao'] ? ' drj-linha-atencao' : '') . ($row['arquivado'] ? ' drj-linha-arquivada' : '');
$final = $can_manage_financial ? $row['financeiro']['valor_2027'] - array_sum(array_diff_key($row['financeiro'], array('valor_2027' => 1))) : 0;
?>
<tr data-student-id="<?php echo esc_attr($row['id']); ?>" class="<?php echo esc_attr($row_class); ?>">
    <td class="drj-ra"><?php echo esc_html($row['ra']); ?></td>
    <td><button type="button" class="drj-student-name" data-action="details"><?php echo esc_html($row['nome']); ?></button><small class="drj-row-save" aria-live="polite"></small></td>
    <td class="drj-class"><?php echo esc_html($row['turma']); ?></td>
    <td>
        <?php if ($row['arquivado']) : ?><span class="drj-status-badge drj-badge-archived">Arquivado</span><?php endif; ?>
        <span class="drj-enrollment-status drj-status-badge <?php echo $row['novo'] ? 'drj-badge-new' : ($row['status'] ? 'drj-badge-ok' : 'drj-badge-pend'); ?>"><?php echo $row['novo'] ? 'Novo' : ($row['status'] ? 'Rematriculado' : 'Pendente'); ?></span>
    </td>
    <?php if ($can_manage_financial) : foreach ($row['financeiro'] as $field => $value) : ?>
        <td><input type="number" min="0" step="0.01" data-financial="<?php echo esc_attr($field); ?>" class="drj-input-fin" value="<?php echo esc_attr(number_format($value, 2, '.', '')); ?>" aria-label="<?php echo esc_attr($field . ' — ' . $row['nome']); ?>" <?php disabled($row['arquivado'], 1); ?>></td>
    <?php endforeach; ?>
    <td><output class="drj-final"><?php echo esc_html(number_format($final, 2, ',', '.')); ?></output></td>
    <td><input type="number" min="1" max="31" step="1" class="drj-input-mini" data-financial="vencimento" value="<?php echo esc_attr($row['vencimento']); ?>" aria-label="Dia do vencimento" <?php disabled($row['arquivado'], 1); ?>></td>
    <td><input type="number" min="1" max="12" step="1" class="drj-input-mini" data-financial="parcelas" value="<?php echo esc_attr($row['parcelas']); ?>" aria-label="Parcelas" <?php disabled($row['arquivado'], 1); ?>></td>
    <?php endif; ?>
    <td><div class="drj-checks-wrapper">
        <?php foreach (array('comercial' => 'Com', 'secretaria' => 'Sec', 'financeiro' => 'Fin', 'ti' => 'TI') as $sector => $label) : $field = 'check_' . $sector; ?>
            <button type="button" class="btn-check <?php echo $student->$field ? 'btn-check-on' : 'btn-check-off'; ?>" data-action="check" data-sector="<?php echo esc_attr($sector); ?>" data-status="<?php echo (int) $student->$field; ?>" aria-pressed="<?php echo $student->$field ? 'true' : 'false'; ?>" title="<?php echo esc_attr(ucfirst($sector)); ?>" <?php disabled($row['arquivado'], 1); ?>><?php echo esc_html($label); ?></button>
        <?php endforeach; ?>
    </div></td>
    <td><button type="button" class="drj-row-action" data-action="<?php echo $row['arquivado'] ? 'restore' : 'archive'; ?>"><?php echo $row['arquivado'] ? '↩ Restaurar' : 'Arquivar'; ?></button></td>
</tr>
