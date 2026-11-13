/** DRJ_TEST_ENV=1 node tests/erp-ui.cjs; requer jsdom instalado. Sem conexão externa. */
if (process.env.DRJ_TEST_ENV !== '1') process.exit(1);
const {JSDOM} = require('jsdom');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const script = fs.readFileSync(path.join(__dirname, '../dashboard-rematriculas/assets/js/erp.js'), 'utf8');
const dom = new JSDOM('<div data-drj-erp data-url="https://dashboard.example.test/wp-admin/admin-ajax.php" data-nonce="nonce-teste"><button data-erp-sync>Sincronizar</button><button data-erp-full>Refazer</button><p data-erp-progress role="status"></p></div>', {runScripts: 'outside-only'});
const w = dom.window;
w.URLSearchParams = URLSearchParams;
w.AbortController = AbortController;
const counts = {lidos: 0, confirmado: 0, ja_confirmado: 0, arquivado: 0, nao_encontrado: 0, sem_ra: 0, ra_duplicado: 0, rejeitado: 0, anterior_ao_periodo: 0};
const calls = [];
let responses = [];
w.fetch = async (url, args) => {
    calls.push({url, args});
    const result = responses.shift();
    if (typeof result === 'function') return result(args);
    if (result instanceof Error) throw result;
    return {ok: true, json: async () => result};
};
w.eval(script);
const button = w.document.querySelector('[data-erp-sync]');
const full = w.document.querySelector('[data-erp-full]');
const progress = w.document.querySelector('[data-erp-progress]');
let checks = 0;
function check(value, label) { assert.ok(value, label); checks++; console.log('OK ' + label); }
const until = async condition => {
    const deadline = Date.now() + 4000;
    while (!condition()) {
        if (Date.now() > deadline) throw new Error('Tempo esgotado ao aguardar interface');
        await new Promise(resolve => setTimeout(resolve, 10));
    }
};
(async () => {
    let release;
    responses = [() => new Promise(resolve => { release = resolve; }), {success: true, data: {done: true, totals: {...counts, lidos: 21, confirmado: 3}}}];
    full.click();
    check(button.disabled && full.disabled, 'sincronização impede cliques simultâneos');
    button.click();
    check(calls.length === 1, 'clique repetido não inicia outra rodada');
    release({ok: true, json: async () => ({success: true, data: {done: false, totals: {...counts, lidos: 20, confirmado: 2}}})});
    await until(() => !button.disabled);
    check(calls.length === 2 && calls[0].args.body.get('full') === '1' && calls[1].args.body.get('full') === '0', 'leitura completa continua páginas sem reiniciar a rodada');
    check(calls.every(call => call.args.credentials === 'same-origin' && call.args.body.get('nonce') === 'nonce-teste'), 'botão usa sessão e nonce administrativos');
    check(progress.textContent.includes('Lidos: 21') && progress.textContent.includes('Confirmados: 3') && progress.textContent.includes('Rodada concluída'), 'progresso mostra totais acumulados e conclusão');
    responses = [{success: false, data: {msg: 'Conta sem autorização para a coligada.'}}];
    button.click(); await until(() => !button.disabled);
    check(progress.textContent === 'Conta sem autorização para a coligada.' && !full.disabled, 'erro do ERP é exibido e permite nova tentativa');
    responses = [async () => ({ok: false, json: async () => { throw new SyntaxError('HTML inesperado'); }})];
    button.click(); await until(() => !button.disabled);
    check(progress.textContent.includes('resposta válida') && !progress.textContent.includes('SyntaxError'), 'resposta inválida da hospedagem recebe orientação legível');
    const abort = new Error('abortado'); abort.name = 'AbortError';
    responses = [abort];
    button.click(); await until(() => !button.disabled);
    check(progress.textContent.includes('retomar') && !full.disabled, 'tempo esgotado orienta a retomar sem duplicar');
    responses = [{success: true, data: {done: true, totals: {...counts, lidos: 1}}}];
    button.click(); await until(() => !button.disabled);
    check(progress.textContent.includes('Rodada concluída') && calls.at(-1).args.body.get('full') === '0', 'nova tentativa retoma a rodada após erro');
    console.log(`TOTAL: ${checks} verificações da interface ERP aprovadas.`);
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(() => w.close());
