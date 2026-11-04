(() => {
    'use strict';
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[char]));
    const currency = new Intl.NumberFormat('pt-BR', {style: 'currency', currency: 'BRL'});
    const money = cents => currency.format(cents / 100);
    const percent = (part, whole) => whole > 0 ? `${(part / whole * 100).toLocaleString('pt-BR', {minimumFractionDigits: 1, maximumFractionDigits: 1})}%` : '—';
    const amounts = ['gross', 'unconditional', 'referral', 'siblings', 'conditional', 'net'];

    class Finance {
        constructor(root) {
            this.root = root;
            this.config = JSON.parse(root.querySelector('.drj-finance-config').textContent);
            this.report = this.config.report;
            this.form = root.querySelector('.drj-fin-filters');
            this.refresh = root.querySelector('[data-fin-refresh]');
            this.requestId = 0;
            this.controller = null;
            this.form.addEventListener('submit', event => { event.preventDefault(); this.load(); });
            this.form.addEventListener('change', event => {
                if (!event.target.matches('[data-fin-filter]')) return;
                if (event.target.dataset.finFilter === 'course') this.form.elements.drj_fin_class.value = '';
                this.load();
            });
            this.refresh.addEventListener('click', () => this.load());
            this.render(this.report);
        }

        feedback(text, error = false) {
            const box = this.root.querySelector('.drj-fin-feedback');
            box.hidden = !text;
            box.classList.toggle('is-error', error);
            box.textContent = text;
        }

        async load() {
            this.controller?.abort();
            const controller = new AbortController();
            this.controller = controller;
            const id = ++this.requestId;
            this.root.setAttribute('aria-busy', 'true');
            this.refresh.disabled = true;
            this.feedback('Atualizando os valores financeiros…');
            const form = new FormData(this.form);
            form.set('action', 'drj_finance_report');
            form.set('nonce', this.config.nonce);
            try {
                const response = await fetch(this.config.ajaxUrl, {method: 'POST', body: form, credentials: 'same-origin', signal: controller.signal});
                let result;
                try { result = await response.json(); } catch { throw new Error('Resposta inválida do servidor. Tente novamente.'); }
                if (!result.success || !result.data?.report) throw new Error(result.data?.msg || 'Não foi possível consultar os valores.');
                if (id !== this.requestId) return;
                this.report = result.data.report;
                this.render(this.report);
                this.feedback('');
            } catch (error) {
                if (id !== this.requestId || error.name === 'AbortError') return;
                // A tela mantém a última consulta consistente, inclusive seus filtros.
                this.syncFilters(this.report);
                this.feedback(`${error.message} Os valores exibidos são da última consulta concluída.`, true);
            } finally {
                if (id === this.requestId) {
                    this.root.setAttribute('aria-busy', 'false');
                    this.refresh.disabled = false;
                }
            }
        }

        syncFilters(report) {
            const course = this.form.elements.drj_fin_course;
            const classes = this.form.elements.drj_fin_class;
            course.innerHTML = '<option value="">Todos os cursos</option>' + report.catalog.courses.map(item => `<option value="${escape(item.value)}">${escape(item.label)}</option>`).join('');
            classes.innerHTML = '<option value="">Todas as turmas</option>' + report.catalog.classes.map(item => `<option value="${escape(item)}">${escape(item === '__sem_turma__' ? 'Sem turma informada' : item)}</option>`).join('');
            Object.entries(report.filters).forEach(([key, value]) => {
                const select = this.form.elements[`drj_fin_${key}`];
                if (select) select.value = value;
            });
        }

        row(values, group) {
            const title = group === 'classes' ? `${escape(values.class || 'Sem turma informada')}<small>${escape(values.course_label)}</small>` : escape(values.label);
            return `<tr><th scope="row">${title}</th><td>${values.students}</td>${amounts.map(key => `<td${key === 'net' ? ` class="drj-fin-net-cell${values[key] < 0 ? ' is-negative' : ''}"` : ''}>${money(values[key])}</td>`).join('')}<td>${percent(values.unconditional, values.gross)}</td></tr>`;
        }

        render(report) {
            const summary = report.summary;
            this.syncFilters(report);
            this.root.querySelectorAll('[data-fin-value]').forEach(element => {
                const key = element.dataset.finValue;
                element.textContent = amounts.includes(key) ? money(summary[key]) : summary[key];
                if (key === 'net') element.classList.toggle('is-negative', summary.net < 0);
            });
            this.root.querySelector('[data-fin-share]').textContent = percent(summary.unconditional, summary.gross);
            this.root.querySelector('[data-fin-context]').textContent = report.filters.basis === 'contract' ? 'Total dos contratos: cada aluno é multiplicado pelo seu número de parcelas.' : 'Por parcela: soma do valor de uma parcela de cada aluno selecionado.';
            this.root.querySelector('[data-fin-updated]').textContent = report.generated_at;
            this.root.querySelector('.drj-fin-alert').hidden = !summary.over_discount;
            this.root.querySelector('[data-fin-warning]').textContent = summary.over_discount;
            ['courses', 'classes'].forEach(group => {
                this.root.querySelector(`[data-fin-table="${group}"]`).innerHTML = report[group].length ? report[group].map(values => this.row(values, group)).join('') : '<tr><td colspan="9" class="drj-fin-empty">Nenhum aluno encontrado para os filtros selecionados.</td></tr>';
            });
            this.root.querySelectorAll('[data-fin-total]').forEach(footer => {
                footer.innerHTML = this.row({...summary, label: 'Total geral filtrado'}, 'courses');
            });
            const max = Math.max(1, ...report.courses.flatMap(course => [course.gross, Math.abs(course.net)]));
            this.root.querySelector('[data-fin-course-chart]').innerHTML = report.courses.length ? report.courses.map(course => `<div class="drj-fin-course-bars"><strong>${escape(course.label)}</strong>${[['gross', 'Bruto'], ['net', 'Líquido']].map(([key, label]) => `<div class="drj-fin-bar-line"><span>${label}</span><div class="drj-fin-track"><div class="drj-fin-bar is-${key} ${course[key] < 0 ? 'is-negative' : ''}" style="width:${Math.abs(course[key]) / max * 100}%"></div></div><b>${money(course[key])}</b></div>`).join('')}</div>`).join('') : '<p class="drj-fin-empty">Nenhum aluno encontrado para os filtros selecionados.</p>';
            this.root.querySelector('[data-fin-scholarship-chart]').innerHTML = [['unconditional', 'Incondicionais'], ['referral', 'Indicação'], ['siblings', 'Irmãos']].map(([key, label]) => `<div class="drj-fin-scholarship-line"><div><span>${label}</span><strong>${money(summary[key])}</strong></div><div class="drj-fin-track"><div class="drj-fin-bar is-${key}" style="width:${summary.scholarships > 0 ? Math.max(0, summary[key]) / summary.scholarships * 100 : 0}%"></div></div></div>`).join('');
        }
    }

    const start = () => document.querySelectorAll('[data-drj-finance]').forEach(root => {
        if (!root.dataset.initialized) {
            root.dataset.initialized = '1';
            new Finance(root);
        }
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
