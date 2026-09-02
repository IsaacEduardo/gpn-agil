@extends('layouts.app')

@section('title', 'EDMS - Arquivo Digital')

@section('content')
<div class="container-fluid py-4">

    {{-- Alert Messages --}}
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-3 alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="fas fa-check-circle me-3 fs-4 text-success"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-3 alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-3 fs-4 text-danger"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Top Action Toolbar & Breadcrumb Header --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                
                {{-- Title & Dynamic Breadcrumbs --}}
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="p-2 bg-primary-subtle text-primary rounded-3 d-inline-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i class="fas fa-archive fs-5"></i>
                        </span>
                        <h4 class="fw-bold text-dark mb-0">EDMS - Gestão de Arquivos Digitais</h4>
                    </div>

                    {{-- Breadcrumb Dinâmico --}}
                    <nav aria-label="breadcrumb" class="ms-1 mt-2">
                        <ol class="breadcrumb mb-0 small text-muted">
                            <li class="breadcrumb-item">
                                <a href="{{ route('edms.index') }}" class="text-decoration-none text-primary fw-semibold">
                                    <i class="fas fa-database me-1"></i> Repositório
                                </a>
                            </li>
                            
                            @if(isset($currentFolder))
                                @foreach($breadcrumbs as $crumb)
                                    <li class="breadcrumb-item">
                                        <a href="{{ route('edms.index', ['folder' => $crumb->id]) }}" class="text-decoration-none text-secondary">
                                            {{ $crumb->nome }}
                                        </a>
                                    </li>
                                @endforeach
                                <li class="breadcrumb-item active fw-bold text-dark">{{ $currentFolder->nome }}</li>
                            @elseif($activeTreeKey === 'entradas')
                                <li class="breadcrumb-item text-secondary">Doc. de Entrada</li>
                                @if($treeYear)
                                    <li class="breadcrumb-item {{ !$treeMonth ? 'active fw-bold text-dark' : '' }}">
                                        <a href="{{ route('edms.index', ['tree' => 'entradas', 'year' => $treeYear, 'view_mode' => $viewMode]) }}" class="text-decoration-none text-secondary">{{ $treeYear }}</a>
                                    </li>
                                @endif
                                @if($treeMonth)
                                    <li class="breadcrumb-item {{ !$treeEspecie ? 'active fw-bold text-dark' : '' }}">
                                        <a href="{{ route('edms.index', ['tree' => 'entradas', 'year' => $treeYear, 'month' => $treeMonth, 'view_mode' => $viewMode]) }}" class="text-decoration-none text-secondary">{{ $treeMonthName }}</a>
                                    </li>
                                @endif
                                @if($treeEspecie)
                                    <li class="breadcrumb-item active fw-bold text-dark">{{ $treeEspecie }}</li>
                                @endif
                            @elseif($activeTreeKey === 'internos')
                                <li class="breadcrumb-item text-secondary">Doc. Internos</li>
                                @if($treeYear)
                                    <li class="breadcrumb-item {{ !$treeMonth ? 'active fw-bold text-dark' : '' }}">
                                        <a href="{{ route('edms.index', ['tree' => 'internos', 'year' => $treeYear, 'view_mode' => $viewMode]) }}" class="text-decoration-none text-secondary">{{ $treeYear }}</a>
                                    </li>
                                @endif
                                @if($treeMonth)
                                    <li class="breadcrumb-item {{ !$treeEspecie ? 'active fw-bold text-dark' : '' }}">
                                        <a href="{{ route('edms.index', ['tree' => 'internos', 'year' => $treeYear, 'month' => $treeMonth, 'view_mode' => $viewMode]) }}" class="text-decoration-none text-secondary">{{ $treeMonthName }}</a>
                                    </li>
                                @endif
                                @if($treeEspecie)
                                    <li class="breadcrumb-item active fw-bold text-dark">{{ $treeEspecie }}</li>
                                @endif
                            @elseif($activeTreeKey === 'pendentes')
                                <li class="breadcrumb-item active fw-bold text-warning-emphasis">Pendentes de Arquivamento</li>
                            @else
                                <li class="breadcrumb-item active fw-semibold text-secondary">Dossiês & Pastas Manuais</li>
                            @endif
                        </ol>
                    </nav>
                </div>

                {{-- Toolbar Actions & View Mode Toggle --}}
                <div class="d-flex flex-wrap align-items-center gap-2">
                    
                    {{-- Toggle View Mode: Table vs Grid --}}
                    <div class="btn-group shadow-sm rounded-pill p-1 bg-light border" role="group" aria-label="Modo de Visualização">
                        <a href="{{ request()->fullUrlWithQuery(['view_mode' => 'table']) }}" 
                           class="btn btn-sm rounded-pill border-0 px-3 {{ $viewMode === 'table' ? 'btn-primary text-white fw-bold shadow-sm' : 'btn-light text-muted' }}" 
                           title="Visualização em Tabela Detalhada">
                            <i class="fas fa-table me-1"></i> Tabela
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['view_mode' => 'grid']) }}" 
                           class="btn btn-sm rounded-pill border-0 px-3 {{ $viewMode === 'grid' ? 'btn-primary text-white fw-bold shadow-sm' : 'btn-light text-muted' }}" 
                           title="Visualização em Grid Amplo">
                            <i class="fas fa-th-large me-1"></i> Grid
                        </a>
                    </div>

                    {{-- Admin Temporalidade --}}
                    @if(auth()->user()->isAdmin() || auth()->user()->hasRole('admin'))
                        <a href="{{ route('edms.retention') }}" class="btn btn-outline-danger btn-sm px-3 rounded-pill fw-semibold shadow-sm">
                            <i class="fas fa-calendar-alt me-1"></i> Temporalidade
                        </a>
                    @endif

                    {{-- Ações da Pasta Física --}}
                    @if(isset($currentFolder) && ($currentFolder->created_by === auth()->id() || auth()->user()->isAdmin()))
                        <button class="btn btn-outline-warning btn-sm px-3 rounded-pill fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#shareFolderModal">
                            <i class="fas fa-share-alt me-1"></i> Partilhar
                        </button>
                        <button class="btn btn-outline-secondary btn-sm px-3 rounded-pill fw-semibold shadow-sm" id="btnFolderHistory" data-bs-toggle="modal" data-bs-target="#folderHistoryModal" data-folder-id="{{ $currentFolder->id }}">
                            <i class="fas fa-history me-1"></i> Histórico
                        </button>
                    @endif

                    {{-- Nova Pasta --}}
                    <button class="btn btn-primary btn-sm px-3 rounded-pill fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#createFolderModal">
                        <i class="fas fa-folder-plus me-1"></i> Nova Pasta
                    </button>
                </div>

            </div>

            {{-- Inline Fast Search & Collapsible Advanced Filter --}}
            <div class="mt-3 pt-3 border-top">
                <form action="{{ route('edms.index') }}" method="GET" class="row g-2 align-items-center">
                    <input type="hidden" name="tree" value="{{ $activeTreeKey }}">
                    @if($currentFolder) <input type="hidden" name="folder" value="{{ $currentFolder->id }}"> @endif
                    @if($treeYear) <input type="hidden" name="year" value="{{ $treeYear }}"> @endif
                    @if($treeMonth) <input type="hidden" name="month" value="{{ $treeMonth }}"> @endif
                    @if($treeEspecie) <input type="hidden" name="especie" value="{{ $treeEspecie }}"> @endif
                    <input type="hidden" name="view_mode" value="{{ $viewMode }}">

                    <div class="col-md-5 col-lg-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control bg-light border-start-0 ps-0" placeholder="Pesquisar por assunto, número, ref, procedência..." value="{{ request('search') }}">
                            @if(request('search'))
                                <a href="{{ route('edms.index', ['tree' => $activeTreeKey, 'year' => $treeYear, 'month' => $treeMonth, 'especie' => $treeEspecie, 'view_mode' => $viewMode]) }}" class="btn btn-light border border-start-0 text-muted" title="Limpar busca">
                                    <i class="fas fa-times"></i>
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="col-md-3 col-lg-2">
                        <select name="type" class="form-select form-select-sm bg-light">
                            <option value="">Todos os Tipos</option>
                            <option value="entrada" {{ request('type') === 'entrada' ? 'selected' : '' }}>Entradas Externas</option>
                            <option value="interno" {{ request('type') === 'interno' ? 'selected' : '' }}>Internos</option>
                        </select>
                    </div>

                    <div class="col-md-4 col-lg-4 d-flex gap-2">
                        <button type="submit" class="btn btn-secondary btn-sm px-3 rounded-pill fw-semibold">
                            <i class="fas fa-filter me-1"></i> Filtrar
                        </button>
                        <button type="button" class="btn btn-link btn-sm text-decoration-none text-muted" data-bs-toggle="collapse" data-bs-target="#advancedFiltersCollapse">
                            <i class="fas fa-sliders-h me-1"></i> Datas
                        </button>
                    </div>

                    {{-- Collapsible Dates --}}
                    <div class="collapse col-12 mt-2 {{ request('date_start') || request('date_end') ? 'show' : '' }}" id="advancedFiltersCollapse">
                        <div class="p-3 bg-light rounded-3 d-flex flex-wrap align-items-center gap-3">
                            <span class="small fw-bold text-muted">Intervalo de Datas:</span>
                            <div class="d-flex align-items-center gap-2">
                                <span class="small text-muted">De:</span>
                                <input type="date" name="date_start" class="form-control form-control-sm" value="{{ request('date_start') }}">
                                <span class="small text-muted">Até:</span>
                                <input type="date" name="date_end" class="form-control form-control-sm" value="{{ request('date_end') }}">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Main Hybrid Layout: 2 Columns (Tree View Left 1/4 + Workspace Right 3/4) --}}
    <div class="row g-4">

        {{-- 1. LEFT SIDEBAR: CRONOLOGICAL TREE VIEW NAVIGATOR (1/4 Width) --}}
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 20px; z-index: 10;">
                <div class="card-header bg-white border-0 py-3 px-3">
                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center justify-content-between">
                        <span><i class="fas fa-sitemap text-primary me-2"></i>Navegador de Arquivos</span>
                        <span class="badge bg-primary-subtle text-primary rounded-pill small px-2">RBAC</span>
                    </h6>
                </div>
                
                <div class="card-body p-2" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                    <div class="list-group list-group-flush border-0 small">
                        
                        {{-- A. DOCUMENTOS DE ENTRADA (TREE ACCORDION: Tipo -> Ano -> Mês -> Espécie) --}}
                        <div class="mb-2">
                            <a href="#treeEntradasCollapse" data-bs-toggle="collapse" 
                               class="list-group-item list-group-item-action border-0 rounded-3 d-flex align-items-center justify-content-between p-2 fw-semibold {{ $activeTreeKey === 'entradas' ? 'bg-warning-subtle text-dark' : 'text-dark' }}"
                               aria-expanded="{{ $activeTreeKey === 'entradas' ? 'true' : 'false' }}">
                                <span class="d-flex align-items-center gap-2 text-truncate">
                                    <i class="fas fa-file-import text-warning fs-6"></i>
                                    <span>Doc. de Entrada</span>
                                </span>
                                <span class="d-flex align-items-center gap-2">
                                    <span class="badge bg-warning-subtle text-dark rounded-pill">{{ $virtualTree['entradas']['total'] ?? 0 }}</span>
                                    <i class="fas fa-chevron-down nav-arrow opacity-50" style="font-size: 0.75rem;"></i>
                                </span>
                            </a>

                            <div class="collapse ps-2 mt-1 {{ $activeTreeKey === 'entradas' ? 'show' : '' }}" id="treeEntradasCollapse">
                                @forelse($virtualTree['entradas']['years'] ?? [] as $ano => $anoData)
                                    {{-- Nível 2: Ano --}}
                                    <div class="mt-1">
                                        <div class="d-flex align-items-center justify-content-between py-1 px-2 rounded {{ $activeTreeKey === 'entradas' && $treeYear == $ano && !$treeMonth && !$treeEspecie ? 'bg-light fw-bold text-primary' : 'text-secondary' }}">
                                            <a href="{{ route('edms.index', ['tree' => 'entradas', 'year' => $ano, 'view_mode' => $viewMode]) }}" class="text-decoration-none text-dark fw-semibold text-truncate">
                                                <i class="far fa-folder-open text-warning me-1"></i> {{ $ano }}
                                            </a>
                                            <span class="badge bg-light text-dark rounded-pill border" style="font-size: 0.7rem;">{{ $anoData['count'] }}</span>
                                        </div>

                                        {{-- Nível 3: Mês --}}
                                        @if($activeTreeKey === 'entradas' && $treeYear == $ano)
                                            <div class="ps-3 border-start ms-2 my-1">
                                                @foreach($anoData['months'] as $mStr => $mGroup)
                                                    <div class="my-1">
                                                        <div class="d-flex align-items-center justify-content-between py-1 px-2 rounded {{ $activeTreeKey === 'entradas' && $treeYear == $ano && $treeMonth == $mStr && !$treeEspecie ? 'bg-primary-subtle fw-bold text-primary' : 'text-secondary' }}">
                                                            <a href="{{ route('edms.index', ['tree' => 'entradas', 'year' => $ano, 'month' => $mStr, 'view_mode' => $viewMode]) }}" class="text-decoration-none text-secondary text-truncate" style="font-size: 0.82rem;">
                                                                <i class="fas fa-folder text-warning opacity-75 me-1"></i> {{ $mGroup['mes_nome'] }}
                                                            </a>
                                                            <span class="badge rounded-pill bg-light text-muted border" style="font-size: 0.65rem;">{{ $mGroup['count'] }}</span>
                                                        </div>

                                                        {{-- Nível 4: Espécies Documentais --}}
                                                        @if($activeTreeKey === 'entradas' && $treeYear == $ano && $treeMonth == $mStr)
                                                            <div class="ps-3 border-start ms-2 my-1">
                                                                @foreach($mGroup['species'] as $especieNome => $eCount)
                                                                    <a href="{{ route('edms.index', ['tree' => 'entradas', 'year' => $ano, 'month' => $mStr, 'especie' => $especieNome, 'view_mode' => $viewMode]) }}" 
                                                                       class="text-decoration-none d-flex align-items-center justify-content-between py-1 px-2 rounded {{ $activeTreeKey === 'entradas' && $treeYear == $ano && $treeMonth == $mStr && $treeEspecie === $especieNome ? 'bg-primary text-white fw-bold' : 'text-muted' }}" 
                                                                       style="font-size: 0.78rem;">
                                                                        <span class="text-truncate me-1"><i class="fas fa-file-alt opacity-50 me-1"></i> {{ $especieNome }}</span>
                                                                        <span class="badge rounded-pill {{ $treeEspecie === $especieNome ? 'bg-white text-primary' : 'bg-light text-muted' }}" style="font-size: 0.65rem;">{{ $eCount }}</span>
                                                                    </a>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="text-muted small ps-2 py-1 italic">Nenhum arquivo de entrada</div>
                                @endforelse
                            </div>
                        </div>

                        {{-- B. DOCUMENTOS INTERNOS (TREE ACCORDION: Tipo -> Ano -> Mês -> Espécie) --}}
                        <div class="mb-2">
                            <a href="#treeInternosCollapse" data-bs-toggle="collapse" 
                               class="list-group-item list-group-item-action border-0 rounded-3 d-flex align-items-center justify-content-between p-2 fw-semibold {{ $activeTreeKey === 'internos' ? 'bg-info-subtle text-dark' : 'text-dark' }}"
                               aria-expanded="{{ $activeTreeKey === 'internos' ? 'true' : 'false' }}">
                                <span class="d-flex align-items-center gap-2 text-truncate">
                                    <i class="fas fa-file-alt text-info fs-6"></i>
                                    <span>Doc. Internos</span>
                                </span>
                                <span class="d-flex align-items-center gap-2">
                                    <span class="badge bg-info-subtle text-dark rounded-pill">{{ $virtualTree['internos']['total'] ?? 0 }}</span>
                                    <i class="fas fa-chevron-down nav-arrow opacity-50" style="font-size: 0.75rem;"></i>
                                </span>
                            </a>

                            <div class="collapse ps-2 mt-1 {{ $activeTreeKey === 'internos' ? 'show' : '' }}" id="treeInternosCollapse">
                                @forelse($virtualTree['internos']['years'] ?? [] as $ano => $anoData)
                                    {{-- Nível 2: Ano --}}
                                    <div class="mt-1">
                                        <div class="d-flex align-items-center justify-content-between py-1 px-2 rounded {{ $activeTreeKey === 'internos' && $treeYear == $ano && !$treeMonth && !$treeEspecie ? 'bg-light fw-bold text-primary' : 'text-secondary' }}">
                                            <a href="{{ route('edms.index', ['tree' => 'internos', 'year' => $ano, 'view_mode' => $viewMode]) }}" class="text-decoration-none text-dark fw-semibold text-truncate">
                                                <i class="far fa-folder-open text-info me-1"></i> {{ $ano }}
                                            </a>
                                            <span class="badge bg-light text-dark rounded-pill border" style="font-size: 0.7rem;">{{ $anoData['count'] }}</span>
                                        </div>

                                        {{-- Nível 3: Mês --}}
                                        @if($activeTreeKey === 'internos' && $treeYear == $ano)
                                            <div class="ps-3 border-start ms-2 my-1">
                                                @foreach($anoData['months'] as $mStr => $mGroup)
                                                    <div class="my-1">
                                                        <div class="d-flex align-items-center justify-content-between py-1 px-2 rounded {{ $activeTreeKey === 'internos' && $treeYear == $ano && $treeMonth == $mStr && !$treeEspecie ? 'bg-primary-subtle fw-bold text-primary' : 'text-secondary' }}">
                                                            <a href="{{ route('edms.index', ['tree' => 'internos', 'year' => $ano, 'month' => $mStr, 'view_mode' => $viewMode]) }}" class="text-decoration-none text-secondary text-truncate" style="font-size: 0.82rem;">
                                                                <i class="fas fa-folder text-info opacity-75 me-1"></i> {{ $mGroup['mes_nome'] }}
                                                            </a>
                                                            <span class="badge rounded-pill bg-light text-muted border" style="font-size: 0.65rem;">{{ $mGroup['count'] }}</span>
                                                        </div>

                                                        {{-- Nível 4: Espécies Documentais --}}
                                                        @if($activeTreeKey === 'internos' && $treeYear == $ano && $treeMonth == $mStr)
                                                            <div class="ps-3 border-start ms-2 my-1">
                                                                @foreach($mGroup['species'] as $especieNome => $eCount)
                                                                    <a href="{{ route('edms.index', ['tree' => 'internos', 'year' => $ano, 'month' => $mStr, 'especie' => $especieNome, 'view_mode' => $viewMode]) }}" 
                                                                       class="text-decoration-none d-flex align-items-center justify-content-between py-1 px-2 rounded {{ $activeTreeKey === 'internos' && $treeYear == $ano && $treeMonth == $mStr && $treeEspecie === $especieNome ? 'bg-primary text-white fw-bold' : 'text-muted' }}" 
                                                                       style="font-size: 0.78rem;">
                                                                        <span class="text-truncate me-1"><i class="fas fa-file-alt opacity-50 me-1"></i> {{ $especieNome }}</span>
                                                                        <span class="badge rounded-pill {{ $treeEspecie === $especieNome ? 'bg-white text-primary' : 'bg-light text-muted' }}" style="font-size: 0.65rem;">{{ $eCount }}</span>
                                                                    </a>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="text-muted small ps-2 py-1 italic">Nenhum arquivo interno</div>
                                @endforelse
                            </div>
                        </div>

                        {{-- C. DOSSIÊS & PASTAS MANUAIS (CUSTOM FOLDERS) --}}
                        <div class="mb-2">
                            <a href="{{ route('edms.index', ['tree' => 'dossies', 'view_mode' => $viewMode]) }}" 
                               class="list-group-item list-group-item-action border-0 rounded-3 d-flex align-items-center justify-content-between p-2 fw-semibold {{ $activeTreeKey === 'dossies' ? 'bg-primary-subtle text-primary' : 'text-dark' }}">
                                <span class="d-flex align-items-center gap-2 text-truncate">
                                    <i class="fas fa-folder text-primary fs-6"></i>
                                    <span>Dossiês & Pastas</span>
                                </span>
                                <span class="badge bg-light text-dark rounded-pill border">{{ count($pastas) }}</span>
                            </a>

                            @if(($activeTreeKey === 'dossies' || request()->filled('search')) && count($pastas) > 0)
                                <div class="ps-3 mt-1">
                                    @foreach($pastas as $pastaItem)
                                        <a href="{{ route('edms.index', ['folder' => $pastaItem->id, 'tree' => 'dossies', 'view_mode' => $viewMode]) }}" 
                                           class="text-decoration-none d-flex align-items-center justify-content-between py-1 px-2 rounded my-1 {{ isset($currentFolder) && $currentFolder->id === $pastaItem->id ? 'bg-primary text-white fw-bold' : 'text-secondary' }}"
                                           style="font-size: 0.82rem;">
                                            <span class="text-truncate"><i class="fas fa-folder me-1 text-warning"></i> {{ $pastaItem->nome }}</span>
                                            <span class="badge rounded-pill {{ isset($currentFolder) && $currentFolder->id === $pastaItem->id ? 'bg-white text-primary' : 'bg-light text-muted' }}" style="font-size: 0.65rem;">
                                                {{ ($pastaItem->documentos_count ?? 0) + ($pastaItem->documentos_internos_count ?? 0) }}
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- D. PENDENTES DE ARQUIVAMENTO --}}
                        <div class="mt-2 pt-2 border-top">
                            <a href="{{ route('edms.index', ['tree' => 'pendentes', 'view_mode' => $viewMode]) }}" 
                               class="list-group-item list-group-item-action border-0 rounded-3 d-flex align-items-center justify-content-between p-2 fw-semibold {{ $activeTreeKey === 'pendentes' ? 'bg-warning text-dark fw-bold' : 'text-dark' }}">
                                <span class="d-flex align-items-center gap-2 text-truncate">
                                    <i class="fas fa-clock text-danger fs-6"></i>
                                    <span>Pendentes Arquivar</span>
                                </span>
                                @if(($pendentesTotal ?? 0) > 0)
                                    <span class="badge bg-danger text-white rounded-pill shadow-sm">{{ $pendentesTotal }}</span>
                                @else
                                    <span class="badge bg-light text-muted rounded-pill">0</span>
                                @endif
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        {{-- 2. RIGHT WORKSPACE AREA: MAIN DATA TABLE & GRID (3/4 Width) --}}
        <div class="col-lg-9">

            {{-- A. SEÇÃO: PENDENTES DE ARQUIVAMENTO (Se selecionado ou se na raiz com itens pendentes) --}}
            @if($activeTreeKey === 'pendentes' || (!isset($currentFolder) && !request()->filled('search') && !request()->filled('year') && !request()->filled('month') && !request()->filled('especie') && $pendentesTotal > 0))
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-warning-subtle border-0 py-3 px-4 rounded-top-4">
                        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center">
                            <i class="fas fa-clock text-danger me-2 fs-5"></i>
                            <span>Documentos Concluídos Pendentes de Classificação / Arquivamento</span>
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light small text-uppercase fw-bold">
                                    <tr>
                                        <th class="ps-4">Tipo / Ref</th>
                                        <th>Assunto / Título</th>
                                        <th>Departamento</th>
                                        <th>Data Conclusão</th>
                                        <th class="text-end pe-4">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{-- Pendentes de Entrada --}}
                                    @forelse($pendentesEntrada as $docEnt)
                                        <tr>
                                            <td class="ps-4">
                                                <span class="badge bg-warning-subtle text-dark border me-1">Entrada</span>
                                                <span class="fw-bold text-dark">#{{ $docEnt->numero_sequencial }}/{{ $docEnt->ano_referencia }}</span>
                                            </td>
                                            <td class="fw-semibold text-dark text-wrap" style="max-width: 320px;">
                                                {{ $docEnt->assunto }}
                                            </td>
                                            <td><span class="small text-muted">{{ optional($docEnt->departamento)->nome ?? 'N/D' }}</span></td>
                                            <td><span class="small text-muted">{{ $docEnt->created_at ? $docEnt->created_at->format('d/m/Y H:i') : '-' }}</span></td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#arquivarEntradaModal{{ $docEnt->id }}">
                                                    <i class="fas fa-archive me-1"></i> Classificar & Arquivar
                                                </button>
                                            </td>
                                        </tr>

                                        {{-- Modal de Arquivamento de Entrada --}}
                                        <div class="modal fade" id="arquivarEntradaModal{{ $docEnt->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <form action="{{ route('pastas.arquivar', $docEnt->id) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="tipo_documento" value="entrada">
                                                    <div class="modal-content rounded-4 border-0 shadow">
                                                        <div class="modal-header border-0 pb-0">
                                                            <h5 class="modal-title fw-bold">Arquivar Entrada #{{ $docEnt->numero_sequencial }}/{{ $docEnt->ano_referencia }}</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p class="small text-muted mb-3">{{ $docEnt->assunto }}</p>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold small">Selecione a Pasta de Destino</label>
                                                                <select name="pasta_id" class="form-select" required>
                                                                    <option value="">-- Selecione uma pasta --</option>
                                                                    @foreach($pastas as $pOp)
                                                                        <option value="{{ $pOp->id }}">{{ $pOp->nome }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-0 pt-0">
                                                            <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" class="btn btn-primary rounded-pill px-4">Arquivar</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @empty
                                    @endforelse

                                    {{-- Pendentes Internos --}}
                                    @forelse($pendentesInternos as $docInt)
                                        <tr>
                                            <td class="ps-4">
                                                <span class="badge bg-info-subtle text-dark border me-1">Interno</span>
                                                <span class="fw-bold text-dark">{{ $docInt->numero_referencia ?? 'S/Ref' }}</span>
                                            </td>
                                            <td class="fw-semibold text-dark text-wrap" style="max-width: 320px;">
                                                {{ $docInt->titulo }}
                                            </td>
                                            <td><span class="small text-muted">{{ optional($docInt->departamento)->nome ?? 'N/D' }}</span></td>
                                            <td><span class="small text-muted">{{ $docInt->updated_at ? $docInt->updated_at->format('d/m/Y H:i') : '-' }}</span></td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#arquivarInternoModal{{ $docInt->id }}">
                                                    <i class="fas fa-archive me-1"></i> Classificar & Arquivar
                                                </button>
                                            </td>
                                        </tr>

                                        {{-- Modal Arquivar Interno --}}
                                        <div class="modal fade" id="arquivarInternoModal{{ $docInt->id }}" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <form action="{{ route('pastas.arquivar', $docInt->id) }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="tipo_documento" value="interno">
                                                    <div class="modal-content rounded-4 border-0 shadow">
                                                        <div class="modal-header border-0 pb-0">
                                                            <h5 class="modal-title fw-bold">Arquivar Doc. Interno</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p class="small text-muted mb-3">{{ $docInt->titulo }}</p>
                                                            <div class="mb-3">
                                                                <label class="form-label fw-bold small">Selecione a Pasta de Destino</label>
                                                                <select name="pasta_id" class="form-select" required>
                                                                    <option value="">-- Selecione uma pasta --</option>
                                                                    @foreach($pastas as $pOp)
                                                                        <option value="{{ $pOp->id }}">{{ $pOp->nome }}</option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer border-0 pt-0">
                                                            <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" class="btn btn-primary rounded-pill px-4">Arquivar</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @empty
                                    @endforelse

                                    @if(count($pendentesEntrada) === 0 && count($pendentesInternos) === 0)
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                <i class="fas fa-check-circle fs-3 text-success d-block mb-2"></i>
                                                Não há documentos pendentes de arquivamento no seu departamento.
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            {{-- B. SEÇÃO PRINCIPAL DE CONTEÚDO: TABELA MODERNA vs GRID AMPLO --}}
            @if($viewMode === 'table')
                
                {{-- TABELA MODERNA (TEXTO COMPLETO SEM CORTE / TRUNCAMENTO) --}}
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-header bg-white border-0 py-3 px-4 rounded-top-4 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="fas fa-list text-primary"></i>
                            <span>Acervo de Documentos Arquivados</span>
                        </h6>
                        <span class="small text-muted fw-semibold">
                            Exibindo {{ $documentosEntrada->total() + $documentosInternos->total() }} registros
                        </span>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                                <thead class="table-light small text-uppercase fw-bold text-secondary">
                                    <tr>
                                        <th class="ps-4" style="min-width: 140px;">Referência / Nº</th>
                                        <th style="min-width: 280px;">Assunto / Título Completo</th>
                                        <th style="min-width: 130px;">Espécie</th>
                                        <th style="min-width: 160px;">Origem / Destino</th>
                                        <th style="min-width: 130px;">Data Arquivado</th>
                                        <th style="min-width: 100px;">Anexo</th>
                                        <th class="text-end pe-4" style="min-width: 120px;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    
                                    {{-- Listagem de Documentos de Entrada --}}
                                    @foreach($documentosEntrada as $docEnt)
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center gap-1">
                                                    <span class="badge bg-warning-subtle text-dark border" style="font-size: 0.7rem;">Entrada</span>
                                                    <span class="fw-bold text-dark">#{{ $docEnt->numero_sequencial }}/{{ $docEnt->ano_referencia }}</span>
                                                </div>
                                                @if($docEnt->classificacao_ref_numero)
                                                    <small class="text-muted d-block mt-1">Ref: {{ $docEnt->classificacao_ref_numero }}</small>
                                                @endif
                                            </td>

                                            {{-- Assunto sem truncamento (sem limit) --}}
                                            <td class="fw-semibold text-dark text-wrap" style="max-width: 400px; word-break: break-word;">
                                                {{ $docEnt->assunto }}
                                                @if($docEnt->pasta)
                                                    <span class="d-block text-muted small fw-normal mt-1"><i class="fas fa-folder text-warning me-1"></i> {{ $docEnt->pasta->nome }}</span>
                                                @endif
                                            </td>

                                            <td>
                                                <span class="badge bg-light text-dark border fw-semibold px-2 py-1">
                                                    {{ $docEnt->classificacao_especie ?? 'Geral' }}
                                                </span>
                                            </td>

                                            <td>
                                                <span class="text-dark small d-block">{{ $docEnt->procedencia ?? 'Externa' }}</span>
                                                <small class="text-muted">{{ optional($docEnt->departamento)->nome }}</small>
                                            </td>

                                            <td>
                                                <span class="small text-muted">{{ $docEnt->arquivado_em ? $docEnt->arquivado_em->format('d/m/Y H:i') : ($docEnt->created_at ? $docEnt->created_at->format('d/m/Y') : '-') }}</span>
                                            </td>

                                            <td>
                                                @if($docEnt->anexos && $docEnt->anexos->count() > 0)
                                                    <a href="{{ route('documentos-entradas.anexos.download', [$docEnt->id, $docEnt->anexos->first()->id]) }}" class="btn btn-sm btn-outline-danger border-0 rounded-circle" title="Baixar PDF Principal">
                                                        <i class="fas fa-file-pdf fs-5"></i>
                                                    </a>
                                                @else
                                                    <span class="text-muted small">-</span>
                                                @endif
                                            </td>

                                            <td class="text-end pe-4">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('documentos-entradas.show', $docEnt->id) }}" class="btn btn-light text-primary border" title="Ver Detalhes">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    @if($docEnt->arquivo_caminho)
                                                        <a href="{{ route('documentos-entradas.arquivo.download', $docEnt->id) }}" class="btn btn-light text-success border" title="Download">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach

                                    {{-- Listagem de Documentos Internos --}}
                                    @foreach($documentosInternos as $docInt)
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center gap-1">
                                                    <span class="badge bg-info-subtle text-dark border" style="font-size: 0.7rem;">Interno</span>
                                                    <span class="fw-bold text-dark">{{ $docInt->numero_referencia ?? 'S/Ref' }}</span>
                                                </div>
                                            </td>

                                            {{-- Título sem truncamento (texto completo) --}}
                                            <td class="fw-semibold text-dark text-wrap" style="max-width: 400px; word-break: break-word;">
                                                {{ $docInt->titulo }}
                                                @if($docInt->pasta)
                                                    <span class="d-block text-muted small fw-normal mt-1"><i class="fas fa-folder text-warning me-1"></i> {{ $docInt->pasta->nome }}</span>
                                                @endif
                                            </td>

                                            <td>
                                                <span class="badge bg-light text-dark border fw-semibold px-2 py-1">
                                                    {{ optional($docInt->documentoEspecie)->nome ?? 'Interno' }}
                                                </span>
                                            </td>

                                            <td>
                                                <span class="text-dark small d-block">{{ optional($docInt->departamento)->nome }}</span>
                                                <small class="text-muted">Autor: {{ optional($docInt->autor)->name }}</small>
                                            </td>

                                            <td>
                                                <span class="small text-muted">{{ $docInt->arquivado_em ? $docInt->arquivado_em->format('d/m/Y H:i') : ($docInt->created_at ? $docInt->created_at->format('d/m/Y') : '-') }}</span>
                                            </td>

                                            <td>
                                                @if($docInt->versoes && $docInt->versoes->count() > 0)
                                                    <a href="{{ route('edms.stream-version', $docInt->versoes->first()->id) }}" target="_blank" class="btn btn-sm btn-outline-danger border-0 rounded-circle" title="Visualizar PDF">
                                                        <i class="fas fa-file-pdf fs-5"></i>
                                                    </a>
                                                @else
                                                    <span class="text-muted small">-</span>
                                                @endif
                                            </td>

                                            <td class="text-end pe-4">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('documentos-internos.show', $docInt->id) }}" class="btn btn-light text-primary border" title="Ver Detalhes">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="{{ route('documentos-internos.pdf', $docInt->id) }}" class="btn btn-light text-success border" title="Baixar PDF">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach

                                    @if(count($documentosEntrada) === 0 && count($documentosInternos) === 0)
                                        <tr>
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <i class="fas fa-folder-open fs-1 text-muted d-block mb-3 opacity-50"></i>
                                                Nenhum documento arquivado encontrado nesta pasta ou seleção de filtro.
                                            </td>
                                        </tr>
                                    @endif

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            @else
                
                {{-- GRID DE PASTAS LARGAS (CARDS EXPANDIDOS SEM NOME TRUNCADO) --}}
                <div class="row g-3 mb-4">
                    @forelse($pastas as $pastaItem)
                        <div class="col-md-6 col-xl-4">
                            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 hover-shadow transition">
                                <div class="d-flex align-items-start justify-content-between mb-3">
                                    <div class="p-3 bg-warning-subtle text-warning rounded-3 me-3">
                                        <i class="fas fa-folder fs-3"></i>
                                    </div>
                                    <span class="badge bg-light text-dark border rounded-pill px-3 py-1">
                                        {{ ($pastaItem->documentos_count ?? 0) + ($pastaItem->documentos_internos_count ?? 0) }} ficheiros
                                    </span>
                                </div>
                                <h6 class="fw-bold text-dark mb-1 text-wrap" style="word-break: break-word;">
                                    <a href="{{ route('edms.index', ['folder' => $pastaItem->id, 'tree' => 'dossies', 'view_mode' => 'grid']) }}" class="text-decoration-none text-dark">
                                        {{ $pastaItem->nome }}
                                    </a>
                                </h6>
                                @if($pastaItem->descricao)
                                    <p class="small text-muted mb-3 text-wrap" style="word-break: break-word;">{{ $pastaItem->descricao }}</p>
                                @endif
                                <div class="mt-auto pt-2 border-top d-flex justify-content-between align-items-center small text-muted">
                                    <span><i class="fas fa-building me-1"></i> {{ optional($pastaItem->departamento)->nome ?? 'Gabinete' }}</span>
                                    <a href="{{ route('edms.index', ['folder' => $pastaItem->id, 'tree' => 'dossies']) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">Abrir</a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="alert alert-light border shadow-sm rounded-4 p-4 text-center text-muted">
                                <i class="fas fa-folder-open fs-2 mb-2 d-block"></i>
                                Nenhuma pasta manual criada neste diretório.
                            </div>
                        </div>
                    @endforelse
                </div>

            @endif

            {{-- Paginação --}}
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="small text-muted">
                    Mostrando resultados paginados
                </div>
                <div>
                    @if(method_exists($documentosEntrada, 'links'))
                        {{ $documentosEntrada->links() }}
                    @endif
                    @if(method_exists($documentosInternos, 'links'))
                        {{ $documentosInternos->links() }}
                    @endif
                </div>
            </div>

        </div>

    </div>

