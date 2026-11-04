<?php
/** Testes dos valores centrais. Use somente WordPress descartável. */
if (PHP_SAPI !== 'cli' || getenv('DRJ_TEST_ENV') !== '1' || !getenv('DRJ_WP_BOOTSTRAP')) exit("Defina DRJ_TEST_ENV=1 e DRJ_WP_BOOTSTRAP.\n");
require getenv('DRJ_WP_BOOTSTRAP');
wp_set_current_user(1);
global $wpdb;
$table = DRJ_Database::table();
$backup = get_option(DRJ_Pricing::OPTION, false);
$flags = $wpdb->get_results("SELECT id, check_comercial, check_secretaria, check_financeiro, check_ti FROM `$table`");
$total = 0;
function drj_pricing_check($valid, $label) {
    global $total;
    if (!$valid) throw new RuntimeException('Falhou: ' . $label);
    $total++;
    echo 'OK ' . $label . PHP_EOL;
}
function drj_pricing_flags($id) {
    $student = DRJ_Students::find($id);
    return array((int) $student->check_comercial, (int) $student->check_secretaria, (int) $student->check_financeiro, (int) $student->check_ti);
}
try {
    delete_option(DRJ_Pricing::OPTION);
    drj_pricing_check(DRJ_Pricing::base('1º ANO') === '1350.00' && DRJ_Pricing::base('6º ANO') === '1290.00' && DRJ_Pricing::base('3ª SÉRIE') === '1520.00', 'cadastro inicial mantém as referências originais por curso');
    drj_pricing_check(DRJ_Pricing::conditional() === '103.00', 'desconto condicional inicial é único');
    $wpdb->query("UPDATE `$table` SET check_comercial = 1, check_secretaria = 1, check_financeiro = 1, check_ti = 1");
    $config = DRJ_Pricing::defaults();
    $config['bases']['EFI'] = '1400.50';
    $config['bases']['EFII'] = '1500.25';
    $config['bases']['EM'] = '1800.00';
    $config['conditional'] = '120.00';
    drj_pricing_check(!is_wp_error(DRJ_Pricing::save($config)), 'administrador cadastra os valores gerais');
    drj_pricing_check(drj_pricing_flags(1) === array(0, 0, 0, 0) && drj_pricing_flags(2) === array(0, 0, 0, 0), 'primeiro cadastro reinicia as conferências');
    $first = DRJ_Students::view_data(DRJ_Students::find(1));
    drj_pricing_check($first['financeiro']['valor_2027'] === 1500.25 && $first['financeiro']['desc_condicional'] === 120.0, 'aluno existente assume base do curso e condicional geral');
    $a = clone DRJ_Students::find(1); $b = clone $a;
    $a->valor_2027 = 1; $a->desc_condicional = 0;
    $b->valor_2027 = 99999; $b->desc_condicional = 999;
    drj_pricing_check(DRJ_Pricing::financial($a) === DRJ_Pricing::financial($b), 'valores individuais antigos não criam exceções à regra central');
    $a->turma = '3ª SÉRIE';
    drj_pricing_check(DRJ_Pricing::financial($a)['valor_2027'] === 1800.0, 'mudança de curso muda automaticamente o valor base');
    $a->turma = '1º ANO';
    drj_pricing_check(DRJ_Pricing::financial($a)['desc_condicional'] === DRJ_Pricing::financial($b)['desc_condicional'], 'condicional é igual em cursos diferentes');
    $report = DRJ_Finance::calculate(array($a, $b), array('basis' => 'installment', 'scope' => 'all', 'course' => '', 'class' => ''));
    drj_pricing_check($report['summary']['gross'] === 290075 && $report['summary']['conditional'] === 24000, 'financeiro usa a mesma referência central do acompanhamento');
    $wpdb->query("UPDATE `$table` SET check_comercial = 1, check_secretaria = 1, check_financeiro = 1, check_ti = 1");
    $unchanged = DRJ_Pricing::save($config);
    drj_pricing_check(!is_wp_error($unchanged) && !$unchanged['changed'] && drj_pricing_flags(1) === array(1, 1, 1, 1), 'salvar configuração sem mudanças preserva conferências');
    $changed = $config; $changed['bases']['EM'] = '1810.00';
    drj_pricing_check(!is_wp_error(DRJ_Pricing::save($changed)), 'valor base de um curso pode ser alterado');
    drj_pricing_check(drj_pricing_flags(2) === array(0, 0, 0, 0) && drj_pricing_flags(1) === array(1, 1, 1, 1), 'alteração de base reinicia somente as conferências do curso afetado');
    $changed['conditional'] = '0.00';
    drj_pricing_check(!is_wp_error(DRJ_Pricing::save($changed)) && DRJ_Pricing::conditional() === '0.00', 'condicional zero é aceito sem voltar a 103');
    drj_pricing_check(drj_pricing_flags(1) === array(0, 0, 0, 0) && drj_pricing_flags(3) === array(0, 0, 0, 0), 'alterar condicional reinicia conferências de cursos diferentes');
    $invalid = $changed; $invalid['bases']['EFI'] = '-10';
    drj_pricing_check(is_wp_error(DRJ_Pricing::save($invalid)) && DRJ_Pricing::get() === $changed, 'valor negativo é recusado sem gravação parcial');
    $invalid = $changed; $invalid['conditional'] = array('120');
    drj_pricing_check(is_wp_error(DRJ_Pricing::save($invalid)), 'campo monetário estruturado é recusado');
    $invalid = $changed; unset($invalid['bases']['EM']);
    drj_pricing_check(is_wp_error(DRJ_Pricing::save($invalid)), 'cadastro incompleto é recusado');
    $localized = $changed; $localized['bases']['EFI'] = '1.450,75';
    drj_pricing_check(!is_wp_error(DRJ_Pricing::save($localized)) && DRJ_Pricing::base('1º ANO') === '1450.75', 'formato monetário brasileiro é normalizado');
    $localized = DRJ_Pricing::get(); $localized['bases']['EFI'] = '0.00';
    drj_pricing_check(!is_wp_error(DRJ_Pricing::save($localized)) && DRJ_Pricing::base('1º ANO') === '0.00', 'base zero cadastrada é respeitada');
    wp_set_current_user(0);
    drj_pricing_check(is_wp_error(DRJ_Pricing::save($config)), 'visitante não altera valores gerais');
    wp_set_current_user(1);
    $html = DRJ_Dashboard::render();
    drj_pricing_check(strpos($html, 'readonly aria-readonly="true"') !== false && strpos($html, 'drj-input-fixed') !== false, 'campos fixos aparecem somente para consulta no dashboard');
    echo 'TOTAL: ' . $total . ' verificações de valores centrais aprovadas.' . PHP_EOL;
} finally {
    if ($backup === false) delete_option(DRJ_Pricing::OPTION); else update_option(DRJ_Pricing::OPTION, $backup, false);
    foreach ($flags as $student) {
        $id = $student->id;
        unset($student->id);
        $wpdb->update($table, (array) $student, array('id' => $id));
    }
}
