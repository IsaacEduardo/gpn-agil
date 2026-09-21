{{--
    Encaminhamento a partir da listagem (sem sair da página).

    - Modal único de encaminhamento (individual OU em lote) com combobox pesquisável.
    - AJAX (fetch) para encaminhar individual/lote e para receber individual, com toasts
      e atualização inline da linha (sem reload). Progressive enhancement: se o JS falhar,
      os formulários nativos continuam a funcionar.

    Requer: $departamentos (coleção) e a `.toast-container` do layout.
--}}

{{-- Modal de encaminhamento (individual + lote) --}}
<div class="modal fade" id="modalEncaminharEntrada" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-primary text-white p-4 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="fas fa-paper-plane me-2"></i><span id="encModalTitle">Encaminhar documento</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Destino (Departamento)</label>
                    <x-departamento-combobox name="destino_departamento_id" :departamentos="$departamentos" />
                    <div class="text-danger small mt-1 d-none" id="encDestinoError"></div>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Despacho / Observação</label>
                    <textarea id="encObservacao" class="form-control" rows="3" placeholder="Insira o despacho (opcional)..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" id="encModalSubmit">
                    <span class="enc-submit-label"><i class="fas fa-paper-plane me-1"></i> Encaminhar</span>
                    <span class="enc-submit-spinner d-none"><span class="spinner-border spinner-border-sm me-1"></span> A encaminhar...</span>
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const modalEl = document.getElementById('modalEncaminharEntrada');
            if (!modalEl) return;
            const bsModal = window.bootstrap ? new bootstrap.Modal(modalEl) : null;
            const combobox = modalEl.querySelector('[data-dep-combobox]');
            const hiddenDestino = modalEl.querySelector('.dep-combobox-value');
            const obsInput = document.getElementById('encObservacao');
            const titleEl = document.getElementById('encModalTitle');
            const errEl = document.getElementById('encDestinoError');
            const submitBtn = document.getElementById('encModalSubmit');

            let mode = 'single';   // 'single' | 'batch'
            let docId = null;

            function encToast(message, type = 'info') {
                const container = document.querySelector('.toast-container');
                const icons = { success: 'fa-check-circle text-success', danger: 'fa-exclamation-circle text-danger', warning: 'fa-exclamation-triangle text-warning', info: 'fa-info-circle text-info' };
                const el = document.createElement('div');
                el.className = 'toast align-items-center border-0 shadow-lg';
                el.setAttribute('role', 'alert'); el.setAttribute('aria-live', 'assertive'); el.setAttribute('aria-atomic', 'true');
                el.innerHTML = `<div class="d-flex"><div class="toast-body d-flex align-items-center gap-2"><i class="fas ${icons[type] || icons.info}"></i><span>${message}</span></div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
                if (!container) { return; }
                container.appendChild(el);
                if (window.bootstrap && window.bootstrap.Toast) { const t = new bootstrap.Toast(el, { delay: 6000 }); t.show(); el.addEventListener('hidden.bs.toast', () => el.remove()); }
                else { el.style.display = 'block'; setTimeout(() => el.remove(), 6000); }
            }

            function selectedIds() {
                return Array.from(document.querySelectorAll('.row-checkbox:checked')).map(c => parseInt(c.value, 10));
            }

            function setBusy(busy) {
                submitBtn.disabled = busy;
                submitBtn.querySelector('.enc-submit-label').classList.toggle('d-none', busy);
                submitBtn.querySelector('.enc-submit-spinner').classList.toggle('d-none', !busy);
            }

            function resetModal() {
                if (combobox && combobox.comboboxReset) combobox.comboboxReset();
                if (hiddenDestino) hiddenDestino.value = '';
                if (obsInput) obsInput.value = '';
                errEl.classList.add('d-none'); errEl.textContent = '';
                setBusy(false);
            }

            window.openEncaminhar = function (id, currentDeptId) {
                mode = 'single'; docId = id;
                resetModal();
                if (combobox) combobox.dataset.exclude = currentDeptId || '';
                titleEl.textContent = 'Encaminhar documento';
                if (bsModal) bsModal.show();
            };

            window.openEncaminharLote = function () {
                const ids = selectedIds();
                if (!ids.length) { encToast('Selecione pelo menos um documento.', 'warning'); return; }
                mode = 'batch'; docId = null;
                resetModal();
                if (combobox) combobox.dataset.exclude = '';
                titleEl.textContent = `Encaminhar ${ids.length} documento(s) em lote`;
                if (bsModal) bsModal.show();
            };

            // Escapa texto vindo do servidor antes de o injetar como HTML: o nome
            // do departamento é dado de utilizador e esta função constrói markup.
            function escaparTexto(valor) {
                const div = document.createElement('div');
                div.textContent = valor == null ? '' : String(valor);
                return div.innerHTML;
            }

            // A célula de localização mostra onde o documento está agora e, por
            // baixo, o trânsito em curso. O nome do local nunca é apagado — antes
            // o recebimento substituía a célula inteira por "Recebido agora" e a
            // linha deixava de dizer onde o documento estava.
            function pintarLocalizacao(loc, nomeAtual, transitoNome, estado) {
                const linhas = [`<span class="fw-medium text-dark loc-nome">${escaparTexto(nomeAtual || '—')}</span>`];

                if (estado === 'transito') {
                    linhas.push(`<span class="text-warning"><i class="fas fa-paper-plane me-1"></i>Em trânsito p/ ${escaparTexto(transitoNome || '—')} agora</span>`);
                } else if (estado === 'recebido') {
                    linhas.push('<span class="text-success"><i class="fas fa-check-circle me-1"></i>Recebido agora</span>');
                }

                loc.innerHTML = `<div class="d-flex flex-column small">${linhas.join('')}</div>`;
            }

            function updateRowForwarded(id, destinoNome) {
                const row = document.querySelector(`tr[data-doc-id="${id}"]`);
                if (!row) return;
                const loc = row.querySelector('.loc-cell');
                if (loc) {
                    // A custódia só muda no recebimento: o nome do local mantém-se
                    // e o destino fica registado como trânsito pendente.
                    const nomeAtual = loc.querySelector('.loc-nome')?.textContent?.trim() || '—';
                    loc.dataset.transitoNome = destinoNome || '';
                    pintarLocalizacao(loc, nomeAtual, destinoNome, 'transito');
                }
                const cb = row.querySelector('.row-checkbox');
                if (cb) { cb.checked = false; cb.dataset.canForward = '0'; }
                row.classList.add('table-success');
                setTimeout(() => row.classList.remove('table-success'), 1600);
            }

            function updateRowReceived(id) {
                const row = document.querySelector(`tr[data-doc-id="${id}"]`);
                if (!row) return;
                const loc = row.querySelector('.loc-cell');
                if (loc) {
                    // Ao receber, o destino do trânsito passa a ser o local atual.
                    const nomeAtual = (loc.dataset.transitoNome || '').trim()
                        || loc.querySelector('.loc-nome')?.textContent?.trim()
                        || '—';
                    loc.dataset.transitoNome = '';
                    pintarLocalizacao(loc, nomeAtual, '', 'recebido');
                }
                const cb = row.querySelector('.row-checkbox');
                if (cb) cb.checked = false;
                row.classList.add('table-success');
                setTimeout(() => row.classList.remove('table-success'), 1600);
            }

            async function submitForward() {
                const destino = hiddenDestino ? hiddenDestino.value : '';
                if (!destino) { errEl.textContent = 'Selecione o departamento de destino.'; errEl.classList.remove('d-none'); return; }
                errEl.classList.add('d-none');

                const ids = mode === 'batch' ? selectedIds() : [docId];
                if (!ids.length || (mode === 'single' && !docId)) return;

                setBusy(true);
                const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
                const obs = obsInput ? obsInput.value : null;
                try {
                    let res, data;
                    if (mode === 'single') {
                        res = await fetch(`{{ url('documentos-entradas') }}/${docId}/encaminhar`, {
                            method: 'POST', headers, credentials: 'same-origin',
                            body: JSON.stringify({ destino_departamento_id: destino, observacao: obs }),
                        });
                        data = await res.json().catch(() => ({}));
                        if (res.ok && data.success) {
                            updateRowForwarded(docId, data.destino);
                            encToast(data.message || 'Documento encaminhado.', 'success');
                            if (bsModal) bsModal.hide();
                        } else {
                            errEl.textContent = data.message || 'Não foi possível encaminhar.'; errEl.classList.remove('d-none');
                        }
                    } else {
                        res = await fetch(`{{ route('documentos-entradas.batch.encaminhar') }}`, {
                            method: 'POST', headers, credentials: 'same-origin',
                            body: JSON.stringify({ ids: JSON.stringify(ids), destino_departamento_id: destino, observacao: obs }),
                        });
                        data = await res.json().catch(() => ({}));
                        encToast(data.message || 'Lote processado.', data.success ? 'success' : 'warning');
                        if (data.success) { ids.forEach(id => updateRowForwarded(id, null)); if (bsModal) bsModal.hide(); }
                        else { errEl.textContent = data.message || 'Nenhum documento encaminhado.'; errEl.classList.remove('d-none'); }
                    }
                } catch (e) {
                    errEl.textContent = 'Erro de comunicação. Tente novamente.'; errEl.classList.remove('d-none');
                } finally {
                    setBusy(false);
                }
            }

            submitBtn.addEventListener('click', submitForward);

            // AJAX para "Receber" individual (formulários marcados com .js-ajax-receber).
            document.addEventListener('submit', async function (e) {
                const form = e.target.closest('form.js-ajax-receber');
                if (!form) return;
                e.preventDefault();
                const docRow = form.closest('tr[data-doc-id]');
                const id = docRow ? docRow.getAttribute('data-doc-id') : null;
                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        body: new URLSearchParams({ _method: 'PATCH' }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.success) {
                        if (id) updateRowReceived(id);
                        encToast(data.message || 'Documento recebido.', data.already ? 'info' : 'success');
                    } else {
                        encToast(data.message || 'Não foi possível receber.', 'danger');
                    }
                } catch (err) {
                    encToast('Erro de comunicação ao receber.', 'danger');
                }
            });

            // Botão de encaminhamento em lote na barra de ações.
            const btnLote = document.getElementById('btnEncaminharLote');
            if (btnLote) btnLote.addEventListener('click', () => window.openEncaminharLote());
        })();
    </script>
@endpush
