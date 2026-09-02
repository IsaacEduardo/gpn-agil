<!-- Modal de Despacho do Chefe de Gabinete -->
<div class="modal fade" id="modalDespacho{{ $doc->id }}" tabindex="-1" aria-labelledby="modalDespachoLabel{{ $doc->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalDespachoLabel{{ $doc->id }}">
                    <i class="fas fa-file-signature me-2"></i> Despachar Documento Entrada #{{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form action="{{ route('documentos-entradas.despachar', $doc) }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3 p-3 bg-light rounded border">
                        <div class="row">
                            <div class="col-md-6 mb-2 mb-md-0">
                                <strong>Origem / Procedência:</strong> {{ $doc->procedencia ?? 'N/I' }}
                            </div>
                            <div class="col-md-6">
                                <strong>Espécie / N.º:</strong> {{ $doc->classificacao_especie ?? 'Geral' }} {{ $doc->classificacao_ref_numero }}
                            </div>
                        </div>
                        <div class="mt-2">
                            <strong>Assunto:</strong> {{ $doc->assunto }}
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="texto_despacho_{{ $doc->id }}" class="form-label fw-bold">
                            Parecer / Despacho do Gabinete <span class="text-danger">*</span>
                        </label>
                        <textarea name="texto_despacho" id="texto_despacho_{{ $doc->id }}" class="form-control" rows="4" placeholder="Insira o texto do despacho, orientação ou decisão do Gabinete..." required>{{ old('texto_despacho', $doc->texto_despacho) }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            Departamento(s) Destinatário(s) <span class="text-danger">*</span>
                        </label>
                        <p class="text-muted small mb-2">Selecione um ou mais departamentos para onde este documento será encaminhado.</p>
                        
                        <div class="row g-2 max-vh-30 overflow-auto p-2 border rounded bg-white" style="max-height: 200px;">
                            @php
                                $selectedDeps = old('departamentos_ids', $doc->departamentosDestino->pluck('id')->toArray());
                            @endphp
                            @foreach($departamentos as $dep)
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="departamentos_ids[]" value="{{ $dep->id }}" id="dep_dest_{{ $doc->id }}_{{ $dep->id }}" {{ in_array($dep->id, $selectedDeps) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="dep_dest_{{ $doc->id }}_{{ $dep->id }}">
                                            {{ $dep->nome }} {{ $dep->sigla ? "({$dep->sigla})" : '' }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold">
                        <i class="fas fa-check-circle me-1"></i> Salvar Despacho (Marcar como Tratado)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
