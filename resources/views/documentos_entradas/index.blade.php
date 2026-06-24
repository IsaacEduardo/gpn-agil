@extends('layouts.app')

@section('styles')
<style>
.active-filter-pill {
    font-size: 0.825rem;
    padding: 0.4rem 0.85rem;
    border-radius: 99px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background-color: rgba(59, 130, 246, 0.08);
    color: var(--primary-accent);
    border: 1px solid rgba(59, 130, 246, 0.18);
    transition: all 0.2s;
}
.active-filter-pill:hover {
    background-color: rgba(59, 130, 246, 0.12);
}
.active-filter-pill .btn-clear {
    color: var(--primary-accent);
    text-decoration: none;
    font-weight: 700;
    line-height: 1;
    font-size: 1.05rem;
    border: none;
    background: none;
    padding: 0;
    margin: 0;
}

/* Side Drawer styling */
.preview-drawer-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.3);
    backdrop-filter: blur(2px);
    z-index: 1040;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.preview-drawer-backdrop.show {
    opacity: 1;
    pointer-events: auto;
}

.preview-drawer {
    position: fixed;
    top: 0;
    right: -480px;
    width: 480px;
    height: 100vh;
    background: #ffffff;
    box-shadow: -8px 0 32px rgba(0, 0, 0, 0.1);
    z-index: 1045;
    transition: right 0.28s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
}
@media (max-width: 575.98px) {
    .preview-drawer {
        width: 100vw;
        right: -100vw;
    }
}
.preview-drawer.open {
    right: 0;
}

/* Keyboard selected row */
tr.keyboard-selected {
    outline: 2px solid var(--primary-accent) !important;
    outline-offset: -2px;
    background-color: rgba(59, 130, 246, 0.04) !important;
}
</style>
@endsection

@section('title', request('meus') === 'pendentes_recebimento' ? 'Por Receber' : 'Entradas de Documentos')

