<div class="preview-header border-bottom p-3 d-flex justify-content-between align-items-center bg-light">
    <div>
        <h5 class="fw-bold text-dark mb-0">Documento #{{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}</h5>
        <span class="small text-muted">Cadastrado por: {{ optional($doc->usuario)->name ?? '—' }}</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        @php($st = $doc->status)
        @php($stColor = $st === 'registrado' ? 'primary' : ($st === 'encaminhado' ? 'info' : ($st === 'tratado' ? 'success' : 'secondary')))
        <span class="badge bg-{{ $stColor }}-subtle text-{{ $stColor }} border border-{{ $stColor }}-subtle rounded-pill text-uppercase px-2.5 py-1" style="font-size: 0.75rem;">
            <i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i>{{ str_replace('_', ' ', $st) }}
        </span>
        <button type="button" class="btn-close" onclick="closePreviewDrawer()" aria-label="Fechar"></button>
    </div>
</div>

<div class="preview-body flex-grow-1 overflow-y-auto p-3" style="max-height: calc(100vh - 145px);">
    {{-- Resumo Contextual & Metadados --}}
    <div class="card border-0 bg-light mb-3 rounded-3 shadow-sm">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-1">
                <h6 class="fw-bold text-uppercase small text-muted mb-0"><i class="fas fa-info-circle me-1 text-primary"></i> Resumo do Documento</h6>
                <div class="d-flex gap-1.5 align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 small fw-semibold shadow-xs" onclick="gerarResumoIaDrawer({{ $doc->id }}, 'ENTRADA')" title="Gerar Resumo Executivo em 3 blocos com DeepSeek">
                        <i class="fas fa-brain me-1 text-primary"></i> ✨ Resumir com IA
                    </button>
                    @if($doc->arquivo_caminho)
                        <a href="{{ route('documentos-entradas.arquivo.download', $doc) }}" target="_blank" class="btn btn-sm btn-outline-danger py-0 px-2 small fw-semibold">
                            <i class="fas fa-file-pdf me-1"></i> PDF
                        </a>
                    @endif
                </div>
            </div>

            <div class="mb-2">
                <span class="text-muted small d-block">Assunto</span>
                <div class="fw-bold text-dark" style="font-size: 0.95rem; line-height: 1.3;">
                    {{ $doc->assunto }}
                </div>
            </div>

            <div class="row g-2 small border-top pt-2 mt-2">
                <div class="col-6">
                    <span class="text-muted d-block">Procedência</span>
                    <strong class="text-dark">{{ $doc->procedencia ?? '—' }}</strong>
                </div>
                <div class="col-6">
                    <span class="text-muted d-block">Espécie / Ref.</span>
                    <strong class="text-dark">{{ $doc->classificacao_especie ?? '—' }} ({{ $doc->classificacao_ref_numero ?? 'S/N' }})</strong>
                </div>
                <div class="col-6 mt-2">
                    <span class="text-muted d-block">Data de Entrada</span>
                    <strong class="text-dark">{{ optional($doc->data_entrada)->format('d/m/Y') ?? '—' }}</strong>
                </div>
                <div class="col-6 mt-2">
                    <span class="text-muted d-block">Setor Atual</span>
                    <strong class="text-dark"><i class="fas fa-building text-secondary me-1"></i>{{ optional($doc->departamento)->nome ?? 'Gabinete' }}</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- Card Colapsável de Resumo com IA --}}
    <div id="drawerIaResumoContainer" class="card border-primary border-opacity-50 mb-3 rounded-3 shadow-sm d-none" style="background: linear-gradient(180deg, #f0f7ff, #ffffff);">
        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center py-2 px-3">
            <h6 class="fw-bold text-primary small text-uppercase mb-0 d-flex align-items-center gap-1.5">
                <i class="fas fa-brain text-primary"></i> Resumo Executivo IA (DeepSeek)
            </h6>
            <button type="button" class="btn-close btn-close-sm" onclick="document.getElementById('drawerIaResumoContainer').classList.add('d-none')" aria-label="Fechar"></button>
        </div>
        <div class="card-body p-3 pt-0" id="drawerIaResumoBody"></div>
    </div>

    {{-- Despacho / Parecer Anterior (Se existir) --}}
    @if($doc->texto_despacho)
        <div class="mb-3">
            <h6 class="fw-bold text-primary small text-uppercase mb-1"><i class="fas fa-file-signature me-1"></i> Despacho Registrado</h6>
            <div class="p-3 bg-primary-subtle border border-primary border-opacity-25 rounded-3 text-dark small" style="white-space: pre-line;">
                <div class="fw-semibold">{{ $doc->texto_despacho }}</div>
                <div class="text-muted border-top pt-1 mt-2 d-flex justify-content-between" style="font-size: 0.72rem;">
                    <span>Emissor: {{ optional($doc->despachadoPor)->name ?? 'Gabinete' }}</span>
                    <span>{{ optional($doc->data_despacho)->format('d/m/Y H:i') }}</span>
                </div>
            </div>
        </div>
    @endif

    {{-- Painel de Ação Rápida Unificada (Preview-and-Act) --}}
    <div class="card border-primary border-opacity-50 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-primary text-white py-2 px-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold small text-uppercase">
                <i class="fas fa-pen-nib me-1"></i> Ação Rápida de Despacho / Delegar
            </h6>
            <span class="badge bg-white text-primary small fw-bold text-uppercase" style="font-size: 0.65rem;">
                {{ $userProfile === 'gabinete' ? 'Gabinete Executivo' : ($userProfile === 'chefe_departamento' ? 'Chefia de Departamento' : 'Técnico Operacional') }}
            </span>
        </div>
        <div class="card-body p-3 bg-white">
            <form id="formDrawerQuickAction" action="{{ route('documentos-entradas.quick-action', $doc) }}" method="POST" onsubmit="event.preventDefault(); submitDrawerQuickAction(this);">
                @csrf

                @if($userProfile === 'gabinete')
                    {{-- Form para Chefe de Gabinete --}}
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">
                            Departamentos de Destino <span class="text-danger">*</span>
                        </label>
                        <select name="destino_departamento_ids[]" class="form-select form-select-sm" multiple required style="min-height: 90px;">
                            @foreach($departamentos as $dep)
                                <option value="{{ $dep->id }}" {{ in_array($dep->id, $doc->departamentosDestino->pluck('id')->toArray()) ? 'selected' : '' }}>
                                    🏢 {{ $dep->nome }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text small text-muted">Pressione Ctrl para selecionar múltiplos departamentos.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">
                            Instrução do Despacho / Diretriz <span class="text-danger">*</span>
                        </label>
                        <textarea name="texto_despacho" class="form-control form-control-sm" rows="3" placeholder="Digite a ordem executiva / orientação para os departamentos..." required></textarea>
                    </div>

                @elseif($userProfile === 'chefe_departamento')
                    {{-- Form para Chefe de Departamento --}}
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">
                            Atribuir ao Técnico da Equipe <span class="text-danger">*</span>
                        </label>
                        <select name="assigned_to_user_id" class="form-select form-select-sm" required>
                            <option value="">-- Selecione o Técnico Responsável --</option>
                            @foreach($depUsuarios as $u)
                                <option value="{{ $u->id }}">👤 {{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">
                            Prazo de Execução <span class="text-danger">*</span>
                        </label>
                        <input type="date" name="prazo_at" class="form-control form-control-sm" value="{{ now()->addDays(3)->format('Y-m-d') }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase text-muted">
                            Instrução do Despacho / Tarefa <span class="text-danger">*</span>
                        </label>
                        <textarea name="descricao" class="form-control form-control-sm" rows="3" placeholder="Descreva os procedimentos técnicos exigidos nesta demanda..." required></textarea>
                    </div>

                @elseif($userProfile === 'tecnico')
                    {{-- Form/View para Técnico --}}
                    @if($minhaTarefa)
                        <input type="hidden" name="tarefa_id" value="{{ $minhaTarefa->id }}">
                        <div class="p-2.5 bg-warning-subtle border border-warning-subtle rounded-3 mb-3 small">
                            <div class="fw-bold text-warning-emphasis mb-1">
                                <i class="fas fa-tasks me-1"></i> Demanda Atribuída: {{ $minhaTarefa->titulo }}
                            </div>
                            <div class="text-dark fst-italic mb-1">{{ $minhaTarefa->descricao }}</div>
                            <div class="text-muted" style="font-size: 0.72rem;">
                                Prazo: <strong>{{ optional($minhaTarefa->prazo_at)->format('d/m/Y') ?? 'S/D' }}</strong>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">
                                Parecer / Resposta do Técnico <span class="text-danger">*</span>
                            </label>
                            <textarea name="observacao" class="form-control form-control-sm" rows="3" placeholder="Descreva o parecer emitido ou a conclusão técnica desta solicitação..." required></textarea>
                        </div>
                    @else
                        <div class="p-3 text-center text-muted small bg-light rounded border">
                            <i class="fas fa-info-circle me-1"></i> Não há demandas diretas sob sua atribuição ativa para este documento.
                        </div>
                    @endif
                @endif
            </form>
        </div>
    </div>

    {{-- Histórico de Tarefas & Encaminhamentos --}}
    @if(isset($tarefas) && $tarefas->count())
        <div class="mb-3">
            <h6 class="fw-bold text-muted small text-uppercase mb-2"><i class="fas fa-tasks me-1"></i> Despachos & Tarefas Emitidas ({{ $tarefas->count() }})</h6>
            <div class="list-group list-group-flush border rounded-3 overflow-hidden bg-white">
                @foreach($tarefas as $t)
                    <div class="list-group-item p-2.5 small">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong class="text-dark">{{ $t->titulo }}</strong>
                            <span class="badge bg-{{ $t->status === 'concluida' ? 'success' : 'warning' }}-subtle text-{{ $t->status === 'concluida' ? 'success' : 'warning-emphasis' }} border border-{{ $t->status === 'concluida' ? 'success' : 'warning' }}-subtle rounded-pill" style="font-size: 0.65rem;">
                                {{ ucfirst($t->status) }}
                            </span>
                        </div>
                        <div class="text-muted fst-italic mt-1" style="font-size: 0.78rem;">{{ $t->descricao }}</div>
                        <div class="d-flex justify-content-between text-muted border-top pt-1 mt-1" style="font-size: 0.7rem;">
                            <span>Para: <strong>{{ optional($t->assignedToUser)->name ?? '—' }}</strong></span>
                            <span>Prazo: <strong>{{ optional($t->prazo_at)->format('d/m/Y') ?? '—' }}</strong></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

{{-- Rodapé de Ação Rápida Unificado --}}
<div class="preview-footer border-top p-3 bg-light d-flex gap-2 align-items-center">
    <button type="submit" form="formDrawerQuickAction" id="btnSubmitDrawerQuickAction" class="btn btn-success fw-bold flex-grow-1 py-2 shadow-sm d-flex align-items-center justify-content-center">
        <i class="fas fa-paper-plane me-1.5"></i> Despachar / Delegar
    </button>

    <a href="{{ route('documentos-entradas.protocolo.etiqueta', [$doc, 'auto_print' => 1]) }}" target="_blank" class="btn btn-outline-dark fw-semibold py-2" title="Imprimir Etiqueta Adesiva (100x50mm)">
        <i class="fas fa-barcode"></i>
    </a>

    <a href="{{ route('documentos-entradas.show', $doc) }}" class="btn btn-outline-secondary fw-semibold py-2" title="Abrir Detalhes Completos">
        <i class="fas fa-external-link-alt me-1"></i> Detalhes
    </a>
</div>

<script>
if (typeof window.submitDrawerQuickAction === 'undefined') {
    window.submitDrawerQuickAction = function(form) {
        if (!form) return;
        const btn = document.getElementById('btnSubmitDrawerQuickAction');
        const originalBtnHtml = btn ? btn.innerHTML : '';

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processando...';
        }

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => { throw new Error(err.error || err.message || 'Erro ao processar.'); });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                if (window.Toast) {
                    window.Toast.success(data.message || 'Despacho/Delegação realizada com sucesso!');
                } else if (typeof showQuickToast === 'function') {
                    showQuickToast(data.message || 'Despacho/Delegação realizada com sucesso!');
                }
                if (typeof closePreviewDrawer === 'function') {
                    closePreviewDrawer();
                }

                if (data.tabs && Array.isArray(data.tabs)) {
                    data.tabs.forEach(tab => {
                        const badge = document.querySelector(`.corporate-underline-tab[href*="tab=${tab.key}"] .tab-badge`);
                        if (badge) {
                            badge.textContent = tab.count;
                        }
                    });
                }

                setTimeout(() => {
                    window.location.reload();
                }, 600);
            } else {
                if (window.Toast) {
                    window.Toast.error('Atenção', data.error || 'Erro ao processar solicitação.');
                }
            }
        })
        .catch(err => {
            console.error(err);
            if (window.Toast) {
                window.Toast.error('Erro de Comunicação', err.message || 'Falha ao conectar com o servidor.');
            }
        })
        .finally(() => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
            }
        });
    };
}

