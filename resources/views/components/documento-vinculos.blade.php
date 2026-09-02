@props([
    'documento',
    'tipo' => 'EXTERNO', // 'EXTERNO' ou 'INTERNO'
])

@php
    $tipoUpper = strtoupper($tipo);
    $docId = $documento->id;
    $vinculoService = app(\App\Services\DocumentoVinculoService::class);
    $vinculosIniciais = $vinculoService->listarVinculos($tipoUpper, $docId, Auth::user());
@endphp

<div class="documento-vinculos-wrapper" id="vinculosContainer-{{ $tipoUpper }}-{{ $docId }}">
    {{-- Header da Seção de Vínculos --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <i class="fas fa-link text-primary"></i> Vínculos & Dossiê do Documento
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill fs-7" id="vinculosCountBadge-{{ $docId }}">
                    {{ count($vinculosIniciais) }}
                </span>
            </h5>
            <p class="text-muted small mb-0">
                Relações bilaterais com respostas formais, pareceres técnicos, documentos complementares ou apensos.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            @if ($tipoUpper === 'EXTERNO')
                <a href="{{ route('documentos-internos.create', ['documento_entrada_id' => $docId, 'tipo_relacao' => 'RESPOSTA']) }}"
                    class="btn btn-sm btn-success shadow-sm d-flex align-items-center gap-1">
                    <i class="fas fa-reply"></i>
                    <span>Elaborar Resposta / Parecer</span>
                </a>
            @endif

            <button type="button" class="btn btn-sm btn-primary shadow-sm d-flex align-items-center gap-1"
                data-bs-toggle="modal" data-bs-target="#modalVincularDoc-{{ $docId }}">
                <i class="fas fa-plus-circle"></i>
                <span>Vincular Documentos</span>
            </button>
        </div>
    </div>

    {{-- Lista de Cards de Documentos Vinculados --}}
    <div id="vinculosList-{{ $docId }}" class="d-flex flex-column gap-3">
        @forelse ($vinculosIniciais as $v)
            @php
                $docRel = $v['documento'];
            @endphp
            <div class="card border border-light-subtle shadow-sm rounded-3 overflow-hidden vinculo-card" id="vinculo-card-{{ $v['id'] }}">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3">
                        
                        {{-- Informações Principais do Documento Vinculado --}}
                        <div class="d-flex gap-3 align-items-start flex-grow-1">
                            {{-- Ícone / Badge de Tipo --}}
                            <div class="rounded-3 p-2 text-center d-flex flex-column align-items-center justify-content-center flex-shrink-0"
                                style="width: 48px; height: 48px; background-color: {{ $docRel['tipo'] === 'EXTERNO' ? '#f3f4f6' : '#e0f2fe' }}; color: {{ $docRel['tipo'] === 'EXTERNO' ? '#4b5563' : '#0369a1' }};">
                                <i class="{{ $docRel['tipo'] === 'EXTERNO' ? 'fas fa-inbox' : 'fas fa-file-signature' }} fs-5"></i>
                            </div>

                            <div class="flex-grow-1 min-w-0">
                                {{-- Badges de Relação Semântica e Tipo --}}
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="badge {{ $v['tipo_relacao_badge'] }} rounded-pill px-2 py-1 small fw-semibold">
                                        <i class="{{ $v['tipo_relacao_icon'] }} me-1"></i> {{ $v['tipo_relacao_label'] }}
                                    </span>

                                    <span class="badge {{ $docRel['tipo_badge'] }} rounded-pill px-2 py-1 small">
                                        {{ $docRel['tipo_label'] }}
                                    </span>

                                    <span class="badge {{ $docRel['status_badge'] }} rounded-pill px-2 py-1 small">
                                        {{ $docRel['status_label'] }}
                                    </span>
                                </div>

                                {{-- Identificador e Título --}}
                                <h6 class="fw-bold mb-1 text-dark">
                                    @if ($docRel['pode_visualizar'])
                                        <a href="{{ $docRel['url_show'] }}" class="text-decoration-none text-dark hover-primary">
                                            <span class="text-primary">{{ $docRel['numero_identificador'] }}</span> — {{ $docRel['titulo'] }}
                                        </a>
                                    @else
                                        <span class="text-muted">{{ $docRel['numero_identificador'] }} — {{ $docRel['titulo'] }}</span>
                                        <i class="fas fa-lock text-muted small ms-1" title="Acesso restrito ao setor"></i>
                                    @endif
                                </h6>

                                {{-- Metadados: Autor/Procedência, Departamento e Data --}}
                                <div class="d-flex flex-wrap gap-3 small text-muted mt-2">
                                    <span>
                                        <i class="fas fa-user-circle me-1 opacity-75"></i>
                                        <strong>{{ $docRel['tipo'] === 'EXTERNO' ? 'Origem:' : 'Autor:' }}</strong> {{ $docRel['autor_ou_procedencia'] }}
                                    </span>
                                    <span>
                                        <i class="fas fa-building me-1 opacity-75"></i>
                                        <strong>Setor:</strong> {{ $docRel['departamento'] }}
                                    </span>
                                    <span>
                                        <i class="far fa-calendar-alt me-1 opacity-75"></i>
                                        {{ $docRel['data'] }}
                                    </span>
                                </div>

                                {{-- Justificativa / Observação do Vínculo --}}
                                @if (!empty($v['justificativa']))
                                    <div class="bg-light rounded p-2 mt-2 small text-secondary border-start border-3 border-primary">
                                        <i class="fas fa-comment-dots me-1 text-primary"></i>
                                        <span class="fst-italic">{{ $v['justificativa'] }}</span>
                                    </div>
                                @endif

                                <div class="mt-2 text-muted" style="font-size: 0.75rem;">
                                    Vinculado por <strong>{{ $v['vinculado_por'] }}</strong> em {{ $v['vinculado_em'] }}
                                </div>
                            </div>
                        </div>

                        {{-- Botões de Ação do Vínculo --}}
                        <div class="d-flex align-items-center gap-2 flex-shrink-0 ms-auto">
                            @if ($docRel['pode_visualizar'])
                                <a href="{{ $docRel['url_show'] }}" class="btn btn-sm btn-outline-secondary" title="Abrir Documento">
                                    <i class="fas fa-eye me-1"></i> Abrir
                                </a>
                                @if (!empty($docRel['url_pdf']))
                                    <a href="{{ $docRel['url_pdf'] }}" target="_blank" class="btn btn-sm btn-outline-danger" title="Ver PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                @endif
                            @endif

                            <button type="button" class="btn btn-sm btn-outline-danger border-0" title="Desvincular"
                                onclick="desvincularDocumento('{{ $v['id'] }}', '{{ $docId }}')">
                                <i class="fas fa-unlink"></i>
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-5 bg-light rounded-3 border border-dashed text-muted empty-state-vinculos">
                <div class="bg-white rounded-circle p-3 d-inline-block shadow-xs mb-3">
                    <i class="fas fa-link fa-2x text-secondary opacity-50"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">Nenhum documento vinculado</h6>
                <p class="small text-muted mb-3">Conecte respostas, pareceres, despachos ou documentos correlatos a este processo.</p>
                <button type="button" class="btn btn-sm btn-primary shadow-sm"
                    data-bs-toggle="modal" data-bs-target="#modalVincularDoc-{{ $docId }}">
                    <i class="fas fa-plus-circle me-1"></i> Vincular Documento
                </button>
            </div>
        @endforelse
    </div>
</div>

{{-- Modal de Pesquisa e Seleção de Documentos para Vínculo --}}
<div class="modal fade" id="modalVincularDoc-{{ $docId }}" tabindex="-1" aria-labelledby="modalVincularDocLabel-{{ $docId }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-3">
            <div class="modal-header bg-light border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="modalVincularDocLabel-{{ $docId }}">
                    <i class="fas fa-link text-primary"></i> Vincular Documentos ao Processo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body p-4">
                {{-- Formulário de Configuração do Vínculo --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Tipo de Relação / Semântica *</label>
                        <select id="selectTipoRelacao-{{ $docId }}" class="form-select">
                            <option value="RESPOSTA" selected>Resposta Formal (Ofício / Devolutiva)</option>
                            <option value="INSTRUCAO_TECNICA">Instrução / Parecer Técnico</option>
                            <option value="COMPLEMENTAR">Documento Complementar / Histórico</option>
                            <option value="APENSO_ANEXO">Apenso / Anexo de Processo</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted text-uppercase">Justificativa / Observações (Opcional)</label>
                        <input type="text" id="inputJustificativa-{{ $docId }}" class="form-control"
                            placeholder="Ex: Parecer emitido em resposta à solicitação..." maxlength="255">
                    </div>
                </div>

                {{-- Campo de Busca Instantânea com Debounce --}}
                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Buscar Documentos no Acervo</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="inputSearchDocs-{{ $docId }}" class="form-control border-start-0 ps-0"
                            placeholder="Digite o número de referência, assunto ou procedência para pesquisar..." autocomplete="off">
                        <button class="btn btn-outline-secondary" type="button" id="btnFilterAll-{{ $docId }}">Todos</button>
                    </div>
                </div>

                {{-- Filtro de Abas/Tipo no Modal --}}
                <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-xs btn-outline-primary active filter-tipo-btn" data-tipo="" data-doc-id="{{ $docId }}">
                        Todos
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-primary filter-tipo-btn" data-tipo="EXTERNO" data-doc-id="{{ $docId }}">
                        <i class="fas fa-inbox me-1"></i> Entradas Externas
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-primary filter-tipo-btn" data-tipo="INTERNO" data-doc-id="{{ $docId }}">
                        <i class="fas fa-file-signature me-1"></i> Documentos Internos
                    </button>
                </div>

                {{-- Resultados da Busca com Checkbox Múltiplo --}}
                <div class="border rounded-3 overflow-hidden" style="min-height: 220px; max-height: 340px; overflow-y: auto;">
                    <div id="searchLoader-{{ $docId }}" class="text-center py-5 d-none">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span class="ms-2 small text-muted">Pesquisando no acervo...</span>
                    </div>

                    <div id="searchResultsList-{{ $docId }}" class="list-group list-group-flush">
                        {{-- Injetado dinamicamente via Javascript --}}
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light border-top d-flex justify-content-between align-items-center py-3">
                <div class="small text-muted">
                    <span id="selectedDocsCount-{{ $docId }}" class="fw-bold text-primary">0</span> documento(s) selecionado(s)
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary px-4 shadow-sm" id="btnConfirmarVinculo-{{ $docId }}" disabled
                        onclick="submeterNovosVinculos('{{ $tipoUpper }}', '{{ $docId }}')">
                        <i class="fas fa-link me-1"></i> Vincular Selecionados
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        initVinculosManager('{{ $tipoUpper }}', '{{ $docId }}');
    });

    if (typeof window.initVinculosManager === 'undefined') {
        window.initVinculosManager = function(tipo, docId) {
            const searchInput = document.getElementById(`inputSearchDocs-${docId}`);
            const resultsContainer = document.getElementById(`searchResultsList-${docId}`);
            const loader = document.getElementById(`searchLoader-${docId}`);
            const confirmBtn = document.getElementById(`btnConfirmarVinculo-${docId}`);
            const counterSpan = document.getElementById(`selectedDocsCount-${docId}`);
            
            let selectedDocs = new Map();
            let currentFiltroTipo = '';
            let debounceTimer = null;

            function updateSelectedCount() {
                const count = selectedDocs.size;
                if (counterSpan) counterSpan.textContent = count;
                if (confirmBtn) confirmBtn.disabled = count === 0;
            }

            function renderResults(resultados) {
                if (!resultsContainer) return;
                resultsContainer.innerHTML = '';

                if (resultados.length === 0) {
                    resultsContainer.innerHTML = `
                        <div class="text-center py-4 text-muted small">
                            <i class="fas fa-search me-1 opacity-50"></i> Nenhum documento encontrado com estes critérios.
                        </div>
                    `;
                    return;
                }

                resultados.forEach(item => {
                    const itemKey = `${item.tipo}_${item.id}`;
                    const isChecked = selectedDocs.has(itemKey);
                    const isDisabled = item.ja_vinculado;

                    const row = document.createElement('label');
                    row.className = `list-group-item list-group-item-action d-flex align-items-start gap-3 p-3 ${isDisabled ? 'bg-light opacity-75' : ''}`;
                    row.style.cursor = isDisabled ? 'not-allowed' : 'pointer';

                    row.innerHTML = `
                        <div class="form-check mt-1">
                            <input class="form-check-input doc-checkbox" type="checkbox" value="${item.id}" data-tipo="${item.tipo}" 
                                ${isChecked ? 'checked' : ''} ${isDisabled ? 'disabled' : ''}>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge ${item.tipo_badge} rounded-pill px-2 py-0" style="font-size: 0.7rem;">
                                    ${item.tipo_label}
                                </span>
                                <span class="badge ${item.status_badge} rounded-pill px-2 py-0" style="font-size: 0.7rem;">
                                    ${item.status_label}
                                </span>
                                ${isDisabled ? '<span class="badge bg-secondary-subtle text-secondary rounded-pill px-2 py-0" style="font-size: 0.7rem;">Já Vinculado</span>' : ''}
                            </div>
                            <div class="fw-bold text-dark text-truncate">
                                <span class="text-primary">${item.numero_identificador}</span> — ${item.titulo}
                            </div>
                            <div class="small text-muted d-flex flex-wrap gap-2 mt-1" style="font-size: 0.75rem;">
                                <span><i class="fas fa-user me-1"></i>${item.autor_ou_procedencia}</span>
                                <span>•</span>
                                <span><i class="fas fa-building me-1"></i>${item.departamento}</span>
                                <span>•</span>
                                <span><i class="far fa-calendar me-1"></i>${item.data}</span>
                            </div>
                        </div>
                    `;

                    const chk = row.querySelector('.doc-checkbox');
                    if (chk && !isDisabled) {
                        chk.addEventListener('change', function(e) {
                            if (this.checked) {
                                selectedDocs.set(itemKey, { id: item.id, tipo: item.tipo });
                            } else {
                                selectedDocs.delete(itemKey);
                            }
                            updateSelectedCount();
                        });
                    }

                    resultsContainer.appendChild(row);
                });
            }

            function fetchDocs(query = '') {
                if (!loader || !resultsContainer) return;
                loader.classList.remove('d-none');
                resultsContainer.classList.add('d-none');

                const params = new URLSearchParams({
                    q: query,
                    filtro_tipo: currentFiltroTipo
                });

                fetch(`/api/documentos/${tipo}/${docId}/pesquisar-vinculos?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(r => r.json())
                .then(data => {
                    loader.classList.add('d-none');
                    resultsContainer.classList.remove('d-none');
                    if (data.success) {
                        renderResults(data.resultados);
                    }
                })
                .catch(err => {
                    console.error('Erro na pesquisa:', err);
                    loader.classList.add('d-none');
                    resultsContainer.classList.remove('d-none');
                    resultsContainer.innerHTML = '<div class="alert alert-danger m-3 small">Erro ao carregar documentos.</div>';
                });
            }

            // Pesquisa ao digitar com debounce de 250ms
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(() => {
                        fetchDocs(this.value.trim());
                    }, 250);
                });
            }

            // Filtros de Tipo (Todos / Externo / Interno)
            document.querySelectorAll(`.filter-tipo-btn[data-doc-id="${docId}"]`).forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll(`.filter-tipo-btn[data-doc-id="${docId}"]`).forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentFiltroTipo = this.getAttribute('data-tipo');
                    fetchDocs(searchInput ? searchInput.value.trim() : '');
                });
            });

            // Carrega lista inicial ao abrir o modal
            const modalEl = document.getElementById(`modalVincularDoc-${docId}`);
            if (modalEl) {
                modalEl.addEventListener('show.bs.modal', function() {
                    selectedDocs.clear();
                    updateSelectedCount();
                    if (searchInput) searchInput.value = '';
                    fetchDocs('');
                });
            }

            window.getSelectedDocsForModal = function(id) {
                if (id === docId) {
                    return Array.from(selectedDocs.values());
                }
                return [];
            };
        };

        window.submeterNovosVinculos = function(tipo, docId) {
            const modalEl = document.getElementById(`modalVincularDoc-${docId}`);
            const modal = bootstrap.Modal.getInstance(modalEl);
            const selectRelacao = document.getElementById(`selectTipoRelacao-${docId}`);
            const inputJustificativa = document.getElementById(`inputJustificativa-${docId}`);
            const confirmBtn = document.getElementById(`btnConfirmarVinculo-${docId}`);

            const docs = window.getSelectedDocsForModal ? window.getSelectedDocsForModal(docId) : [];
            if (!docs || docs.length === 0) {
                if (window.Toast) {
                    window.Toast.warning('Seleção Obrigatória', 'Selecione ao menos um documento para vincular.');
                }
                return;
            }

            if (confirmBtn) {
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Vinculando...';
            }

            fetch(`/api/documentos/${tipo}/${docId}/vincular`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    documentos: docs,
                    tipo_relacao: selectRelacao ? selectRelacao.value : 'COMPLEMENTAR',
                    justificativa: inputJustificativa ? inputJustificativa.value : ''
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (modal) modal.hide();
                    if (window.Toast) {
                        window.Toast.success('Vínculo Criado', data.message || 'Documentos vinculados com sucesso!');
                    }
                    setTimeout(() => window.location.reload(), 400);
                } else {
                    if (window.Toast) {
                        window.Toast.error('Erro ao Vincular', data.message || 'Erro ao vincular documentos.');
                    }
                    if (confirmBtn) {
                        confirmBtn.disabled = false;
                        confirmBtn.innerHTML = '<i class="fas fa-link me-1"></i> Vincular Selecionados';
                    }
                }
            })
            .catch(err => {
                console.error(err);
                if (window.Toast) {
                    window.Toast.error('Erro de Conexão', 'Erro de conexão ao vincular documentos.');
                }
                if (confirmBtn) {
                    confirmBtn.disabled = false;
                    confirmBtn.innerHTML = '<i class="fas fa-link me-1"></i> Vincular Selecionados';
                }
            });
        };

        window.desvincularDocumento = function(vinculoId, docId) {
            if (!confirm('Tem certeza que deseja remover o vínculo entre estes documentos?')) {
                return;
            }

            fetch(`/api/documentos/vinculos/${vinculoId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (window.Toast) {
                        window.Toast.success('Vínculo Removido', 'O vínculo entre os documentos foi removido.');
                    }
                    const card = document.getElementById(`vinculo-card-${vinculoId}`);
                    if (card) {
                        card.style.transition = 'all 0.3s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'translateY(-10px)';
                        setTimeout(() => {
                            card.remove();
                            const remaining = document.querySelectorAll(`#vinculosList-${docId} .vinculo-card`).length;
                            const badge = document.getElementById(`vinculosCountBadge-${docId}`);
                            if (badge) badge.textContent = remaining;
                            if (remaining === 0) {
                                window.location.reload();
                            }
                        }, 300);
                    } else {
                        window.location.reload();
                    }
                } else {
                    if (window.Toast) {
                        window.Toast.error('Erro', data.message || 'Erro ao desvincular documento.');
                    }
                }
            })
            .catch(err => {
                console.error(err);
                if (window.Toast) {
                    window.Toast.error('Erro de Comunicação', 'Erro ao processar desvinculação.');
                }
            });
        };
    }
</script>