</div>

{{-- MODAIS DO SISTEMA --}}

{{-- 1. Modal Criar Pasta --}}
<div class="modal fade" id="createFolderModal" tabindex="-1" aria-labelledby="createFolderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('edms.create-folder') }}" method="POST">
            @csrf
            @if(isset($currentFolder))
                <input type="hidden" name="parent_id" value="{{ $currentFolder->id }}">
            @endif
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="createFolderModalLabel">Nova Pasta / Dossiê</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Nome da Pasta</label>
                        <input type="text" name="nome" class="form-control rounded-3" required placeholder="Ex: Contratos 2026, Relatórios Técnicos...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Descrição / Observações</label>
                        <textarea name="descricao" class="form-control rounded-3" rows="3" placeholder="Finalidade ou conteúdo desta pasta..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">Criar Pasta</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- 2. Modal Partilhar Pasta --}}
@if(isset($currentFolder))
    <div class="modal fade" id="shareFolderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('edms.share-folder', $currentFolder->id) }}" method="POST">
                @csrf
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold">Partilhar Pasta: {{ $currentFolder->nome }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">Selecione os departamentos que terão acesso de leitura a esta pasta:</p>
                        <div class="mb-3" style="max-height: 250px; overflow-y: auto;">
                            @foreach($departamentos as $dep)
                                <div class="form-check py-1">
                                    <input class="form-check-input" type="checkbox" name="departamento_ids[]" value="{{ $dep->id }}" id="depShare{{ $dep->id }}">
                                    <label class="form-check-label small" for="depShare{{ $dep->id }}">
                                        {{ $dep->nome }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning rounded-pill px-4">Salvar Partilha</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endif

@endsection
