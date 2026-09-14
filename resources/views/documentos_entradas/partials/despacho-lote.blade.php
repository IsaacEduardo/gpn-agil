{{--
    Despacho em lote a partir da listagem. A lista de destinos é comum, pelo que
    só é legítima dentro de um gabinete — o servidor recusa seleções que
    atravessem gabinetes (ver DocumentoEntradaService::despacharBatch).
--}}
<div class="modal fade" id="modalDespacharLote" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning-subtle border-bottom">
                <h5 class="modal-title fw-bold text-dark">
                    <i class="fas fa-file-signature me-2"></i>
                    Despachar <span id="despLoteContagem">0</span> documento(s)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <form id="formDespacharLote" action="{{ route('documentos-entradas.batch.despachar') }}" method="POST">
                @csrf
                <input type="hidden" name="ids" id="despLoteIds">

                <div class="modal-body p-4">
                    <div id="despLoteErro" class="alert alert-danger d-none" role="alert"></div>

                    <div class="alert alert-warning small">
                        <i class="fas fa-circle-info me-1"></i>
                        O mesmo texto e os mesmos destinos são aplicados a todos os documentos selecionados.
                        Selecione documentos <strong>do mesmo gabinete</strong>: os departamentos de destino não são os mesmos entre gabinetes.
                    </div>

                    <div class="mb-3">
                        <label for="despLoteTexto" class="form-label fw-bold">
                            Parecer / Despacho do Gabinete <span class="text-danger">*</span>
                        </label>
                        <textarea name="texto_despacho" id="despLoteTexto" class="form-control" rows="4" required
                                  placeholder="Texto do despacho, orientação ou decisão do Gabinete..."></textarea>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold">
                            Departamento(s) Destinatário(s) <span class="text-danger">*</span>
                        </label>
                        <div class="row g-2 p-2 border rounded bg-white" style="max-height: 220px; overflow-y: auto;">
                            @foreach ($departamentos as $dep)
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="departamentos_ids[]"
                                               value="{{ $dep->id }}" id="desp_lote_dep_{{ $dep->id }}">
                                        <label class="form-check-label" for="desp_lote_dep_{{ $dep->id }}">
                                            {{ $dep->nome }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold" id="despLoteSubmit">
                        <i class="fas fa-check-circle me-1"></i> Despachar Selecionados
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const botao = document.getElementById('btnDespacharLote');
        const modalEl = document.getElementById('modalDespacharLote');
        if (!botao || !modalEl) return;

        const modal = new bootstrap.Modal(modalEl);
        const form = document.getElementById('formDespacharLote');
        const erro = document.getElementById('despLoteErro');
        const submit = document.getElementById('despLoteSubmit');

        const selecionados = () => Array.from(
            document.querySelectorAll('.row-checkbox:checked, input[name="ids[]"]:checked')
        ).map(c => parseInt(c.value, 10)).filter(Boolean);

        botao.addEventListener('click', function() {
            const ids = selecionados();
            if (!ids.length) return;

            document.getElementById('despLoteIds').value = JSON.stringify(ids);
            document.getElementById('despLoteContagem').textContent = ids.length;
            erro.classList.add('d-none');
            modal.show();
        });

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            erro.classList.add('d-none');
            submit.disabled = true;

            fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            })
            .then(r => r.json().then(d => ({ ok: r.ok, d })))
            .then(({ ok, d }) => {
                if (ok && d.success) {
                    window.location.reload();
                    return;
                }
                erro.textContent = d.message || 'Não foi possível despachar os documentos selecionados.';
                erro.classList.remove('d-none');
                submit.disabled = false;
            })
            .catch(() => {
                erro.textContent = 'Falha de comunicação com o servidor.';
                erro.classList.remove('d-none');
                submit.disabled = false;
            });
        });
    });
</script>
@endpush
