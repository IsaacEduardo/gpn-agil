@extends('layouts.app')

@section('title', 'Feedbacks')

@section('content')
<div class="card">
    <div class="card-header bg-gradient-primary d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-comments me-2"></i>Feedbacks
        </h5>
        <div>
            <a href="{{ route('feedbacks.create') }}" class="btn btn-light btn-sm">
                <i class="fas fa-plus me-1"></i> Novo Feedback
            </a>
            @if(Auth::user()->hasPermission('gerar_relatorio_feedbacks'))
                <a href="{{ route('feedbacks.relatorio') }}" class="btn btn-light btn-sm ms-1">
                    <i class="fas fa-chart-bar me-1"></i> Relatório
                </a>
            @endif
        </div>
    </div>
    
    <div class="card-body">
        <!-- Filtros -->
        <div class="row mb-4">
            <div class="col-md-12">
                <form action="{{ route('feedbacks.index') }}" method="GET" class="row g-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Buscar por título ou comentário" name="search" value="{{ request('search') }}">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <select name="tipo" class="form-select" onchange="this.form.submit()">
                            <option value="">Todos os tipos</option>
                            <option value="elogio" {{ request('tipo') == 'elogio' ? 'selected' : '' }}>Elogios</option>
                            <option value="sugestao" {{ request('tipo') == 'sugestao' ? 'selected' : '' }}>Sugestões</option>
                            <option value="critica" {{ request('tipo') == 'critica' ? 'selected' : '' }}>Críticas</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <select name="avaliacao" class="form-select" onchange="this.form.submit()">
                            <option value="">Todas as avaliações</option>
                            <option value="5" {{ request('avaliacao') == '5' ? 'selected' : '' }}>5 estrelas</option>
                            <option value="4" {{ request('avaliacao') == '4' ? 'selected' : '' }}>4 estrelas</option>
                            <option value="3" {{ request('avaliacao') == '3' ? 'selected' : '' }}>3 estrelas</option>
                            <option value="2" {{ request('avaliacao') == '2' ? 'selected' : '' }}>2 estrelas</option>
                            <option value="1" {{ request('avaliacao') == '1' ? 'selected' : '' }}>1 estrela</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <a href="{{ route('feedbacks.index') }}" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-sync-alt me-1"></i> Limpar
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Lista de Feedbacks -->
        @if($feedbacks->count() > 0)
            <div class="row">
                @foreach($feedbacks as $feedback)
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-{{ $feedback->tipo == 'elogio' ? 'success' : ($feedback->tipo == 'sugestao' ? 'info' : 'warning') }} text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">
                                        @if($feedback->tipo == 'elogio')
                                            <i class="fas fa-thumbs-up me-1"></i> Elogio
                                        @elseif($feedback->tipo == 'sugestao')
                                            <i class="fas fa-lightbulb me-1"></i> Sugestão
                                        @else
                                            <i class="fas fa-exclamation-triangle me-1"></i> Crítica
                                        @endif
                                    </h6>
                                    <div>
                                        @for($i = 1; $i <= 5; $i++)
                                            @if($i <= $feedback->avaliacao)
                                                <i class="fas fa-star text-warning"></i>
                                            @else
                                                <i class="far fa-star text-warning"></i>
                                            @endif
                                        @endfor
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title">{{ $feedback->titulo }}</h5>
                                <p class="card-text">{{ Str::limit($feedback->comentario, 150) }}</p>
                            </div>
                            <div class="card-footer bg-light d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-calendar-alt me-1"></i> {{ $feedback->created_at->format('d/m/Y') }}
                                    <br>
                                    <i class="fas fa-user me-1"></i> {{ $feedback->usuario->name }}
                                </small>
                                <div>
                                    <a href="{{ route('feedbacks.show', $feedback->id) }}" class="btn btn-sm btn-info text-white">
                                        <i class="fas fa-eye me-1"></i> Detalhes
                                    </a>
                                    
                                    @if(Auth::user()->id == $feedback->user_id || Auth::user()->hasPermission('excluir_feedbacks'))
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal{{ $feedback->id }}">
                                            <i class="fas fa-trash me-1"></i>
                                        </button>
                                        
                                        <!-- Modal de confirmação para exclusão -->
                                        <div class="modal fade" id="deleteModal{{ $feedback->id }}" tabindex="-1" aria-labelledby="deleteModalLabel{{ $feedback->id }}" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title" id="deleteModalLabel{{ $feedback->id }}">Confirmar Exclusão</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Tem certeza que deseja excluir este feedback?</p>
                                                        <p><strong>Título:</strong> {{ $feedback->titulo }}</p>
                                                        <p><strong>Tipo:</strong> {{ ucfirst($feedback->tipo) }}</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <form action="{{ route('feedbacks.destroy', $feedback->id) }}" method="POST">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-danger">Confirmar Exclusão</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <!-- Paginação -->
            <div class="d-flex justify-content-center mt-4">
                {{ $feedbacks->appends(request()->query())->links() }}
            </div>
        @else
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>Nenhum feedback encontrado.
            </div>
        @endif
    </div>
</div>
@endsection
