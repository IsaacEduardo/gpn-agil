@extends('layouts.app')

@section('styles')
    <style>
        .clickable-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .clickable-row:hover {
            background-color: #f8f9fa;
        }

        .sortable-link {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sortable-link:hover {
            color: #0d6efd;
        }

        /* Modal Preview Styles */
        #previewModal .modal-body {
            max-height: 70vh;
            overflow-y: auto;
            background-color: #f8f9fa;
        }

        .preview-paper {
            background: white;
            padding: 40px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            min-height: 300px;
        }

        .drop-zone {
            border: 2px dashed #0d6efd;
            background: rgba(13,102,245,0.1);
            padding: 20px;
            text-align: center;
            margin-bottom: 10px;
            border-radius: 8px;
            font-weight: 600;
            color: #0d6efd;
        }
    </style>
@endsection

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>Documentos Internos</h3>
            <div>
                <div class="dropdown d-inline-block me-2">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"
                        aria-expanded="false">
                        <i class="fas fa-download me-2"></i>Exportar
                    </button>
                    <ul class="dropdown-menu shadow-sm border-0">
                        <li>
                            <a class="dropdown-item" href="{{ route('documentos-internos.export.pdf', request()->query()) }}"
                                target="_blank">
                                <i class="fas fa-file-pdf me-2 text-danger"></i>Exportar PDF
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item"
                                href="{{ route('documentos-internos.export.excel', request()->query()) }}">
                                <i class="fas fa-file-excel me-2 text-success"></i>Exportar Excel
                            </a>
                        </li>
                    </ul>
                </div>
                <a href="{{ route('documentos-internos.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Novo Documento
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Filtros Avançados -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <div class="d-flex align-items-center justify-content-between" data-bs-toggle="collapse"
                    data-bs-target="#filterCollapse" aria-expanded="false" style="cursor: pointer;">
                    <h5 class="mb-0 text-secondary"><i class="fas fa-filter me-2"></i>Filtros de Pesquisa</h5>
                    <i class="fas fa-chevron-down text-muted"></i>
                </div>
            </div>
            <div class="collapse {{ request()->anyFilled(['search', 'especie_id', 'status', 'data_inicio', 'data_fim', 'autor_id']) ? 'show' : '' }}"
                id="filterCollapse">
                <div class="card-body">
                    <form action="{{ route('documentos-internos.index') }}" method="GET">
                        <!-- Maintain sort params -->
                        <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
                        <input type="hidden" name="order" value="{{ request('order') }}">

                        <div class="row g-3">
                            <!-- Busca Textual -->
                            <div class="col-md-4">
                                <label class="form-label small text-muted text-uppercase fw-bold">Buscar</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="fas fa-search text-muted"></i></span>
                                    <input type="text" name="search" class="form-control border-start-0 ps-0"
                                        placeholder="Título ou Referência..." value="{{ request('search') }}">
                                </div>
                            </div>

                            <!-- Espécie -->
                            <div class="col-md-3">
                                <label class="form-label small text-muted text-uppercase fw-bold">Espécie</label>
                                <select name="especie_id" class="form-select">
                                    <option value="">Todas</option>
                                    @foreach ($especies as $especie)
                                        <option value="{{ $especie->id }}"
                                            {{ request('especie_id') == $especie->id ? 'selected' : '' }}>
                                            {{ $especie->nome }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Autor -->
                            <div class="col-md-3">
                                <label class="form-label small text-muted text-uppercase fw-bold">Autor</label>
                                <select name="autor_id" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach ($autores as $autor)
                                        <option value="{{ $autor->id }}"
                                            {{ request('autor_id') == $autor->id ? 'selected' : '' }}>
                                            {{ $autor->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Status -->
                            <div class="col-md-2">
                                <label class="form-label small text-muted text-uppercase fw-bold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="rascunho" {{ request('status') == 'rascunho' ? 'selected' : '' }}>
                                        Rascunho</option>
                                    <option value="finalizado" {{ request('status') == 'finalizado' ? 'selected' : '' }}>
                                        Finalizado</option>
                                </select>
                            </div>

                            <!-- Data Início -->
                            <div class="col-md-3">
                                <label class="form-label small text-muted text-uppercase fw-bold">Período</label>
                                <div class="input-group">
                                    <input type="date" name="data_inicio" class="form-control"
                                        value="{{ request('data_inicio') }}" title="Data Início">
                                    <span class="input-group-text text-muted">até</span>
                                    <input type="date" name="data_fim" class="form-control"
                                        value="{{ request('data_fim') }}" title="Data Fim">
                                </div>
                            </div>

                            <!-- Botões -->
                            <div class="col-12 d-flex justify-content-between align-items-center mt-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="favoritos" id="favoritosCheck"
                                        value="1" {{ request()->boolean('favoritos') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="favoritosCheck"><i
                                            class="fas fa-heart text-danger me-1"></i> Ver apenas meus favoritos</label>
                                </div>
                                <div>
                                    <a href="{{ route('documentos-internos.index') }}"
                                        class="btn btn-light text-secondary me-2">
                                        <i class="fas fa-times me-1"></i> Limpar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-1"></i> Filtrar Resultados
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Tabela --}}
        <div class="card shadow-sm border-0 rounded-3 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="docsTable"
                    style="border-collapse: separate; border-spacing: 0;">
                    <thead class="bg-light">
                        <tr>
                            @php
                                $sortLink = function ($col, $label) {
                                    $direction =
                                        request('sort_by') == $col && request('order') == 'asc' ? 'desc' : 'asc';
                                    $icon = 'fa-sort';
                                    if (request('sort_by') == $col) {
                                        $icon = request('order') == 'asc' ? 'fa-sort-up' : 'fa-sort-down';
                                    }
                                    $url = route(
                                        'documentos-internos.index',
                                        array_merge(request()->query(), ['sort_by' => $col, 'order' => $direction]),
                                    );
                                    return "<a href='{$url}' class='sortable-link'>{$label} <i class='fas {$icon} text-muted small'></i></a>";
                                };
                            @endphp

                            <th class="ps-3 border-bottom" style="width: 40px;">
                                <input type="checkbox" id="selectAllDocs" class="form-check-input" title="Selecionar Todos">
                            </th>
                            <th class="ps-3 py-3 border-bottom text-uppercase small fw-bold text-muted">
                                {!! $sortLink('numero_referencia', 'Referência') !!}</th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">
                                {!! $sortLink('titulo', 'Título') !!}</th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">Espécie</th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">Status</th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">Departamento</th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">Autor</th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">
                                {!! $sortLink('created_at', 'Data') !!}</th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted text-end pe-4">Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documentos as $doc)
                            <tr class="clickable-row" data-href="{{ route('documentos-internos.show', $doc) }}" data-preview-url="{{ route('documentos-internos.show', $doc) }}" data-doc-id="{{ $doc->id }}" data-doc-type="interno" tabindex="0" draggable="true">
                                <td class="ps-3 text-center" onclick="event.stopPropagation()">
                                    <input type="checkbox" class="form-check-input archive-select"
                                        data-doc-id="{{ $doc->id }}" data-doc-type="interno"
                                        title="Selecionar para arquivar em lote">
                                </td>
                                <td class="ps-3">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-white border rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0 shadow-sm"
                                            style="width: 34px; height: 34px;">
                                            <i class="fas fa-file-lines text-primary small"></i>
                                        </div>
                                        <div class="fw-bold text-dark text-break" style="max-width: 220px;">{{ $doc->numero_referencia }}</div>
                                    </div>
                                </td>

                                <td style="max-width: 320px;">
                                    <div class="fw-medium text-dark text-truncate">
                                        {{ $doc->titulo }}
                                        @if ($doc->is_favorited)
                                            <i class="fas fa-heart text-danger ms-1 small" title="Favorito"></i>
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    <span class="badge bg-light text-secondary border">{{ $doc->especie->nome }}</span>
                                </td>

                                <td>
                                    @php($sc = $doc->status->color())
                                    <span
                                        class="badge bg-{{ $sc }}-subtle text-{{ $sc }}-emphasis border border-{{ $sc }}-subtle rounded-pill">
                                        {{ $doc->status->label() }}
                                    </span>
                                </td>

                                <td><span class="text-dark small fw-medium">{{ $doc->departamento->nome }}</span></td>

                                <td><span class="text-muted small">{{ $doc->autor->name }}</span></td>

                                <td class="text-muted small">{{ $doc->created_at->format('d/m/Y H:i') }}</td>

                                <td class="text-end pe-4" onclick="event.stopPropagation()">
                                    <div class="dropdown">
                                        <button
                                            class="btn btn-icon btn-sm btn-light rounded-circle shadow-sm dropdown-action-btn"
                                            type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                            style="width: 32px; height: 32px;">
                                            <i class="fas fa-ellipsis-v text-muted"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                                            <li>
                                                <h6 class="dropdown-header text-uppercase small fw-bold">Gerenciar</h6>
                                            </li>
                                            <li><a class="dropdown-item"
                                                    href="{{ route('documentos-internos.show', $doc) }}"><i
                                                        class="fas fa-eye me-2 text-primary w-20"></i>Detalhes</a></li>
                                            <li><button type="button" class="dropdown-item preview-trigger"
                                                    data-url="{{ route('documentos-internos.show', $doc) }}"><i
                                                        class="fas fa-magnifying-glass me-2 text-info w-20"></i>Pré-visualizar</button>
                                            </li>
                                            <li><a class="dropdown-item"
                                                    href="{{ route('documentos-internos.pdf', $doc) }}"><i
                                                        class="fas fa-file-pdf me-2 text-danger w-20"></i>Baixar PDF</a>
                                            </li>
                                            @if ($doc->status->value === 'rascunho')
                                                <li><a class="dropdown-item"
                                                        href="{{ route('documentos-internos.edit', $doc) }}"><i
                                                            class="fas fa-edit me-2 text-secondary w-20"></i>Editar</a>
                                                </li>
                                            @endif
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li><button type="button"
                                                    class="dropdown-item favorite-btn {{ $doc->is_favorited ? 'active' : '' }}"
                                                    onclick="toggleFavorite(event, '{{ $doc->id }}')"><i
                                                        class="{{ $doc->is_favorited ? 'fas' : 'far' }} fa-heart me-2 text-danger w-20"></i>Favoritar</button>
                                            </li>
                                            <li><button type="button" class="dropdown-item"
                                                    onclick="shareDoc(event, '{{ route('documentos-internos.show', $doc) }}')"><i
                                                        class="fas fa-share-alt me-2 text-secondary w-20"></i>Copiar
                                                    link</button></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center justify-content-center">
                                        <div class="bg-light rounded-circle p-4 mb-3">
                                            <i class="fas fa-folder-open fa-3x text-muted opacity-50"></i>
                                        </div>
                                        <h5 class="fw-bold text-muted">Nenhum documento encontrado</h5>
                                        <p class="text-muted small mb-3">Tente ajustar os filtros ou crie um novo
                                            registro.</p>
                                        <a href="{{ route('documentos-internos.create') }}"
                                            class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus me-1"></i> Novo Documento
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginação --}}
            <div
                class="d-flex flex-column flex-md-row justify-content-between align-items-center p-4 border-top bg-light gap-3">
                <div class="small text-muted">
                    Mostrando <span class="fw-bold text-dark">{{ $documentos->firstItem() ?? 0 }}</span> a <span
                        class="fw-bold text-dark">{{ $documentos->lastItem() ?? 0 }}</span> de <span
                        class="fw-bold text-dark">{{ $documentos->total() }}</span> registros
                </div>
                <div>
                    {{ $documentos->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>

    {{-- Arquivamento por arrastar-e-soltar (barra de destinos + multi-seleção) --}}
    <x-archive-dropzone document-type="interno" />

    <!-- Modal Preview -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-search me-2"></i>Visualização Rápida</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 position-relative">
                    <div id="previewLoader" class="position-absolute top-50 start-50 translate-middle text-center">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">Carregando documento...</p>
                    </div>
                    <div id="previewContent" class="preview-paper m-3 d-none">
                        <!-- Content will be injected here -->
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-light">
                    <a href="#" id="btnFullView" class="btn btn-primary w-100">Abrir Documento Completo</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Render Recent Docs
            renderRecentDocs();

            // Inicializa os dropdowns de ações com posicionamento fixo
            // (evita que o menu seja cortado pelo overflow da table-responsive)
            document.querySelectorAll('.dropdown-action-btn').forEach(function(btn) {
                new bootstrap.Dropdown(btn, {
                    popperConfig: function(defaultBsPopperConfig) {
                        return {
                            ...defaultBsPopperConfig,
                            strategy: 'fixed'
                        };
                    }
                });
            });

            // 1. Clickable Rows
            const rows = document.querySelectorAll('.clickable-row');
            rows.forEach(row => {
                row.addEventListener('click', function(e) {
                    // Prevent navigation if text was selected or special keys pressed
                    if (window.getSelection().toString().length > 0) return;
                    if (e.ctrlKey || e.metaKey) return; // Allow opening in new tab naturally

                    // Prevent if clicked on button or link inside row
                    if (e.target.closest('button') || e.target.closest('a') || e.target.closest(
                            '.hover-actions')) return;

                    const href = this.getAttribute('data-href');
                    if (href) {
                        // Add to history
                        addToHistory(href, this.querySelector('td:nth-child(2)').innerText);
                        window.location.href = href;
                    }
                });

                // Keyboard Navigation (Enter key)
                row.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        this.click();
                    }
                });
            });

            // 2. Keyboard Navigation & Shortcuts
            const table = document.getElementById('docsTable');
            if (table) {
                table.addEventListener('keydown', function(e) {
                    const activeRow = document.activeElement.closest('tr');
                    if (!activeRow) return;

                    if (e.key === 'ArrowDown' || e.key === 'j') {
                        e.preventDefault();
                        const nextRow = activeRow.nextElementSibling;
                        if (nextRow && nextRow.classList.contains('clickable-row')) nextRow.focus();
                    } else if (e.key === 'ArrowUp' || e.key === 'k') {
                        e.preventDefault();
                        const prevRow = activeRow.previousElementSibling;
                        if (prevRow && prevRow.classList.contains('clickable-row')) prevRow.focus();
                    }
                });
            }

            // Global Shortcuts
            document.addEventListener('keydown', function(e) {
                // Focus search on '/'
                if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
                    e.preventDefault();
                    document.querySelector('input[name="search"]').focus();
                }
            });

            // 3. Modal Preview
            const previewModalEl = document.getElementById('previewModal');
            // Inicializar o modal via Bootstrap API
            const previewModal = new bootstrap.Modal(previewModalEl);

            const previewContent = document.getElementById('previewContent');
            const previewLoader = document.getElementById('previewLoader');
            const btnFullView = document.getElementById('btnFullView');

            // Trigger on hover with delay (as requested) or click on icon
            let hoverTimeout;
            const previewTriggers = document.querySelectorAll('.preview-trigger');

            previewTriggers.forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    const url = this.getAttribute('data-url');
                    // Small delay to prevent accidental opens
                    hoverTimeout = setTimeout(() => {
                        openPreview(url);
                    }, 500);
                });

                btn.addEventListener('mouseleave', function() {
                    clearTimeout(hoverTimeout);
                });

                // Also open on click immediately
                btn.addEventListener('click', function(e) {
                    e.stopPropagation(); // Garantir que não dispare o clique da linha
                    clearTimeout(hoverTimeout); // Clear hover timer
                    openPreview(this.getAttribute('data-url'));
                });
            });

            function openPreview(url) {
                if (!url) return;

                // Reset Modal State
                previewContent.classList.add('d-none');
                previewLoader.classList.remove('d-none');
                btnFullView.href = url; // Define o link IMEDIATAMENTE

                // Show Modal
                previewModal.show();

                loadPreviewContent(url);
            }

            function loadPreviewContent(url) {
                // Append preview=true to the URL
                const previewUrl = new URL(url);
                previewUrl.searchParams.append('preview', 'true');

                fetch(previewUrl)
                    .then(response => {
                        if (!response.ok) throw new Error('Erro na requisição');
                        return response.json();
                    })
                    .then(data => {
                        if (data.html) {
                            previewContent.innerHTML = data.html;
                        } else {
                            previewContent.innerHTML =
                                '<div class="alert alert-warning text-center">Formato de resposta inválido.</div>';
                        }

                        previewLoader.classList.add('d-none');
                        previewContent.classList.remove('d-none');
                    })
                    .catch(err => {
                        console.error(err);
                        previewLoader.classList.add('d-none');
                        previewContent.innerHTML =
                            '<div class="alert alert-danger text-center">Erro ao carregar pré-visualização. <br> <a href="' +
                            url + '" class="alert-link">Clique aqui para abrir o documento completo.</a></div>';
                        previewContent.classList.remove('d-none');
                    });
            }
        });

        // Global Functions
        window.toggleFavorite = function(event, docId) {
            event.stopPropagation();
            const btn = event.currentTarget;
            const icon = btn.querySelector('i');

            // Optimistic UI update
            const isNowActive = !btn.classList.contains('active');
            btn.classList.toggle('active');
            icon.classList.toggle('fas');
            icon.classList.toggle('far');

            // API Call
            fetch(`/documentos-internos/${docId}/favorite`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    // Verify sync
                    if (data.is_favorited !== isNowActive) {
                        // Revert if mismatch
                        btn.classList.toggle('active');
                        icon.classList.toggle('fas');
                        icon.classList.toggle('far');
                    }
                })
                .catch(error => {
                    console.error('Error toggling favorite:', error);
                    // Revert on error
                    btn.classList.toggle('active');
                    icon.classList.toggle('fas');
                    icon.classList.toggle('far');
                });
        };

        window.shareDoc = function(event, url) {
            event.stopPropagation();
            navigator.clipboard.writeText(url).then(() => {
                // Show toast or tooltip
                const btn = event.currentTarget;
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check text-success"></i>';
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        };

        // LocalStorage History
        function addToHistory(url, title) {
            try {
                let history = JSON.parse(localStorage.getItem('docHistory') || '[]');
                // Remove duplicate if exists
                history = history.filter(item => item.url !== url);
                // Add to top
                history.unshift({
                    url,
                    title,
                    date: new Date().toISOString()
                });
                // Limit to 6 (row of 3 or 6)
                history = history.slice(0, 6);
                localStorage.setItem('docHistory', JSON.stringify(history));
            } catch (e) {
                console.warn('LocalStorage error:', e);
            }
        }

        function renderRecentDocs() {
            try {
                const history = JSON.parse(localStorage.getItem('docHistory') || '[]');
                const container = document.getElementById('recent-docs-container');
                const list = document.getElementById('recent-docs-list');

                if (history.length === 0) {
                    container.classList.add('d-none');
                    return;
                }

                container.classList.remove('d-none');
                list.innerHTML = history.map(doc => `
                    <div class="col-md-2 col-6">
                        <a href="${doc.url}" class="text-decoration-none">
                            <div class="card recent-doc-card h-100 p-3 text-center border-0 bg-light">
                                <i class="fas fa-file-alt fa-2x text-primary mb-2"></i>
                                <div class="small text-dark fw-bold text-truncate" title="${doc.title}">${doc.title}</div>
                                <div class="text-muted" style="font-size: 0.7rem;">${new Date(doc.date).toLocaleDateString()}</div>
                            </div>
                        </a>
                    </div>
                `).join('');
            } catch (e) {
                console.warn('Error rendering history:', e);
            }
        }
    </script>

    {{-- Barra Flutuante de Ações em Lote (UX P1) --}}
    <div id="batchActionBar" class="card shadow-lg border-0 rounded-4 position-fixed bottom-0 start-50 translate-middle-x mb-4 px-4 py-3 bg-dark text-white d-none" style="z-index: 1050; min-width: 480px;">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <i class="fas fa-tasks text-warning me-2"></i>
                <span class="fw-bold" id="selectedDocsCount">0</span> selecionados
            </div>
            <div class="d-flex align-items-center gap-2">
                <button id="batchDownloadZipBtn" type="button" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-file-archive me-1"></i> Descarregar ZIP
                </button>
                <button id="batchApproveBtn" type="button" class="btn btn-sm btn-success">
                    <i class="fas fa-check-circle me-1"></i> Aprovar Lote
                </button>
                <button id="batchSignBtn" type="button" class="btn btn-sm btn-primary">
                    <i class="fas fa-signature me-1"></i> Assinar Lote
                </button>
                <button id="batchCancelBtn" type="button" class="btn btn-sm btn-link text-white-50 text-decoration-none">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <form id="batchActionForm" method="POST" class="d-none">
        @csrf
        <div id="batchInputsContainer"></div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAllDocs');
            const checkboxes = document.querySelectorAll('.archive-select');
            const batchBar = document.getElementById('batchActionBar');
            const selectedCount = document.getElementById('selectedDocsCount');
            const batchForm = document.getElementById('batchActionForm');
            const batchInputsContainer = document.getElementById('batchInputsContainer');

            function updateBatchBar() {
                const checked = document.querySelectorAll('.archive-select:checked');
                if (checked.length > 0) {
                    if (selectedCount) selectedCount.textContent = checked.length;
                    if (batchBar) batchBar.classList.remove('d-none');
                } else {
                    if (batchBar) batchBar.classList.add('d-none');
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(cb => cb.checked = selectAll.checked);
                    updateBatchBar();
                });
            }

            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateBatchBar);
            });

            document.getElementById('batchCancelBtn')?.addEventListener('click', function() {
                checkboxes.forEach(cb => cb.checked = false);
                if (selectAll) selectAll.checked = false;
                updateBatchBar();
            });

            function submitBatchForm(url) {
                const checked = document.querySelectorAll('.archive-select:checked');
                if (checked.length === 0) return;

                batchInputsContainer.innerHTML = '';
                checked.forEach(cb => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'documento_ids[]';
                    input.value = cb.getAttribute('data-doc-id');
                    batchInputsContainer.appendChild(input);
                });

                batchForm.action = url;
                batchForm.submit();
            }

            document.getElementById('batchDownloadZipBtn')?.addEventListener('click', function() {
                submitBatchForm("{{ route('documentos-internos.batch-zip') }}");
            });

            document.getElementById('batchApproveBtn')?.addEventListener('click', function() {
                if (confirm('Deseja aprovar todos os documentos selecionados?')) {
                    submitBatchForm("{{ route('gabinete.batch-approve') }}");
                }
            });

            document.getElementById('batchSignBtn')?.addEventListener('click', function() {
                submitBatchForm("{{ route('gabinete.batch-sign') }}");
            });
        });
    </script>
@endpush
