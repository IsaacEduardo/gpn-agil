@extends('layouts.app')

@section('title', request('meus') === 'pendentes_recebimento' ? 'Por Receber' : 'Entradas de Documentos')

@section('breadcrumbs')
    <div class="container py-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ request('meus') === 'pendentes_recebimento' ? 'Por Receber' : 'Entradas de Documentos' }}</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="mb-0 text-primary fw-bold">
                        <i class="fas fa-inbox me-2"></i>{{ request('meus') === 'pendentes_recebimento' ? 'Por Receber' : 'Entradas de Documentos' }}
                    </h5>
                    @if(request('meus'))
                        <span class="badge bg-secondary rounded-pill">{{ request('meus') === 'pendentes_recebimento' ? 'Pendentes' : 'Filtro Ativo' }}</span>
                    @endif
                </div>
                <div class="d-flex gap-2">
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-download me-1"></i> Exportar
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="{{ route('documentos-entradas.export.pdf', request()->query()) }}">
                                    <i class="fas fa-file-pdf me-2 text-danger"></i>PDF
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('documentos-entradas.export.excel', request()->query()) }}">
                                    <i class="fas fa-file-excel me-2 text-success"></i>Excel
                                </a>
                            </li>
                        </ul>
                    </div>
                    <a href="{{ route('documentos-entradas.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Novo Registro
                    </a>
                </div>
            </div>

            <div class="card-body p-4">
                {{-- Quick Filters & Toggle --}}
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
                    <div class="btn-group" role="group">
                        <a href="{{ route('documentos-entradas.index') }}"
                           class="btn btn-outline-secondary {{ !request('meus') ? 'active' : '' }}">Todos</a>
                        <a href="{{ route('documentos-entradas.index', ['meus' => 'pendentes_recebimento'] + request()->except('page')) }}"
                           class="btn btn-outline-secondary {{ request('meus') === 'pendentes_recebimento' ? 'active' : '' }}">Por Receber</a>
                        <a href="{{ route('documentos-entradas.index', ['meus' => 'visto_pendente'] + request()->except('page')) }}"
                           class="btn btn-outline-secondary {{ request('meus') === 'visto_pendente' ? 'active' : '' }}">Visto Pendente</a>
                        <a href="{{ route('documentos-entradas.index', ['meus' => 'visto_gabinete_pendente'] + request()->except('page')) }}"
                           class="btn btn-outline-secondary {{ request('meus') === 'visto_gabinete_pendente' ? 'active' : '' }}">Visto Gab. Pendente</a>
                    </div>
                    
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="toggleHighlightPorReceber">
                        <label class="form-check-label text-muted small user-select-none" for="toggleHighlightPorReceber">Realçar "Por Receber"</label>
                    </div>
                </div>

                {{-- Filters Section --}}
                <div class="bg-light p-4 rounded mb-4 border">
                    <div class="d-flex justify-content-between align-items-center mb-3 cursor-pointer" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
                         <h6 class="mb-0 fw-bold text-muted text-uppercase"><i class="fas fa-filter me-2"></i>Filtros Avançados</h6>
                         <i class="fas fa-chevron-down text-muted transition-icon"></i>
                    </div>
                    
                    <div class="collapse {{ request()->anyFilled(['search', 'status', 'departamento_id', 'data_de', 'data_ate', 'ano', 'sort', 'direction']) ? 'show' : '' }}" id="filterCollapse">
                        <form method="GET" action="{{ route('documentos-entradas.index') }}" class="row g-3" id="filtrosForm">
                            {{-- Preserve 'meus' if set --}}
                            @if(request('meus'))
                                <input type="hidden" name="meus" value="{{ request('meus') }}">
                            @endif

                            <div class="col-md-4">
                                <label class="form-label fw-bold text-muted small text-uppercase">Busca</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" name="search" value="{{ request('search') }}" class="form-control border-start-0 ps-0"
                                        placeholder="Assunto, procedência, espécie..." />
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label fw-bold text-muted small text-uppercase">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach (['registrado', 'encaminhado', 'encaminhado_externo', 'arquivado', 'cancelado'] as $st)
                                        <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>
                                            {{ ucwords(str_replace('_', ' ', $st)) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">Departamento</label>
                                <select name="departamento_id" class="form-select">
                                    <option value="">Todos</option>
                                    @foreach ($departamentos as $dep)
                                        <option value="{{ $dep->id }}"
                                            {{ request('departamento_id') == $dep->id ? 'selected' : '' }}>{{ $dep->nome }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">Ano</label>
                                <input type="number" name="ano" value="{{ request('ano') }}" class="form-control"
                                    min="2000" max="3000" placeholder="Ex: {{ date('Y') }}" />
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">Data Inicial</label>
                                <input type="date" name="data_de" value="{{ request('data_de') }}" class="form-control" />
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">Data Final</label>
                                <input type="date" name="data_ate" value="{{ request('data_ate') }}" class="form-control" />
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-bold text-muted small text-uppercase">Ordenar por</label>
                                <div class="input-group">
                                    <select name="sort" class="form-select">
                                        @foreach (['data_entrada' => 'Data', 'numero_sequencial' => 'Nº', 'ano_referencia' => 'Ano'] as $key => $label)
                                            <option value="{{ $key }}"
                                                {{ request('sort', $key) === $key ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <select name="direction" class="form-select" style="max-width: 90px;">
                                        <option value="asc" {{ request('direction') === 'asc' ? 'selected' : '' }}>Asc</option>
                                        <option value="desc" {{ request('direction', 'desc') === 'desc' ? 'selected' : '' }}>Desc</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="d-flex gap-2 w-100">
                                    <button class="btn btn-primary flex-grow-1" type="submit">
                                        <i class="fas fa-filter me-1"></i> Filtrar
                                    </button>
                                    <a href="{{ route('documentos-entradas.index') }}" class="btn btn-outline-secondary" title="Limpar Filtros">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
                        <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                {{-- Table --}}
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-uppercase small fw-bold text-muted text-nowrap">Nº / Ano</th>
                                <th class="text-uppercase small fw-bold text-muted">Entrada</th>
                                <th class="text-uppercase small fw-bold text-muted">Detalhes</th>
                                <th class="text-uppercase small fw-bold text-muted">Procedência / Assunto</th>
                                <th class="text-uppercase small fw-bold text-muted text-center">Vistos</th>
                                <th class="text-uppercase small fw-bold text-muted">Encaminhamento</th>
                                <th class="text-uppercase small fw-bold text-muted text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($documentos as $doc)
                                @php($enc = $doc->ultimoEncaminhamento)
                                <tr class="{{ ($enc && !$enc->recebido_em && $doc->can_receive) ? 'row-por-receber' : '' }}"
                                    style="cursor: pointer;"
                                    onclick="window.location='{{ route('documentos-entradas.show', $doc) }}'">
                                    
                                    {{-- Nº / Ano --}}
                                    <td class="fw-medium text-nowrap">
                                        @if ($enc && !$enc->recebido_em && $doc->can_receive)
                                            <i class="fas fa-hourglass-half text-warning me-1" title="Por receber" data-bs-toggle="tooltip"></i>
                                        @endif
                                        <span class="badge bg-light text-dark border">{{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}</span>
                                    </td>

                                    {{-- Entrada --}}
                                    <td class="small text-muted text-nowrap">
                                        <i class="far fa-calendar me-1"></i>
                                        {{ \Carbon\Carbon::parse($doc->data_entrada)->format('d/m/Y') }}
                                    </td>

                                    {{-- Detalhes (Espécie / Ref) --}}
                                    <td>
                                        <div class="d-flex flex-column small">
                                            <span class="fw-medium text-truncate" style="max-width: 150px;" title="Espécie: {{ $doc->classificacao_especie }}">
                                                {{ $doc->classificacao_especie ?? '—' }}
                                            </span>
                                            @if($doc->classificacao_ref_numero)
                                                <span class="text-muted text-truncate" style="max-width: 150px;" title="Ref: {{ $doc->classificacao_ref_numero }}">
                                                    Ref: {{ $doc->classificacao_ref_numero }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- Procedência / Assunto --}}
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-medium text-truncate" style="max-width: 200px;" title="{{ $doc->procedencia }}">
                                                {{ $doc->procedencia ?? '—' }}
                                            </span>
                                            <small class="text-muted text-truncate" style="max-width: 250px;" title="{{ $doc->assunto }}">
                                                {{ $doc->assunto }}
                                            </small>
                                        </div>
                                    </td>

                                    {{-- Vistos --}}
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-1">
                                            <span class="badge rounded-pill bg-{{ $doc->visto_departamento_status === 'aprovado' ? 'success' : ($doc->visto_departamento_status === 'rejeitado' ? 'danger' : 'secondary') }}" 
                                                  data-bs-toggle="tooltip" title="Visto Departamento: {{ ucfirst($doc->visto_departamento_status ?? 'Pendente') }}">
                                                Dep.
                                            </span>
                                            <span class="badge rounded-pill bg-{{ ($doc->visto_gabinete_status ?? null) === 'aprovado' ? 'success' : (($doc->visto_gabinete_status ?? null) === 'rejeitado' ? 'danger' : 'secondary') }}"
                                                  data-bs-toggle="tooltip" title="Visto Gabinete: {{ ucfirst($doc->visto_gabinete_status ?? 'Pendente') }}">
                                                Gab.
                                            </span>
                                        </div>
                                        @if($doc->saida_gabinete_data)
                                            <div class="small text-muted mt-1" title="Saída Gabinete">
                                                <i class="fas fa-sign-out-alt me-1"></i>{{ \Carbon\Carbon::parse($doc->saida_gabinete_data)->format('d/m/Y') }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Encaminhamento --}}
                                    <td>
                                        @if ($enc)
                                            @php($origemNome = optional($enc->origemDepartamento)->nome)
                                            @php($destinoNome = optional($enc->destinoDepartamento)->nome)
                                            
                                            <div class="d-flex flex-column small">
                                                <span class="text-truncate" style="max-width: 180px;" title="{{ ($origemNome ? $origemNome . ' → ' : '') . ($destinoNome ?? '—') }}">
                                                    <i class="fas fa-random me-1 text-muted"></i>
                                                    {{ ($origemNome ? $origemNome . ' → ' : '') . ($destinoNome ?? '—') }}
                                                </span>
                                                
                                                <div class="mt-1">
                                                    @if ($enc->recebido_em)
                                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                                            Recebido {{ \Carbon\Carbon::parse($enc->recebido_em)->format('d/m/Y') }}
                                                        </span>
                                                    @else
                                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">
                                                            Encaminhado {{ $enc->encaminhado_em ? \Carbon\Carbon::parse($enc->encaminhado_em)->format('d/m/Y') : '' }}
                                                        </span>
                                                        @if ($doc->can_receive)
                                                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 ms-1">Por Receber</span>
                                                        @endif
                                                    @endif
                                                    
                                                    @if ($doc->is_chefe)
                                                        <span class="badge bg-secondary ms-1">Chefe</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>

                                    {{-- Ações --}}
                                    <td class="text-end" onclick="event.stopPropagation()">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border-0 rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 32px; height: 32px;">
                                                <i class="fas fa-ellipsis-v text-muted"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                                <li><h6 class="dropdown-header text-uppercase small fw-bold">Ações</h6></li>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('documentos-entradas.show', $doc) }}">
                                                        <i class="fas fa-eye me-2 text-primary w-20"></i>Ver Detalhes
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('documentos-entradas.edit', $doc) }}">
                                                        <i class="fas fa-edit me-2 text-secondary w-20"></i>Editar
                                                    </a>
                                                </li>
                                                @if ($doc->arquivo_caminho ?? false)
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('documentos-entradas.arquivo.download', $doc) }}" target="_blank">
                                                        <i class="fas fa-file-download me-2 text-info w-20"></i>Baixar Arquivo
                                                    </a>
                                                </li>
                                                @endif
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item" href="{{ route('documentos-entradas.protocolo', $doc) }}" target="_blank">
                                                        <i class="fas fa-print me-2 text-success w-20"></i>Imprimir Protocolo
                                                    </a>
                                                </li>
                                                @if ($doc->can_receive)
                                                <li>
                                                    <form action="{{ route('documentos-entradas.encaminhamentos.receber', ['documento' => $doc->id, 'encaminhamento' => $enc->id]) }}" method="POST">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button class="dropdown-item text-success" type="submit">
                                                            <i class="fas fa-check-circle me-2 w-20"></i>Receber Documento
                                                        </button>
                                                    </form>
                                                </li>
                                                @endif
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form action="{{ route('documentos-entradas.destroy', $doc) }}" method="POST" onsubmit="return confirm('Tem certeza que deseja remover este registro?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="dropdown-item text-danger" type="submit">
                                                            <i class="fas fa-trash-alt me-2 w-20"></i>Eliminar
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="mb-3 text-muted">
                                            <i class="fas fa-inbox fa-3x opacity-25"></i>
                                        </div>
                                        <h5 class="text-muted fw-normal">Nenhum documento encontrado</h5>
                                        <p class="text-muted small mb-0">Tente ajustar os filtros ou crie um novo registro.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-4 border-top pt-3">
                    <div class="small text-muted">
                        Mostrando {{ $documentos->firstItem() ?? 0 }}–{{ $documentos->lastItem() ?? 0 }} de {{ $documentos->total() }} registros
                    </div>
                    <div>
                        {{ $documentos->onEachSide(1)->links('pagination::bootstrap-5') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .highlight-por-receber-on .row-por-receber { 
            background-color: rgba(var(--bs-warning-rgb), .05) !important; 
        }
        .highlight-por-receber-on .row-por-receber:hover {
            background-color: rgba(var(--bs-warning-rgb), .1) !important;
        }
        .cursor-pointer { cursor: pointer; }
        .transition-icon { transition: transform 0.2s; }
        [aria-expanded="true"] .transition-icon { transform: rotate(180deg); }
        .w-20 { width: 20px; display: inline-block; text-align: center; }
    </style>

    <script>
        document.querySelectorAll('#filtrosForm select').forEach(el => {
            el.addEventListener('change', () => document.getElementById('filtrosForm').submit());
        });
        
        // Highlight logic
        (function () {
            const key = 'highlightPorReceber';
            const container = document.querySelector('.table-responsive'); // Apply class to table container or specific rows
            const toggle = document.getElementById('toggleHighlightPorReceber');
            
            // Function to apply class to table rows instead of full container for better scoped styling
            const applyHighlight = (isStart) => {
                const table = document.querySelector('table');
                if (isStart) {
                    table.classList.add('highlight-por-receber-on');
                } else {
                    table.classList.remove('highlight-por-receber-on');
                }
            };

            const initial = (localStorage.getItem(key) || 'on') === 'on';
            applyHighlight(initial);
            toggle.checked = initial;
            
            toggle.addEventListener('change', () => {
                const isChecked = toggle.checked;
                applyHighlight(isChecked);
                localStorage.setItem(key, isChecked ? 'on' : 'off');
            });
        })();
    </script>
@endsection
