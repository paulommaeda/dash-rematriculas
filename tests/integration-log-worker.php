<?php
/** Subprocesso sintético utilizado pelo teste de concorrência. */
if (PHP_SAPI !== 'cli' || getenv('DRJ_TEST_ENV') !== '1' || !getenv('DRJ_WP_BOOTSTRAP') || empty($argv[1])) exit(1);
require getenv('DRJ_WP_BOOTSTRAP');
$ra = $argv[1];
$result = DRJ_Integration_Log::process(array('origem' => 'api', 'sistema' => 'teste-concorrencia', 'canal' => 'api', 'requerimento' => 'REQ-CONC', 'ra' => $ra, 'processar' => true), function () use ($ra) {
    usleep(250000);
    return DRJ_Rematriculas::confirm($ra, current_time('mysql'));
});
echo wp_json_encode(is_wp_error($result) ? array('error' => $result->get_error_code()) : $result);
