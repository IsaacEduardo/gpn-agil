@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    {{-- Header Section --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold text-primary mb-1 d-flex align-items-center">
                <span class="p-2 bg-primary-subtle text-primary rounded-3 me-3 d-inline-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                    <i class="fas fa-archive fs-5"></i>
                </span>
                <span>EDMS - Gestão de Arquivos Digitais</span>
            </h4>
            <p class="text-muted small mb-0 ms-0 ms-md-5">
                @if(isset($currentFolder))
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0 bg-transparent">
                            <li class="breadcrumb-item">
                                <a href="{{ route('edms.index') }}" class="text-primary text-decoration-none">
                                    <i class="fas fa-home me-1"></i> Raiz
                                </a>
                            </li>
                            @foreach($breadcrumbs as $crumb)
                                <li class="breadcrumb-item">
                                    <a href="{{ route('edms.index', ['folder' => $crumb->id]) }}" class="text-primary text-decoration-none">
                                        {{ $crumb->nome }}
                                    </a>
                                </li>
                            @endforeach
                            <li class="breadcrumb-item active fw-semibold text-dark" aria-current="page">{{ $currentFolder->nome }}</li>
                        </ol>
                    </nav>
                @else
                    Gerencie, localize e organize de forma cronológica a documentação oficial da província
                @endif
            </p>
        </div>
        <div class="d-flex gap-2">
            @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('Admin'))
                <a href="{{ route('edms.retention') }}" class="btn btn-outline-danger px-3 rounded-pill fw-semibold shadow-sm">
                    <i class="fas fa-calendar-alt me-1"></i> Tabela de Temporalidade
                </a>
            @endif
            @if(isset($currentFolder) && ($currentFolder->created_by === auth()->id() || auth()->user()->hasRole('admin') || auth()->user()->hasRole('Admin')))
                <button class="btn btn-outline-warning px-3 rounded-pill fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#shareFolderModal">
                    <i class="fas fa-share-alt me-1"></i> Partilhar Pasta
                </button>
                <button class="btn btn-outline-secondary px-3 rounded-pill fw-semibold shadow-sm" id="btnFolderHistory" data-bs-toggle="modal" data-bs-target="#folderHistoryModal" data-folder-id="{{ $currentFolder->id }}">
                    <i class="fas fa-history me-1"></i> Histórico
                </button>
            @endif
            <button class="btn btn-outline-primary px-3 rounded-pill fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#createFolderModal">
                <i class="fas fa-folder-plus me-1"></i> Nova Pasta
            </button>
        </div>
    </div>

    {{-- Alert Success / Errors --}}
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4 alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="fas fa-check-circle me-3 fs-4 text-success"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Advanced Search & Filters Card --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h6 class="fw-bold text-dark mb-3"><i class="fas fa-filter text-primary me-2"></i>Pesquisa Avançada e Filtros</h6>
            <form action="{{ route('edms.index', ['folder' => $currentFolder->id ?? null]) }}" method="GET" class="row g-3 align-items-end">
                @if(isset($currentFolder))
                    <input type="hidden" name="folder" value="{{ $currentFolder->id }}">
                @endif
                <div class="col-md-4">
                    <label class="form-label small text-muted fw-semibold">Termo de Busca</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control bg-light border-0" placeholder="Assunto, título, ref, conteúdo..." value="{{ request('search') }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted fw-semibold">Tipo</label>
                    <select name="type" class="form-select bg-light border-0">
                        <option value="">Todos</option>
                        <option value="interno" {{ request('type') == 'interno' ? 'selected' : '' }}>Interno</option>
                        <option value="entrada" {{ request('type') == 'entrada' ? 'selected' : '' }}>Entrada</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted fw-semibold">Período de Arquivamento</label>
                    <div class="input-group">
                        <input type="date" name="date_start" class="form-control bg-light border-0" value="{{ request('date_start') }}" title="Data Início">
                        <span class="input-group-text bg-transparent border-0 text-muted small">até</span>
                        <input type="date" name="date_end" class="form-control bg-light border-0" value="{{ request('date_end') }}" title="Data Fim">
                    </div>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary rounded-3 w-100 fw-semibold">
                        <i class="fas fa-search me-1"></i> Filtrar
                    </button>
                    @if(request('search') || request('type') || request('date_start') || request('date_end'))
                        <a href="{{ route('edms.index', ['folder' => $currentFolder->id ?? null]) }}" class="btn btn-light rounded-3 text-muted" title="Limpar Filtros">
                            <i class="fas fa-times"></i>
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Main Contents --}}
    @if(!isset($currentFolder) && !request('search') && !request('type') && !request('date_start') && !request('date_end'))
        {{-- ROOT VIEW WITH TABS: ARCHIVE VS PENDING INBOX --}}
        <div class="card border-0 bg-transparent">
            <div class="card-header border-0 bg-transparent p-0 mb-3">
                <ul class="nav nav-pills gap-2" id="edmsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active px-4 py-2 rounded-pill fw-semibold d-flex align-items-center shadow-sm" id="folders-tab" data-bs-toggle="tab" data-bs-target="#folders-content" type="button" role="tab" aria-controls="folders-content" aria-selected="true">
                            <i class="fas fa-folder-open me-2"></i>Pastas do Departamento
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        @php
                            $totalPendentes = $pendentesInternos->count() + $pendentesEntrada->count();
                        @endphp
                        <button class="nav-link px-4 py-2 rounded-pill fw-semibold d-flex align-items-center position-relative shadow-sm" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-content" type="button" role="tab" aria-controls="pending-content" aria-selected="false">
                            <i class="fas fa-inbox me-2"></i>Pendentes de Arquivamento
                            @if($totalPendentes > 0)
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-white">
                                    {{ $totalPendentes }}
                                </span>
                            @endif
                        </button>
                    </li>
                </ul>
            </div>

            <div class="tab-content" id="edmsTabsContent">
                {{-- TAB: FOLDERS --}}
                <div class="tab-pane fade show active" id="folders-content" role="tabpanel" aria-labelledby="folders-tab">
                    <div class="row g-3">
                        @forelse($pastas as $pasta)
                            <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                                <a href="{{ route('edms.index', ['folder' => $pasta->id]) }}" class="text-decoration-none text-dark">
                                    <div class="card border-0 shadow-sm rounded-4 h-100 card-hover transition-all border-start-system border-start {{ $pasta->is_system ? 'border-primary' : 'border-warning' }}">
                                        <div class="card-body p-4 d-flex align-items-center gap-3">
                                            <div class="p-3 rounded-4 {{ $pasta->is_system ? 'bg-primary-subtle text-primary' : 'bg-warning-subtle text-warning' }}">
                                                <i class="fas fa-folder{{ $pasta->is_system ? '' : '-open' }} fs-3"></i>
                                            </div>
                                            <div class="overflow-hidden">
                                                <h6 class="fw-bold mb-1 text-truncate" title="{{ $pasta->nome }}">{{ $pasta->nome }}</h6>
                                                <small class="text-muted d-block">{{ $pasta->children_count + $pasta->documentos_internos_count }} itens no total</small>
                                            </div>
                                            @if($pasta->is_system)
                                                <span class="ms-auto badge bg-primary-subtle text-primary text-uppercase" style="font-size: 0.65rem">Sistema</span>
                                            @endif
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @empty
                            <div class="col-12 text-center py-5">
                                <div class="p-4 bg-white shadow-sm rounded-4 d-inline-block text-muted">
                                    <i class="fas fa-folder-open fs-1 mb-3 opacity-50 text-secondary"></i>
                                    <p class="mb-0 fw-semibold">Nenhuma pasta encontrada.</p>
                                    <p class="small mb-0">Crie uma nova pasta clicando no botão acima.</p>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- TAB: PENDING DOCUMENTS --}}
                <div class="tab-pane fade" id="pending-content" role="tabpanel" aria-labelledby="pending-tab">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-body p-0">
                            @if($totalPendentes == 0)
                                <div class="text-center py-5 text-muted">
                                    <div class="p-3 bg-light rounded-circle d-inline-flex mb-3">
                                        <i class="fas fa-check fs-2 text-success"></i>
                                    </div>
                                    <p class="fw-bold mb-0">Tudo em dia!</p>
                                    <p class="small mb-0">Não há documentos aguardando arquivamento para o seu departamento.</p>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-4">Documento</th>
                                                <th>Tipo</th>
                                                <th>Ref / Sequencial</th>
                                                <th>Data Registro</th>
                                                <th>Autor / Procedência</th>
                                                <th class="text-end pe-4">Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {{-- Pendentes Internos --}}
                                            @foreach($pendentesInternos as $doc)
                                                <tr draggable="true" data-doc-id="{{ $doc->id }}" data-doc-type="interno">
                                                    <td class="ps-4">
                                                        <div class="d-flex align-items-center gap-3">
                                                            <div class="p-2 bg-info-subtle text-info rounded-3">
                                                                <i class="fas fa-file-alt"></i>
                                                            </div>
                                                            <div class="fw-bold text-dark text-truncate" style="max-width: 320px;" title="{{ $doc->titulo }}">
                                                                {{ $doc->titulo }}
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info-subtle text-info text-uppercase">Interno</span>
                                                    </td>
                                                    <td><code>{{ $doc->numero_referencia }}</code></td>
                                                    <td>{{ $doc->created_at->format('d/m/Y') }}</td>
                                                    <td>{{ $doc->autor->name ?? 'Sistema' }}</td>
                                                    <td class="text-end pe-4">
                                                        <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm btn-arquivar"
                                                            data-bs-toggle="modal" data-bs-target="#arquivarPendenteModal"
                                                            data-doc-id="{{ $doc->id }}"
                                                            data-doc-type="interno"
                                                            data-doc-title="{{ $doc->titulo }}">
                                                            <i class="fas fa-archive me-1"></i> Arquivar
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach

                                            {{-- Pendentes Entrada --}}
                                            @foreach($pendentesEntrada as $doc)
                                                <tr draggable="true" data-doc-id="{{ $doc->id }}" data-doc-type="entrada">
                                                    <td class="ps-4">
                                                        <div class="d-flex align-items-center gap-3">
                                                            <div class="p-2 bg-success-subtle text-success rounded-3">
                                                                <i class="fas fa-file-import"></i>
                                                            </div>
                                                            <div class="fw-bold text-dark text-truncate" style="max-width: 320px;" title="{{ $doc->assunto }}">
                                                                {{ $doc->assunto }}
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success-subtle text-success text-uppercase">Entrada</span>
                                                    </td>
                                                    <td><code>{{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}</code></td>
                                                    <td>{{ $doc->created_at->format('d/m/Y') }}</td>
                                                    <td>{{ $doc->procedencia }}</td>
                                                    <td class="text-end pe-4">
                                                        <button class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm btn-arquivar"
                                                            data-bs-toggle="modal" data-bs-target="#arquivarPendenteModal"
                                                            data-doc-id="{{ $doc->id }}"
                                                            data-doc-type="entrada"
                                                            data-doc-title="{{ $doc->assunto }}">
                                                            <i class="fas fa-archive me-1"></i> Arquivar
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- VIEWING A FOLDER OR A SEARCH RESULT --}}
        @if(request('search') || request('type') || request('date_start') || request('date_end'))
            <div class="alert alert-info border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center">
                <i class="fas fa-search me-3 fs-4 text-info"></i>
                <div>
                    Você está a visualizar resultados da <strong>Pesquisa Avançada</strong> no arquivo.
                    <a href="{{ route('edms.index', ['folder' => $currentFolder->id ?? null]) }}" class="alert-link ms-2">Limpar busca e retornar</a>
                </div>
            </div>
        @endif

        @if(isset($currentFolder))
            <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #f8fafd 0%, #f1f5f9 100%); border: 1px solid rgba(0, 123, 255, 0.05) !important;">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="fas fa-cloud-upload-alt text-primary me-2"></i>Upload Direto de Ficheiro</h6>
                    <form action="{{ route('edms.upload-file') }}" method="POST" enctype="multipart/form-data" id="edmsUploadForm">
                        @csrf
                        <input type="hidden" name="pasta_id" value="{{ $currentFolder->id }}">
                        <div id="dropzone" class="border border-2 border-dashed rounded-4 p-4 text-center cursor-pointer position-relative transition-all d-flex flex-column align-items-center justify-content-center" style="border-color: #cbd5e1 !important; background: rgba(255, 255, 255, 0.6); min-height: 150px;">
                            <input type="file" name="file" id="fileInput" class="position-absolute top-0 start-0 w-100 h-100 opacity-0 cursor-pointer" required>
                            <div class="p-3 bg-white rounded-circle shadow-sm text-primary mb-3">
                                <i class="fas fa-cloud-upload-alt fs-3"></i>
                            </div>
                            <h6 class="fw-bold text-secondary mb-1">Arraste e solte o seu arquivo aqui ou clique para navegar</h6>
                            <p class="text-muted small mb-0">PDF, PNG, JPG (Max: 20MB)</p>
                            <div id="fileInfo" class="mt-3 d-none align-items-center gap-2 p-2 bg-white rounded-3 shadow-sm border">
                                <i class="fas fa-file-pdf text-danger fs-5" id="fileIcon"></i>
                                <span id="fileName" class="fw-semibold small text-dark"></span>
                                <button type="button" class="btn-close" id="clearFileBtn" aria-label="Limpar" style="font-size: 0.7rem;"></button>
                            </div>
                        </div>
                        
                        <div class="row mt-3 g-2 align-items-center justify-content-between">
                            <div class="col-md-8">
                                <input type="text" name="titulo" class="form-control rounded-3 border-0 bg-white shadow-sm" placeholder="Título do Ficheiro no Arquivo (Opcional - usa o nome original se vazio)">
                            </div>
                            <div class="col-md-4 text-end">
                                <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm w-100">
                                    <i class="fas fa-check me-1"></i> Carregar e Arquivar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Folders Content Navigator --}}
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-transparent border-0 p-4 pb-0 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold text-dark mb-0">Conteúdo do Arquivo</h5>
                    <span class="badge bg-secondary-subtle text-secondary rounded-pill">
                        {{ $pastas->count() + $documentosInternos->count() + $documentosEntrada->count() }} itens
                    </span>
                </div>
                {{-- View Mode Switcher (Grid vs Timeline) --}}
                <ul class="nav nav-pills bg-light p-1 rounded-pill" id="viewModeTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active px-3 py-1 rounded-pill small fw-semibold" id="grid-view-tab" data-bs-toggle="tab" data-bs-target="#grid-view" type="button" role="tab" aria-controls="grid-view" aria-selected="true">
                            <i class="fas fa-th-large me-1"></i> Grade
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link px-3 py-1 rounded-pill small fw-semibold" id="timeline-view-tab" data-bs-toggle="tab" data-bs-target="#timeline-view" type="button" role="tab" aria-controls="timeline-view" aria-selected="false">
                            <i class="fas fa-history me-1"></i> Linha do Tempo
                        </button>
                    </li>
                </ul>
            </div>

            <div class="card-body p-4">
                <div class="tab-content" id="viewModeTabsContent">
                    {{-- VIEW: GRID VIEW --}}
                    <div class="tab-pane fade show active" id="grid-view" role="tabpanel" aria-labelledby="grid-view-tab">
                        
                        {{-- Subpastas --}}
                        @if($pastas->isNotEmpty())
                            <h6 class="text-muted fw-bold small text-uppercase mb-3"><i class="fas fa-folder text-warning me-2"></i>Subpastas</h6>
                            <div class="row g-3 mb-4">
                                @foreach($pastas as $pasta)
                                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                                        <a href="{{ route('edms.index', ['folder' => $pasta->id]) }}" class="text-decoration-none text-dark">
                                            <div class="card border-0 shadow-sm rounded-4 bg-light card-hover transition-all">
                                                <div class="card-body p-3 d-flex align-items-center gap-3">
                                                    <i class="fas fa-folder{{ $pasta->is_system ? '' : '-open' }} text-warning fs-3"></i>
                                                    <div class="overflow-hidden">
                                                        <h6 class="fw-bold mb-1 text-truncate" title="{{ $pasta->nome }}">{{ $pasta->nome }}</h6>
                                                        <small class="text-muted d-block">{{ $pasta->children_count + $pasta->documentos_internos_count }} itens</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Arquivos / Documentos --}}
                        @if($documentosInternos->isNotEmpty() || $documentosEntrada->isNotEmpty())
                            <h6 class="text-muted fw-bold small text-uppercase mb-3"><i class="fas fa-file-alt text-primary me-2"></i>Documentos Arquivados</h6>
                            <div class="row g-3">
                                {{-- Documentos Internos --}}
                                @foreach($documentosInternos as $doc)
                                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                                        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white card-hover border-top border-info border-4">
                                            <div class="card-body p-4 d-flex flex-column h-100">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <span class="badge bg-info-subtle text-info rounded-pill">v{{ $doc->versao_atual }}</span>
                                                    <small class="text-muted" style="font-size: 0.75rem">
                                                        <i class="fas fa-calendar-alt me-1"></i>{{ $doc->arquivado_em ? $doc->arquivado_em->format('d/m/Y') : $doc->created_at->format('d/m/Y') }}
                                                    </small>
                                                </div>
                                                <h6 class="fw-bold text-dark text-truncate mb-1" title="{{ $doc->titulo }}">{{ $doc->titulo }}</h6>
                                                <small class="text-muted d-block mb-2"><code>{{ $doc->numero_referencia }}</code></small>
                                                
                                                {{-- Temporalidade --}}
                                                @php
                                                    $schedule = $doc->documentoEspecie ? $doc->documentoEspecie->retentionSchedule : null;
                                                    $venceu = false;
                                                    $anosRestantes = null;
                                                    $mensagemTemporalidade = '';
                                                    if ($schedule) {
                                                        $dataCorte = $doc->arquivado_em ? \Carbon\Carbon::parse($doc->arquivado_em) : $doc->created_at;
                                                        $dataVencimento = $dataCorte->copy()->addYears($schedule->temporalidade_anos);
                                                        $venceu = now()->greaterThan($dataVencimento);
                                                        if (!$venceu) {
                                                            $anosRestantes = now()->diffInYears($dataVencimento);
                                                            $mensagemTemporalidade = "Guarda: " . ($anosRestantes > 0 ? "{$anosRestantes}a restantes" : "fim este ano") . " (" . ucfirst($schedule->acao_final) . ")";
                                                        } else {
                                                            $mensagemTemporalidade = "Vencido: " . ucfirst($schedule->acao_final);
                                                        }
                                                    }
                                                @endphp
                                                @if($schedule)
                                                    <div class="mb-3 py-1 px-2 rounded-3 text-start small d-inline-flex align-items-center gap-1 {{ $venceu ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning' }}" style="font-size: 0.7rem; width: fit-content;">
                                                        <i class="fas {{ $venceu ? 'fa-exclamation-triangle' : 'fa-clock' }}"></i>
                                                        <span>{{ $mensagemTemporalidade }}</span>
                                                    </div>
                                                @endif

                                                <p class="text-muted small text-truncate-3 mb-4" style="font-size: 0.8rem">
                                                    {!! strip_tags($doc->conteudo_final) !!}
                                                </p>
                                                @if(request('search') && $doc->pasta)
                                                    <div class="bg-light p-2 rounded-3 mb-3 text-truncate small">
                                                        <i class="fas fa-folder text-warning me-1"></i>
                                                        <span class="text-muted">Pasta: </span>
                                                        <strong>{{ $doc->pasta->nome }}</strong>
                                                    </div>
                                                @endif
                                                <div class="mt-auto pt-2 d-flex gap-2">
                                                    @php
                                                        $latestVersion = $doc->versoes->sortByDesc('created_at')->first();
                                                        $mimeType = $latestVersion ? strtolower($latestVersion->mime_type ?? '') : '';
                                                        $isPreviewable = $latestVersion && (str_contains($mimeType, 'pdf') || str_contains($mimeType, 'image') || str_contains($mimeType, 'png') || str_contains($mimeType, 'jpeg') || str_contains($mimeType, 'jpg'));
                                                    @endphp
                                                    
                                                    @if($isPreviewable)
                                                        <button class="btn btn-primary btn-sm rounded-pill fw-semibold flex-fill" 
                                                            data-bs-toggle="modal" data-bs-target="#edmsPreviewModal"
                                                            data-preview-url="{{ route('edms.stream-version', $latestVersion->id) }}"
                                                            data-preview-title="{{ $doc->titulo }}"
                                                            data-preview-type="{{ str_contains($mimeType, 'image') ? 'image' : 'pdf' }}">
                                                            <i class="fas fa-eye me-1"></i> Ver
                                                        </button>
                                                    @endif
                                                    <a href="{{ route('documentos-internos.show', $doc->id) }}" class="btn btn-outline-info btn-sm rounded-pill fw-semibold flex-fill">
                                                        <i class="fas fa-info-circle me-1"></i> Detalhes
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                {{-- Documentos Entrada --}}
                                @foreach($documentosEntrada as $doc)
                                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                                        <div class="card border-0 shadow-sm rounded-4 h-100 bg-white card-hover border-top border-success border-4">
                                            <div class="card-body p-4 d-flex flex-column h-100">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <span class="badge bg-success-subtle text-success rounded-pill">Entrada</span>
                                                    <small class="text-muted" style="font-size: 0.75rem">
                                                        <i class="fas fa-calendar-alt me-1"></i>{{ $doc->arquivado_em ? $doc->arquivado_em->format('d/m/Y') : $doc->created_at->format('d/m/Y') }}
                                                    </small>
                                                </div>
                                                <h6 class="fw-bold text-dark text-truncate mb-1" title="{{ $doc->assunto }}">{{ $doc->assunto }}</h6>
                                                <small class="text-muted d-block mb-3"><code>{{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}</code></small>
                                                <p class="text-muted small mb-3" style="font-size: 0.8rem">
                                                    <strong>Procedência:</strong> {{ $doc->procedencia }} <br>
                                                    <span class="text-truncate-2 d-block"><strong>Obs:</strong> {{ $doc->observacoes ?: 'Nenhuma observação.' }}</span>
                                                </p>

                                                @if($doc->anexos->isNotEmpty())
                                                    <div class="mb-3">
                                                        <small class="text-muted fw-bold d-block mb-1" style="font-size: 0.75rem;">Anexos:</small>
                                                        <div class="d-flex flex-wrap gap-1">
                                                            @foreach($doc->anexos as $anexo)
                                                                @php
                                                                    $anMime = strtolower($anexo->mime_type ?? '');
                                                                    $anPreviewable = str_contains($anMime, 'pdf') || str_contains($anMime, 'image') || str_contains($anMime, 'png') || str_contains($anMime, 'jpeg') || str_contains($anMime, 'jpg');
                                                                @endphp
                                                                @if($anPreviewable)
                                                                    <button type="button" class="btn btn-xs btn-light text-primary border rounded-pill py-0 px-2 fw-semibold" style="font-size: 0.7rem;"
                                                                        data-bs-toggle="modal" data-bs-target="#edmsPreviewModal"
                                                                        data-preview-url="{{ route('edms.stream-attachment', $anexo->id) }}"
                                                                        data-preview-title="{{ $anexo->nome_original }}"
                                                                        data-preview-type="{{ str_contains($anMime, 'image') ? 'image' : 'pdf' }}">
                                                                        <i class="fas fa-eye me-1"></i> {{ Str::limit($anexo->nome_original, 15) }}
                                                                    </button>
                                                                @else
                                                                    <a href="{{ route('documentos-entradas.anexos.download', [$doc->id, $anexo->id]) }}" class="btn btn-xs btn-light border rounded-pill py-0 px-2 text-muted text-decoration-none" style="font-size: 0.7rem;">
                                                                        <i class="fas fa-download me-1"></i> {{ Str::limit($anexo->nome_original, 15) }}
                                                                    </a>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif

                                                @if(request('search') && $doc->pasta)
                                                    <div class="bg-light p-2 rounded-3 mb-3 text-truncate small">
                                                        <i class="fas fa-folder text-warning me-1"></i>
                                                        <span class="text-muted">Pasta: </span>
                                                        <strong>{{ $doc->pasta->nome }}</strong>
                                                    </div>
                                                @endif
                                                <div class="mt-auto pt-2 d-grid">
                                                    <a href="{{ route('documentos-entradas.show', $doc->id) }}" class="btn btn-outline-success btn-sm rounded-pill fw-semibold">
                                                        <i class="fas fa-eye me-1"></i> Ver Protocolo
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        {{-- Pasta Vazia --}}
                        @if($pastas->isEmpty() && $documentosInternos->isEmpty() && $documentosEntrada->isEmpty())
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-folder-open fs-1 mb-3 opacity-50"></i>
                                <p class="mb-0 fw-semibold">Esta pasta está vazia.</p>
                            </div>
                        @endif

                    </div>

                    {{-- VIEW: TIMELINE VIEW --}}
                    <div class="tab-pane fade" id="timeline-view" role="tabpanel" aria-labelledby="timeline-view-tab">
                        @php
                            $todosDocumentos = collect();
                            foreach($documentosInternos as $doc) {
                                $doc->tipo_edms = 'interno';
                                $todosDocumentos->push($doc);
                            }
                            foreach($documentosEntrada as $doc) {
                                $doc->tipo_edms = 'entrada';
                                $todosDocumentos->push($doc);
                            }
                            $todosDocumentos = $todosDocumentos->sortByDesc(function($doc) {
                                return $doc->arquivado_em ?? $doc->created_at;
                            });
                            
                            $documentosAgrupados = $todosDocumentos->groupBy(function($doc) {
                                $date = $doc->arquivado_em ?? $doc->created_at;
                                return $date ? $date->translatedFormat('F \\d\\e Y') : 'Sem Data';
                            });
                        @endphp

                        @if($todosDocumentos->isEmpty())
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-calendar-alt fs-1 mb-3 opacity-50"></i>
                                <p class="mb-0 fw-semibold">Não há documentos arquivados nesta pasta para exibir na linha do tempo.</p>
                            </div>
                        @else
                            <div class="timeline py-3">
                                @foreach($documentosAgrupados as $mesAno => $docs)
                                    <div class="timeline-group-header mb-4 mt-2">
                                        <span class="badge bg-primary px-3 py-2 rounded-pill fw-bold text-uppercase fs-7 shadow-sm">
                                            <i class="fas fa-calendar-day me-2"></i>{{ $mesAno }}
                                        </span>
                                    </div>

                                    <div class="position-relative border-start border-2 border-primary-subtle ms-3 ps-4 pb-4">
                                        @foreach($docs as $doc)
                                            <div class="timeline-item mb-4 position-relative">
                                                {{-- Bullet --}}
                                                <span class="position-absolute translate-middle-x bg-white rounded-circle border border-3 border-primary shadow-sm" style="left: -33px; top: 12px; width: 16px; height: 16px;"></span>
                                                
                                                <div class="card border-0 shadow-sm rounded-4 card-hover transition-all">
                                                    <div class="card-body p-4">
                                                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                                                            <div class="d-flex align-items-center gap-3">
                                                                @if($doc->tipo_edms === 'interno')
                                                                    <div class="p-3 bg-info-subtle text-info rounded-4">
                                                                        <i class="fas fa-file-alt fs-4"></i>
                                                                    </div>
                                                                    <div>
                                                                        <h6 class="fw-bold mb-1 text-dark">{{ $doc->titulo }}</h6>
                                                                        <small class="text-muted d-block">
                                                                            <span class="badge bg-info-subtle text-info text-uppercase me-2" style="font-size: 0.65rem;">Interno</span>
                                                                            <code>{{ $doc->numero_referencia }}</code>
                                                                        </small>
                                                                    </div>
                                                                @else
                                                                    <div class="p-3 bg-success-subtle text-success rounded-4">
                                                                        <i class="fas fa-file-import fs-4"></i>
                                                                    </div>
                                                                    <div>
                                                                        <h6 class="fw-bold mb-1 text-dark">{{ $doc->assunto }}</h6>
                                                                        <small class="text-muted d-block">
                                                                            <span class="badge bg-success-subtle text-success text-uppercase me-2" style="font-size: 0.65rem;">Entrada</span>
                                                                            <code>{{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}</code>
                                                                        </small>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                            <div class="d-flex align-items-center gap-3 ms-0 ms-sm-auto text-sm-end">
                                                                <div>
                                                                    <small class="text-muted d-block">Arquivado em</small>
                                                                    <span class="fw-semibold text-dark">{{ $doc->arquivado_em ? $doc->arquivado_em->format('d/m/Y H:i') : $doc->created_at->format('d/m/Y H:i') }}</span>
                                                                </div>
                                                                <div class="d-flex gap-1">
                                                                    @if($doc->tipo_edms === 'interno')
                                                                        @php
                                                                            $latestVersion = $doc->versoes->sortByDesc('created_at')->first();
                                                                            $mimeType = $latestVersion ? strtolower($latestVersion->mime_type ?? '') : '';
                                                                            $isPreviewable = $latestVersion && (str_contains($mimeType, 'pdf') || str_contains($mimeType, 'image') || str_contains($mimeType, 'png') || str_contains($mimeType, 'jpeg') || str_contains($mimeType, 'jpg'));
                                                                        @endphp
                                                                        @if($isPreviewable)
                                                                            <button type="button" class="btn btn-sm btn-light rounded-circle p-2 shadow-sm text-primary" style="width: 38px; height: 38px;"
                                                                                data-bs-toggle="modal" data-bs-target="#edmsPreviewModal"
                                                                                data-preview-url="{{ route('edms.stream-version', $latestVersion->id) }}"
                                                                                data-preview-title="{{ $doc->titulo }}"
                                                                                data-preview-type="{{ str_contains($mimeType, 'image') ? 'image' : 'pdf' }}"
                                                                                title="Pré-visualizar">
                                                                                <i class="fas fa-eye"></i>
                                                                            </button>
                                                                        @endif
                                                                    @elseif($doc->tipo_edms === 'entrada' && $doc->anexos->isNotEmpty())
                                                                        @php
                                                                            $firstAnexo = $doc->anexos->first();
                                                                            $anMime = strtolower($firstAnexo->mime_type ?? '');
                                                                            $anPreviewable = str_contains($anMime, 'pdf') || str_contains($anMime, 'image') || str_contains($anMime, 'png') || str_contains($anMime, 'jpeg') || str_contains($anMime, 'jpg');
                                                                        @endphp
                                                                        @if($anPreviewable)
                                                                            <button type="button" class="btn btn-sm btn-light rounded-circle p-2 shadow-sm text-primary" style="width: 38px; height: 38px;"
                                                                                data-bs-toggle="modal" data-bs-target="#edmsPreviewModal"
                                                                                data-preview-url="{{ route('edms.stream-attachment', $firstAnexo->id) }}"
                                                                                data-preview-title="{{ $firstAnexo->nome_original }}"
                                                                                data-preview-type="{{ str_contains($anMime, 'image') ? 'image' : 'pdf' }}"
                                                                                title="Pré-visualizar Primeiro Anexo">
                                                                                <i class="fas fa-eye"></i>
                                                                            </button>
                                                                        @endif
                                                                    @endif
                                                                    <a href="{{ $doc->tipo_edms === 'interno' ? route('documentos-internos.show', $doc->id) : route('documentos-entradas.show', $doc->id) }}" class="btn btn-light rounded-circle p-2 shadow-sm text-secondary" title="Ver Detalhes" style="width: 38px; height: 38px;">
                                                                        <i class="fas fa-info-circle"></i>
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- Arquivamento por arrastar-e-soltar dos pendentes (tipo lido de cada linha) --}}
<x-archive-dropzone />

