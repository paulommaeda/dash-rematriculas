<?php
/** Testes de cálculo financeiro. Execute apenas com WordPress de testes. */
if (PHP_SAPI !== 'cli' || getenv('DRJ_TEST_ENV') !== '1' || !getenv('DRJ_WP_BOOTSTRAP')) exit("Defina DRJ_TEST_ENV=1 e DRJ_WP_BOOTSTRAP.\n");
require getenv('DRJ_WP_BOOTSTRAP');
wp_set_current_user(1);
$checks = 0;
function drj_fin_check($value, $label) {
    global $checks;
    if (!$value) throw new RuntimeException('Falhou: ' . $label);
    $checks++;
    echo 'OK ' . $label . PHP_EOL;
}
function drj_fin_student($data) {
    return (object) array_merge(array('ra' => 'LEGADO', 'turma' => '1º ANO', 'tipo_aluno' => 'antigo', 'status_rematricula' => 1, 'parcelas' => 12, 'valor_2027' => 1000, 'desc_condicional' => 0, 'desc_incondicional' => 0, 'desc_indicacao' => 0, 'desc_irmaos' => 0, 'arquivado' => 0), $data);
}
function drj_fin_filters($changes = array()) {
    return array_merge(array('basis' => 'installment', 'scope' => 'confirmed', 'course' => '', 'class' => ''), $changes);
}
$students = array(
    drj_fin_student(array('ra' => 'A', 'turma' => '1º ANO', 'valor_2027' => '1000.25', 'desc_condicional' => '50.25', 'desc_incondicional' => 100, 'desc_indicacao' => 25, 'desc_irmaos' => 10, 'parcelas' => 10)),
    drj_fin_student(array('ra' => 'RA-OFICIAL', 'turma' => '6º ANO', 'tipo_aluno' => 'novo', 'status_rematricula' => 0, 'valor_2027' => 2000, 'desc_incondicional' => 200)),
    drj_fin_student(array('ra' => 'C', 'turma' => '3ª SÉRIE', 'valor_2027' => 0, 'desc_condicional' => 103, 'desc_indicacao' => 50, 'desc_irmaos' => 20)),
    drj_fin_student(array('ra' => 'D', 'turma' => '6º ANO', 'status_rematricula' => 0, 'valor_2027' => 1500, 'desc_condicional' => 103, 'desc_incondicional' => 1000, 'parcelas' => 6)),
    drj_fin_student(array('ra' => 'E', 'arquivado' => 1, 'valor_2027' => 9000, 'desc_incondicional' => 1000)),
);
$report = DRJ_Finance::calculate($students, drj_fin_filters());
$total = $report['summary'];
drj_fin_check($total['students'] === 3, 'visão inicial inclui rematriculados e novos e exclui pendentes');
drj_fin_check($total['gross'] === 452025, 'bruto soma os valores por aluno em centavos');
drj_fin_check($total['scholarships'] === 40500, 'total de benefícios mantém incondicional indicação e irmãos para calcular o líquido');
drj_fin_check($total['conditional'] === 15325, 'desconto condicional é separado de bolsas');
drj_fin_check($total['net'] === 396200, 'líquido deduz bolsas e desconto condicional');
drj_fin_check($total['scholarship_students'] === 2, 'contagem de bolsistas considera somente desconto incondicional');
drj_fin_check(count($report['courses']) === 3 && count($report['classes']) === 3, 'relatório agrupa por curso e turma');
drj_fin_check($report['courses'][0]['course'] === 'EFI' && $report['courses'][1]['course'] === 'EFII' && $report['courses'][2]['course'] === 'EM', 'cursos aparecem na ordem pedagógica');
drj_fin_check($report['courses'][2]['gross'] === 152000, 'valor padrão do Médio mantém referência do painel original');
$all = DRJ_Finance::calculate($students, drj_fin_filters(array('scope' => 'all')));
drj_fin_check($all['summary']['students'] === 4 && $all['summary']['gross'] === 602025, 'projeção completa inclui pendentes e exclui arquivados');
drj_fin_check($all['summary']['scholarships'] === 140500 && $all['summary']['conditional'] === 25625 && $all['summary']['net'] === 435900, 'projeção completa preserva conciliação dos descontos');
$pending = DRJ_Finance::calculate($students, drj_fin_filters(array('scope' => 'pending')));
drj_fin_check($pending['summary']['students'] === 1 && $pending['summary']['net'] === 39700, 'filtro de pendentes retorna apenas o saldo correspondente');
$annual = DRJ_Finance::calculate($students, drj_fin_filters(array('basis' => 'contract')));
drj_fin_check($annual['summary']['gross'] === 5224250 && $annual['summary']['net'] === 4591400, 'total de contratos respeita números diferentes de parcelas');
drj_fin_check($annual['summary']['scholarships'] === 459000 && $annual['summary']['conditional'] === 173850, 'bolsas e condicionais também são multiplicados pelas parcelas');
$course = DRJ_Finance::calculate($students, drj_fin_filters(array('course' => 'EFII', 'scope' => 'all')));
drj_fin_check($course['summary']['students'] === 2 && $course['summary']['gross'] === 350000, 'filtro de curso reúne os alunos corretos');
drj_fin_check(count($course['classes']) === 1 && $course['classes'][0]['scholarships'] === 120000, 'bolsas de uma turma são consolidadas');
drj_fin_check($course['catalog']['classes'] === array('6º ANO'), 'lista de turmas acompanha o curso selecionado');
$class = DRJ_Finance::calculate($students, drj_fin_filters(array('class' => '1º ANO')));
drj_fin_check($class['summary']['students'] === 1 && $class['summary']['net'] === 81500, 'filtro por turma recalcula o total geral');
foreach (array($report, $all, $annual, $course, $class, $pending) as $scope) {
    foreach (array('courses', 'classes') as $group) {
        foreach (DRJ_Finance::FIELDS as $field) {
            if (array_sum(array_column($scope[$group], $field)) !== $scope['summary'][$field]) throw new RuntimeException('Conciliação divergente: ' . $field);
        }
    }
}
drj_fin_check(true, 'totais por curso e por turma conciliam com o geral em todos os filtros');
drj_fin_check(DRJ_Finance::course('5º ANO') === 'EFI' && DRJ_Finance::course('6º ANO') === 'EFII' && DRJ_Finance::course('3ª SÉRIE') === 'EM', 'identificação de curso aceita ano e série acentuada');
drj_fin_check(DRJ_Finance::course('ANO') === 'EF' && DRJ_Finance::course('Grupo Especial') === 'OUTRO', 'turma sem curso reconhecido não recebe classificação inventada');
$negative = DRJ_Finance::calculate(array(drj_fin_student(array('valor_2027' => 100, 'desc_incondicional' => 150))), drj_fin_filters());
drj_fin_check($negative['summary']['net'] === -5000 && $negative['summary']['over_discount'] === 1, 'líquido negativo é preservado e sinalizado para revisão');
$pennies = array();
for ($i = 0; $i < 100; $i++) $pennies[] = drj_fin_student(array('valor_2027' => '0.01'));
drj_fin_check(DRJ_Finance::calculate($pennies, drj_fin_filters())['summary']['net'] === 100, 'somas repetidas não acumulam erros de ponto flutuante');
$empty = DRJ_Finance::calculate(array(), drj_fin_filters());
drj_fin_check($empty['summary']['gross'] === 0 && !$empty['courses'] && !$empty['classes'], 'relatório vazio retorna totais zerados');
drj_fin_check(DRJ_Finance::percentage(0, 0) === '—', 'base zerada não provoca divisão por zero');
$invalid = DRJ_Finance::filters(array('drj_fin_basis' => array('x'), 'drj_fin_scope' => 'inventado', 'drj_fin_course' => 'fraude'));
drj_fin_check($invalid['basis'] === 'installment' && $invalid['scope'] === 'confirmed' && $invalid['course'] === '', 'filtros inválidos voltam a valores permitidos');
drj_fin_check(shortcode_exists('dash_financeiro'), 'shortcode financeiro está registrado');
$blank = DRJ_Finance::calculate(array(drj_fin_student(array('turma' => '', 'valor_2027' => 500))), drj_fin_filters(array('class' => '__sem_turma__')));
drj_fin_check($blank['summary']['students'] === 1 && $blank['catalog']['classes'] === array('__sem_turma__'), 'filtro de turma não informada tem valor próprio');
$html = DRJ_Finance::render();
drj_fin_check(strpos($html, 'data-drj-finance') !== false && strpos($html, 'Totais por turma') !== false, 'tela financeira é renderizada no servidor');
$previous_get = $_GET;
$_GET = array('drj_painel' => 'financeiro');
drj_fin_check(strpos(DRJ_Dashboard::shortcode(), 'data-drj-finance') === false, 'parâmetro de URL não transforma acompanhamento em financeiro');
$_GET = array();
drj_fin_check(strpos(DRJ_Finance::shortcode(), 'data-drj-finance') !== false, 'shortcode dedicado abre o financeiro por padrão');
$_GET = array('drj_painel' => 'rematriculas');
drj_fin_check(strpos(DRJ_Finance::shortcode(), 'data-drj-finance') !== false, 'financeiro permanece em sua página independente');
$_GET = $previous_get;
drj_fin_check(strpos($html, 'Desc. incondicional') !== false && strpos($html, '% bolsa (incond.)') !== false, 'tabelas exibem coluna incondicional e base do percentual');
drj_fin_check(DRJ_Finance::percentage($total['unconditional'], $total['gross']) === '6,6%', 'percentual geral usa somente incondicional');
drj_fin_check(DRJ_Finance::percentage($report['courses'][0]['unconditional'], $report['courses'][0]['gross']) === '10,0%', 'percentual do curso exclui indicação irmãos e condicional');
drj_fin_check(DRJ_Finance::percentage($report['courses'][2]['unconditional'], $report['courses'][2]['gross']) === '0,0%', 'curso com indicação e irmãos mas sem incondicional tem bolsa zero');
wp_set_current_user(0);
drj_fin_check(strpos(DRJ_Finance::render(), 'data-drj-finance') === false, 'renderização financeira sem acesso não entrega dados');
echo 'TOTAL: ' . $checks . ' verificações financeiras aprovadas.' . PHP_EOL;