if (typeof window.gerarResumoIaDrawer === 'undefined') {
    window.gerarResumoIaDrawer = function(docId, tipo) {
        const container = document.getElementById('drawerIaResumoContainer');
        const body = document.getElementById('drawerIaResumoBody');
        if (!container || !body) return;

        container.classList.remove('d-none');
        body.innerHTML = `
            <div class="text-center py-3 text-muted">
                <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                <div class="small fw-semibold">Consultando DeepSeek AI e extraindo metadados, despachos e texto OCR...</div>
            </div>
        `;

        fetch('{{ route("api.ia.resumir") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: JSON.stringify({ documento_id: docId, tipo: tipo || 'ENTRADA' })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success && data.resumo) {
                let text = data.resumo;
                text = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
                text = text.replace(/^### (.*$)/gim, '<h6 class="fw-bold text-dark mt-2 mb-1" style="font-size:0.85rem;"><i class="fas fa-layer-group text-primary me-1"></i>$1</h6>');
                text = text.replace(/^## (.*$)/gim, '<h6 class="fw-bold text-dark mt-2 mb-1" style="font-size:0.88rem;">$1</h6>');
                text = text.replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>');
                text = text.replace(/^\> (.*$)/gim, '<div class="p-2 my-1 bg-white border-start border-3 border-primary rounded-end small text-muted fst-italic">$1</div>');
                text = text.replace(/^\s*[-*]\s+(.*$)/gim, '<li class="small text-secondary mb-1">$1</li>');
                text = text.replace(/(<li.*<\/li>)/gims, '<ul class="ps-3 mb-2">$1</ul>');
                text = text.replace(/<\/ul>\s*<ul>/gim, '');
                
                body.innerHTML = `
                    <div class="small text-dark" style="line-height:1.55;">
                        ${text}
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top border-primary border-opacity-10 mt-2">
                        <span class="small text-muted" style="font-size:0.7rem;"><i class="fas fa-check-circle text-success me-1"></i>Resumo Executivo em 3 Blocos</span>
                        <button type="button" class="btn btn-link btn-xs text-muted p-0" onclick="gerarResumoIaDrawer(${docId}, '${tipo}')" title="Atualizar resumo">
                            <i class="fas fa-rotate-right me-1"></i>Atualizar
                        </button>
                    </div>
                `;
            } else {
                body.innerHTML = `<div class="alert alert-warning p-2 small mb-0"><i class="fas fa-triangle-exclamation me-1"></i>${data.erro || 'Não foi possível gerar o resumo.'}</div>`;
            }
        })
        .catch(err => {
            body.innerHTML = `<div class="alert alert-danger p-2 small mb-0"><i class="fas fa-circle-exclamation me-1"></i>Erro ao comunicar com o servidor: ${err.message}</div>`;
        });
    };
}
</script>
