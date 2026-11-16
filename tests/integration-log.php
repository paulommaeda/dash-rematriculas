<?php
/** WordPress descartável com dados sintéticos; não execute sobre um banco real. */
if (PHP_SAPI !== 'cli' || getenv('DRJ_TEST_ENV') !== '1' || !getenv('DRJ_WP_BOOTSTRAP')) exit("Defina DRJ_TEST_ENV=1 e DRJ_WP_BOOTSTRAP.\n");
require getenv('DRJ_WP_BOOTSTRAP');
$total = 0;
function drj_log_check($valid, $label) {
    global $total;
    if (!$valid) throw new RuntimeException('Falhou: ' . $label);
    $total++; echo 'OK ' . $label . PHP_EOL;
}
function drj_log_row($id) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare('SELECT * FROM `' . DRJ_Integration_Log::table() . '` WHERE id = %d', $id));
}
function drj_log_erp($id, $ra, $version = '1', $status = 'pendente') {
    return array('idrequerimento' => (string) $id, 'codcoligada' => '9001', 'versao' => $version, 'status' => $status, 'protocolo' => 'REQ-TESTE-' . $id,
        'criado_em' => '2026-11-16T13:00:00Z', 'resultado' => array('ra' => $ra));
}
$students = DRJ_Database::table(); $table = DRJ_Integration_Log::table(); $events = DRJ_Integration_Log::table(true);
$tables_backup = array();
foreach (array($table, $events) as $name) $tables_backup[$name] = $wpdb->get_results("SELECT * FROM `$name`", ARRAY_A);
$wpdb->query("DELETE FROM `$events`"); $wpdb->query("DELETE FROM `$table`");
$prefix = '000LOG-' . wp_generate_password(10, false, false) . '-';
$ids = array(); $user = get_current_user_id(); $old_version = get_option('drj_versao_banco');
try {
    wp_set_current_user(1);
    foreach (array('A','B','C','D','E','F','G','H','I','J','K','L') as $suffix) {
        $wpdb->insert($students, array('ra' => $prefix . $suffix, 'nome_aluno' => 'Aluno sintético do log', 'turma' => '6º ANO', 'tipo_aluno' => 'antigo',
            'desc_incondicional' => 100, 'desc_condicional' => 25, 'valor_2027' => 999, 'check_comercial' => 1, 'check_secretaria' => 1, 'check_financeiro' => 1, 'check_ti' => 1));
        $ids[$suffix] = (int) $wpdb->insert_id;
    }
    $before = (array) DRJ_Students::find($ids['A']);
    $first = DRJ_Rematriculas::api_item(array('ra' => $prefix . 'A', 'id_requerimento' => 'REQ-1', 'sistema_origem' => 'teste', 'data_rematricula' => '2026-11-16T13:00:00Z',
        'senha' => 'NAO-GRAVAR-CREDENCIAL', 'dados_medicos' => 'NAO-GRAVAR-DADOS-MEDICOS'));
    drj_log_check(!is_wp_error($first) && $first['situacao'] === 'confirmado' && !empty($first['log_id']), 'aceite retorna referência ao log');
    $row = drj_log_row($first['log_id']);
    drj_log_check($row->resultado === 'aceito' && (int) $row->aceito === 1 && $row->vinculo_ra === $prefix . 'A', 'registro único conserva aceite e vínculo com RA');
    $event = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$events` WHERE requerimento_id = %d", $row->id));
    drj_log_check($event->resultado === 'aceito' && $event->canal === 'api', 'histórico registra leitura e decisão final');
    $after = (array) DRJ_Students::find($ids['A']);
    unset($before['status_rematricula'], $before['data_rematricula'], $after['status_rematricula'], $after['data_rematricula']);
    drj_log_check($before === $after, 'log não altera valores, conferências ou cadastro');
    $snapshot = $wpdb->get_results("SELECT * FROM `$students` ORDER BY id", ARRAY_A);
    $ledger_count = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
    update_option('drj_versao_banco', '4.0.0', false);
    drj_log_check(DRJ_Database::upgrade() && DRJ_Database::ready(), 'migração cria estrutura e conclui versão nova');
    drj_log_check($snapshot === $wpdb->get_results("SELECT * FROM `$students` ORDER BY id", ARRAY_A) && $ledger_count === $wpdb->get_var("SELECT COUNT(*) FROM `$table`"), 'migração preserva alunos e registros de integração existentes');
    $unique = $wpdb->get_row("SHOW INDEX FROM `$table` WHERE Key_name = 'drj_chave'");
    drj_log_check($unique && !(int) $unique->Non_unique, 'banco impõe identidade única por requerimento');
    $repeat = DRJ_Rematriculas::api_item(array('ra' => $prefix . 'A', 'id_requerimento' => 'REQ-1', 'sistema_origem' => 'teste', 'data_rematricula' => '2026-12-01T13:00:00Z'));
    drj_log_check($repeat['log_id'] === $first['log_id'] && !empty($repeat['duplicado']) && !$repeat['alterado'] && $repeat['data_rematricula'] === $first['data_rematricula'], 'mesmo ID reaproveita log e primeira confirmação');
    $row = drj_log_row($first['log_id']);
    drj_log_check((int) $row->tentativas === 2 && (int) $row->duplicados === 1 && (int) $row->aceito === 1, 'reenvio aumenta contadores sem duplicar registro');
    drj_log_check((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$events` WHERE requerimento_id = %d", $row->id)) === 2, 'histórico mantém as duas decisões do mesmo requerimento');
    $conflict = DRJ_Rematriculas::api_item(array('ra' => $prefix . 'B', 'id_requerimento' => 'REQ-1', 'sistema_origem' => 'teste'));
    drj_log_check(is_wp_error($conflict) && $conflict->get_error_code() === 'drj_requerimento_ra_alterado' && !(int) DRJ_Students::find($ids['B'])->status_rematricula, 'mesmo ID não confirma outro RA');
    drj_log_check(drj_log_row($row->id)->resultado === 'rejeitado' && drj_log_row($row->id)->vinculo_ra === $prefix . 'A', 'conflito fica explicado sem perder o vínculo original');
    $other_system = DRJ_Rematriculas::api_item(array('ra' => $prefix . 'B', 'id_requerimento' => 'REQ-1', 'sistema_origem' => 'outro'));
    drj_log_check(!is_wp_error($other_system) && $other_system['log_id'] !== $first['log_id'], 'IDs de sistemas externos diferentes não colidem');
    $header = DRJ_Rematriculas::api_item(array('ra' => $prefix . 'C'), 'HEADER-1');
    $header_again = DRJ_Rematriculas::api_item(array('ra' => $prefix . 'C'), 'HEADER-1');
    drj_log_check(!is_wp_error($header) && $header['log_id'] === $header_again['log_id'] && !empty($header_again['duplicado']), 'Idempotency-Key identifica envio simples');
    drj_log_check(is_wp_error(DRJ_Rematriculas::api_item(array('ra' => $prefix . 'D', 'id_requerimento' => 'OUTRO'), 'HEADER-2')), 'identificadores divergentes são rejeitados');
    $fallback = DRJ_Rematriculas::api_item(array('ra' => $prefix . 'D'));
    $fallback_again = DRJ_Rematriculas::api_item(array('ra' => $prefix . 'D'));
    drj_log_check($fallback['log_id'] === $fallback_again['log_id'], 'envio antigo sem ID continua protegido por RA');
    $unknown = DRJ_Rematriculas::api_item(array('ra' => $prefix . 'AUSENTE', 'id_requerimento' => 'CORRIGIVEL'));
    drj_log_check(is_wp_error($unknown) && $unknown->get_error_code() === 'drj_aluno_nao_encontrado', 'RA ausente é rejeitado e registrado');
    drj_log_check(!empty($unknown->get_error_data()['log_id']) && drj_log_row($unknown->get_error_data()['log_id'])->resultado === 'rejeitado', 'recusa também retorna referência para investigar o log');
    $fixed = DRJ_Rematriculas::api_item(array('ra' => $prefix . 'E', 'id_requerimento' => 'CORRIGIVEL'));
    drj_log_check(!is_wp_error($fixed) && $fixed['situacao'] === 'confirmado' && (int) drj_log_row($fixed['log_id'])->tentativas === 2, 'falha definitiva permite corrigir RA e reenviar no mesmo registro');
    $erp = drj_log_erp('101', $prefix . 'F', '2');
    $erp_first = DRJ_Rematriculas::erp_item($erp, '9001');
    $erp_api = DRJ_Rematriculas::erp_item($erp, '9001', '', '', 'api');
    drj_log_check($erp_first['log_id'] === $erp_api['log_id'] && !empty($erp_api['duplicado']), 'consulta e recebimento REST do ERP compartilham identidade');
    $old = drj_log_erp('101', $prefix . 'G', '1');
    $old_result = DRJ_Rematriculas::erp_item($old, '9001');
    drj_log_check($old_result['situacao'] === 'versao_antiga' && !(int) DRJ_Students::find($ids['G'])->status_rematricula, 'versão antiga não confirma outro aluno');
    $new = drj_log_erp('101', $prefix . 'G', '3');
    $new_result = DRJ_Rematriculas::erp_item($new, '9001');
    drj_log_check(is_wp_error($new_result) && $new_result->get_error_code() === 'drj_requerimento_ra_alterado', 'versão nova também não reaproveita requerimento para outro RA');
    drj_log_check(drj_log_row($erp_first['log_id'])->versao === '3', 'log mantém a maior versão lida');
    $rejected = drj_log_erp('102', $prefix . 'G', '3', 'rejeitado');
    $rejected_result = DRJ_Rematriculas::erp_item($rejected, '9001');
    drj_log_check($rejected_result['situacao'] === 'rejeitado' && drj_log_row($rejected_result['log_id'])->resultado === 'rejeitado', 'rejeição na origem aparece com motivo');
    $stale = DRJ_Rematriculas::erp_item(drj_log_erp('102', $prefix . 'G', '2', 'aprovado'), '9001');
    drj_log_check($stale['situacao'] === 'versao_antiga' && !(int) DRJ_Students::find($ids['G'])->status_rematricula, 'aprovação antiga não supera rejeição mais recente');
    $missing = drj_log_erp('103', null);
    $missing_result = DRJ_Rematriculas::erp_item($missing, '9001');
    $missing['resultado']['ra'] = $prefix . 'G';
    $resolved = DRJ_Rematriculas::erp_item($missing, '9001');
    drj_log_check($missing_result['situacao'] === 'sem_ra' && $missing_result['log_id'] === $resolved['log_id'] && $resolved['situacao'] === 'confirmado', 'pendência sem RA pode ser resolvida e aceita sem novo registro');
    $foreign = drj_log_erp('103', $prefix . 'H'); $foreign['codcoligada'] = '9002';
    $foreign_result = DRJ_Rematriculas::erp_item($foreign, '9002');
    drj_log_check(!is_wp_error($foreign_result) && $foreign_result['log_id'] !== $resolved['log_id'], 'mesmo ID em coligadas distintas tem identidades separadas');
    $wpdb->update($students, array('arquivado' => 1), array('id' => $ids['C']));
    $archived = DRJ_Rematriculas::api_item(array('ra' => $prefix . 'C'), 'HEADER-1');
    drj_log_check($archived['situacao'] === 'arquivado' && !(int) $archived['alterado'] && (int) DRJ_Students::find($ids['C'])->arquivado === 1, 'reenvio de requerimento aceito não restaura aluno arquivado');
    $uncertain_context = array('origem' => 'api', 'sistema' => 'teste', 'canal' => 'api', 'requerimento' => 'INTERROMPIDO', 'ra' => $prefix . 'I', 'processar' => true);
    $uncertain = DRJ_Integration_Log::process($uncertain_context, function () use ($prefix) { DRJ_Rematriculas::confirm($prefix . 'I', current_time('mysql')); throw new RuntimeException('interrupção sintética'); });
    drj_log_check(is_wp_error($uncertain) && (int) DRJ_Students::find($ids['I'])->status_rematricula === 1, 'interrupção após confirmar é registrada como erro retomável');
    $invalid_retry = DRJ_Rematriculas::api_item(array('ra' => $prefix . 'I', 'id_requerimento' => 'INTERROMPIDO', 'sistema_origem' => 'teste', 'data_rematricula' => 'invalida'));
    $wrong_retry = DRJ_Rematriculas::api_item(array('ra' => $prefix . 'J', 'id_requerimento' => 'INTERROMPIDO', 'sistema_origem' => 'teste'));
    drj_log_check(is_wp_error($invalid_retry) && is_wp_error($wrong_retry) && $wrong_retry->get_error_code() === 'drj_requerimento_ra_alterado' && !(int) DRJ_Students::find($ids['J'])->status_rematricula, 'reserva sobrevive a erro e validação recusada; outro RA continua bloqueado');
    $resume = DRJ_Rematriculas::api_item(array('ra' => $prefix . 'I', 'id_requerimento' => 'INTERROMPIDO', 'sistema_origem' => 'teste'));
    drj_log_check(!is_wp_error($resume) && $resume['situacao'] === 'ja_confirmado' && (int) drj_log_row($resume['log_id'])->aceito === 1, 'retomada conclui log sem repetir a confirmação');
    $jobs = array();
    for ($i = 0; $i < 2; $i++) {
        $pipes = array();
        $process = proc_open(array(PHP_BINARY, '-c', php_ini_loaded_file(), __DIR__ . '/integration-log-worker.php', $prefix . 'K'), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        if (!is_resource($process)) throw new RuntimeException('Não foi possível iniciar teste de concorrência.');
        fclose($pipes[0]); $jobs[] = array($process, $pipes);
    }
    $concurrent = array();
    foreach ($jobs as $job) {
        $output = stream_get_contents($job[1][1]); $stderr = stream_get_contents($job[1][2]);
        fclose($job[1][1]); fclose($job[1][2]);
        $exit = proc_close($job[0]);
        if ($exit !== 0 || $stderr) throw new RuntimeException('Subprocesso de concorrência falhou: ' . $stderr);
        $concurrent[] = json_decode($output, true);
    }
    drj_log_check(!isset($concurrent[0]['error']) && !isset($concurrent[1]['error']) && $concurrent[0]['log_id'] === $concurrent[1]['log_id'] && (int) $concurrent[0]['alterado'] + (int) $concurrent[1]['alterado'] === 1, 'envios simultâneos confirmam uma única vez e compartilham registro');
    drj_log_check((int) drj_log_row($concurrent[0]['log_id'])->tentativas === 2 && (int) drj_log_row($concurrent[0]['log_id'])->duplicados === 1, 'concorrência preserva as duas leituras e um reenvio evitado');
    $rows_text = wp_json_encode($wpdb->get_results("SELECT * FROM `$table`", ARRAY_A)) . wp_json_encode($wpdb->get_results("SELECT * FROM `$events`", ARRAY_A));
    drj_log_check(strpos($rows_text, 'NAO-GRAVAR-CREDENCIAL') === false && strpos($rows_text, 'NAO-GRAVAR-DADOS-MEDICOS') === false, 'histórico não guarda credenciais nem dados médicos do payload');
    DRJ_Integration_Log::failure('erp', 'drj_erp_offline', 'Falha de conexão sintética.', 503, '9001');
    DRJ_Integration_Log::failure('erp', 'drj_erp_offline', 'Falha de conexão sintética.', 503, '9001');
    $failure_row = $wpdb->get_row("SELECT * FROM `$table` WHERE codigo = 'drj_erp_offline'");
    drj_log_check($failure_row && (int) $failure_row->tentativas === 2 && $failure_row->resultado === 'erro', 'falhas gerais aparecem e são agrupadas sem duplicar linha');
    $all = DRJ_Integration_Log::listing(DRJ_Integration_Log::filters(array()));
    drj_log_check(!is_wp_error($all) && (int) $all['summary']['duplicados'] >= 3 && (int) $all['summary']['leituras'] > (int) $all['summary']['lidos'], 'resumo distingue requerimentos de tentativas e reenvios');
    $filtered = DRJ_Integration_Log::listing(DRJ_Integration_Log::filters(array('origem' => 'api', 'busca' => 'REQ-1')));
    drj_log_check(count($filtered['rows']) === 2, 'busca por ID e origem filtra o histórico');
    $only_error = DRJ_Integration_Log::listing(DRJ_Integration_Log::filters(array('resultado' => 'erro')));
    drj_log_check(count($only_error['rows']) === 1 && $only_error['rows'][0]->codigo === 'drj_erp_offline', 'filtro de resultado isola falhas');
    $safe = DRJ_Integration_Log::filters(array('origem' => array('api'), 'resultado' => 'SQL', 'busca' => array('x'), 'desde' => '2026-02-30', 'pagina' => array(3)));
    drj_log_check($safe['origem'] === '' && $safe['resultado'] === '' && $safe['busca'] === '' && $safe['desde'] === '' && $safe['pagina'] === 1, 'filtros inválidos não viram consultas livres');
    wp_set_current_user(0);
    drj_log_check(is_wp_error(DRJ_Integration_Log::listing(DRJ_Integration_Log::filters(array()))) && is_wp_error(DRJ_Integration_Log::details($first['log_id'])), 'visitante não lê lista nem detalhes');
    wp_set_current_user(1);
    $original_get = $_GET; $_GET = array('detalhes' => $first['log_id']);
    ob_start(); DRJ_Integration_Log::admin(); $html = ob_get_clean(); $_GET = $original_get;
    drj_log_check(strpos($html, 'Logs de integração') !== false && strpos($html, 'Histórico do registro #') !== false && strpos($html, 'Ver histórico #') !== false, 'tela mostra lista, decisões e histórico por requerimento');
    for ($i = 1; $i <= 55; $i++) DRJ_Integration_Log::process(array('origem' => 'api', 'sistema' => 'paginacao', 'requerimento' => 'PAGE-' . $i, 'canal' => 'api'), function () { return array('situacao' => 'sem_ra', 'alterado' => false); });
    $page1 = DRJ_Integration_Log::listing(DRJ_Integration_Log::filters(array('busca' => 'PAGE-', 'pagina' => 1)));
    $page2 = DRJ_Integration_Log::listing(DRJ_Integration_Log::filters(array('busca' => 'PAGE-', 'pagina' => 2)));
    drj_log_check(count($page1['rows']) === 50 && count($page2['rows']) === 5 && $page2['pages'] === 2, 'paginação limita resultados e alcança registros além dos primeiros 50');
    $date_record = DRJ_Integration_Log::process(array('origem' => 'api', 'requerimento' => 'DATE-ENTRY', 'canal' => 'api'), function () { return array('situacao' => 'sem_ra', 'alterado' => false); });
    $wpdb->update($table, array('ultima_leitura' => '2026-11-16 01:30:00'), array('id' => $date_record['log_id']));
    $old_timezone = get_option('timezone_string'); update_option('timezone_string', 'America/Sao_Paulo', false);
    $date15 = DRJ_Integration_Log::listing(DRJ_Integration_Log::filters(array('busca' => 'DATE-ENTRY', 'desde' => '2026-11-15', 'ate' => '2026-11-15')));
    $date16 = DRJ_Integration_Log::listing(DRJ_Integration_Log::filters(array('busca' => 'DATE-ENTRY', 'desde' => '2026-11-16')));
    update_option('timezone_string', $old_timezone, false);
    drj_log_check(count($date15['rows']) === 1 && !$date16['rows'], 'datas de filtro respeitam o dia local do WordPress');
    $old = gmdate('Y-m-d H:i:s', time() - 91 * DAY_IN_SECONDS);
    $wpdb->query($wpdb->prepare("UPDATE `$events` SET criado_em = %s WHERE requerimento_id = %d", $old, $first['log_id']));
    DRJ_Integration_Log::cleanup();
    drj_log_check((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `$events` WHERE requerimento_id = %d", $first['log_id'])) === 0 && drj_log_row($first['log_id'])->vinculo_ra === $prefix . 'A', 'retenção remove só eventos antigos e preserva identidade e vínculo');
    $after_cleanup = DRJ_Rematriculas::api_item(array('ra' => $prefix . 'A', 'id_requerimento' => 'REQ-1', 'sistema_origem' => 'teste'));
    drj_log_check(!empty($after_cleanup['duplicado']) && $after_cleanup['log_id'] === $first['log_id'], 'limpeza do histórico não permite duplicar requerimento');
    drj_log_check(DRJ_Integration_Log::compare_version('999999999999999999', '100000000000000000') > 0 && DRJ_Integration_Log::compare_version('10', '9') > 0, 'versões de 18 dígitos são comparadas sem conversão para inteiro');
    echo 'TOTAL: ' . $total . ' verificações de logs e duplicidade aprovadas.' . PHP_EOL;
} finally {
    $wpdb->query($wpdb->prepare("DELETE FROM `$students` WHERE ra LIKE %s", $wpdb->esc_like($prefix) . '%'));
    $wpdb->query("DELETE FROM `$events`"); $wpdb->query("DELETE FROM `$table`");
    foreach ($tables_backup as $name => $rows) foreach ($rows as $data) $wpdb->insert($name, $data);
    update_option('drj_versao_banco', $old_version, false);
    wp_set_current_user($user);
}
