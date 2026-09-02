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
            color: var(--primary-accent, #C8102E);
        }

        /* Active Filter Pills */
        .active-filter-pill {
            font-size: 0.825rem;
            padding: 0.4rem 0.85rem;
            border-radius: 99px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background-color: rgba(200, 16, 46, 0.08);
            color: var(--primary-accent, #C8102E);
            border: 1px solid rgba(200, 16, 46, 0.18);
            transition: all 0.2s;
        }
        .active-filter-pill:hover {
            background-color: rgba(200, 16, 46, 0.14);
        }
        .active-filter-pill .btn-clear {
            color: var(--primary-accent, #C8102E);
            text-decoration: none;
            font-weight: 700;
            line-height: 1;
            font-size: 1.05rem;
            border: none;
            background: none;
            padding: 0;
            margin: 0;
        }

        /* Corporate Underline Navigation Tabs System */
        .corporate-underline-nav {
            display: flex;
            gap: 0.25rem;
            overflow-x: auto;
            white-space: nowrap;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .corporate-underline-nav::-webkit-scrollbar {
            display: none;
        }

        .corporate-underline-tab {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #6b7280;
            text-decoration: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            transition: color 0.2s ease, border-color 0.2s ease;
            white-space: nowrap;
        }

        .corporate-underline-tab:hover:not(.active) {
            color: #111827;
            border-bottom-color: #d1d5db;
        }

        .corporate-underline-tab.active {
            color: #111827;
            font-weight: 600;
            border-bottom-color: var(--primary-accent, #C8102E);
        }

        .corporate-underline-tab .tab-badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.15rem 0.55rem;
            border-radius: 9999px;
            line-height: 1.2;
            transition: all 0.2s ease;
        }

        .tab-badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }

        .tab-badge-info {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .tab-badge-neutral {
            background-color: #f3f4f6;
            color: #4b5563;
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
            border: 2px dashed var(--primary-accent, #C8102E);
            background: rgba(200, 16, 46, 0.08);
            padding: 20px;
            text-align: center;
            margin-bottom: 10px;
            border-radius: 8px;
            font-weight: 600;
            color: var(--primary-accent, #C8102E);
        }
    </style>
@endsection

@section('title', 'Documentos Internos')

@section('breadcrumbs')
    <div class="container py-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-decoration-none text-muted">Início</a></li>
                <li class="breadcrumb-item active text-primary fw-bold" aria-current="page">Documentos Internos</li>
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
                    <i class="fas fa-file-signature text-primary me-2"></i>Documentos Internos
                </h2>
                <p class="text-muted mb-0 small">Elaboração, análise, homologação e consulta do acervo documental interno.</p>
            </div>
            <div class="d-flex gap-2">
                <div class="dropdown d-inline-block">
                    <button class="btn btn-outline-secondary dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown"
                        aria-expanded="false">
                        <i class="fas fa-download me-1"></i> Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                        <li>
                            <a class="dropdown-item py-2" href="{{ route('documentos-internos.export.pdf', request()->query()) }}"
                                target="_blank">
                                <i class="fas fa-file-pdf me-2 text-danger"></i>Exportar PDF
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2" href="{{ route('documentos-internos.export.excel', request()->query()) }}">
                                <i class="fas fa-file-excel me-2 text-success"></i>Exportar Excel
                            </a>
                        </li>
                    </ul>
                </div>
                @php
                    $u = Auth::user();
                    $canTemplates = $u && ($u->isAdmin() || $u->hasRole('admin') || $u->hasRole('Admin') || (method_exists($u, 'isChefeGabinete') && $u->isChefeGabinete()) || (method_exists($u, 'isSuperChefeGabinete') && $u->isSuperChefeGabinete()));
                @endphp
                @if ($canTemplates)
                    <a href="{{ route('modelos.index') }}" class="btn btn-outline-primary shadow-sm">
                        <i class="fas fa-file-contract me-1"></i> Modelos
                    </a>
                @endif
                <a href="{{ route('documentos-internos.create') }}" class="btn btn-primary shadow-sm fw-medium">
                    <i class="fas fa-plus me-1"></i> Novo Documento
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Main Card --}}
        <div class="card shadow-sm border-0 rounded-3 overflow-hidden">
            {{-- Corporate Underline Tabs Header --}}
            <div class="card-header bg-white px-3 pt-2 pb-0 border-bottom">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">

                    {{-- Left Aligned Dynamic Workflow Navigation Tabs --}}
                    <div class="corporate-underline-nav flex-grow-1">
                        @if (isset($workflowTabs) && count($workflowTabs))
                            @foreach ($workflowTabs as $tab)
                                <a href="{{ route('documentos-internos.index', ['tab' => $tab['key']] + request()->except('tab', 'page')) }}"
                                    class="corporate-underline-tab {{ $tab['is_active'] ? 'active' : '' }}">
                                    @if (!empty($tab['icon']))
                                        <i class="{{ $tab['icon'] }} me-1 opacity-75"></i>
                                    @endif
                                    <span>{{ $tab['label'] }}</span>
                                    <span class="tab-badge {{ $tab['badge_class'] }}">
                                        {{ number_format($tab['count'], 0, ',', '.') }}
                                    </span>
                                </a>
                            @endforeach
                        @endif
                    </div>

                    {{-- Right Aligned Expandable Search & Filter Toggle --}}
                    <div class="d-flex gap-2 align-items-center mb-2 mb-md-0 ms-auto">
                        <div class="expandable-search-wrapper position-relative d-flex align-items-center">
                            @php
                                $hasSearch = !empty(request('search'));
                            @endphp

                            {{-- Search Trigger Button (Collapsed) --}}
                            <button type="button" 
                                id="btn-trigger-search" 
                                class="btn btn-sm text-secondary bg-transparent border-0 rounded-3 d-flex align-items-center justify-content-center p-0 {{ $hasSearch ? 'd-none' : '' }}"
                                style="width: 36px; height: 36px; transition: background-color 0.2s;"
                                title="Pesquisar">
                                <i class="fas fa-search fs-6"></i>
                            </button>

                            {{-- Expanded Search Form Input --}}
                            <form action="{{ route('documentos-internos.index') }}" method="GET" 
                                id="form-expandable-search" 
                                class="expandable-search-form d-flex align-items-center {{ $hasSearch ? 'expanded' : '' }}">
                                @if (request('tab'))
                                    <input type="hidden" name="tab" value="{{ request('tab') }}">
                                @endif
                                @if (request('especie_id'))
                                    <input type="hidden" name="especie_id" value="{{ request('especie_id') }}">
                                @endif
                                @if (request('departamento_id'))
                                    <input type="hidden" name="departamento_id" value="{{ request('departamento_id') }}">
                                @endif
                                @if (request('autor_id'))
                                    <input type="hidden" name="autor_id" value="{{ request('autor_id') }}">
                                @endif

                                <div class="position-relative d-flex align-items-center">
                                    <i class="fas fa-search position-absolute text-muted small" style="left: 10px; pointer-events: none; z-index: 5;"></i>
                                    <input type="text" 
                                        name="search" 
                                        id="input-expandable-search"
                                        value="{{ request('search') }}"
                                        placeholder="Pesquisar no acervo..." 
                                        class="form-control form-control-sm rounded-3 shadow-none text-dark bg-white" 
                                        style="padding-left: 30px; padding-right: 28px; height: 36px; width: {{ $hasSearch ? '240px' : '0px' }}; opacity: {{ $hasSearch ? '1' : '0' }}; transition: width 0.25s ease-in-out, opacity 0.2s ease-in-out; border: 1px solid #d1d5db;"
                                        autocomplete="off">
                                    <button type="button" 
                                        id="btn-close-search" 
                                        class="btn btn-sm p-0 position-absolute text-muted border-0 bg-transparent" 
                                        style="right: 8px; font-size: 11px; display: {{ $hasSearch ? 'block' : 'none' }}; z-index: 5;"
                                        title="Fechar e limpar">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </form>
                        </div>

                        {{-- Advanced Filter Button --}}
                        <button class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1 shadow-none rounded-3" 
                            type="button" 
                            style="height: 36px;"
                            data-bs-toggle="collapse" 
                            data-bs-target="#filterCollapse" 
                            aria-expanded="{{ request()->anyFilled(['especie_id', 'status', 'departamento_id', 'autor_id', 'data_inicio', 'data_fim', 'favoritos']) ? 'true' : 'false' }}">
                            <i class="fas fa-filter text-muted small"></i>
                            <span class="small fw-medium">Filtros</span>
                        </button>
                    </div>

                </div>
            </div>

            {{-- Collapsible Advanced Filters Drawer/Card --}}
            <div class="collapse {{ request()->anyFilled(['especie_id', 'status', 'departamento_id', 'autor_id', 'data_inicio', 'data_fim', 'favoritos']) ? 'show' : '' }} bg-light border-bottom"
                id="filterCollapse">
                <div class="p-4">
                    <form method="GET" action="{{ route('documentos-internos.index') }}" class="row g-3">
                        @if (request('tab'))
                            <input type="hidden" name="tab" value="{{ request('tab') }}">
                        @endif
                        @if (request('search'))
                            <input type="hidden" name="search" value="{{ request('search') }}">
                        @endif
                        <input type="hidden" name="sort_by" value="{{ request('sort_by') }}">
                        <input type="hidden" name="order" value="{{ request('order') }}">

                        <!-- Espécie -->
                        <div class="col-md-3">
                            <label class="form-label small text-muted text-uppercase fw-bold">Espécie Documental</label>
                            <select name="especie_id" class="form-select form-select-sm">
                                <option value="">Todas as Espécies</option>
                                @foreach ($especies as $especie)
                                    <option value="{{ $especie->id }}"
                                        {{ request('especie_id') == $especie->id ? 'selected' : '' }}>
                                        {{ $especie->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Departamento (Exclusivo para Chefe de Gabinete / Admin) -->
                        @if ($isChefeGabinete && isset($departamentos) && $departamentos->isNotEmpty())
                            <div class="col-md-3">
                                <label class="form-label small text-muted text-uppercase fw-bold">Departamento / Setor</label>
                                <select name="departamento_id" class="form-select form-select-sm">
                                    <option value="">Todos os Departamentos</option>
                                    @foreach ($departamentos as $dep)
                                        <option value="{{ $dep->id }}"
                                            {{ request('departamento_id') == $dep->id ? 'selected' : '' }}>
                                            {{ $dep->nome }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <!-- Autor -->
                        <div class="col-md-3">
                            <label class="form-label small text-muted text-uppercase fw-bold">Autor / Redator</label>
                            <select name="autor_id" class="form-select form-select-sm">
                                <option value="">Todos os Autores</option>
                                @foreach ($autores as $autor)
                                    <option value="{{ $autor->id }}"
                                        {{ request('autor_id') == $autor->id ? 'selected' : '' }}>
                                        {{ $autor->name }} {{ $autor->id === Auth::id() ? '(Você)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Status Específico (Override opcional) -->
                        <div class="col-md-3">
                            <label class="form-label small text-muted text-uppercase fw-bold">Status Específico</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">Automático por Aba</option>
                                <option value="rascunho" {{ request('status') == 'rascunho' ? 'selected' : '' }}>Rascunho</option>
                                <option value="em_analise" {{ request('status') == 'em_analise' ? 'selected' : '' }}>Em Análise / Revisão</option>
                                <option value="aprovado" {{ request('status') == 'aprovado' ? 'selected' : '' }}>Aprovado</option>
                                <option value="assinado" {{ request('status') == 'assinado' ? 'selected' : '' }}>Assinado</option>
                                <option value="arquivado" {{ request('status') == 'arquivado' ? 'selected' : '' }}>Arquivado</option>
                            </select>
                        </div>

                        <!-- Período -->
                        <div class="col-md-4">
                            <label class="form-label small text-muted text-uppercase fw-bold">Período de Criação</label>
                            <div class="input-group input-group-sm">
                                <input type="date" name="data_inicio" class="form-control"
                                    value="{{ request('data_inicio') }}" title="Data Início">
                                <span class="input-group-text text-muted">até</span>
                                <input type="date" name="data_fim" class="form-control"
                                    value="{{ request('data_fim') }}" title="Data Fim">
                            </div>
                        </div>

                        <!-- Botões e Switch de Favoritos -->
                        <div class="col-12 d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="favoritos" id="favoritosCheck"
                                    value="1" {{ request()->boolean('favoritos') ? 'checked' : '' }}>
                                <label class="form-check-label small fw-medium" for="favoritosCheck">
                                    <i class="fas fa-heart text-danger me-1"></i> Apenas meus favoritos
                                </label>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('documentos-internos.index', array_filter(['tab' => request('tab')])) }}"
                                    class="btn btn-sm btn-light border text-secondary px-3">
                                    <i class="fas fa-times me-1"></i> Limpar Filtros
                                </a>
                                <button type="submit" class="btn btn-sm btn-primary px-3 shadow-sm">
                                    <i class="fas fa-filter me-1"></i> Aplicar Filtros
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Active Filter Pills --}}
            @php
                $activeFiltersCount = 0;
                $activeFilterList = [];
                if (request('search')) {
                    $activeFiltersCount++;
                    $activeFilterList[] = ['label' => 'Busca: "' . request('search') . '"', 'param' => 'search'];
                }
                if (request('especie_id')) {
                    $esp = $especies->firstWhere('id', request('especie_id'));
                    if ($esp) {
                        $activeFiltersCount++;
                        $activeFilterList[] = ['label' => 'Espécie: ' . $esp->nome, 'param' => 'especie_id'];
                    }
                }
                if (request('departamento_id') && isset($departamentos)) {
                    $dep = $departamentos->firstWhere('id', request('departamento_id'));
                    if ($dep) {
                        $activeFiltersCount++;
                        $activeFilterList[] = ['label' => 'Setor: ' . $dep->nome, 'param' => 'departamento_id'];
                    }
                }
                if (request('autor_id')) {
                    $aut = $autores->firstWhere('id', request('autor_id'));
                    if ($aut) {
                        $activeFiltersCount++;
                        $activeFilterList[] = ['label' => 'Autor: ' . $aut->name, 'param' => 'autor_id'];
                    }
                }
                if (request('status')) {
                    $activeFiltersCount++;
                    $activeFilterList[] = ['label' => 'Status: ' . ucfirst(str_replace('_', ' ', request('status'))), 'param' => 'status'];
                }
                if (request('data_inicio') || request('data_fim')) {
                    $activeFiltersCount++;
                    $label = 'Período: ' . (request('data_inicio') ? date('d/m/Y', strtotime(request('data_inicio'))) : 'início') . ' até ' . (request('data_fim') ? date('d/m/Y', strtotime(request('data_fim'))) : 'hoje');
                    $activeFilterList[] = ['label' => $label, 'param' => 'data_inicio,data_fim'];
                }
                if (request()->boolean('favoritos')) {
                    $activeFiltersCount++;
                    $activeFilterList[] = ['label' => 'Favoritos', 'param' => 'favoritos'];
                }
            @endphp

            @if ($activeFiltersCount > 0)
                <div class="px-4 py-2 bg-light border-bottom d-flex flex-wrap align-items-center gap-2">
                    <span class="small fw-bold text-muted text-uppercase me-1" style="font-size: 0.75rem;">Filtros Ativos:</span>
                    @foreach ($activeFilterList as $f)
                        @php
                            $paramsToRemove = explode(',', $f['param']);
                            $clearUrl = route('documentos-internos.index', request()->except(array_merge($paramsToRemove, ['page'])));
                        @endphp
                        <span class="active-filter-pill">
                            <span>{{ $f['label'] }}</span>
                            <a href="{{ $clearUrl }}" class="btn-clear" title="Remover filtro">&times;</a>
                        </span>
                    @endforeach
                    <a href="{{ route('documentos-internos.index', array_filter(['tab' => request('tab')])) }}" class="small text-muted text-decoration-none ms-2">
                        Limpar todos
                    </a>
                </div>
            @endif

            {{-- Tabela Otimizada --}}
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="docsTable"
                    style="border-collapse: separate; border-spacing: 0;">
                    <thead class="bg-light">
                        <tr>
                            @php
                                $sortLink = function ($col, $label) {
                                    $direction = request('sort_by') == $col && request('order') == 'asc' ? 'desc' : 'asc';
                                    $icon = 'fa-sort';
                                    if (request('sort_by') == $col) {
                                        $icon = request('order') == 'asc' ? 'fa-sort-up text-primary' : 'fa-sort-down text-primary';
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
                            <th class="ps-2 py-3 border-bottom text-uppercase small fw-bold text-muted">
                                {!! $sortLink('numero_referencia', 'Referência / Ano') !!}
                            </th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">
                                {!! $sortLink('titulo', 'Título / Objeto') !!}
                            </th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">Espécie</th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">Status</th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">Autor</th>
                            @if ($isChefeGabinete)
                                <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">Departamento</th>
                            @endif
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted">
                                {!! $sortLink('created_at', 'Data') !!}
                            </th>
                            <th class="py-3 border-bottom text-uppercase small fw-bold text-muted text-end pe-4">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($documentos as $doc)
                            <tr class="clickable-row" data-href="{{ route('documentos-internos.show', $doc) }}" data-preview-url="{{ route('documentos-internos.show', $doc) }}" data-doc-id="{{ $doc->id }}" data-doc-type="interno" tabindex="0" draggable="true">
                                <td class="ps-3 text-center" onclick="event.stopPropagation()">
                                    <input type="checkbox" class="form-check-input archive-select"
                                        data-doc-id="{{ $doc->id }}" data-doc-type="interno"
                                        title="Selecionar documento">
                                </td>
                                <td class="ps-2">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-white border rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0 shadow-sm"
                                            style="width: 34px; height: 34px;">
                                            <i class="fas fa-file-signature text-primary small"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark text-break" style="max-width: 200px;">
                                                {{ $doc->numero_referencia ?: '#' . $doc->id }}
                                            </div>
                                            <div class="text-muted" style="font-size: 0.725rem;">
                                                v{{ $doc->versao_semantica }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td style="max-width: 340px;">
                                    <div class="fw-semibold text-dark text-truncate" title="{{ $doc->titulo }}">
                                        {{ $doc->titulo }}
                                        @if ($doc->is_favorited)
                                            <i class="fas fa-heart text-danger ms-1 small" title="Favorito"></i>
                                        @endif
                                    </div>
                                    @if ($doc->destinatario_nome || $doc->destinatario_orgao)
                                        <div class="text-muted text-truncate small" style="font-size: 0.75rem;">
                                            <i class="fas fa-user-tag me-1 text-muted opacity-75"></i>
                                            Para: {{ $doc->destinatario_nome ?: $doc->destinatario_orgao }}
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    <span class="badge bg-light text-secondary border px-2 py-1">
                                        {{ $doc->especie->nome ?? 'Documento' }}
                                    </span>
                                </td>

                                <td>
                                    @php
                                        $statusVal = is_string($doc->status) ? $doc->status : ($doc->status?->value ?? 'rascunho');
                                        $statusBadgeClass = match($statusVal) {
                                            'rascunho' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                                            'em_analise', 'pendente_tratamento' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                                            'aprovado', 'assinado', 'finalizado' => 'bg-success-subtle text-success-emphasis border border-success-subtle',
                                            'arquivado' => 'bg-dark-subtle text-dark border border-dark-subtle',
                                            default => 'bg-light text-dark border',
                                        };
                                        $statusLabel = is_object($doc->status) && method_exists($doc->status, 'label') 
                                            ? $doc->status->label() 
                                            : ucfirst(str_replace('_', ' ', $statusVal));
                                    @endphp
                                    <span class="badge {{ $statusBadgeClass }} rounded-pill px-2 py-1">
                                        {{ $statusLabel }}
                                    </span>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="text-dark small fw-medium">{{ $doc->autor->name ?? '—' }}</span>
                                        @if ($doc->criado_por === Auth::id())
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-1 py-0 ms-1" style="font-size: 0.65rem;">Você</span>
                                        @endif
                                    </div>
                                </td>

                                @if ($isChefeGabinete)
                                    <td>
                                        <span class="text-dark small fw-medium">{{ $doc->departamento->nome ?? '—' }}</span>
                                    </td>
                                @endif

                                <td class="text-muted small">
                                    <i class="far fa-clock me-1 opacity-75"></i>{{ $doc->created_at->format('d/m/Y H:i') }}
                                </td>

                                <td class="text-end pe-4" onclick="event.stopPropagation()">
                                    <div class="dropdown">
                                        <button class="btn btn-icon btn-sm btn-light rounded-circle shadow-sm dropdown-action-btn"
                                            type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                            style="width: 32px; height: 32px;">
                                            <i class="fas fa-ellipsis-v text-muted"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                                            <li>
                                                <h6 class="dropdown-header text-uppercase small fw-bold">Ações</h6>
                                            </li>
                                            <li>
                                                <a class="dropdown-item py-2" href="{{ route('documentos-internos.show', $doc) }}">
                                                    <i class="fas fa-eye me-2 text-primary w-20"></i>Visualizar
                                                </a>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item py-2 preview-trigger"
                                                    data-url="{{ route('documentos-internos.show', $doc) }}">
                                                    <i class="fas fa-magnifying-glass me-2 text-info w-20"></i>Pré-visualizar
                                                </button>
                                            </li>
                                            <li>
                                                <a class="dropdown-item py-2" href="{{ route('documentos-internos.pdf', $doc) }}" target="_blank">
                                                    <i class="fas fa-file-pdf me-2 text-danger w-20"></i>Baixar PDF
                                                </a>
                                            </li>
                                            @if ($statusVal === 'rascunho')
                                                <li>
                                                    <a class="dropdown-item py-2" href="{{ route('documentos-internos.edit', $doc) }}">
                                                        <i class="fas fa-edit me-2 text-warning w-20"></i>Editar Minuta
                                                    </a>
                                                </li>
                                            @endif
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button type="button"
                                                    class="dropdown-item py-2 favorite-btn {{ $doc->is_favorited ? 'active' : '' }}"
                                                    onclick="toggleFavorite(event, '{{ $doc->id }}')">
                                                    <i class="{{ $doc->is_favorited ? 'fas' : 'far' }} fa-heart me-2 text-danger w-20"></i>{{ $doc->is_favorited ? 'Desfavoritar' : 'Favoritar' }}
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item py-2"
                                                    onclick="shareDoc(event, '{{ route('documentos-internos.show', $doc) }}')">
                                                    <i class="fas fa-share-alt me-2 text-secondary w-20"></i>Copiar Link
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isChefeGabinete ? 9 : 8 }}" class="text-center py-5">
                                    <div class="d-flex flex-column align-items-center justify-content-center">
                                        <div class="bg-light rounded-circle p-4 mb-3">
                                            <i class="fas fa-folder-open fa-3x text-muted opacity-50"></i>
                                        </div>
                                        <h5 class="fw-bold text-muted">Nenhum documento encontrado</h5>
                                        <p class="text-muted small mb-3">Tente ajustar os filtros ou selecionar outra aba de trabalho.</p>
                                        <a href="{{ route('documentos-internos.create') }}" class="btn btn-primary btn-sm px-3 shadow-sm">
                                            <i class="fas fa-plus me-1"></i> Criar Novo Documento
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Paginação --}}
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center p-4 border-top bg-light gap-3">
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

    {{-- Arquivamento por arrastar-e-soltar --}}
    <x-archive-dropzone document-type="interno" />

    <!-- Modal Preview Rápido -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title fw-bold"><i class="fas fa-search me-2 text-primary"></i>Visualização Rápida</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 position-relative">
                    <div id="previewLoader" class="position-absolute top-50 start-50 translate-middle text-center">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">Carregando documento...</p>
                    </div>
                    <div id="previewContent" class="preview-paper m-3 d-none">
                        <!-- Content injected via AJAX -->
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
            // Retractable Search Logic
            const btnTrigger = document.getElementById('btn-trigger-search');
            const searchForm = document.getElementById('form-expandable-search');
            const searchInput = document.getElementById('input-expandable-search');
            const btnClose = document.getElementById('btn-close-search');

            if (btnTrigger && searchInput) {
                btnTrigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    btnTrigger.classList.add('d-none');
                    searchForm.classList.add('expanded');
                    searchInput.style.width = '240px';
                    searchInput.style.opacity = '1';
                    searchInput.focus();
                    if (btnClose) btnClose.style.display = 'block';
                });
            }

            if (btnClose && searchInput) {
                btnClose.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (searchInput.value.trim() !== '') {
                        searchInput.value = '';
                        searchForm.submit();
                    } else {
                        searchInput.style.width = '0px';
                        searchInput.style.opacity = '0';
                        searchForm.classList.remove('expanded');
                        if (btnTrigger) btnTrigger.classList.remove('d-none');
                        btnClose.style.display = 'none';
                    }
                });
            }

            // Inicializa dropdowns com posicionamento fixed para não cortar na tabela
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
                    if (window.getSelection().toString().length > 0) return;
                    if (e.ctrlKey || e.metaKey) return;
                    if (e.target.closest('button') || e.target.closest('a') || e.target.closest('.dropdown') || e.target.closest('input')) return;

                    const href = this.getAttribute('data-href');
                    if (href) {
                        window.location.href = href;
                    }
                });

                row.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        this.click();
                    }
                });
            });

            // 2. Keyboard Navigation
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

            // Focus search on '/'
            document.addEventListener('keydown', function(e) {
                if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
                    e.preventDefault();
                    if (btnTrigger && !btnTrigger.classList.contains('d-none')) {
                        btnTrigger.click();
                    } else if (searchInput) {
                        searchInput.focus();
                    }
                }
            });

            // 3. Modal Preview
            const previewModalEl = document.getElementById('previewModal');
            let previewModal = null;
            if (previewModalEl) {
                previewModal = new bootstrap.Modal(previewModalEl);
            }

            const previewContent = document.getElementById('previewContent');
            const previewLoader = document.getElementById('previewLoader');
            const btnFullView = document.getElementById('btnFullView');

            let hoverTimeout;
            const previewTriggers = document.querySelectorAll('.preview-trigger');

            previewTriggers.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    clearTimeout(hoverTimeout);
                    openPreview(this.getAttribute('data-url'));
                });
            });

            function openPreview(url) {
                if (!url || !previewModal) return;

                previewContent.classList.add('d-none');
                previewLoader.classList.remove('d-none');
                if (btnFullView) btnFullView.href = url;

                previewModal.show();
                loadPreviewContent(url);
            }

            function loadPreviewContent(url) {
                const previewUrl = new URL(url, window.location.origin);
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
                            previewContent.innerHTML = '<div class="alert alert-warning text-center m-3">Formato de resposta inválido.</div>';
                        }
                        previewLoader.classList.add('d-none');
                        previewContent.classList.remove('d-none');
                    })
                    .catch(err => {
                        console.error(err);
                        previewLoader.classList.add('d-none');
                        previewContent.innerHTML = '<div class="alert alert-danger text-center m-3">Erro ao carregar pré-visualização. <br> <a href="' + url + '" class="alert-link">Clique aqui para abrir o documento completo.</a></div>';
                        previewContent.classList.remove('d-none');
                    });
            }
        });

        // Global Favorite & Share Functions
        window.toggleFavorite = function(event, docId) {
            event.stopPropagation();
            const btn = event.currentTarget;
            const icon = btn.querySelector('i');

            const isNowActive = !btn.classList.contains('active');
            btn.classList.toggle('active');
            if (icon) {
                icon.classList.toggle('fas');
                icon.classList.toggle('far');
            }

            fetch(`/documentos-internos/${docId}/favorite`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.is_favorited !== isNowActive) {
                    btn.classList.toggle('active');
                    if (icon) {
                        icon.classList.toggle('fas');
                        icon.classList.toggle('far');
                    }
                }
            })
            .catch(error => {
                console.error('Error toggling favorite:', error);
                btn.classList.toggle('active');
                if (icon) {
                    icon.classList.toggle('fas');
                    icon.classList.toggle('far');
                }
            });
        };

        window.shareDoc = function(event, url) {
            event.stopPropagation();
            navigator.clipboard.writeText(url).then(() => {
                const btn = event.currentTarget;
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check text-success me-2"></i>Copiado!';
                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        };
    </script>

    {{-- Barra Flutuante de Ações em Lote --}}
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
