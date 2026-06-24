@extends('layouts.app')

@section('title', __('Gestão de Tarefas'))

@section('content')
    <div class="container-fluid py-4" x-data="taskManager()">

        {{-- Modal Detalhes da Tarefa --}}
        <div class="modal fade" id="modalDetalheTarefa" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header border-bottom-0 pb-0">
                        <div>
                            <h5 class="modal-title fw-bold text-dark" id="modalDetalheTarefaTitulo"></h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body pt-2">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge rounded-pill" id="modalDetalheTarefaStatus"></span>
                            <span class="badge bg-light text-dark border" id="modalDetalheTarefaPrazo"></span>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Descrição</label>
                            <div class="p-3 bg-light rounded border-start border-4 border-primary" id="modalDetalheTarefaDescricao" style="white-space: pre-line;"></div>
                        </div>

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Documento</label>
                                <a href="#" id="modalDetalheTarefaDocLink" class="text-decoration-none fw-medium"></a>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Solicitado por</label>
                                <div class="fw-medium text-dark" id="modalDetalheTarefaSolicitante"></div>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Destino</label>
                                <div class="fw-medium text-dark" id="modalDetalheTarefaDestino"></div>
                            </div>
                            <div class="col-sm-6">
                                <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Criado em</label>
                                <div class="fw-medium text-dark" id="modalDetalheTarefaCriado"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 pt-0">
                        <a href="#" id="modalDetalheTarefaVerDoc" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-file-alt me-1"></i> Ver Documento
                        </a>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        </div>
        {{-- Header & Toolbar --}}
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h4 class="fw-bold text-primary mb-1">
                    <i class="fas fa-check-double me-2"></i>{{ __('Minhas Tarefas') }}
                </h4>
                <p class="text-muted small mb-0">{{ __('Gerencie suas atividades e prazos com eficiência.') }}</p>
            </div>

            <div class="d-flex gap-2 align-items-center">
                {{-- View Switcher --}}
                <div class="btn-group shadow-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" :class="{ 'active': view === 'list' }"
                        @click="setView('list')" title="{{ __('Lista') }}">
                        <i class="fas fa-list"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" :class="{ 'active': view === 'grid' }"
                        @click="setView('grid')" title="{{ __('Grade') }}">
                        <i class="fas fa-th-large"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" :class="{ 'active': view === 'kanban' }"
                        @click="setView('kanban')" title="{{ __('Kanban') }}">
                        <i class="fas fa-columns"></i>
                    </button>
                </div>

                {{-- New Task Button --}}
                <a href="#" class="btn btn-primary shadow-sm">
                    <i class="fas fa-plus me-1"></i> {{ __('Nova Tarefa') }}
                </a>
            </div>
        </div>

        {{-- Filters Bar --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form action="{{ route('tarefas.index') }}" method="GET" class="row g-3 align-items-end">
                    <input type="hidden" name="view" :value="view">

                    <div class="col-md-4">
                        <label class="form-label small text-muted mb-1">{{ __('Buscar') }}</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i
                                    class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0"
                                placeholder="{{ __('Título, descrição ou documento...') }}" value="{{ request('search') }}">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">{{ __('Status') }}</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">{{ __('Todos') }}</option>
                            <option value="pendente" {{ request('status') === 'pendente' ? 'selected' : '' }}>
                                {{ __('Pendentes') }}</option>
                            <option value="concluido" {{ request('status') === 'concluido' ? 'selected' : '' }}>
                                {{ __('Concluídas') }}</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">{{ __('Ordenar por') }}</label>
                        <select name="sort" class="form-select" onchange="this.form.submit()">
                            <option value="prazo_at" {{ request('sort') === 'prazo_at' ? 'selected' : '' }}>
                                {{ __('Prazo') }}</option>
                            <option value="created_at" {{ request('sort') === 'created_at' ? 'selected' : '' }}>
                                {{ __('Data de Criação') }}</option>
                            <option value="titulo" {{ request('sort') === 'titulo' ? 'selected' : '' }}>
                                {{ __('Título') }}</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-secondary w-100">{{ __('Filtrar') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Views Content --}}

        {{-- LIST VIEW --}}
        <div x-show="view === 'list'" class="fade-in">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Tarefa</th>
                                <th>Documento</th>
                                <th>Prazo</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tarefas as $tarefa)
                                <tr>
                                    <td class="ps-4">
                                        <a href="#" class="text-decoration-none btn-ver-tarefa"
                                            data-titulo="{{ e($tarefa->titulo) }}"
                                            data-descricao="{{ e($tarefa->descricao) }}"
                                            data-status="{{ $tarefa->status }}"
                                            data-prazo="{{ $tarefa->prazo_at ? $tarefa->prazo_at->format('d/m/Y') : '—' }}"
                                            data-prazo-vencido="{{ $tarefa->prazo_at && $tarefa->prazo_at->isPast() && $tarefa->status === 'pendente' ? '1' : '0' }}"
                                            data-doc-numero="{{ $tarefa->documento->numero_sequencial }}/{{ $tarefa->documento->ano_referencia }}"
                                            data-doc-url="{{ route('documentos-entradas.show', $tarefa->documento_entrada_id) }}"
                                            data-solicitante="{{ optional($tarefa->assignedBy)->name ?? '—' }}"
                                            data-destino="{{ $tarefa->assignedToUser ? optional($tarefa->assignedToUser)->name : (optional($tarefa->assignedToDepartamento)->nome ?? '—') }}"
                                            data-destino-tipo="{{ $tarefa->assignedToUser ? 'user' : ($tarefa->assignedToDepartamento ? 'dep' : '') }}"
                                            data-criado="{{ $tarefa->created_at->format('d/m/Y H:i') }}">
                                            <div class="fw-bold text-dark">{{ $tarefa->titulo }}</div>
                                            <div class="small text-muted text-truncate" style="max-width: 300px;">{{ $tarefa->descricao }}</div>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('documentos-entradas.show', $tarefa->documento_entrada_id) }}"
                                            class="badge bg-light text-primary border text-decoration-none">
                                            <i class="fas fa-file-alt me-1"></i>
                                            {{ $tarefa->documento->numero_sequencial }}/{{ $tarefa->documento->ano_referencia }}
                                        </a>
                                    </td>
                                    <td>
                                        @if ($tarefa->prazo_at)
                                            <span
                                                class="{{ $tarefa->prazo_at->isPast() && $tarefa->status === 'pendente' ? 'text-danger fw-bold' : 'text-muted' }}">
                                                {{ $tarefa->prazo_at->format('d/m/Y') }}
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span
                                            class="badge rounded-pill bg-{{ $tarefa->status === 'concluida' ? 'success' : ($tarefa->status === 'cancelada' ? 'danger' : 'warning') }}">
                                            {{ ucfirst($tarefa->status) }}
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        @if ($tarefa->status === 'pendente')
                                            <form
                                                action="{{ route('documentos-entradas.tarefas.concluir', [$tarefa->documento_entrada_id, $tarefa->id]) }}"
                                                method="POST" class="d-inline">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-success rounded-circle"
                                                    title="Concluir" onclick="return confirm('Concluir tarefa?')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('documentos-entradas.show', $tarefa->documento_entrada_id) }}"
                                            class="btn btn-sm btn-light border rounded-circle" title="Ver Documento">
                                            <i class="fas fa-eye text-muted"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="fas fa-tasks fa-2x mb-3 opacity-50"></i>
                                        <p>Nenhuma tarefa encontrada.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($tarefas->hasPages())
                    <div class="card-footer bg-white border-0 py-3">
                        {{ $tarefas->links() }}
                    </div>
                @endif
            </div>
        </div>

        {{-- GRID VIEW --}}
        <div x-show="view === 'grid'" class="fade-in" style="display: none;">
            <div class="row g-3">
                @forelse($tarefas as $tarefa)
                    <div class="col-md-6 col-lg-4 col-xl-3">
                        <div class="card h-100 border-0 shadow-sm hover-lift task-card">
                            <div class="card-body p-3 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span
                                        class="badge bg-{{ $tarefa->status === 'concluida' ? 'success' : 'warning' }} bg-opacity-10 text-{{ $tarefa->status === 'concluida' ? 'success' : 'warning' }}">
                                        {{ ucfirst($tarefa->status) }}
                                    </span>
                                    @if ($tarefa->prazo_at)
                                        <small
                                            class="{{ $tarefa->prazo_at->isPast() && $tarefa->status === 'pendente' ? 'text-danger fw-bold' : 'text-muted' }}">
                                            {{ $tarefa->prazo_at->format('d/m') }}
                                        </small>
                                    @endif
                                </div>

                                <a href="#" class="text-decoration-none btn-ver-tarefa"
                                    data-titulo="{{ e($tarefa->titulo) }}"
                                    data-descricao="{{ e($tarefa->descricao) }}"
                                    data-status="{{ $tarefa->status }}"
                                    data-prazo="{{ $tarefa->prazo_at ? $tarefa->prazo_at->format('d/m/Y') : '—' }}"
                                    data-prazo-vencido="{{ $tarefa->prazo_at && $tarefa->prazo_at->isPast() && $tarefa->status === 'pendente' ? '1' : '0' }}"
                                    data-doc-numero="{{ $tarefa->documento->numero_sequencial }}/{{ $tarefa->documento->ano_referencia }}"
                                    data-doc-url="{{ route('documentos-entradas.show', $tarefa->documento_entrada_id) }}"
                                    data-solicitante="{{ optional($tarefa->assignedBy)->name ?? '—' }}"
                                    data-destino="{{ $tarefa->assignedToUser ? optional($tarefa->assignedToUser)->name : (optional($tarefa->assignedToDepartamento)->nome ?? '—') }}"
                                    data-destino-tipo="{{ $tarefa->assignedToUser ? 'user' : ($tarefa->assignedToDepartamento ? 'dep' : '') }}"
                                    data-criado="{{ $tarefa->created_at->format('d/m/Y H:i') }}">
                                    <h6 class="fw-bold text-dark mb-1">{{ Str::limit($tarefa->titulo, 40) }}</h6>
                                    <p class="text-muted small flex-grow-1 mb-3">{{ Str::limit($tarefa->descricao, 80) }}</p>
                                </a>

                                <div
                                    class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top border-light">
                                    <a href="{{ route('documentos-entradas.show', $tarefa->documento_entrada_id) }}"
                                        class="text-decoration-none small text-primary">
                                        <i class="fas fa-file-alt me-1"></i> Doc
                                    </a>

                                    @if ($tarefa->status === 'pendente')
                                        <form
                                            action="{{ route('documentos-entradas.tarefas.concluir', [$tarefa->documento_entrada_id, $tarefa->id]) }}"
                                            method="POST">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-success py-0 px-2"
                                                style="font-size: 0.8rem;">
                                                Concluir
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-center py-5 text-muted">
                        <p>Nenhuma tarefa encontrada.</p>
                    </div>
                @endforelse
            </div>
            @if ($tarefas->hasPages())
                <div class="mt-4">
                    {{ $tarefas->links() }}
                </div>
            @endif
        </div>

        {{-- KANBAN VIEW --}}
        <div x-show="view === 'kanban'" class="fade-in" style="display: none;">
            <div class="row g-4 kanban-container">
                {{-- Coluna Pendentes --}}
                <div class="col-md-4">
                    <div class="kanban-column bg-light rounded-3 p-3 h-100">
                        <h6 class="d-flex align-items-center justify-content-between mb-3 fw-bold text-dark">
                            <span><i class="fas fa-clock text-warning me-2"></i>A Fazer</span>
                            <span
                                class="badge bg-white text-dark shadow-sm">{{ $tarefas->where('status', 'pendente')->count() }}</span>
                        </h6>
                        <div id="kanban-pendente" class="kanban-items min-h-200" data-status="pendente">
                            @foreach ($tarefas->where('status', 'pendente') as $tarefa)
                                <div class="card border-0 shadow-sm mb-2 cursor-grab task-item"
                                    data-id="{{ $tarefa->id }}" data-doc-id="{{ $tarefa->documento_entrada_id }}">
                                    <div class="card-body p-3">
                                        <a href="#" class="text-decoration-none btn-ver-tarefa"
                                            data-titulo="{{ e($tarefa->titulo) }}"
                                            data-descricao="{{ e($tarefa->descricao) }}"
                                            data-status="{{ $tarefa->status }}"
                                            data-prazo="{{ $tarefa->prazo_at ? $tarefa->prazo_at->format('d/m/Y') : '—' }}"
                                            data-prazo-vencido="{{ $tarefa->prazo_at && $tarefa->prazo_at->isPast() && $tarefa->status === 'pendente' ? '1' : '0' }}"
                                            data-doc-numero="{{ $tarefa->documento->numero_sequencial }}/{{ $tarefa->documento->ano_referencia }}"
                                            data-doc-url="{{ route('documentos-entradas.show', $tarefa->documento_entrada_id) }}"
                                            data-solicitante="{{ optional($tarefa->assignedBy)->name ?? '—' }}"
                                            data-destino="{{ $tarefa->assignedToUser ? optional($tarefa->assignedToUser)->name : (optional($tarefa->assignedToDepartamento)->nome ?? '—') }}"
                                            data-destino-tipo="{{ $tarefa->assignedToUser ? 'user' : ($tarefa->assignedToDepartamento ? 'dep' : '') }}"
                                            data-criado="{{ $tarefa->created_at->format('d/m/Y H:i') }}">
                                            <h6 class="card-title text-dark fw-bold mb-1" style="font-size: 0.95rem;">
                                                {{ $tarefa->titulo }}</h6>
                                            <p class="card-text text-muted small mb-2">
                                                {{ Str::limit($tarefa->descricao, 60) }}</p>
                                        </a>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted"><i class="far fa-calendar me-1"></i>
                                                {{ $tarefa->prazo_at ? $tarefa->prazo_at->format('d/m') : '-' }}</small>
                                            <div class="avatar-xs bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                                                style="width:24px;height:24px;font-size:10px;">
                                                {{ substr($tarefa->assignedBy->name ?? 'S', 0, 1) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Coluna Concluídas --}}
                <div class="col-md-4">
                    <div class="kanban-column bg-light rounded-3 p-3 h-100">
                        <h6 class="d-flex align-items-center justify-content-between mb-3 fw-bold text-dark">
                            <span><i class="fas fa-check-circle text-success me-2"></i>Concluídas</span>
                            <span
                                class="badge bg-white text-dark shadow-sm">{{ $tarefas->where('status', 'concluida')->count() }}</span>
                        </h6>
                        <div id="kanban-concluido" class="kanban-items min-h-200" data-status="concluida">
                            @foreach ($tarefas->where('status', 'concluida') as $tarefa)
                                <div class="card border-0 shadow-sm mb-2 task-item opacity-75"
                                    data-id="{{ $tarefa->id }}">
                                    <div class="card-body p-3">
                                        <a href="#" class="text-decoration-none btn-ver-tarefa"
                                            data-titulo="{{ e($tarefa->titulo) }}"
                                            data-descricao="{{ e($tarefa->descricao) }}"
                                            data-status="{{ $tarefa->status }}"
                                            data-prazo="{{ $tarefa->prazo_at ? $tarefa->prazo_at->format('d/m/Y') : '—' }}"
                                            data-prazo-vencido="0"
                                            data-doc-numero="{{ $tarefa->documento->numero_sequencial }}/{{ $tarefa->documento->ano_referencia }}"
                                            data-doc-url="{{ route('documentos-entradas.show', $tarefa->documento_entrada_id) }}"
                                            data-solicitante="{{ optional($tarefa->assignedBy)->name ?? '—' }}"
                                            data-destino="{{ $tarefa->assignedToUser ? optional($tarefa->assignedToUser)->name : (optional($tarefa->assignedToDepartamento)->nome ?? '—') }}"
                                            data-destino-tipo="{{ $tarefa->assignedToUser ? 'user' : ($tarefa->assignedToDepartamento ? 'dep' : '') }}"
                                            data-criado="{{ $tarefa->created_at->format('d/m/Y H:i') }}">
                                            <h6 class="card-title text-decoration-line-through text-muted mb-1"
                                                style="font-size: 0.95rem;">{{ $tarefa->titulo }}</h6>
                                            <small class="text-success"><i class="fas fa-check me-1"></i> Finalizado</small>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Coluna Arquivo (Apenas informativo ou arrastar para remover da vista) --}}
                <div class="col-md-4">
                    <div class="kanban-column bg-light rounded-3 p-3 h-100 border border-dashed">
                        <h6 class="text-center text-muted py-2">
                            Mais tarefas nas outras páginas...
                        </h6>
                        <div class="text-center">
                            {{ $tarefas->links('pagination::simple-bootstrap-5') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @section('styles')
        <style>
            .fade-in {
                animation: fadeIn 0.3s ease-in;
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .hover-lift {
                transition: transform 0.2s, box-shadow 0.2s;
            }

            .hover-lift:hover {
                transform: translateY(-5px);
                box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15) !important;
            }

            .cursor-grab {
                cursor: grab;
            }

            .cursor-grab:active {
                cursor: grabbing;
            }

            .min-h-200 {
                min-height: 200px;
            }

            .kanban-column {
                background-color: #f8f9fa;
            }

            /* Sortable Ghost Class */
            .sortable-ghost {
                opacity: 0.4;
                background-color: #e2e8f0;
                border: 2px dashed #cbd5e1;
            }

            /* Task detail link cursor */
            .btn-ver-tarefa {
                cursor: pointer;
            }
            .btn-ver-tarefa:hover .fw-bold {
                color: var(--bs-primary) !important;
            }
        </style>
        <script src="//unpkg.com/alpinejs" defer></script>
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    @endsection

    @section('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('taskManager', () => ({
                    view: localStorage.getItem('task_view') || 'list',

                    setView(newView) {
                        this.view = newView;
                        localStorage.setItem('task_view', newView);

                        // Re-initialize Kanban if needed when switching
                        if (newView === 'kanban') {
                            setTimeout(() => this.initKanban(), 100);
                        }
                    },

                    init() {
                        if (this.view === 'kanban') this.initKanban();
                        this.initModalDetalheTarefa();
                    },

                    initModalDetalheTarefa() {
                        document.addEventListener('click', function(e) {
                            const link = e.target.closest('.btn-ver-tarefa');
                            if (!link) return;
                            e.preventDefault();

                            const d = link.dataset;
                            document.getElementById('modalDetalheTarefaTitulo').textContent = d.titulo;

                            // Description
                            const descEl = document.getElementById('modalDetalheTarefaDescricao');
                            descEl.textContent = d.descricao || 'Sem descrição detalhada.';
                            if (!d.descricao) descEl.classList.add('text-muted', 'fst-italic');
                            else descEl.classList.remove('text-muted', 'fst-italic');

                            // Status badge
                            const statusEl = document.getElementById('modalDetalheTarefaStatus');
                            const statusMap = {
                                pendente: { bg: 'bg-warning', label: 'Pendente' },
                                concluida: { bg: 'bg-success', label: 'Concluída' },
                                cancelada: { bg: 'bg-danger', label: 'Cancelada' },
                            };
                            const st = statusMap[d.status] || { bg: 'bg-secondary', label: d.status };
                            statusEl.className = 'badge rounded-pill ' + st.bg;
                            statusEl.textContent = st.label;

                            // Prazo
                            const prazoEl = document.getElementById('modalDetalheTarefaPrazo');
                            prazoEl.innerHTML = '<i class="far fa-calendar-alt me-1"></i> Prazo: ' + d.prazo;
                            if (d.prazoVencido === '1') {
                                prazoEl.className = 'badge bg-danger-subtle text-danger border border-danger-subtle';
                            } else {
                                prazoEl.className = 'badge bg-light text-dark border';
                            }

                            // Document link
                            const docLinkEl = document.getElementById('modalDetalheTarefaDocLink');
                            docLinkEl.href = d.docUrl;
                            docLinkEl.innerHTML = '<i class="fas fa-file-alt me-1"></i> ' + d.docNumero;

                            // Solicitante
                            document.getElementById('modalDetalheTarefaSolicitante').textContent = d.solicitante;

                            // Destino
                            const destinoEl = document.getElementById('modalDetalheTarefaDestino');
                            const iconClass = d.destinoTipo === 'user' ? 'fa-user' : (d.destinoTipo === 'dep' ? 'fa-building' : 'fa-minus');
                            destinoEl.innerHTML = '<i class="fas ' + iconClass + ' text-secondary me-1"></i> ' + d.destino;

                            // Criado em
                            document.getElementById('modalDetalheTarefaCriado').textContent = d.criado;

                            // Ver Documento button
                            document.getElementById('modalDetalheTarefaVerDoc').href = d.docUrl;

                            // Open modal
                            const modal = new bootstrap.Modal(document.getElementById('modalDetalheTarefa'));
                            modal.show();
                        });
                    },

                    initKanban() {
                        const pendenteCol = document.getElementById('kanban-pendente');
                        const concluidoCol = document.getElementById('kanban-concluido');

                        if (pendenteCol && concluidoCol) {
                            // Pendente List
                            new Sortable(pendenteCol, {
                                group: 'kanban',
                                animation: 150,
                                ghostClass: 'sortable-ghost',
                                onEnd: (evt) => this.handleDrop(evt)
                            });

                            // Concluido List
                            new Sortable(concluidoCol, {
                                group: 'kanban',
                                animation: 150,
                                ghostClass: 'sortable-ghost',
                                onEnd: (evt) => this.handleDrop(evt)
                            });
                        }
                    },

                    handleDrop(evt) {
                        const item = evt.item;
                        const toStatus = evt.to.getAttribute('data-status');
                        const taskId = item.getAttribute('data-id');
                        const docId = item.getAttribute('data-doc-id');

                        if (toStatus === 'concluida' && evt.from !== evt.to) {
                            if (confirm('Deseja realmente concluir esta tarefa?')) {
                                // Submit form via fetch
                                fetch(`/documentos-entradas/${docId}/tarefas/${taskId}/concluir`, {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': document.querySelector(
                                            'meta[name="csrf-token"]').content,
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-HTTP-Method-Override': 'PATCH'
                                    }
                                }).then(async res => {
                                    if (res.ok) {
                                        // Add visual feedback
                                        item.classList.add('opacity-75');
                                        item.querySelector('.card-title').classList.add(
                                            'text-decoration-line-through', 'text-muted');
                                    } else {
                                        const data = await res.json().catch(() => ({}));
                                        alert(data.message || 'Erro ao atualizar tarefa.');
                                        evt.from.appendChild(item); // Revert
                                    }
                                }).catch(() => {
                                    alert('Erro de conexão.');
                                    evt.from.appendChild(item);
                                });
                            } else {
                                evt.from.appendChild(item); // Revert
                            }
                        }
                    }
                }));
            });
        </script>
    @endsection
@endsection
