@extends('layouts.app')

@section('title', 'Arquivo de Documentos')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Arquivo de Documentos - Pastas</h3>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPastaModal">
            <i class="fas fa-folder-plus me-1"></i> Nova Pasta
        </button>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ route('pastas.search') }}" method="GET" class="row g-2">
                <div class="col-md-10">
                    <input type="text" name="query" class="form-control" placeholder="Pesquisar documentos arquivados...">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">
                        <i class="fas fa-search me-1"></i> Pesquisar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        @forelse($pastas as $pasta)
            <div class="col-md-3 mb-4">
                <div class="card h-100 shadow-sm text-center folder-card">
                    <div class="card-body">
                        <div class="folder-icon mb-2 text-warning">
                            <i class="fas fa-folder fa-4x"></i>
                        </div>
                        @if($pasta->departamento)
                            <div class="small text-muted fw-bold mb-2" title="{{ $pasta->departamento->gabinete->nome ?? '' }} / {{ $pasta->departamento->nome }}">
                                {{ $pasta->departamento->gabinete->sigla ?? '?' }} / {{ $pasta->departamento->sigla ?? '?' }}
                            </div>
                        @endif
                        <h5 class="card-title text-truncate" title="{{ $pasta->nome }}">{{ $pasta->nome }}</h5>
                        <p class="card-text text-muted small">{{ $pasta->descricao ?? 'Sem descrição' }}</p>
                        <a href="{{ route('pastas.show', $pasta->id) }}" class="stretched-link"></a>
                    </div>
                    <div class="card-footer bg-transparent border-top-0 d-flex justify-content-between">
                         <button class="btn btn-sm btn-outline-primary z-index-2 position-relative" 
                            onclick="event.preventDefault(); editPasta({{ $pasta->id }}, '{{ $pasta->nome }}', '{{ $pasta->descricao }}')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form action="{{ route('pastas.destroy', $pasta->id) }}" method="POST" class="d-inline z-index-2 position-relative" onsubmit="return confirm('Tem certeza que deseja excluir esta pasta?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center text-muted">
                <i class="fas fa-folder-open fa-3x mb-3"></i>
                <p>Nenhuma pasta encontrada.</p>
            </div>
        @endforelse
    </div>
</div>

<!-- Modal Create -->
<div class="modal fade" id="createPastaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('pastas.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nova Pasta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="editPastaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editPastaForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Editar Pasta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" id="edit_nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" id="edit_descricao" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function editPasta(id, nome, descricao) {
        document.getElementById('editPastaForm').action = '/pastas/' + id;
        document.getElementById('edit_nome').value = nome;
        document.getElementById('edit_descricao').value = descricao;
        new bootstrap.Modal(document.getElementById('editPastaModal')).show();
    }
</script>

<style>
    .folder-card:hover {
        background-color: #f8f9fa;
        transform: translateY(-2px);
        transition: all 0.2s;
    }
    .z-index-2 {
        z-index: 2;
    }
</style>
@endsection