@section('breadcrumbs')
    <div class="container py-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-decoration-none text-muted">Início</a>
                </li>
                <li class="breadcrumb-item active text-primary fw-bold" aria-current="page">
                    {{ request('meus') === 'pendentes_recebimento' ? 'Por Receber' : 'Entradas de Documentos' }}</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
    <div class="container pb-5">

        {{-- Header & Actions --}}
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i
                        class="fas fa-inbox text-primary me-2"></i>{{ request('meus') === 'pendentes_recebimento' ? 'Por Receber' : 'Entradas' }}
                </h2>
                <p class="text-muted mb-0 small">Gerencie e acompanhe todos os documentos de entrada.</p>
            </div>
            <div class="d-flex gap-2">
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle shadow-sm"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-download me-1"></i> Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                        <li>
                            <a class="dropdown-item py-2"
                                href="{{ route('documentos-entradas.export.pdf', request()->query()) }}">
                                <i class="fas fa-file-pdf me-2 text-danger"></i>Exportar PDF
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2"
                                href="{{ route('documentos-entradas.export.excel', request()->query()) }}">
                                <i class="fas fa-file-excel me-2 text-success"></i>Exportar Excel
                            </a>
                        </li>
                    </ul>
                </div>
                <a href="{{ route('documentos-entradas.create') }}" class="btn btn-primary shadow-sm fw-medium">
                    <i class="fas fa-plus me-1"></i> Novo Documento
                </a>
            </div>
        </div>

        {{-- Main Card --}}
        <div class="card shadow-sm border-0 rounded-3 overflow-hidden">
            {{-- Toolbar / Quick Filters --}}
            <div
                class="card-header bg-white p-3 border-bottom d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">

                {{-- Tabs-like Filters --}}
                <div class="nav nav-pills p-1 bg-light rounded-pill" role="tablist">
                    <a href="{{ route('documentos-entradas.index') }}"
                        class="nav-link rounded-pill px-3 py-1 small fw-medium {{ !request('meus') ? 'active bg-white text-primary shadow-sm' : 'text-muted' }}">
                        Todos
                    </a>
                    <a href="{{ route('documentos-entradas.index', ['meus' => 'pendentes_recebimento'] + request()->except('page')) }}"
                        class="nav-link rounded-pill px-3 py-1 small fw-medium {{ request('meus') === 'pendentes_recebimento' ? 'active bg-white text-primary shadow-sm' : 'text-muted' }}">
                        Por Receber
                    </a>
                    <a href="{{ route('documentos-entradas.index', ['meus' => 'visto_pendente'] + request()->except('page')) }}"
                        class="nav-link rounded-pill px-3 py-1 small fw-medium {{ request('meus') === 'visto_pendente' ? 'active bg-white text-primary shadow-sm' : 'text-muted' }}">
                        Visto Pendente
                    </a>
                    <a href="{{ route('documentos-entradas.index', ['meus' => 'visto_gabinete_pendente'] + request()->except('page')) }}"
                        class="nav-link rounded-pill px-3 py-1 small fw-medium {{ request('meus') === 'visto_gabinete_pendente' ? 'active bg-white text-primary shadow-sm' : 'text-muted' }}">
                        Visto Gab. Pendente
                    </a>
                </div>

                {{-- Advanced Filter Toggle & Search --}}
                <div class="d-flex gap-2 w-100 w-md-auto">
                    <div class="input-group input-group-sm w-100 w-md-auto" style="min-width: 200px;">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <form action="{{ route('documentos-entradas.index') }}" method="GET" class="flex-grow-1">
                            @if (request('meus'))
                                <input type="hidden" name="meus" value="{{ request('meus') }}">
                            @endif
                            <input type="text" name="search" value="{{ request('search') }}"
                                class="form-control border-start-0 bg-light" placeholder="Pesquisar..."
                                style="border-top-right-radius: 0; border-bottom-right-radius: 0;">
                        </form>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary d-flex align-items-center" type="button"
                        data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false">
                        <i class="fas fa-filter me-1"></i> Filtros
                    </button>
                </div>
            </div>

            {{-- Collapsible Advanced Filters --}}
            <div class="collapse {{ request()->anyFilled(['status', 'departamento_id', 'data_de', 'data_ate', 'ano']) ? 'show' : '' }} bg-light border-bottom"
                id="filterCollapse">
                <div class="p-4">
                    <form method="GET" action="{{ route('documentos-entradas.index') }}" class="row g-3">
                        @if (request('meus'))
                            <input type="hidden" name="meus" value="{{ request('meus') }}">
                        @endif
                        @if (request('search'))
                            <input type="hidden" name="search" value="{{ request('search') }}">
                        @endif

                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-uppercase text-muted">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                @foreach (['registrado', 'encaminhado', 'encaminhado_externo', 'arquivado', 'cancelado'] as $st)
                                    <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>
                                        {{ ucwords(str_replace('_', ' ', $st)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-uppercase text-muted">Departamento</label>
                            <select name="departamento_id" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                @foreach ($departamentos as $dep)
                                    <option value="{{ $dep->id }}"
                                        {{ request('departamento_id') == $dep->id ? 'selected' : '' }}>
                                        {{ $dep->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-uppercase text-muted">Ano</label>
                            <input type="number" name="ano" value="{{ request('ano') }}"
                                class="form-control form-control-sm" placeholder="{{ date('Y') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-uppercase text-muted">Data Inicial</label>
                            <input type="date" name="data_de" value="{{ request('data_de') }}"
                                class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-uppercase text-muted">Data Final</label>
                            <input type="date" name="data_ate" value="{{ request('data_ate') }}"
                                class="form-control form-control-sm">
                        </div>
                        <div class="col-12 text-end">
                            <a href="{{ route('documentos-entradas.index') }}"
                                class="btn btn-link btn-sm text-decoration-none text-muted me-2">Limpar</a>
                            <button type="submit" class="btn btn-primary btn-sm px-4">Aplicar Filtros</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Alerts --}}
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show m-3 rounded-3" role="alert">
                    <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show m-3 rounded-3" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @php($anyFilter = request()->anyFilled(['status', 'departamento_id', 'data_de', 'data_ate', 'ano', 'search']))
            @if ($anyFilter)
                <div class="px-4 pt-3 pb-0 d-flex flex-wrap align-items-center gap-2">
                    <span class="small text-muted fw-bold text-uppercase me-2" style="font-size: 0.7rem; letter-spacing: 0.05em;">Filtros Ativos:</span>
                    @if (request()->filled('search'))
                        <div class="active-filter-pill">
                            <span>Busca: "{{ request('search') }}"</span>
                            <a href="{{ request()->fullUrlWithQuery(['search' => null]) }}" class="btn-clear">&times;</a>
                        </div>
                    @endif
                    @if (request()->filled('status'))
                        <div class="active-filter-pill">
                            <span>Status: {{ ucwords(str_replace('_', ' ', request('status'))) }}</span>
                            <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}" class="btn-clear">&times;</a>
                        </div>
                    @endif
                    @if (request()->filled('departamento_id'))
                        @php($depName = $departamentos->firstWhere('id', request('departamento_id'))->nome ?? 'Departamento')
                        <div class="active-filter-pill">
                            <span>Depto: {{ $depName }}</span>
                            <a href="{{ request()->fullUrlWithQuery(['departamento_id' => null]) }}" class="btn-clear">&times;</a>
                        </div>
                    @endif
                    @if (request()->filled('ano'))
                        <div class="active-filter-pill">
                            <span>Ano: {{ request('ano') }}</span>
                            <a href="{{ request()->fullUrlWithQuery(['ano' => null]) }}" class="btn-clear">&times;</a>
                        </div>
                    @endif
                    @if (request()->filled('data_de'))
                        <div class="active-filter-pill">
                            <span>Início: {{ \Carbon\Carbon::parse(request('data_de'))->format('d/m/Y') }}</span>
                            <a href="{{ request()->fullUrlWithQuery(['data_de' => null]) }}" class="btn-clear">&times;</a>
                        </div>
                    @endif
                    @if (request()->filled('data_ate'))
                        <div class="active-filter-pill">
                            <span>Fim: {{ \Carbon\Carbon::parse(request('data_ate'))->format('d/m/Y') }}</span>
                            <a href="{{ request()->fullUrlWithQuery(['data_ate' => null]) }}" class="btn-clear">&times;</a>
                        </div>
                    @endif
                    <a href="{{ route('documentos-entradas.index') }}" class="btn btn-link btn-sm text-decoration-none small text-danger p-0 ms-2">Limpar Todos</a>
                </div>
            @endif

            {{-- Batch Toolbar --}}
            <div id="batchActionsToolbar"
                class="bg-primary-subtle text-primary px-4 py-2 d-none align-items-center justify-content-between border-bottom border-primary border-opacity-25">
                <div class="small fw-bold">
                    <i class="fas fa-check-double me-2"></i><span id="selectedCount">0</span> itens selecionados
                </div>
                <div class="d-flex gap-2">
                    <form action="{{ route('documentos-entradas.batch.receber') }}" method="POST"
                        id="batchReceiveForm">
                        @csrf
                        <input type="hidden" name="ids" id="batchReceiveIds">
                        <button type="submit" class="btn btn-sm btn-primary shadow-sm"
                            onclick="return confirm('Confirma o recebimento?')">
                            <i class="fas fa-inbox me-1"></i> Receber
                        </button>
                    </form>
                    <button type="button" class="btn btn-sm btn-success shadow-sm" id="btnEncaminharLote">
                        <i class="fas fa-paper-plane me-1"></i> Encaminhar
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary"
                        id="clearSelectionBtn">Cancelar</button>
                </div>
            </div>

            {{-- Table --}}
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="border-collapse: separate; border-spacing: 0;">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 border-bottom" style="width: 40px;">
                                <input type="checkbox" class="form-check-input" id="selectAllCheckbox">
                            </th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">Nº / Ano</th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">Procedência / Assunto
                            </th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">Status / Vistos</th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">Localização</th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted text-end pe-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documentos as $doc)
                            @php($enc = $doc->ultimoEncaminhamento)
                            @php($isPorReceber = $enc && !$enc->recebido_em && $doc->can_receive)
                            <tr class="position-relative {{ $isPorReceber ? 'bg-warning-subtle' : '' }}"
                                data-doc-id="{{ $doc->id }}"
                                data-doc-type="entrada"
                                data-dept-id="{{ $doc->departamento_id }}"
                                data-show-url="{{ route('documentos-entradas.show', $doc) }}"
                                draggable="true"
                                style="cursor: pointer; transition: background-color 0.2s;">

                                <td class="ps-4" onclick="event.stopPropagation()">
                                    @if ($isPorReceber || $doc->can_forward)
                                        <input type="checkbox" class="form-check-input row-checkbox"
                                            value="{{ $doc->id }}"
                                            data-can-receive="{{ $isPorReceber ? 1 : 0 }}"
                                            data-can-forward="{{ $doc->can_forward ? 1 : 0 }}">
                                    @else
                                        <input type="checkbox" class="form-check-input" disabled>
                                    @endif
                                </td>

                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-white border rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm"
                                            style="width: 40px; height: 40px;">
                                            <i class="fas fa-file-alt text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark">
                                                {{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}</div>
                                            <div class="small text-muted">
                                                {{ \Carbon\Carbon::parse($doc->data_entrada)->format('d/m/Y') }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td style="max-width: 300px;">
                                    <div class="fw-medium text-dark text-truncate">
                                        {{ $doc->procedencia ?? 'Sem procedência' }}</div>
                                    <div class="small text-muted text-truncate">{{ $doc->assunto }}</div>
                                    <div class="small text-muted text-truncate mt-1">
                                        <span
                                            class="badge bg-light text-secondary border">{{ $doc->classificacao_especie ?? 'Geral' }}</span>
                                        @if ($doc->classificacao_ref_numero)
                                            <span class="badge bg-light text-secondary border">Ref:
                                                {{ $doc->classificacao_ref_numero }}</span>
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    {{-- Vistos Badges --}}
                                    <div class="d-flex gap-1 mb-1">
                                        @php($depStatus = $doc->visto_departamento_status ?? 'pendente')
                                        @php($depColor = $depStatus === 'aprovado' ? 'success' : ($depStatus === 'rejeitado' ? 'danger' : 'secondary'))
                                        <span
                                            class="badge bg-{{ $depColor }}-subtle text-{{ $depColor }} border border-{{ $depColor }}-subtle rounded-pill"
                                            title="Visto Departamento: {{ ucfirst($depStatus) }}">
                                            <i
                                                class="fas {{ $depStatus === 'aprovado' ? 'fa-check' : 'fa-clock' }} me-1"></i>Dep.
                                        </span>

                                        @php($gabStatus = $doc->visto_gabinete_status ?? 'pendente')
                                        @php($gabColor = $gabStatus === 'aprovado' ? 'success' : ($gabStatus === 'rejeitado' ? 'danger' : 'secondary'))
                                        <span
                                            class="badge bg-{{ $gabColor }}-subtle text-{{ $gabColor }} border border-{{ $gabColor }}-subtle rounded-pill"
                                            title="Visto Gabinete: {{ ucfirst($gabStatus) }}">
                                            <i
                                                class="fas {{ $gabStatus === 'aprovado' ? 'fa-check' : 'fa-clock' }} me-1"></i>Gab.
                                        </span>
                                    </div>
                                    @if ($doc->saida_gabinete_data)
                                        <div class="badge bg-dark-subtle text-dark border border-dark-subtle rounded-pill">
                                            <i class="fas fa-sign-out-alt me-1"></i>Saiu Gab.
                                        </div>
                                    @endif
                                    
                                    @php($sla = $doc->sla_status)
                                    @if ($sla === 'critical')
                                        <div class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill mt-1" title="SLA Crítico: {{ $doc->dias_decorridos }} dias decorridos">
                                            <i class="fas fa-exclamation-triangle me-1"></i>Crítico
                                        </div>
                                    @elseif ($sla === 'warning')
                                        <div class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill mt-1" title="SLA Excedido: {{ $doc->dias_decorridos }} dias decorridos">
                                            <i class="fas fa-exclamation-circle me-1"></i>Atrasado
                                        </div>
                                    @endif
                                </td>

                                <td class="loc-cell">
                                    @if ($enc)
                                        <div class="d-flex flex-column small">
                                            <span
                                                class="fw-medium text-dark">{{ optional($enc->destinoDepartamento)->nome ?? '—' }}</span>
                                            @if ($enc->recebido_em)
                                                <span class="text-success"><i
                                                        class="fas fa-check-circle me-1"></i>Recebido
                                                    {{ \Carbon\Carbon::parse($enc->recebido_em)->format('d/m') }}</span>
                                            @else
                                                <span class="text-warning"><i
                                                        class="fas fa-paper-plane me-1"></i>Encaminhado
                                                    {{ optional($enc->encaminhado_em)->format('d/m') }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>

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
                                                    href="{{ route('documentos-entradas.show', $doc) }}"><i
                                                        class="fas fa-eye me-2 text-primary w-20"></i>Detalhes</a></li>
                                            <li><a class="dropdown-item"
                                                    href="{{ route('documentos-entradas.edit', $doc) }}"><i
                                                        class="fas fa-edit me-2 text-secondary w-20"></i>Editar</a></li>
                                            @if ($doc->arquivo_caminho)
                                                <li><a class="dropdown-item"
                                                        href="{{ route('documentos-entradas.arquivo.download', $doc) }}"
                                                        target="_blank"><i
                                                            class="fas fa-download me-2 text-info w-20"></i>Baixar</a></li>
                                            @endif
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            @if ($doc->can_forward)
                                                <li>
                                                    <button type="button" class="dropdown-item text-primary fw-medium"
                                                        onclick="openEncaminhar({{ $doc->id }}, {{ $doc->departamento_id }})"><i
                                                            class="fas fa-paper-plane me-2 w-20"></i>Encaminhar</button>
                                                </li>
                                            @endif
                                            @if ($doc->can_receive)
                                                <li>
                                                    <form
                                                        action="{{ route('documentos-entradas.encaminhamentos.receber', ['documento' => $doc->id, 'encaminhamento' => $enc->id]) }}"
                                                        method="POST" class="js-ajax-receber">
                                                        @csrf @method('PATCH')
                                                        <button class="dropdown-item text-success fw-medium"
                                                            type="submit"><i
                                                                class="fas fa-inbox me-2 w-20"></i>Receber</button>
                                                    </form>
                                                </li>
                                            @endif
                                            <li>
                                                <form action="{{ route('documentos-entradas.destroy', $doc) }}"
                                                    method="POST" onsubmit="return confirm('Tem certeza?');">
                                                    @csrf @method('DELETE')
                                                    <button class="dropdown-item text-danger" type="submit"><i
                                                            class="fas fa-trash-alt me-2 w-20"></i>Eliminar</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center justify-content-center">
                                        <div class="bg-light rounded-circle p-4 mb-3">
                                            <i class="fas fa-inbox fa-3x text-muted opacity-50"></i>
                                        </div>
                                        <h5 class="fw-bold text-muted">Nenhum documento encontrado</h5>
                                        <p class="text-muted small mb-3">Tente ajustar os filtros ou crie um novo registro.
                                        </p>
                                        @can('create', App\Models\DocumentoEntrada::class)
                                            <a href="{{ route('documentos-entradas.create') }}"
                                                class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus me-1"></i> Novo Documento
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="d-flex justify-content-between align-items-center p-4 border-top bg-light">
                <div class="small text-muted">
                    Exibindo {{ $documentos->firstItem() ?? 0 }} a {{ $documentos->lastItem() ?? 0 }} de
                    {{ $documentos->total() }}
                </div>
                <div>
                    {{ $documentos->onEachSide(1)->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>

    {{-- Encaminhamento (individual + lote) a partir da listagem, via AJAX --}}
    @include('documentos_entradas.partials.encaminhamento-index')

    {{-- Arquivamento por arrastar-e-soltar: arraste uma linha para a barra de destinos --}}
    <x-archive-dropzone document-type="entrada" />

    <script>
        // Fix para Dropdowns dentro de tabelas responsivas
        // Inicializa explicitamente com strategy: 'fixed' para escapar do overflow: hidden/auto da tabela
        document.addEventListener('DOMContentLoaded', function() {
            const dropdowns = document.querySelectorAll('.dropdown-action-btn');
            dropdowns.forEach(btn => {
                // Cria ou recupera a instância e força a configuração
                const dropdown = new bootstrap.Dropdown(btn, {
                    popperConfig: function(defaultBsPopperConfig) {
                        return {
                            ...defaultBsPopperConfig,
                            strategy: 'fixed'
                        };
                    }
                });
            });
        });

        // Batch Selection Logic
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('selectAllCheckbox');
            const rowCheckboxes = document.querySelectorAll('.row-checkbox');
            const toolbar = document.getElementById('batchActionsToolbar');
            const countSpan = document.getElementById('selectedCount');
            const hiddenInput = document.getElementById('batchReceiveIds');
            const clearBtn = document.getElementById('clearSelectionBtn');

            function updateBatchUI() {
                const checked = Array.from(rowCheckboxes).filter(cb => cb.checked);
                countSpan.textContent = checked.length;
                hiddenInput.value = JSON.stringify(checked.map(cb => cb.value));

                if (checked.length > 0) {
                    toolbar.classList.remove('d-none');
                    toolbar.classList.add('d-flex');
                } else {
                    toolbar.classList.add('d-none');
                    toolbar.classList.remove('d-flex');
                }

                selectAll.checked = rowCheckboxes.length > 0 && checked.length === rowCheckboxes.length;
                selectAll.indeterminate = checked.length > 0 && checked.length < rowCheckboxes.length;
            }

            selectAll.addEventListener('change', () => {
                rowCheckboxes.forEach(cb => cb.checked = selectAll.checked);
                updateBatchUI();
            });

            rowCheckboxes.forEach(cb => cb.addEventListener('change', updateBatchUI));

            clearBtn.addEventListener('click', () => {
                rowCheckboxes.forEach(cb => cb.checked = false);
                updateBatchUI();
            });
        });

        // Preview Drawer Functions
        function openPreviewDrawer(docId) {
            const drawer = document.getElementById('previewDrawer');
            const backdrop = document.getElementById('previewDrawerBackdrop');
            
            drawer.classList.add('open');
            backdrop.classList.add('show');
            
            drawer.innerHTML = `
                <div class="h-100 d-flex flex-column align-items-center justify-content-center p-4">
                    <div class="spinner-border text-primary mb-2" role="status"></div>
                    <span class="text-muted small">Carregando detalhes...</span>
                </div>
            `;
            
            fetch(`/documentos-entradas/${docId}/preview-ajax`)
                .then(response => {
                    if (!response.ok) throw new Error('Falha ao obter dados.');
                    return response.text();
                })
                .then(html => {
                    drawer.innerHTML = html;
                })
                .catch(error => {
                    drawer.innerHTML = `
                        <div class="p-4 text-center">
                            <div class="text-danger mb-2"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
                            <span class="text-muted small">${error.message || 'Erro ao carregar detalhes.'}</span>
                            <div class="mt-3">
                                <button class="btn btn-sm btn-outline-secondary" onclick="closePreviewDrawer()">Fechar</button>
                            </div>
                        </div>
                    `;
                });
        }
        
        function closePreviewDrawer() {
            document.getElementById('previewDrawer').classList.remove('open');
            document.getElementById('previewDrawerBackdrop').classList.remove('show');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const tbody = document.querySelector('table tbody');
            if (!tbody) return;
            const rows = Array.from(tbody.querySelectorAll('tr[data-doc-id]'));
            let activeIndex = -1;

            // Row click for preview
            tbody.addEventListener('click', (e) => {
                const tr = e.target.closest('tr[data-doc-id]');
                if (!tr) return;

                // Prevent preview if clicking interactive elements
                if (e.target.closest('input') || 
                    e.target.closest('a') || 
                    e.target.closest('button') || 
                    e.target.closest('.dropdown') ||
                    e.target.closest('.dropdown-menu')) {
                    return;
                }

                e.preventDefault();
                const docId = tr.getAttribute('data-doc-id');
                
                activeIndex = rows.indexOf(tr);
                updateKeyboardSelection();
                
                openPreviewDrawer(docId);
            });

            // Double click to view full page
            tbody.addEventListener('dblclick', (e) => {
                const tr = e.target.closest('tr[data-doc-id]');
                if (!tr) return;
                
                if (e.target.closest('input') || 
                    e.target.closest('a') || 
                    e.target.closest('button') || 
                    e.target.closest('.dropdown')) {
                    return;
                }
                
                const showUrl = tr.getAttribute('data-show-url');
                if (showUrl) {
                    window.location.href = showUrl;
                }
            });

            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (document.activeElement.tagName === 'INPUT' || 
                    document.activeElement.tagName === 'TEXTAREA' || 
                    document.activeElement.tagName === 'SELECT') {
                    return;
                }

                if (rows.length === 0) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIndex = (activeIndex + 1) % rows.length;
                    updateKeyboardSelection();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIndex = (activeIndex - 1 + rows.length) % rows.length;
                    updateKeyboardSelection();
                } else if (e.key === 'Enter' || e.key === ' ') {
                    if (activeIndex >= 0 && activeIndex < rows.length) {
                        e.preventDefault();
                        const tr = rows[activeIndex];
                        const docId = tr.getAttribute('data-doc-id');
                        openPreviewDrawer(docId);
                    }
                } else if (e.key === 'Escape') {
                    closePreviewDrawer();
                }
            });

            function updateKeyboardSelection() {
                rows.forEach((row, idx) => {
                    row.classList.toggle('keyboard-selected', idx === activeIndex);
                });
                if (activeIndex >= 0 && activeIndex < rows.length) {
                    rows[activeIndex].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                }
            }

            // Hook generic modals
            const genericEncModal = document.getElementById('genericEncaminharModal');
            if (genericEncModal) {
                genericEncModal.addEventListener('show.bs.modal', (event) => {
                    const button = event.relatedTarget;
                    if (!button) return;
                    const actionUrl = button.getAttribute('data-action-url');
                    const form = genericEncModal.querySelector('form');
                    form.action = actionUrl;
                    form.reset();
                });
            }

            const genericArqModal = document.getElementById('genericArquivarModal');
            if (genericArqModal) {
                genericArqModal.addEventListener('show.bs.modal', (event) => {
                    const button = event.relatedTarget;
                    if (!button) return;
                    const actionUrl = button.getAttribute('data-action-url');
                    const form = genericArqModal.querySelector('form');
                    form.action = actionUrl;
                    form.reset();
                });
            }
        });
    </script>

    {{-- Side Drawer and Backdrop --}}
    <div class="preview-drawer-backdrop" id="previewDrawerBackdrop" onclick="closePreviewDrawer()"></div>
    <div class="preview-drawer" id="previewDrawer"></div>

    {{-- Generic Modals --}}
    <!-- Modal Encaminhar Generico -->
    <div class="modal fade" id="genericEncaminharModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Encaminhar documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="form-generic-encaminhar" action="" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Destino (Departamento)</label>
                            <select name="destino_departamento_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                @foreach ($departamentos as $dep)
                                    <option value="{{ $dep->id }}">{{ $dep->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Despacho / Observação</label>
                            <textarea name="observacao" class="form-control" rows="3" placeholder="Insira o despacho aqui..."></textarea>
                        </div>
                        <div class="col-12 text-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> Encaminhar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Arquivar Generico -->
    <div class="modal fade" id="genericArquivarModal" tabindex="-1" style="z-index: 1060;">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="form-generic-arquivar" action="" method="POST">
                    @csrf
                    <input type="hidden" name="tipo" value="entrada">
                    <div class="modal-header">
                        <h5 class="modal-title">Arquivar Documento</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Selecione a pasta onde deseja arquivar este documento.</p>
                        <div class="mb-3">
                            <label class="form-label">Pasta de Arquivo</label>
                            <select name="pasta_id" class="form-select" required>
                                <option value="auto" selected>✨ Arquivamento Automático (Organização Cronológica)</option>
                                <option value="">-- Ou selecione uma pasta manualmente --</option>
                                @inject('pastaService', 'App\Services\PastaService')
                                @foreach ($pastaService->getFolderTreeOptions(auth()->user()) as $id => $nome)
                                    <option value="{{ $id }}">{{ $nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <p class="text-muted small">
                            <i class="fas fa-info-circle"></i> O documento será movido para a pasta selecionada e ficará disponível apenas na busca do arquivo.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Arquivar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
