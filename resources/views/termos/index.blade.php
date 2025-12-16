@extends('layouts.app')

@section('title', 'Termos de Entrega')

@section('breadcrumbs')
    <div class="container py-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
                <li class="breadcrumb-item active" aria-current="page">Termos de Entrega</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-file-contract me-2"></i>Termos de Entrega</h5>
                <div class="d-flex gap-2">
                    <a href="{{ route('termos.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Novo Termo
                    </a>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="bg-light p-3 rounded mb-4 border">
                    <form method="GET" action="{{ route('termos.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="beneficiario"
                                class="form-label fw-bold text-muted small text-uppercase">Beneficiário</label>
                            <input type="text" class="form-control" id="beneficiario" name="beneficiario"
                                value="{{ request('beneficiario') }}" placeholder="Nome do beneficiário">
                        </div>
                        <div class="col-md-3">
                            <label for="tipo" class="form-label fw-bold text-muted small text-uppercase">Tipo</label>
                            @php $tipo = request('tipo'); @endphp
                            <select id="tipo" name="tipo" class="form-select">
                                <option value="">Todos</option>
                                <option value="definitiva" {{ $tipo === 'definitiva' ? 'selected' : '' }}>Definitiva
                                </option>
                                <option value="devolutivo" {{ $tipo === 'devolutivo' ? 'selected' : '' }}>Devolutivo
                                </option>
                                <option value="viatura" {{ $tipo === 'viatura' ? 'selected' : '' }}>Viatura</option>
                            </select>
                        </div>
                        <div class="col-md-5 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                            <a href="{{ route('termos.index') }}" class="btn btn-outline-secondary" title="Limpar Filtros">
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

                @if ($termos->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-uppercase small fw-bold text-muted">ID</th>
                                    <th class="text-uppercase small fw-bold text-muted">Beneficiário</th>
                                    <th class="text-uppercase small fw-bold text-muted">Item/Viatura</th>
                                    <th class="text-uppercase small fw-bold text-muted">Tipo</th>
                                    <th class="text-uppercase small fw-bold text-muted">Data</th>
                                    <th class="text-uppercase small fw-bold text-muted">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($termos as $termo)
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                {{ $termo->id }}
                                            </span>
                                        </td>
                                        <td class="fw-medium">{{ $termo->beneficiario_nome ?? '—' }}</td>
                                        <td>
                                            @if ($termo->tipo === 'viatura')
                                                @php $viatura = \App\Models\Viatura::find($termo->viatura_id); @endphp
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-car-side text-secondary me-2"></i>
                                                    <div>
                                                        <div>{{ optional($viatura)->identificacao }}</div>
                                                        <small class="text-muted">{{ optional($viatura)->placa }}</small>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-box text-secondary me-2"></i>
                                                    {{ $termo->item_descricao ?? '—' }}
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $badgeClass = match ($termo->tipo) {
                                                    'devolutivo' => 'bg-warning text-dark bg-opacity-75',
                                                    'viatura' => 'bg-info text-dark bg-opacity-75',
                                                    default => 'bg-primary bg-opacity-75',
                                                };

                                                $icon = match ($termo->tipo) {
                                                    'devolutivo' => 'fa-undo',
                                                    'viatura' => 'fa-car',
                                                    default => 'fa-hand-holding',
                                                };

                                                $label = match ($termo->tipo) {
                                                    'definitiva' => 'Definitiva',
                                                    'devolutivo' => 'Devolutivo',
                                                    'viatura' => 'Viatura',
                                                    default => $termo->tipo,
                                                };
                                            @endphp
                                            <span class="badge {{ $badgeClass }} px-3 py-2 rounded-pill">
                                                <i class="fas {{ $icon }} me-1"></i>{{ $label }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-muted">
                                                <i class="far fa-calendar me-1"></i>
                                                {{ $termo->created_at->format('d/m/Y') }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="{{ route('termos.show', $termo->id) }}"
                                                    class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip"
                                                    title="Ver Detalhes">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="{{ route('termos.pdf', $termo->id) }}"
                                                    class="btn btn-sm btn-outline-danger" target="_blank"
                                                    data-bs-toggle="tooltip" title="Gerar PDF">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $termos->appends(request()->query())->links() }}
                    </div>
                @else
                    <div class="text-center py-5">
                        <div class="mb-3 text-muted">
                            <i class="fas fa-file-contract fa-3x"></i>
                        </div>
                        <h5 class="text-muted">Nenhum termo encontrado</h5>
                        <p class="text-muted mb-3">Tente ajustar os filtros ou crie um novo termo.</p>
                        <a href="{{ route('termos.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Novo Termo
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            })
        });
    </script>
@endsection
