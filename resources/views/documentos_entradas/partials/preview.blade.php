<div class="preview-header border-bottom p-3 d-flex justify-content-between align-items-center bg-light">
    <div>
        <h5 class="fw-bold text-dark mb-0">Documento #{{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}</h5>
        <span class="small text-muted">Cadastrado por: {{ optional($doc->usuario)->name ?? '—' }}</span>
    </div>
    <div class="d-flex align-items-center gap-2">
        @php($st = $doc->status)
        @php($stColor = $st === 'registrado' ? 'primary' : ($st === 'encaminhado' ? 'info' : ($st === 'encaminhado_externo' ? 'dark' : 'success')))
        <span class="badge bg-{{ $stColor }}-subtle text-{{ $stColor }} border border-{{ $stColor }}-subtle rounded-pill text-uppercase" style="font-size: 0.75rem; padding: 0.35em 0.8em;">
            {{ str_replace('_', ' ', $st) }}
        </span>
        <button type="button" class="btn-close" onclick="closePreviewDrawer()" aria-label="Close"></button>
    </div>
</div>

<div class="preview-body flex-grow-1 overflow-y-auto p-3" style="max-height: calc(100vh - 140px);">
    {{-- Info Card --}}
    <div class="card border-0 bg-light mb-3 rounded-3">
        <div class="card-body p-3">
            <h6 class="fw-bold text-muted small text-uppercase mb-2"><i class="fas fa-info-circle me-1"></i>Metadados</h6>
            <div class="row g-2 small">
                <div class="col-6">
                    <span class="text-muted d-block">Procedência</span>
                    <strong class="text-dark">{{ $doc->procedencia ?? '—' }}</strong>
                </div>
                <div class="col-6">
                    <span class="text-muted d-block">Espécie</span>
                    <strong class="text-dark">{{ $doc->classificacao_especie ?? '—' }}</strong>
                </div>
                <div class="col-6 mt-2">
                    <span class="text-muted d-block">Referência</span>
                    <strong class="text-dark">{{ $doc->classificacao_ref_numero ?? '—' }}</strong>
                </div>
                <div class="col-6 mt-2">
                    <span class="text-muted d-block">Data de Entrada</span>
                    <strong class="text-dark">{{ optional($doc->data_entrada)->format('d/m/Y H:i') ?? '—' }}</strong>
                </div>
                <div class="col-12 mt-2">
                    <span class="text-muted d-block">Setor/Departamento Atual</span>
                    <strong class="text-dark"><i class="fas fa-sitemap text-primary me-1"></i>{{ optional($doc->departamento)->nome ?? '—' }}</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- Assunto --}}
    <div class="mb-3">
        <h6 class="fw-bold text-muted small text-uppercase mb-1">Assunto</h6>
        <div class="text-dark fw-medium" style="font-size: 0.95rem; line-height: 1.4;">
            {{ $doc->assunto }}
        </div>
    </div>

    {{-- Observações --}}
    @if($doc->observacoes)
        <div class="mb-3">
            <h6 class="fw-bold text-muted small text-uppercase mb-1">Observações</h6>
            <div class="p-3 bg-light rounded text-secondary small" style="white-space: pre-line; border-left: 3px solid #cbd5e1;">
                {{ $doc->observacoes }}
            </div>
        </div>
    @endif

    {{-- Vistos Status --}}
    <div class="mb-3">
        <h6 class="fw-bold text-muted small text-uppercase mb-2">Vistos & Pareceres</h6>
        <div class="row g-2">
            <div class="col-6">
                <div class="p-2 border rounded d-flex align-items-center justify-content-between bg-white">
                    <span class="small text-muted">Departamento</span>
                    @php($depStatus = $doc->visto_departamento_status ?? 'pendente')
                    @php($depColor = $depStatus === 'aprovado' ? 'success' : ($depStatus === 'rejeitado' ? 'danger' : 'secondary'))
                    <span class="badge bg-{{ $depColor }}-subtle text-{{ $depColor }} border border-{{ $depColor }}-subtle rounded-pill text-uppercase" style="font-size: 0.65rem;">
                        {{ $depStatus }}
                    </span>
                </div>
            </div>
            <div class="col-6">
                <div class="p-2 border rounded d-flex align-items-center justify-content-between bg-white">
                    <span class="small text-muted">Gabinete</span>
                    @php($gabStatus = $doc->visto_gabinete_status ?? 'pendente')
                    @php($gabColor = $gabStatus === 'aprovado' ? 'success' : ($gabStatus === 'rejeitado' ? 'danger' : 'secondary'))
                    <span class="badge bg-{{ $gabColor }}-subtle text-{{ $gabColor }} border border-{{ $gabColor }}-subtle rounded-pill text-uppercase" style="font-size: 0.65rem;">
                        {{ $gabStatus }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Arquivos --}}
    <div class="mb-3">
        <h6 class="fw-bold text-muted small text-uppercase mb-2">Arquivos e Anexos ({{ $doc->anexos->count() + ($doc->arquivo_caminho ? 1 : 0) }})</h6>
        <div class="list-group list-group-flush border rounded-3 overflow-hidden bg-white">
            @if($doc->arquivo_caminho)
                <a href="{{ route('documentos-entradas.arquivo.download', $doc) }}" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-2.5 text-decoration-none">
                    <span class="text-truncate text-primary small fw-semibold"><i class="fas fa-file-pdf me-2 text-danger"></i>Arquivo Principal</span>
                    <i class="fas fa-external-link-alt text-muted small" style="font-size: 0.75rem;"></i>
                </a>
            @endif
            @foreach($doc->anexos as $anexo)
                <a href="{{ route('documentos-entradas.anexos.download', [$doc, $anexo]) }}" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-2.5 text-decoration-none">
                    <span class="text-truncate text-dark small"><i class="fas fa-paperclip me-2 text-muted"></i>{{ $anexo->nome_original }}</span>
                    <i class="fas fa-download text-muted small" style="font-size: 0.75rem;"></i>
                </a>
            @endforeach
            @if(!$doc->arquivo_caminho && $doc->anexos->count() === 0)
                <div class="list-group-item p-3 text-center text-muted small">Nenhum arquivo anexado.</div>
            @endif
        </div>
    </div>

    {{-- Histórico simplificado --}}
    @if($doc->encaminhamentos->count())
        <div class="mb-2">
            <h6 class="fw-bold text-muted small text-uppercase mb-2">Histórico de Encaminhamentos</h6>
            <div class="position-relative ps-3 border-start mb-2" style="margin-left: 8px; border-color: #e2e8f0 !important;">
                @foreach($doc->encaminhamentos as $enc)
                    <div class="mb-3 position-relative">
                        <div class="position-absolute bg-{{ $enc->recebido_em ? 'success' : 'warning' }} rounded-circle" style="width: 8px; height: 8px; left: -18px; top: 5px; border: 1.5px solid #fff;"></div>
                        <div class="small fw-semibold text-dark">{{ optional($enc->origemDepartamento)->nome ?? '—' }} <i class="fas fa-arrow-right mx-1 text-muted" style="font-size: 0.7rem;"></i> {{ optional($enc->destinoDepartamento)->nome ?? '—' }}</div>
                        <div class="text-muted" style="font-size: 0.72rem;">
                            {{ optional($enc->encaminhado_em)->format('d/m/Y H:i') }}
                            @if($enc->recebido_em)
                                <span class="text-success ms-2"><i class="fas fa-check-circle me-0.5"></i> Recebido</span>
                            @else
                                <span class="text-warning ms-2"><i class="fas fa-clock me-0.5"></i> Pendente</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

