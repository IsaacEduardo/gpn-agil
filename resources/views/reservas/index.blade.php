@extends('layouts.app')

@section('title', 'Reservas de Espaços')

@section('breadcrumbs')
    <div class="container py-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
                <li class="breadcrumb-item active" aria-current="page">Reservas</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
            <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-calendar-alt me-2"></i>Reservas de Espaços</h5>
            <div>
                <a href="{{ route('reservas.calendar') }}" class="btn btn-outline-primary me-2">
                    <i class="fas fa-calendar me-1"></i> Calendário
                </a>
                <a href="{{ route('reservas.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Nova Reserva
                </a>
            </div>
        </div>
        <div class="card-body">
            <!-- Filtros -->
            <div class="bg-light p-3 rounded mb-4 border">
                <form action="{{ route('reservas.index') }}" method="GET" class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Tipo de Espaço</label>
                        <select name="tipo_espaco" class="form-select">
                            <option value="">Todos os Espaços</option>
                            <option value="salao_nobre" {{ request('tipo_espaco') == 'salao_nobre' ? 'selected' : '' }}>
                                Salão Nobre</option>
                            <option value="anfiteatro" {{ request('tipo_espaco') == 'anfiteatro' ? 'selected' : '' }}>
                                Anfiteatro</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label fw-bold text-muted small text-uppercase">Status</label>
                        <select name="status" class="form-select">
                            <option value="">Todos os Status</option>
                            <option value="pendente" {{ request('status') == 'pendente' ? 'selected' : '' }}>Pendente
                            </option>
                            <option value="aprovada" {{ request('status') == 'aprovada' ? 'selected' : '' }}>Aprovada
                            </option>
                            <option value="rejeitada" {{ request('status') == 'rejeitada' ? 'selected' : '' }}>Rejeitada
                            </option>
                            <option value="cancelada" {{ request('status') == 'cancelada' ? 'selected' : '' }}>Cancelada
                            </option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fw-bold text-muted small text-uppercase">Data Início</label>
                        <input type="date" name="data_inicio" class="form-control" value="{{ request('data_inicio') }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fw-bold text-muted small text-uppercase">Data Fim</label>
                        <input type="date" name="data_fim" class="form-control" value="{{ request('data_fim') }}">
                    </div>
                    <div class="col-12 col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-search me-1"></i> Filtrar
                        </button>
                        <a href="{{ route('reservas.index') }}" class="btn btn-outline-secondary" title="Limpar Filtros">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>

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

            <!-- Tabela de Reservas -->
            @if ($reservas->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle" data-dt="true" data-dt-paging="false">
                        <thead class="table-light">
                            <tr>
                                <th class="text-uppercase small fw-bold text-muted">Código</th>
                                <th class="text-uppercase small fw-bold text-muted">Espaço</th>
                                <th class="text-uppercase small fw-bold text-muted">Evento</th>
                                <th class="text-uppercase small fw-bold text-muted">Solicitante</th>
                                <th class="text-uppercase small fw-bold text-muted">Data/Hora</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reservas as $reserva)
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            {{ $reserva->codigo_reserva }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-2 bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center"
                                                style="width: 32px; height: 32px;">
                                                <i class="fas fa-building"></i>
                                            </div>
                                            <span class="fw-medium">
                                                {{ $reserva->tipo_espaco == 'salao_nobre' ? 'Salão Nobre' : 'Anfiteatro' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ $reserva->nome_evento }}</div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-2 bg-secondary-subtle text-secondary rounded-circle d-flex align-items-center justify-content-center"
                                                style="width: 32px; height: 32px;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <div class="fw-medium">{{ $reserva->usuario->name ?? 'N/A' }}</div>
                                                <div class="small text-muted">
                                                    {{ $reserva->usuario->departamento->sigla ?? '' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="fw-medium">
                                                <i class="far fa-calendar me-1 text-muted"></i>
                                                {{ date('d/m/Y', strtotime($reserva->data_evento)) }}
                                            </span>
                                            <span class="small text-muted">
                                                <i class="far fa-clock me-1"></i>
                                                {{ date('H:i', strtotime($reserva->hora_inicio)) }} -
                                                {{ date('H:i', strtotime($reserva->hora_fim)) }}
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($reserva->isPendente())
                                            <span class="badge bg-warning text-dark bg-opacity-75 px-3 py-2 rounded-pill">
                                                <i class="fas fa-clock me-1"></i>{{ $reserva->status_nome }}
                                            </span>
                                        @elseif($reserva->isAprovada())
                                            <span class="badge bg-success bg-opacity-75 px-3 py-2 rounded-pill">
                                                <i class="fas fa-check me-1"></i>{{ $reserva->status_nome }}
                                            </span>
                                        @elseif($reserva->isRejeitada())
                                            <span class="badge bg-danger bg-opacity-75 px-3 py-2 rounded-pill">
                                                <i class="fas fa-times me-1"></i>{{ $reserva->status_nome }}
                                            </span>
                                        @else
                                            <span class="badge bg-secondary bg-opacity-75 px-3 py-2 rounded-pill">
                                                {{ $reserva->status_nome }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('reservas.show', $reserva->id) }}"
                                                class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip"
                                                title="Ver Detalhes">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @if (
                                                $reserva->podeSerEditada() &&
                                                    ($reserva->usuario_id == Auth::id() || Auth::user()->hasPermission('gerenciar_reservas')))
                                                <a href="{{ route('reservas.edit', $reserva->id) }}"
                                                    class="btn btn-sm btn-outline-warning" data-bs-toggle="tooltip"
                                                    title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{ $reservas->appends(request()->query())->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <div class="mb-3 text-muted">
                        <i class="fas fa-calendar-times fa-3x"></i>
                    </div>
                    <h5 class="text-muted">Nenhuma reserva encontrada</h5>
                    <p class="text-muted mb-3">Tente ajustar os filtros ou crie uma nova reserva.</p>
                    <a href="{{ route('reservas.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Nova Reserva
                    </a>
                </div>
            @endif
        </div>
    </div>
@endsection
