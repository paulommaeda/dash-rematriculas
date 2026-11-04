(() => {
    'use strict';
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[char]));
    const normalize = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const money = value => Number(value || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const date = value => value ? value.slice(0, 10).split('-').reverse().join('/') : 'Pendente';

    class Dashboard {
        constructor(root) {
            this.root = root;
            this.config = JSON.parse(root.querySelector('.drj-config').textContent);
            this.students = new Map(this.config.students.map(student => [String(student.id), student]));
            this.rows = new Map([...root.querySelectorAll('[data-student-id]')].map(row => [row.dataset.studentId, row]));
            this.queues = new Map();
            this.timers = new Map();
            this.unsaved = new Set();
            this.saved = new Map([...this.rows].map(([id, row]) => [id, JSON.stringify(this.financialValues(row))]));
            this.financialPending = new Map();
            this.filterTimer = null;
            this.modalStudent = null;
            this.modalVersion = 0;
            this.modalBusy = false;
            root.addEventListener('input', event => this.input(event));
            root.addEventListener('change', event => this.change(event));
            root.addEventListener('click', event => this.click(event));
            root.addEventListener('submit', event => {
                if (event.target.matches('.drj-details-form')) {
                    event.preventDefault();
                    this.saveDetails();
                }
            });
            root.addEventListener('keydown', event => {
                if (event.key === 'Escape') this.closeModal();
                if (event.key === 'Tab') this.trapFocus(event);
            });
            window.addEventListener('beforeunload', event => {
                if (this.unsaved.size || this.queues.size || this.syncing || this.modalBusy) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
            this.filter();
        }

        async request(action, data = {}) {
            const form = new FormData();
            Object.entries({action: `drj_${action}`, nonce: this.config.nonce, ...data}).forEach(([key, value]) => form.append(key, value));
            const response = await fetch(this.config.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: form});
            let result;
            try { result = await response.json(); } catch { throw new Error('Resposta inválida do servidor. Atualize a página e tente novamente.'); }
            if (!result.success) throw new Error(result.data?.msg || 'Não foi possível salvar. Tente novamente.');
            return result.data || {};
        }

        notify(message, error = false) {
            const box = this.root.querySelector('.drj-feedback');
            box.hidden = false;
            box.classList.toggle('is-error', error);
            box.textContent = message;
        }

        enqueue(id, operation) {
            const pending = (this.queues.get(id) || Promise.resolve()).catch(() => {}).then(operation);
            this.queues.set(id, pending);
            pending.finally(() => {
                if (this.queues.get(id) === pending) this.queues.delete(id);
            }).catch(() => {});
            return pending;
        }

        financialValues(row) {
            return Object.fromEntries([...row.querySelectorAll('[data-financial]')].map(input => [input.dataset.financial, input.value]));
        }

        recalculate(row) {
            const values = this.financialValues(row);
            if (!row.querySelector('.drj-final')) return;
            row.querySelector('.drj-final').textContent = money(Number(values.valor_2027) - ['desc_condicional', 'desc_incondicional', 'desc_indicacao', 'desc_irmaos'].reduce((total, key) => total + Number(values[key] || 0), 0));
        }

        input(event) {
            const input = event.target;
            if (input.matches('[data-filter]')) {
                clearTimeout(this.filterTimer);
                this.filterTimer = setTimeout(() => this.filter(), 120);
            }
            if (input.matches('[data-financial]')) {
                const row = input.closest('[data-student-id]');
                const id = row.dataset.studentId;
                this.recalculate(row);
                this.unsaved.add(id);
                row.querySelector('.drj-row-save').textContent = 'Alterações pendentes…';
                clearTimeout(this.timers.get(id));
                this.timers.set(id, setTimeout(() => this.flush(id).catch(() => {}), 650));
            }
        }

        change(event) {
            if (event.target.matches('[data-filter]')) this.filter();
            if (event.target.matches('[data-financial]')) {
                this.flush(event.target.closest('[data-student-id]').dataset.studentId).catch(() => {});
            }
        }

        async flush(id) {
            clearTimeout(this.timers.get(id));
            this.timers.delete(id);
            const row = this.rows.get(id);
            if (!this.unsaved.has(id)) return this.queues.get(id);
            const invalid = [...row.querySelectorAll('[data-financial]')].find(input => !input.validity.valid || input.value === '');
            if (invalid) {
                row.querySelector('.drj-row-save').textContent = 'Preencha valores válidos para salvar.';
                throw new Error('Corrija os valores financeiros antes de continuar.');
            }
            const values = this.financialValues(row);
            const snapshot = JSON.stringify(values);
            const existing = this.financialPending.get(id);
            if (existing?.snapshot === snapshot) return existing.promise;
            if (this.saved.get(id) === snapshot && !this.queues.has(id)) {
                this.unsaved.delete(id);
                row.querySelector('.drj-row-save').textContent = 'Salvo';
                return;
            }
            const operation = this.enqueue(id, async () => {
                row.querySelector('.drj-row-save').textContent = 'Salvando…';
                try {
                    await this.request('salvar_financeiro', {id, ...values});
                    this.saved.set(id, snapshot);
                    const student = this.students.get(id);
                    Object.keys(student.financeiro).forEach(key => { student.financeiro[key] = Number(values[key]); });
                    student.vencimento = values.vencimento;
                    student.parcelas = Number(values.parcelas);
                    row.querySelectorAll('[data-action="check"]').forEach(button => this.setCheck(button, 0));
                    this.rowColor(row);
                    if (JSON.stringify(this.financialValues(row)) === snapshot) {
                        this.unsaved.delete(id);
                        row.querySelector('.drj-row-save').textContent = 'Salvo';
                    } else {
                        row.querySelector('.drj-row-save').textContent = 'Alterações pendentes…';
                    }
                } catch (error) {
                    row.querySelector('.drj-row-save').innerHTML = 'Não salvo. <button type="button" class="drj-link" data-action="retry">Tentar novamente</button>';
                    this.notify(error.message, true);
                    throw error;
                }
            });
            this.financialPending.set(id, {snapshot, promise: operation});
            operation.finally(() => {
                if (this.financialPending.get(id)?.promise === operation) this.financialPending.delete(id);
            }).catch(() => {});
            return operation;
        }

        async click(event) {
            const button = event.target.closest('[data-action]');
            if (!button || !this.root.contains(button) || button.disabled) return;
            const action = button.dataset.action;
            const id = button.closest('[data-student-id]')?.dataset.studentId || this.modalStudent;
            try {
                if (action === 'details' || action === 'archive') this.openModal(id, action === 'archive');
                if (action === 'close') this.closeModal();
                if (action === 'retry') await this.flush(id);
                if (action === 'check') await this.toggleCheck(id, button);
                if (action === 'restore') {
                    this.openModal(id);
                    this.root.querySelector('[data-action="confirm-archive"]').focus();
                }
                if (action === 'confirm-archive') await this.archive(id, button);
                if (action === 'sync') await this.sync(button);
            } catch (error) { this.notify(error.message, true); }
        }

        setCheck(button, status) {
            button.dataset.status = String(status);
            button.classList.toggle('btn-check-on', status === 1);
            button.classList.toggle('btn-check-off', status === 0);
            button.setAttribute('aria-pressed', status ? 'true' : 'false');
        }

        rowColor(row) {
            row.classList.toggle('drj-linha-ok', [...row.querySelectorAll('[data-action="check"]')].every(button => button.dataset.status === '1'));
        }

        async toggleCheck(id, button) {
            button.disabled = true;
            try {
                await this.flush(id);
                await this.enqueue(id, async () => {
                    if (this.unsaved.has(id)) throw new Error('Aguarde o salvamento financeiro antes de conferir.');
                    const value = button.dataset.status === '1' ? 0 : 1;
                    const result = await this.request('salvar_check', {id, tipo: button.dataset.sector, valor: value});
                    this.setCheck(button, value);
                    this.rowColor(this.rows.get(id));
                    if (result.warning) this.notify(result.warning, true);
                });
            } finally { button.disabled = false; }
        }

        openModal(id, focusArchive = false) {
            if (this.modalBusy) return;
            const student = this.students.get(id);
            const row = this.rows.get(id);
            const values = this.financialValues(row);
            const archived = Boolean(student.arquivado);
            this.previousFocus = document.activeElement;
            this.modalStudent = id;
            const version = ++this.modalVersion;
            const container = this.root.querySelector('.drj-modal-container');
            const labels = {valor_2027: 'Valor base 2027', desc_condicional: 'Desc. condicional', desc_incondicional: 'Desc. incondicional', desc_indicacao: 'Desc. indicação', desc_irmaos: 'Desc. irmãos'};
            container.innerHTML = `<div class="drj-mod2-overlay"><div class="drj-mod2-box" role="dialog" aria-modal="true" aria-labelledby="drj-dialog-title" tabindex="-1">
                <div class="drj-mod2-header"><div><h2 class="drj-mod2-title" id="drj-dialog-title">${escape(student.nome)}</h2><p class="drj-mod2-subtitle">Matrícula/rematrícula: ${escape(date(student.data))}</p></div><button type="button" class="drj-mod2-close" data-action="close" aria-label="Fechar">×</button></div>
                <div class="drj-mod2-body"><form class="drj-details-form">
                    <fieldset ${archived ? 'disabled' : ''}><legend class="drj-mod2-section-title">Informações do aluno</legend>
                    <div class="drj-register-grid"><label>RA oficial<input class="drj-mod2-input-text" name="novo_ra" maxlength="100" required value="${escape(student.ra)}"></label><label>Turma<input class="drj-mod2-input-text" name="turma" list="drj-class-list" maxlength="100" required value="${escape(student.turma)}"><datalist id="drj-class-list">${this.config.classes.map(value => `<option value="${escape(value)}"></option>`).join('')}</datalist></label></div>
                    ${student.ra.startsWith('NOV-') ? '<p class="drj-muted">RA provisório. A classificação de aluno novo será mantida após a troca pelo RA oficial.</p>' : ''}
                    ${this.config.canManageFinancial ? `<div class="drj-mod2-section-title">Resumo financeiro</div><div class="drj-mod2-fin-box"><div class="drj-mod2-grid">${Object.entries(labels).map(([key, label]) => `<div><div class="drj-mod2-label">${label}</div><div class="drj-mod2-value">R$ ${money(values[key])}</div></div>`).join('')}<div><div class="drj-mod2-label">Vencimento</div><div class="drj-mod2-value">Dia ${escape(values.vencimento)}</div></div><div><div class="drj-mod2-label">Parcelas</div><div class="drj-mod2-value">${escape(values.parcelas)}x</div></div></div><p class="drj-mod2-value">Valor final: R$ ${escape(row.querySelector('.drj-final').textContent)}</p></div>` : ''}
                    <div class="drj-mod2-section-title">Observações</div><label class="drj-checkbox-atencao"><input type="checkbox" name="check_atencao" ${student.atencao ? 'checked' : ''}> Destacar aluno com atenção</label><label>Anotações<textarea name="observacao" class="drj-mod2-textarea">${escape(student.observacao)}</textarea></label>
                    <div class="drj-mod2-footer"><button type="submit" class="drj-mod2-btn">Salvar alterações</button></div></fieldset></form>
                    <section class="drj-archive-section"><h3>${archived ? 'Desistência arquivada' : 'Arquivar desistência'}</h3>
                    <p>${archived ? `Arquivado em ${escape(date(student.data_arquivamento))}. O retorno mantém os valores e o status de matrícula anteriores.` : 'O aluno sairá do acompanhamento e dos indicadores ativos. Todos os dados serão preservados e você poderá restaurá-lo.'}</p>
                    <label>${archived ? 'Motivo registrado' : 'Motivo da desistência (obrigatório)'}<textarea class="drj-mod2-textarea" data-archive-reason ${archived ? 'readonly' : ''}>${escape(student.motivo)}</textarea></label>
                    <button type="button" class="drj-archive-button ${archived ? 'is-restore' : ''}" data-action="confirm-archive">${archived ? 'Restaurar para acompanhamento' : 'Confirmar desistência e arquivar'}</button></section>
                    <section class="drj-history"><h3>Histórico de arquivamento</h3><div data-history>Carregando…</div></section>
                </div></div></div>`;
            container.querySelector('.drj-mod2-overlay').addEventListener('click', event => {
                if (event.target === event.currentTarget) this.closeModal();
            });
            (focusArchive ? container.querySelector('[data-archive-reason]') : container.querySelector('.drj-mod2-box')).focus();
            this.request('historico_aluno', {id}).then(result => {
                if (this.modalStudent !== id || this.modalVersion !== version) return;
                const target = container.querySelector('[data-history]');
                target.innerHTML = result.history?.length ? `<ul>${result.history.map(item => `<li><strong>${item.acao === 'arquivado' ? 'Arquivado' : 'Restaurado'}</strong> · ${escape(date(item.criado_em))} ${escape(item.criado_em.slice(11, 16))} · ${escape(item.autor)}${item.motivo ? `<p>${escape(item.motivo)}</p>` : ''}</li>`).join('')}</ul>` : '<p>Nenhum arquivamento registrado.</p>';
            }).catch(() => {
                if (this.modalStudent === id && this.modalVersion === version) container.querySelector('[data-history]').textContent = 'Não foi possível carregar o histórico.';
            });
        }

        closeModal() {
            if (this.modalBusy) return;
            this.modalStudent = null;
            this.modalVersion++;
            this.root.querySelector('.drj-modal-container').replaceChildren();
            this.previousFocus?.focus();
        }

        trapFocus(event) {
            const modal = this.root.querySelector('[role="dialog"]');
            if (!modal) return;
            const inputs = [...modal.querySelectorAll('button, input, textarea, select, [tabindex="0"]')].filter(element => !element.matches(':disabled') && element.offsetParent !== null);
            const first = inputs[0], last = inputs[inputs.length - 1];
            if (event.shiftKey && (document.activeElement === first || document.activeElement === modal)) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && (document.activeElement === last || document.activeElement === modal)) { event.preventDefault(); first?.focus(); }
        }

        async saveDetails() {
            const id = this.modalStudent;
            const form = this.root.querySelector('.drj-details-form');
            const button = form.querySelector('[type="submit"]');
            if (this.modalBusy || !form.reportValidity()) return;
            this.modalBusy = true;
            button.disabled = true;
            button.textContent = 'Salvando…';
            try {
                await this.flush(id);
                if (this.unsaved.has(id)) throw new Error('Conclua o salvamento financeiro antes de continuar.');
                const data = Object.fromEntries(new FormData(form));
                data.check_atencao = form.elements.check_atencao.checked ? 1 : 0;
                const result = await this.enqueue(id, () => this.request('salvar_modal', {id, ...data}));
                this.students.set(id, result.student);
                const row = this.rows.get(id);
                row.querySelector('.drj-ra').textContent = result.student.ra;
                row.querySelector('.drj-class').textContent = result.student.turma;
                row.classList.toggle('drj-linha-atencao', Boolean(result.student.atencao));
                // O valor base pode mudar com a turma quando ainda usa o preço padrão.
                if (this.config.canManageFinancial && result.student.financeiro) Object.entries(result.student.financeiro).forEach(([field, value]) => { row.querySelector(`[data-financial="${field}"]`).value = Number(value).toFixed(2); });
                this.saved.set(id, JSON.stringify(this.financialValues(row)));
                this.recalculate(row);
                this.updateClassOptions();
                this.filter();
                this.modalBusy = false;
                this.closeModal();
                this.notify(result.msg);
            } catch (error) {
                this.notify(error.message, true);
            } finally {
                this.modalBusy = false;
                button.disabled = false;
                button.textContent = 'Salvar alterações';
            }
        }

        async archive(id, button) {
            if (this.modalBusy) return;
            const student = this.students.get(id);
            const archive = !student.arquivado;
            const reason = this.root.querySelector('[data-archive-reason]').value.trim();
            if (archive && !reason) {
                this.root.querySelector('[data-archive-reason]').focus();
                throw new Error('Informe o motivo da desistência para arquivar.');
            }
            const detailsForm = this.root.querySelector('.drj-details-form');
            if (archive && (detailsForm.elements.novo_ra.value !== student.ra || detailsForm.elements.turma.value !== student.turma || detailsForm.elements.observacao.value !== student.observacao || Number(detailsForm.elements.check_atencao.checked) !== student.atencao)) {
                throw new Error('Salve as alterações do aluno antes de arquivar a desistência.');
            }
            this.modalBusy = true;
            button.disabled = true;
            try {
                await this.flush(id);
                if (this.unsaved.has(id)) throw new Error('Conclua o salvamento financeiro antes de arquivar.');
                const result = await this.enqueue(id, () => this.request('arquivar_aluno', {id, arquivar: archive ? 1 : 0, motivo: archive ? reason : ''}));
                this.modalBusy = false;
                this.closeModal();
                this.notify(result.msg);
                // Reconsulta no servidor: lista, totais e contagem de arquivados permanecem consistentes.
                window.location.reload();
            } finally {
                this.modalBusy = false;
                button.disabled = false;
            }
        }

        updateClassOptions() {
            const select = this.root.querySelector('[data-filter="class"]');
            const current = select.value;
            this.config.classes = [...new Set([...this.students.values()].map(student => student.turma))].sort((a, b) => a.localeCompare(b, 'pt-BR', {numeric: true}));
            select.innerHTML = '<option value="">Todas</option>' + this.config.classes.map(value => `<option value="${escape(value)}">${escape(value)}</option>`).join('');
            select.value = this.config.classes.includes(current) ? current : '';
        }

        async sync(button) {
            if (this.syncing) return;
            for (const id of [...this.unsaved]) await this.flush(id);
            await Promise.all([...this.queues.values()]);
            if (this.unsaved.size) throw new Error('Salve os valores pendentes antes de sincronizar.');
            this.syncing = true;
            button.disabled = true;
            let cursor = 0, until = 0, updated = 0;
            try {
                while (true) {
                    const result = await this.request('sincronizar_ff', {cursor, until});
                    updated += result.updated;
                    button.textContent = `Sincronizando… ${updated} atualizado(s)`;
                    if (result.done) break;
                    if (result.cursor <= cursor) throw new Error('A sincronização não avançou. Tente novamente.');
                    cursor = result.cursor;
                    until = result.until;
                }
                this.notify(`Sincronização concluída: ${updated} aluno(s) atualizado(s).`);
                this.syncing = false;
                window.location.reload();
            } finally {
                this.syncing = false;
                button.disabled = false;
                button.textContent = '🔄 Sincronizar Agora';
            }
        }

        filter() {
            const filters = Object.fromEntries([...this.root.querySelectorAll('[data-filter]')].map(input => [input.dataset.filter, input.value]));
            const search = normalize(filters.name);
            const active = [];
            let visible = 0, archived = 0;
            this.students.forEach((student, id) => {
                let show = (!search || normalize(student.nome).includes(search) || normalize(student.ra).includes(search)) && (!filters.class || filters.class === student.turma) && (!filters.level || filters.level === student.nivel);
                if (filters.status === 'novo') show = show && student.novo;
                if (filters.status === '1') show = show && !student.novo && student.status === 1;
                if (filters.status === '0') show = show && !student.novo && student.status === 0;
                if (filters.start || filters.end) show = show && Boolean(student.data) && (!filters.start || student.data >= filters.start) && (!filters.end || student.data <= filters.end);
                this.rows.get(id).hidden = !show;
                if (show) {
                    visible++;
                    if (student.arquivado) archived++;
                    else active.push(student);
                }
            });
            const remat = active.filter(student => !student.novo && student.status === 1).length;
            const novos = active.filter(student => student.novo).length;
            const metrics = {total: active.length, remat, novos, pend: active.length - remat - novos, conv: `${active.length ? ((remat + novos) / active.length * 100).toLocaleString('pt-BR', {maximumFractionDigits: 1}) : 0}%`};
            Object.entries(metrics).forEach(([key, value]) => { this.root.querySelector(`[data-metric="${key}"]`).textContent = value; });
            this.root.querySelector('.drj-results').textContent = `${visible} registro(s) exibido(s) · ${active.length} em acompanhamento · ${archived} arquivado(s).`;
            this.root.querySelector('.drj-empty').hidden = visible !== 0;
            this.charts(active);
        }

        charts(students) {
            const colors = ['#10b981', '#3b82f6', '#f59e0b', '#e2e8f0'];
            const groups = new Map(), days = new Map();
            let ef = 0, em = 0;
            students.forEach(student => {
                if (!groups.has(student.turma)) groups.set(student.turma, [0, 0, 0, 0]);
                groups.get(student.turma)[student.novo ? 1 : student.status ? 0 : 2]++;
                if (student.novo || student.status) {
                    if (student.nivel === 'EF') ef++;
                    if (student.nivel === 'EM') em++;
                }
                if (!student.novo && student.status && student.data) days.set(student.data, (days.get(student.data) || 0) + 1);
            });
            const daysSorted = [...days].sort((a, b) => a[0].localeCompare(b[0]));
            const evolution = this.root.querySelector('[data-chart="evolution"]');
            if (!daysSorted.length) evolution.innerHTML = '<p class="drj-chart-empty">Sem rematrículas confirmadas neste filtro.</p>';
            else {
                const max = Math.max(...daysSorted.map(([, value]) => value), 1);
                const points = daysSorted.map(([day, value], index) => [35 + (daysSorted.length === 1 ? 260 : index / (daysSorted.length - 1) * 520), 155 - value / max * 120, day, value]);
                evolution.innerHTML = `<svg viewBox="0 0 600 200" role="img" aria-label="Rematrículas concluídas por dia"><line x1="35" y1="155" x2="565" y2="155" stroke="#cbd5e1"/><text x="15" y="38" fill="#64748b" font-size="12">${max}</text><polyline points="${points.map(point => `${point[0]},${point[1]}`).join(' ')}" fill="none" stroke="#4f46e5" stroke-width="3"/>${points.map(([x, y, day, value], index) => `<circle cx="${x}" cy="${y}" r="4" fill="#4f46e5"><title>${escape(date(day))}: ${value} rematrícula(s)</title></circle>${index === 0 || index === points.length - 1 || index % Math.max(1, Math.ceil(points.length / 6)) === 0 ? `<text x="${x}" y="180" text-anchor="middle" font-size="12" fill="#64748b">${escape(date(day).slice(0, 5))}</text>` : ''}`).join('')}</svg>`;
            }
            const levels = this.root.querySelector('[data-chart="levels"]');
            const sum = ef + em;
            const percentage = sum ? ef / sum * 100 : 0;
            levels.innerHTML = sum ? `<div class="drj-level-chart"><div class="drj-donut" role="img" aria-label="Fundamental: ${ef}. Médio: ${em}." style="background:conic-gradient(#10b981 0% ${percentage}%, #4f46e5 ${percentage}% 100%)"><span>${sum}</span></div><div><p><i style="background:#10b981"></i> Fundamental: <strong>${ef}</strong></p><p><i style="background:#4f46e5"></i> Médio: <strong>${em}</strong></p></div></div>` : '<p class="drj-chart-empty">Sem matrículas confirmadas neste filtro.</p>';
            const classes = this.root.querySelector('[data-chart="classes"]');
            if (!groups.size) { classes.innerHTML = '<p class="drj-chart-empty">Sem alunos ativos neste filtro.</p>'; return; }
            const sorted = [...groups].sort((a, b) => a[0].localeCompare(b[0], 'pt-BR', {numeric: true}));
            const maximum = Math.max(30, ...sorted.map(([, counts]) => counts[0] + counts[1] + counts[2]));
            const labels = ['Rematriculados', 'Novos', 'Pendentes', 'Vagas'];
            classes.innerHTML = `<div class="drj-chart-legend">${labels.map((label, index) => `<span><i style="background:${colors[index]}"></i>${label}</span>`).join('')}</div><div class="drj-class-bars">${sorted.map(([name, counts]) => {
                const total = counts[0] + counts[1] + counts[2];
                counts[3] = Math.max(0, 30 - total);
                return `<div class="drj-class-bar"><span>${escape(name)}</span><div class="drj-stack" role="img" aria-label="${escape(name)}: ${labels.map((label, index) => `${label} ${counts[index]}`).join(', ')}">${counts.map((count, index) => `<span style="width:${count / maximum * 100}%;background:${colors[index]}"><title>${labels[index]}: ${count}</title>${count > 0 ? count : ''}</span>`).join('')}</div><strong>${total}${total > 30 ? ' ⚠' : ''}</strong></div>`;
            }).join('')}</div>`;
        }
    }

    const start = () => document.querySelectorAll('[data-drj-dashboard]').forEach(root => {
        if (!root.dataset.initialized) {
            root.dataset.initialized = '1';
            new Dashboard(root);
        }
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
