@extends('layouts.app')

@section('title', 'Detalhes da Reserva')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <a href="{{ route('reservas.index') }}" class="btn btn-outline-secondary btn-sm me-3 rounded-circle"
                            style="width: 32px; height: 32px; padding: 0; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <h5 class="mb-0 text-primary fw-bold">
                            <i class="fas fa-ticket-alt me-2"></i>Reserva #{{ $reserva->codigo_reserva }}
                        </h5>
                    </div>
                    <div class="d-flex gap-2">
                        @php
                            $actor = Auth::user();
                            $actorDeps =
                                method_exists($actor, 'departamentos') && $actor->departamentos
                                    ? $actor->departamentos->pluck('id')->all()
                                    : [];
                            if (!count($actorDeps) && $actor->departamento_id) {
                                $actorDeps = [$actor->departamento_id];
                            }
                            $owner = $reserva->usuario;
                            $ownerDeps =
                                $owner && method_exists($owner, 'departamentos') && $owner->departamentos
                                    ? $owner->departamentos->pluck('id')->all()
                                    : [];
                            if ($owner && !count($ownerDeps) && $owner->departamento_id) {
                                $ownerDeps = [$owner->departamento_id];
                            }
                            $inScope = count(array_intersect($actorDeps, $ownerDeps)) > 0;
                            if ($actor->role && $actor->role->name === 'admin') {
                                $inScope = true;
                            }
                        @endphp

                        @if ($reserva->isPendente() && Auth::user()->hasPermission('aprovar_reservas'))
                            <button type="button" class="btn btn-success text-white" data-bs-toggle="modal"
                                data-bs-target="#aprovarModal">
                                <i class="fas fa-check me-1"></i> Aprovar
                            </button>
                            <button type="button" class="btn btn-danger text-white" data-bs-toggle="modal"
                                data-bs-target="#rejeitarModal">
                                <i class="fas fa-times me-1"></i> Rejeitar
                            </button>
                        @endif

                        @if (
                            $reserva->podeSerEditada() &&
                                ($reserva->usuario_id == Auth::id() || Auth::user()->hasPermission('gerenciar_reservas')))
                            <a href="{{ route('reservas.edit', $reserva->id) }}" class="btn btn-outline-warning">
                                <i class="fas fa-edit me-1"></i> Editar
                            </a>
                        @endif

                        @if (
                            $reserva->podeSerCancelada() &&
                                ($reserva->usuario_id == Auth::id() || Auth::user()->hasPermission('gerenciar_reservas')))
                            <form action="{{ route('reservas.cancelar', $reserva->id) }}" method="POST" class="d-inline"
                                onsubmit="return confirm('Tem certeza que deseja cancelar esta reserva?')">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="fas fa-ban me-1"></i> Cancelar
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
                <div class="card-body p-4">
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <div class="row g-4">
                        <!-- Cabeçalho com Status e Data -->
                        <div class="col-12">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start bg-light p-4 rounded border">
                                <div>
                                    <h4 class="fw-bold mb-1">{{ $reserva->evento_titulo }}</h4>
                                    <p class="text-muted mb-2">
                                        <i class="fas fa-building me-1"></i>
                                        {{ $reserva->tipo_espaco == 'salao_nobre' ? 'Salão Nobre' : 'Anfiteatro' }}
                                    </p>
                                    <div class="d-flex align-items-center gap-3 text-secondary">
                                        <span><i class="far fa-calendar me-1"></i>
                                            {{ \Carbon\Carbon::parse($reserva->data_evento)->format('d/m/Y') }}</span>
                                        <span><i class="far fa-clock me-1"></i> {{ $reserva->hora_inicio }} -
                                            {{ $reserva->hora_fim }}</span>
                                    </div>
                                </div>
                                <div class="mt-3 mt-md-0 text-md-end">
                                    <div class="mb-2">
                                        @if ($reserva->isPendente())
                                            <span class="badge bg-warning text-dark fs-6 px-3 py-2 rounded-pill">
                                                <i class="fas fa-clock me-1"></i>{{ $reserva->status_nome }}
                                            </span>
                                        @elseif($reserva->isAprovada())
                                            <span class="badge bg-success fs-6 px-3 py-2 rounded-pill">
                                                <i class="fas fa-check me-1"></i>{{ $reserva->status_nome }}
                                            </span>
                                        @elseif($reserva->isRejeitada())
                                            <span class="badge bg-danger fs-6 px-3 py-2 rounded-pill">
                                                <i class="fas fa-times me-1"></i>{{ $reserva->status_nome }}
                                            </span>
                                        @else
                                            <span class="badge bg-secondary fs-6 px-3 py-2 rounded-pill">
                                                {{ $reserva->status_nome }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="small text-muted">
                                        Solicitado em: {{ $reserva->created_at->format('d/m/Y H:i') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detalhes em Grid -->
                        <div class="col-md-6">
                            <h6 class="text-uppercase text-muted fw-bold small border-bottom pb-2 mb-3">Solicitante</h6>
                            <dl class="row">
                                <dt class="col-sm-4 text-muted fw-normal">Nome</dt>
                                <dd class="col-sm-8 fw-medium">{{ $reserva->solicitante_nome }}</dd>

                                <dt class="col-sm-4 text-muted fw-normal">E-mail</dt>
                                <dd class="col-sm-8"><a href="mailto:{{ $reserva->solicitante_email }}" class="text-decoration-none">{{ $reserva->solicitante_email }}</a></dd>

                                <dt class="col-sm-4 text-muted fw-normal">Telefone</dt>
                                <dd class="col-sm-8">
                                    @if ($reserva->solicitante_telefone)
                                        <a href="tel:{{ $reserva->solicitante_telefone }}" class="text-decoration-none">{{ $reserva->solicitante_telefone }}</a>
                                    @else
                                        <span class="text-muted fst-italic">Não informado</span>
                                    @endif
                                </dd>

                                <dt class="col-sm-4 text-muted fw-normal">Usuário Sistema</dt>
                                <dd class="col-sm-8">{{ $reserva->usuario->name ?? 'N/A' }}</dd>
                            </dl>
                        </div>

                        <div class="col-md-6">
                            <h6 class="text-uppercase text-muted fw-bold small border-bottom pb-2 mb-3">Detalhes do Evento</h6>
                            <dl class="row">
                                <dt class="col-sm-4 text-muted fw-normal">Participantes</dt>
                                <dd class="col-sm-8">
                                    @if ($reserva->numero_participantes)
                                        {{ $reserva->numero_participantes }} pessoas
                                    @else
                                        <span class="text-muted fst-italic">Não informado</span>
                                    @endif
                                </dd>

                                <dt class="col-sm-4 text-muted fw-normal">Descrição</dt>
                                <dd class="col-sm-8">
                                    @if ($reserva->evento_descricao)
                                        {{ $reserva->evento_descricao }}
                                    @else
                                        <span class="text-muted fst-italic">Sem descrição</span>
                                    @endif
                                </dd>

                                <dt class="col-sm-4 text-muted fw-normal">Observações</dt>
                                <dd class="col-sm-8">
                                    @if ($reserva->observacoes)
                                        {{ $reserva->observacoes }}
                                    @else
                                        <span class="text-muted fst-italic">Nenhuma observação</span>
                                    @endif
                                </dd>
                            </dl>
                        </div>

                        <!-- Histórico de Aprovação -->
                        @if ($reserva->data_aprovacao || $reserva->motivo_rejeicao)
                            <div class="col-12">
                                <div class="alert {{ $reserva->isAprovada() ? 'alert-success' : 'alert-danger' }} mb-0 border-0 shadow-sm">
                                    <h6 class="alert-heading fw-bold">
                                        <i class="fas {{ $reserva->isAprovada() ? 'fa-check-circle' : 'fa-exclamation-circle' }} me-2"></i>
                                        Histórico de {{ $reserva->isAprovada() ? 'Aprovação' : 'Rejeição' }}
                                    </h6>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Responsável:</strong> {{ $reserva->aprovador->name ?? 'Sistema' }}
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Data:</strong> {{ \Carbon\Carbon::parse($reserva->data_aprovacao)->format('d/m/Y H:i') }}
                                        </div>
                                        @if ($reserva->motivo_rejeicao)
                                            <div class="col-12 mt-2">
                                                <strong>Motivo:</strong> {{ $reserva->motivo_rejeicao }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modais de Aprovação/Rejeição -->
    @if ($reserva->isPendente())
        <div class="modal fade" id="aprovarModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('reservas.aprovar', $reserva->id) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title"><i class="fas fa-check me-2"></i>Aprovar Reserva</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Tem certeza que deseja aprovar a reserva <strong>#{{ $reserva->codigo_reserva }}</strong>?</p>
                            <div class="mb-3">
                                <label for="observacoes_aprovacao" class="form-label">Observações (Opcional)</label>
                                <textarea class="form-control" id="observacoes_aprovacao" name="observacoes" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success">Confirmar Aprovação</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="rejeitarModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('reservas.rejeitar', $reserva->id) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title"><i class="fas fa-times me-2"></i>Rejeitar Reserva</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Tem certeza que deseja rejeitar a reserva <strong>#{{ $reserva->codigo_reserva }}</strong>?</p>
                            <div class="mb-3">
                                <label for="motivo_rejeicao" class="form-label">Motivo da Rejeição *</label>
                                <textarea class="form-control" id="motivo_rejeicao" name="motivo_rejeicao" rows="3" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-danger">Confirmar Rejeição</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