<div class="preview-footer border-top p-3 bg-light d-flex gap-2">
    <a href="{{ route('documentos-entradas.show', $doc) }}" class="btn btn-primary btn-sm flex-grow-1 fw-semibold py-2">
        <i class="fas fa-eye me-1"></i> Abrir Detalhes
    </a>
    
    @if($canReceive)
        <form action="{{ route('documentos-entradas.encaminhamentos.receber', ['documento' => $doc->id, 'encaminhamento' => $doc->ultimoEncaminhamento->id]) }}" method="POST" class="flex-grow-1">
            @csrf @method('PATCH')
            <button type="submit" class="btn btn-success btn-sm w-100 fw-semibold py-2"><i class="fas fa-check me-1"></i> Receber</button>
        </form>
    @endif

    @if($canForward)
        <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold py-2" data-bs-toggle="modal" data-bs-target="#genericEncaminharModal" data-doc-id="{{ $doc->id }}" data-action-url="{{ route('documentos-entradas.encaminhar', $doc) }}">
            <i class="fas fa-paper-plane me-1"></i> Encaminhar
        </button>
    @endif
    
    <button type="button" class="btn btn-outline-secondary btn-sm fw-semibold py-2" data-bs-toggle="modal" data-bs-target="#genericArquivarModal" data-doc-id="{{ $doc->id }}" data-action-url="{{ route('pastas.arquivar', $doc->id) }}">
        <i class="fas fa-archive me-1"></i> Arquivar
    </button>
</div>
