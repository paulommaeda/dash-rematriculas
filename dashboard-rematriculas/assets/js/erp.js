(() => {
    'use strict';
    const root = document.querySelector('[data-drj-erp]');
    if (!root) return;
    const buttons = [...root.querySelectorAll('button')];
    const progress = root.querySelector('[data-erp-progress]');
    let running = false;
    const summarize = report => {
        const t = report.totals;
        return `Lidos: ${t.lidos} · Confirmados: ${t.confirmado} · Já confirmados: ${t.ja_confirmado} · Arquivados: ${t.arquivado} · RA não cadastrado: ${t.nao_encontrado} · Sem RA: ${t.sem_ra} · RA duplicado: ${t.ra_duplicado} · Rejeitados: ${t.rejeitado} · Anteriores ao período: ${t.anterior_ao_periodo}.`;
    };
    async function sync(full) {
        if (running) return;
        running = true;
        buttons.forEach(button => { button.disabled = true; });
        progress.textContent = 'Consultando requerimentos no ERP…';
        try {
            let report;
            do {
                const controller = new AbortController();
                const timer = setTimeout(() => controller.abort(), 25000);
                let response;
                try {
                    response = await fetch(root.dataset.url, {
                        method: 'POST', credentials: 'same-origin', signal: controller.signal,
                        body: new URLSearchParams({action: 'drj_sync_erp', nonce: root.dataset.nonce, full: full ? '1' : '0'})
                    });
                } finally {
                    clearTimeout(timer);
                }
                const result = await response.json().catch(() => { throw new Error('O servidor não retornou uma resposta válida. Atualize a página e tente novamente.'); });
                if (!response.ok || !result?.success) throw new Error(result?.data?.msg || 'Não foi possível sincronizar. Atualize a página e tente novamente.');
                report = result.data;
                if (typeof report?.done !== 'boolean' || !report.totals) throw new Error('O servidor retornou um resultado incompleto. Atualize a página e tente novamente.');
                full = false;
                progress.textContent = summarize(report) + (report.done ? ' Rodada concluída. Atualize o acompanhamento para ver os dados.' : ' Buscando próxima página…');
                if (!report.done) await new Promise(resolve => setTimeout(resolve, 150));
            } while (!report.done);
        } catch (error) {
            progress.textContent = error.name === 'AbortError' ? 'A consulta demorou demais. Clique em continuar para retomar; reenvios não duplicam confirmações.' : error.message;
        } finally {
            running = false;
            buttons.forEach(button => { button.disabled = false; });
            root.querySelector('[data-erp-sync]').textContent = 'Sincronizar ou continuar';
        }
    }
    root.querySelector('[data-erp-sync]').addEventListener('click', () => sync(false));
    root.querySelector('[data-erp-full]').addEventListener('click', () => sync(true));
})();
