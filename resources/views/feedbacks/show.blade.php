@extends('layouts.app')

@section('title', 'Detalhes do Feedback')

@section('content')
<div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-comment-dots me-2"></i>Detalhes do Feedback
        </h5>
        <div>
            @if(Auth::user()->id == $feedback->user_id || Auth::user()->hasPermission('excluir_feedbacks'))
                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal">
                    <i class="fas fa-trash me-1"></i> Excluir
                </button>
            @endif
            <a href="{{ route('feedbacks.index') }}" class="btn btn-light btn-sm ms-1">
                <i class="fas fa-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </div>
    
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <!-- Informações principais -->
                <div class="card mb-4">
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
                        <h4 class="card-title">{{ $feedback->titulo }}</h4>
                        <div class="mb-4">
                            <div class="bg-light p-3 rounded">
                                <p class="card-text">{{ $feedback->comentario }}</p>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Data de Envio:</strong> {{ $feedback->created_at->format('d/m/Y H:i') }}</p>
                                <p>
                                    <strong>Enviado por:</strong> 
                                    @if($feedback->anonimo)
                                        <span class="badge bg-secondary">Anônimo</span>
                                    @else
                                        {{ $feedback->usuario->name }}
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p>
                                    <strong>Módulo Relacionado:</strong> 
                                    @if($feedback->modulo == 'outro')
                                        {{ $feedback->outro_modulo }}
                                    @elseif($feedback->modulo)
                                        {{ ucfirst($feedback->modulo) }}
                                    @else
                                        <span class="text-muted">Não especificado</span>
                                    @endif
                                </p>
                                <p>
                                    <strong>Status:</strong>
                                    @if($feedback->respondido)
                                        <span class="badge bg-success">Respondido</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Aguardando resposta</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Respostas -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-reply me-2"></i>Respostas</h6>
                    </div>
                    <div class="card-body">
                        @if($feedback->respostas && $feedback->respostas->count() > 0)
                            @foreach($feedback->respostas as $resposta)
                                <div class="d-flex mb-4">
                                    <div class="flex-shrink-0">
                                        <div class="avatar avatar-sm rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    </div>
                                    <div class="ms-3 flex-grow-1">
                                        <div class="d-flex align-items-center mb-1">
                                            <h6 class="mb-0">{{ $resposta->usuario->name }}</h6>
                                            <small class="text-muted ms-2">{{ $resposta->created_at->format('d/m/Y H:i') }}</small>
                                        </div>
                                        <div class="bg-light p-3 rounded">
                                            <p class="mb-0">{{ $resposta->conteudo }}</p>
                                        </div>
                                        
                                        @if(Auth::user()->hasPermission('excluir_respostas_feedback'))
                                            <div class="mt-2 text-end">
                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteRespostaModal{{ $resposta->id }}">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </div>
                                            
                                            <!-- Modal de exclusão de resposta -->
                                            <div class="modal fade" id="deleteRespostaModal{{ $resposta->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title">Excluir Resposta</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Tem certeza que deseja excluir esta resposta?</p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                            <form action="{{ route('feedbacks.respostas.destroy', [$feedback->id, $resposta->id]) }}" method="POST">
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
                            @endforeach
                        @else
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>Nenhuma resposta para este feedback.
                            </div>
                        @endif
                        
                        @if(Auth::user()->hasPermission('responder_feedbacks'))
                            <div class="mt-4">
                                <form action="{{ route('feedbacks.respostas.store', $feedback->id) }}" method="POST">
                                    @csrf
                                    <div class="mb-3">
                                        <label for="resposta" class="form-label">Adicionar Resposta</label>
                                        <textarea class="form-control @error('conteudo') is-invalid @enderror" id="resposta" name="conteudo" rows="3" required>{{ old('conteudo') }}</textarea>
                                        @error('conteudo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-1"></i> Enviar Resposta
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Estatísticas -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Estatísticas</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>Avaliação</h6>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: {{ ($feedback->avaliacao / 5) * 100 }}%;" aria-valuenow="{{ $feedback->avaliacao }}" aria-valuemin="0" aria-valuemax="5">
                                    {{ $feedback->avaliacao }}/5
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Tempo de Resposta</h6>
                            @if($feedback->respondido)
                                @php
                                    $primeiraResposta = $feedback->respostas->sortBy('created_at')->first();
                                    $tempoResposta = $feedback->created_at->diffInHours($primeiraResposta->created_at);
                                @endphp
                                
                                @if($tempoResposta < 24)
                                    <div class="alert alert-success mb-0">
                                        <i class="fas fa-clock me-1"></i> {{ $tempoResposta }} hora(s)
                                    </div>
                                @elseif($tempoResposta < 72)
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-clock me-1"></i> {{ ceil($tempoResposta / 24) }} dia(s)
                                    </div>
                                @else
                                    <div class="alert alert-danger mb-0">
                                        <i class="fas fa-clock me-1"></i> {{ ceil($tempoResposta / 24) }} dia(s)
                                    </div>
                                @endif
                            @else
                                @php
                                    $tempoEspera = $feedback->created_at->diffInHours(now());
                                @endphp
                                
                                @if($tempoEspera < 24)
                                    <div class="alert alert-info mb-0">
                                        <i class="fas fa-hourglass-half me-1"></i> Aguardando resposta ({{ $tempoEspera }} hora(s))
                                    </div>
                                @elseif($tempoEspera < 72)
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-hourglass-half me-1"></i> Aguardando resposta ({{ ceil($tempoEspera / 24) }} dia(s))
                                    </div>
                                @else
                                    <div class="alert alert-danger mb-0">
                                        <i class="fas fa-hourglass-half me-1"></i> Aguardando resposta ({{ ceil($tempoEspera / 24) }} dia(s))
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
                
                <!-- Ações -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-cogs me-2"></i>Ações</h6>
                    </div>
                    <div class="card-body">
                        @if(Auth::user()->hasPermission('gerenciar_feedbacks'))
                            <div class="mb-3">
                                <form action="{{ route('feedbacks.update.status', $feedback->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <label class="form-label">Alterar Status</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn {{ $feedback->respondido ? 'btn-warning' : 'btn-success' }}">
                                            @if($feedback->respondido)
                                                <i class="fas fa-undo me-1"></i> Marcar como Não Respondido
                                            @else
                                                <i class="fas fa-check me-1"></i> Marcar como Respondido
                                            @endif
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="mb-3">
                                <form action="{{ route('feedbacks.update.destaque', $feedback->id) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <label class="form-label">Destaque</label>
                                    <div class="d-grid">
                                        <button type="submit" class="btn {{ $feedback->destaque ? 'btn-secondary' : 'btn-primary' }}">
                                            @if($feedback->destaque)
                                                <i class="fas fa-star me-1"></i> Remover Destaque
                                            @else
                                                <i class="fas fa-star me-1"></i> Destacar Feedback
                                            @endif
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif
                        
                        @if(Auth::user()->hasPermission('exportar_feedbacks'))
                            <div>
                                <label class="form-label">Exportar</label>
                                <div class="d-grid">
                                    <a href="{{ route('feedbacks.export', $feedback->id) }}" class="btn btn-info text-white">
                                        <i class="fas fa-file-export me-1"></i> Exportar como PDF
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                
                <!-- Feedbacks Relacionados -->
                @if($feedbacksRelacionados && $feedbacksRelacionados->count() > 0)
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-link me-2"></i>Feedbacks Relacionados</h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                @foreach($feedbacksRelacionados as $relacionado)
                                    <a href="{{ route('feedbacks.show', $relacionado->id) }}" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">{{ Str::limit($relacionado->titulo, 30) }}</h6>
                                            <small>{{ $relacionado->created_at->format('d/m/Y') }}</small>
                                        </div>
                                        <small class="text-muted">
                                            @if($relacionado->tipo == 'elogio')
                                                <span class="badge bg-success">Elogio</span>
                                            @elseif($relacionado->tipo == 'sugestao')
                                                <span class="badge bg-info">Sugestão</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Crítica</span>
                                            @endif
                                        </small>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Modal de exclusão do feedback -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Excluir Feedback</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir este feedback?</p>
                <p><strong>Título:</strong> {{ $feedback->titulo }}</p>
                <p><strong>Tipo:</strong> {{ ucfirst($feedback->tipo) }}</p>
                
                @if($feedback->respostas && $feedback->respostas->count() > 0)
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Atenção: Este feedback possui {{ $feedback->respostas->count() }} resposta(s) que também serão excluídas.
                    </div>
                @endif
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
@endsection