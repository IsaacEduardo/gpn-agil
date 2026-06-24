@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row mb-4 align-items-center">
            <div class="col">
                <h4 class="mb-0 text-primary fw-bold">
                    <i class="fas fa-folder-open me-2"></i>Arquivo Digital
                </h4>
                <div class="text-muted small mt-1">
                    @if (isset($currentFolder))
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0 p-0 bg-transparent">
                                <li class="breadcrumb-item">
                                    <a href="{{ route('pastas.index') }}" class="text-decoration-none">
                                        <i class="fas fa-home"></i> Raiz
                                    </a>
                                </li>
                                @foreach ($breadcrumbs as $crumb)
                                    <li class="breadcrumb-item">
                                        <a href="{{ route('pastas.index', ['folder' => $crumb->id]) }}"
                                            class="text-decoration-none">
                                            {{ $crumb->nome }}
                                        </a>
                                    </li>
                                @endforeach
                                <li class="breadcrumb-item active" aria-current="page">{{ $currentFolder->nome }}</li>
                            </ol>
                        </nav>
                    @else
                        Gerencie os documentos do seu departamento
                    @endif
                </div>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#createFolderModal">
                    <i class="fas fa-plus me-1"></i> Nova Pasta
                </button>
            </div>
        </div>

        {{-- Folder Grid --}}
        @if (isset($currentFolder))
            <div class="card mb-4 border-0 shadow-sm bg-light">
                <div class="card-body p-3">
                    <form action="{{ route('pastas.index') }}" method="GET" class="row g-2 align-items-end">
                        <input type="hidden" name="folder" value="{{ $currentFolder->id }}">

                        <div class="col-md-4">
                            <label class="form-label small text-muted mb-1">Buscar</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i
                                        class="fas fa-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0"
                                    placeholder="Assunto, Nº Ref, Título..." value="{{ request('search') }}">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Tipo de Documento</label>
                            <select name="type" class="form-select">
                                <option value="all" {{ request('type') == 'all' ? 'selected' : '' }}>Todos</option>
                                <option value="entrada" {{ request('type') == 'entrada' ? 'selected' : '' }}>Entradas
                                </option>
                                <option value="interno" {{ request('type') == 'interno' ? 'selected' : '' }}>Internos
                                </option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Data de Arquivamento</label>
                            <div class="input-group">
                                <input type="date" name="date_start" class="form-control" placeholder="De"
                                    value="{{ request('date_start') }}" title="De">
                                <span class="input-group-text border-0 bg-transparent">-</span>
                                <input type="date" name="date_end" class="form-control" placeholder="Até"
                                    value="{{ request('date_end') }}" title="Até">
                            </div>
                        </div>

                        <div class="col-md-2 d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary btn-sm flex-fill">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                            @if (request('search') || request('type') || request('date_start'))
                                <a href="{{ route('pastas.index', ['folder' => $currentFolder->id]) }}"
                                    class="btn btn-outline-secondary btn-sm" title="Limpar Filtros">
                                    <i class="fas fa-times"></i>
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="row g-3">
            @if (isset($currentFolder) && $currentFolder->parent_id)
                <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                    <a href="{{ route('pastas.index', ['folder' => $currentFolder->parent_id]) }}"
                        class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm bg-light hover-shadow">
                            <div
                                class="card-body text-center d-flex flex-column align-items-center justify-content-center p-3">
                                <i class="fas fa-level-up-alt fa-2x text-muted mb-2"></i>
                                <h6 class="card-title text-dark mb-0 text-truncate w-100">Voltar</h6>
                            </div>
                        </div>
                    </a>
                </div>
            @elseif(isset($currentFolder))
                <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                    <a href="{{ route('pastas.index') }}" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm bg-light hover-shadow">
                            <div
                                class="card-body text-center d-flex flex-column align-items-center justify-content-center p-3">
                                <i class="fas fa-level-up-alt fa-2x text-muted mb-2"></i>
                                <h6 class="card-title text-dark mb-0 text-truncate w-100">Voltar p/ Raiz</h6>
                            </div>
                        </div>
                    </a>
                </div>
            @endif

            @forelse($pastas as $pasta)
                <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                    <a href="{{ route('pastas.index', ['folder' => $pasta->id]) }}" class="text-decoration-none">
                        <div
                            class="card h-100 border-0 shadow-sm hover-shadow transition-all {{ $pasta->is_system ? 'bg-blue-50 border-primary-subtle' : '' }}">
                            <div class="card-body text-center p-3">
                                <div class="mb-3 position-relative">
                                    @if ($pasta->type === 'entrada')
                                        <i class="fas fa-inbox fa-3x text-success"></i>
                                    @elseif($pasta->type === 'saida')
                                        <i class="fas fa-paper-plane fa-3x text-primary"></i>
                                    @elseif($pasta->type === 'interno')
                                        <i class="fas fa-file-alt fa-3x text-info"></i>
                                    @elseif($pasta->is_system)
                                        <i class="fas fa-folder fa-3x text-warning"></i>
                                        <i
                                            class="fas fa-lock position-absolute bottom-0 end-0 text-dark small bg-white rounded-circle p-1 border"></i>
                                    @else
                                        <i class="fas fa-folder fa-3x text-warning"></i>
                                    @endif
                                </div>
                                <h6 class="card-title text-dark fw-bold mb-1 text-truncate" title="{{ $pasta->nome }}">
                                    {{ $pasta->nome }}
                                </h6>
                                <small class="text-muted d-block text-truncate" style="font-size: 0.75rem">
                                    {{ ($pasta->children_count ?? 0) + ($pasta->documentos_count ?? 0) + ($pasta->documentos_internos_count ?? 0) }}
                                    itens
                                </small>
                            </div>
                            {{-- Dropdown actions --}}
                            @if (!$pasta->is_system)
                                <div class="position-absolute top-0 end-0 p-2">
                                    <div class="dropdown">
                                        <button class="btn btn-link btn-sm text-muted p-0" type="button"
                                            data-bs-toggle="dropdown" onclick="event.preventDefault();">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                            <li><a class="dropdown-item text-danger" href="#"
                                                    onclick="alert('Funcionalidade de exclusão em desenvolvimento')"><i
                                                        class="fas fa-trash me-2"></i>Excluir</a></li>
                                        </ul>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </a>
                </div>
            @empty
                @if (!isset($documentosEntrada) || ($documentosEntrada->isEmpty() && $documentosInternos->isEmpty()))
                    <div class="col-12">
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-folder-open fa-3x mb-3 opacity-50"></i>
                            <p>Esta pasta está vazia.</p>
                        </div>
                    </div>
                @endif
            @endforelse

            {{-- Documentos --}}
            @if (isset($documentosEntrada))
                @foreach ($documentosEntrada as $doc)
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                        <div class="card h-100 border-0 shadow-sm hover-shadow transition-all bg-white">
                            <div class="card-body text-center p-3">
                                <div class="mb-3 position-relative">
                                    <i class="fas fa-file-alt fa-3x text-secondary"></i>
                                    <span
                                        class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary">
                                        Ent
                                    </span>
                                </div>
                                <h6 class="card-title text-dark fw-bold mb-1 text-truncate" title="{{ $doc->assunto }}">
                                    {{ $doc->assunto }}
                                </h6>
                                <small class="text-muted d-block text-truncate" style="font-size: 0.7rem">
                                    {{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}
                                </small>
                                <small class="text-muted d-block text-truncate" style="font-size: 0.7rem">
                                    {{ $doc->arquivado_em ? $doc->arquivado_em->format('d/m/Y') : '-' }}
                                </small>
                            </div>
                            <a href="{{ route('documentos-entradas.show', $doc->id) }}" class="stretched-link"></a>
                        </div>
                    </div>
                @endforeach
            @endif

            @if (isset($documentosInternos))
                @foreach ($documentosInternos as $doc)
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                        <div class="card h-100 border-0 shadow-sm hover-shadow transition-all bg-white">
                            <div class="card-body text-center p-3">
                                <div class="mb-3 position-relative">
                                    <i class="fas fa-file-pdf fa-3x text-danger"></i>
                                    <span
                                        class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-info text-dark">
                                        Int
                                    </span>
                                </div>
                                <h6 class="card-title text-dark fw-bold mb-1 text-truncate" title="{{ $doc->titulo }}">
                                    {{ $doc->titulo }}
                                </h6>
                                <small class="text-muted d-block text-truncate" style="font-size: 0.7rem">
                                    {{ $doc->numero_referencia }}
                                </small>
                                <small class="text-muted d-block text-truncate" style="font-size: 0.7rem">
                                    {{ $doc->arquivado_em ? $doc->arquivado_em->format('d/m/Y') : '-' }}
                                </small>
                            </div>
                            <a href="{{ route('documentos-internos.show', $doc->id) }}" class="stretched-link"></a>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    {{-- Create Folder Modal --}}
    <div class="modal fade" id="createFolderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('pastas.store') }}" method="POST">
                @csrf
                <input type="hidden" name="parent_id" value="{{ $currentFolder->id ?? '' }}">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Nova Pasta</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nome da Pasta</label>
                            <input type="text" name="nome" class="form-control" required
                                placeholder="Ex: Relatórios 2024">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descrição (Opcional)</label>
                            <textarea name="descricao" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Criar Pasta</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <style>
        .hover-shadow:hover {
            transform: translateY(-2px);
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15) !important;
        }

        .transition-all {
            transition: all 0.2s ease-in-out;
        }

        .bg-blue-50 {
            background-color: #f8faff !important;
        }
    </style>
@endsection