{{-- Modals --}}

{{-- Create Folder Modal --}}
<div class="modal fade" id="createFolderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('edms.create-folder') }}" method="POST">
            @csrf
            <input type="hidden" name="parent_id" value="{{ $currentFolder->id ?? '' }}">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 bg-primary text-white p-4 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-folder-plus me-2"></i>Criar Nova Pasta</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">Nome da Pasta</label>
                        <input type="text" name="nome" class="form-control bg-light border-0 py-2" required placeholder="Ex: Despachos Gabinete 2026">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small text-muted fw-semibold">Descrição (Opcional)</label>
                        <textarea name="descricao" class="form-control bg-light border-0 py-2" rows="3" placeholder="Insira informações sobre o conteúdo desta pasta..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">Criar Pasta</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Archive Pending Document Modal (Single Dynamic Modal) --}}
<div class="modal fade" id="arquivarPendenteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <form action="" method="POST">
                @csrf
                <input type="hidden" name="tipo" id="modal-doc-type" value="interno">
                <div class="modal-header border-0 bg-primary text-white p-4 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-archive me-2"></i>Arquivar Documento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 rounded-4 d-flex align-items-center mb-3">
                        <i class="fas fa-file-signature me-3 fs-5"></i>
                        <div>
                            <span class="small text-muted d-block">Documento selecionado:</span>
                            <strong id="modal-doc-title-text" class="text-dark"></strong>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-semibold">Selecione a Pasta de Destino</label>
                        <select name="pasta_id" id="modal-pasta-id" class="form-select bg-light border-0 py-2" required>
                            <option value="auto" selected>✨ Arquivamento Automático (Organização Cronológica)</option>
                            <option value="">-- Ou selecione uma pasta manualmente --</option>
                            @inject('pastaService', 'App\Services\PastaService')
                            @foreach ($pastaService->getFolderTreeOptions(auth()->user()) as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="text-muted small mb-0">
                        <i class="fas fa-info-circle me-1"></i> O documento será categorizado e arquivado eletronicamente, garantindo a sua rastreabilidade cronológica.
                    </p>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm">Arquivar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Premium Preview Modal --}}
<div class="modal fade" id="edmsPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-height: 90vh;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 bg-dark text-white p-4">
                <h5 class="modal-title fw-bold" id="previewModalTitle"><i class="fas fa-eye me-2"></i>Visualizador de Documento</h5>
                <div class="d-flex align-items-center gap-2">
                    <a href="" id="previewDownloadBtn" class="btn btn-outline-light btn-sm rounded-pill px-3 me-2" download>
                        <i class="fas fa-download me-1"></i> Baixar Original
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
            </div>
            <div class="modal-body p-0 bg-secondary position-relative" style="height: 70vh;">
                <!-- Elegant Spinner Loader -->
                <div id="previewLoader" class="position-absolute top-50 start-50 translate-middle d-flex flex-column align-items-center text-white">
                    <div class="spinner-border text-light mb-2" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">A carregar...</span>
                    </div>
                    <span class="fw-semibold">A decriptografar e carregar documento...</span>
                </div>
                <!-- Preview Frame -->
                <iframe id="previewIframe" src="" class="w-100 h-100 d-none" style="border: none;"></iframe>
                <!-- Fallback Preview Image -->
                <div id="previewImageContainer" class="w-100 h-100 d-none justify-content-center align-items-center bg-dark p-3">
                    <img id="previewImage" src="" class="img-fluid rounded shadow-sm" style="max-height: 100%; object-fit: contain;">
                </div>
            </div>
        </div>
    </div>
</div>

@if(isset($currentFolder))
    @php
        $sharedDepts = [];
        $sharedMeta = $currentFolder->metadata()->where('key', 'shared_departments')->first();
        if ($sharedMeta) {
            $sharedDepts = json_decode($sharedMeta->value, true) ?: [];
        }
    @endphp
    {{-- Partilhar Pasta Modal --}}
    <div class="modal fade" id="shareFolderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="{{ route('edms.share-folder', $currentFolder->id) }}" method="POST">
                @csrf
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header border-0 bg-warning text-dark p-4 rounded-top-4">
                        <h5 class="modal-title fw-bold"><i class="fas fa-share-alt me-2"></i>Partilhar Pasta</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="text-muted small mb-3">Selecione os departamentos que terão acesso de visualização a esta pasta e aos seus documentos arquivados.</p>
                        
                        <div class="mb-3" style="max-height: 250px; overflow-y: auto;">
                            <label class="form-label small text-muted fw-semibold mb-2">Departamentos</label>
                            @foreach($departamentos as $dept)
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="departamento_ids[]" value="{{ $dept->id }}" id="deptShare_{{ $dept->id }}"
                                        {{ in_array($dept->id, $sharedDepts) ? 'checked' : '' }}>
                                    <label class="form-check-label text-dark" for="deptShare_{{ $dept->id }}">
                                        {{ $dept->nome }} ({{ $dept->sigla }})
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer border-0 p-4 pt-0">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning rounded-pill px-4 shadow-sm text-dark fw-bold">Salvar Partilha</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Folder History Modal --}}
    <div class="modal fade" id="folderHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header border-0 bg-secondary text-white p-4 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-history me-2"></i>Histórico e Trilha de Auditoria</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="table-responsive" style="max-height: 400px;" id="folderHistoryTableContainer">
                        <table class="table table-hover align-middle mb-0" id="folderHistoryTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Utilizador</th>
                                    <th>Ação</th>
                                    <th>IP</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Loaded via Javascript -->
                            </tbody>
                        </table>
                    </div>
                    <!-- Loader inside History Modal -->
                    <div id="historyLoader" class="d-none text-center py-4">
                        <div class="spinner-border text-secondary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="text-muted small mt-2">A obter dados de auditoria...</p>
                    </div>
                    <!-- Empty history state -->
                    <div id="historyEmptyState" class="d-none text-center py-4 text-muted">
                        <i class="fas fa-info-circle fs-3 mb-2"></i>
                        <p class="mb-0 small">Sem registros de atividade nesta pasta.</p>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dinamizar o modal de arquivamento para pendentes
    const arquivarButtons = document.querySelectorAll('.btn-arquivar');
    const modal = document.getElementById('arquivarPendenteModal');
    
    if (modal) {
        modal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const docId = button.getAttribute('data-doc-id');
            const docType = button.getAttribute('data-doc-type');
            const docTitle = button.getAttribute('data-doc-title');
            
            modal.querySelector('#modal-doc-id-input')?.remove(); // limpar se existir anterior
            
            // Injetar input extra para ID
            const hiddenId = document.createElement('input');
            hiddenId.type = 'hidden';
            hiddenId.name = 'id';
            hiddenId.id = 'modal-doc-id-input';
            hiddenId.value = docId;
            modal.querySelector('form').appendChild(hiddenId);
            
            modal.querySelector('#modal-doc-type').value = docType;
            modal.querySelector('#modal-doc-title-text').textContent = docTitle;
            
            // Set form action route
            modal.querySelector('form').action = `/pastas/${docId}/arquivar`;
            
            // Sugestões inteligentes de pastas padrão baseadas no tipo de documento
            const pastaSelect = modal.querySelector('#modal-pasta-id');
            
            if (docType === 'entrada') {
                pastaSelect.value = 'auto';
            } else {
                // Para documentos internos, tenta pré-selecionar Despachos
                let selected = false;
                for (let i = 0; i < pastaSelect.options.length; i++) {
                    const optText = pastaSelect.options[i].text.toLowerCase();
                    if (optText.includes('interno') && optText.includes('despachos')) {
                        pastaSelect.selectedIndex = i;
                        selected = true;
                        break;
                    }
                }
                if (!selected) {
                    pastaSelect.value = 'auto';
                }
            }
        });
    }

    // Drag-and-drop Dropzone para upload direto
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('fileInput');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileIcon = document.getElementById('fileIcon');
    const clearFileBtn = document.getElementById('clearFileBtn');
    
    if (dropzone && fileInput) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            }, false);
        });
        
        fileInput.addEventListener('change', (e) => {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                fileName.textContent = file.name;
                
                // Set icon based on extension
                const extension = file.name.split('.').pop().toLowerCase();
                if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) {
                    fileIcon.className = 'fas fa-file-image text-warning fs-5';
                } else if (extension === 'pdf') {
                    fileIcon.className = 'fas fa-file-pdf text-danger fs-5';
                } else {
                    fileIcon.className = 'fas fa-file text-secondary fs-5';
                }
                
                fileInfo.classList.remove('d-none');
                fileInfo.classList.add('d-flex');
            }
        });
        
        clearFileBtn.addEventListener('click', (e) => {
            e.preventDefault();
            fileInput.value = '';
            fileInfo.classList.add('d-none');
            fileInfo.classList.remove('d-flex');
        });
    }

    // Visualizador de Documentos (PDF/Imagem)
    const previewModal = document.getElementById('edmsPreviewModal');
    if (previewModal) {
        previewModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const url = button.getAttribute('data-preview-url');
            const title = button.getAttribute('data-preview-title');
            const type = button.getAttribute('data-preview-type') || 'pdf';
            
            const iframe = document.getElementById('previewIframe');
            const imgContainer = document.getElementById('previewImageContainer');
            const img = document.getElementById('previewImage');
            const loader = document.getElementById('previewLoader');
            const downloadBtn = document.getElementById('previewDownloadBtn');
            const modalTitle = document.getElementById('previewModalTitle');
            
            modalTitle.innerHTML = `<i class="fas fa-eye me-2"></i> ${title}`;
            downloadBtn.href = url + '?download=1';
            
            loader.classList.remove('d-none');
            iframe.classList.add('d-none');
            imgContainer.classList.add('d-none');
            
            if (type === 'image') {
                img.src = url;
                img.onload = function() {
                    loader.classList.add('d-none');
                    imgContainer.classList.remove('d-none');
                    imgContainer.classList.add('d-flex');
                };
            } else {
                iframe.src = url;
                iframe.onload = function() {
                    loader.classList.add('d-none');
                    iframe.classList.remove('d-none');
                };
            }
        });
        
        previewModal.addEventListener('hide.bs.modal', function() {
            document.getElementById('previewIframe').src = '';
            document.getElementById('previewImage').src = '';
        });
    }

    // Modal de Histórico de Pasta (Auditoria via AJAX)
    const folderHistoryModal = document.getElementById('folderHistoryModal');
    if (folderHistoryModal) {
        folderHistoryModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const folderId = button.getAttribute('data-folder-id');
            
            const tableBody = document.querySelector('#folderHistoryTable tbody');
            const loader = document.getElementById('historyLoader');
            const tableContainer = document.getElementById('folderHistoryTableContainer');
            const emptyState = document.getElementById('historyEmptyState');
            
            tableBody.innerHTML = '';
            loader.classList.remove('d-none');
            tableContainer.classList.add('d-none');
            emptyState.classList.add('d-none');
            
            fetch(`/edms/folder/${folderId}/history`)
                .then(res => res.json())
                .then(data => {
                    loader.classList.add('d-none');
                    if (data.length === 0) {
                        emptyState.classList.remove('d-none');
                    } else {
                        data.forEach(log => {
                            const tr = document.createElement('tr');
                            
                            let details = '';
                            if (log.details && log.details.titulo) {
                                details = ` (${log.details.titulo})`;
                            } else if (log.details && log.details.nome) {
                                details = ` (${log.details.nome})`;
                            } else if (log.details && log.details.file_name) {
                                details = ` (${log.details.file_name})`;
                            }
                            
                            tr.innerHTML = `
                                <td class="fw-semibold text-dark">${log.user_name}</td>
                                <td><span class="badge bg-secondary-subtle text-secondary text-uppercase">${log.action}</span>${details}</td>
                                <td><code class="small text-muted">${log.ip || 'N/A'}</code></td>
                                <td class="small text-muted">${log.date}</td>
                            `;
                            tableBody.appendChild(tr);
                        });
                        tableContainer.classList.remove('d-none');
                    }
                })
                .catch(err => {
                    loader.classList.add('d-none');
                    emptyState.classList.remove('d-none');
                    emptyState.innerHTML = '<i class="fas fa-times-circle text-danger fs-3 mb-2"></i><p class="mb-0 text-danger">Erro ao carregar histórico.</p>';
                });
        });
    }
});
</script>

<style>
    .card-hover {
        transition: all 0.25s ease-in-out;
    }
    .card-hover:hover {
        transform: translateY(-4px);
        box-shadow: 0 .75rem 1.5rem rgba(0, 0, 0, .075) !important;
    }
    .border-start-system {
        border-left-width: 4px !important;
    }
    .text-truncate-3 {
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .text-truncate-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .fs-7 {
        font-size: 0.8rem !important;
    }
    
    /* Timeline styling */
    .timeline {
        position: relative;
    }
    .timeline-item {
        position: relative;
    }
    #dropzone {
        transition: all 0.2s ease-in-out;
    }
    #dropzone:hover, #dropzone.dragover {
        border-color: #0d6efd !important;
        background-color: rgba(13, 110, 253, 0.05) !important;
        transform: scale(1.005);
    }
    .btn-xs {
        padding: 0.15rem 0.4rem;
        font-size: 0.75rem;
    }
</style>
@endsection

