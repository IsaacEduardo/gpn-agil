@extends('layouts.app')

@section('title', 'Credenciais')

@section('breadcrumbs')
    <div class="container py-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
                <li class="breadcrumb-item active" aria-current="page">Credenciais</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-id-card me-2"></i>Credenciais de Viatura</h5>
                <div class="d-flex gap-2">
                    <a href="{{ route('credenciais.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Nova Credencial
                    </a>
                </div>
            </div>
            <div class="card-body p-4">
                <div class="bg-light p-3 rounded mb-4 border">
                    <form method="GET" action="{{ route('credenciais.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label for="beneficiario"
                                class="form-label fw-bold text-muted small text-uppercase">Beneficiário</label>
                            <input type="text" class="form-control" id="beneficiario" name="beneficiario"
                                value="{{ request('beneficiario') }}" placeholder="Nome do beneficiário">
                        </div>
                        <div class="col-md-5 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-filter me-1"></i> Filtrar
                            </button>
                            <a href="{{ route('credenciais.index') }}" class="btn btn-outline-secondary" title="Limpar Filtros">
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

                @if ($credenciais->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-uppercase small fw-bold text-muted">ID</th>
                                    <th class="text-uppercase small fw-bold text-muted">Tipo</th>
                                    <th class="text-uppercase small fw-bold text-muted">Beneficiário</th>
                                    <th class="text-uppercase small fw-bold text-muted">Viatura</th>
                                    <th class="text-uppercase small fw-bold text-muted">Documento (BI)</th>
                                    <th class="text-uppercase small fw-bold text-muted">Data Emissão</th>
                                    <th class="text-uppercase small fw-bold text-muted">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($credenciais as $credencial)
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                {{ $credencial->id }}
                                            </span>
                                        </td>
                                        <td>
                                            @if (($credencial->tipo_credencial ?? '') === 'seguir_viagem')
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                    <i class="fas fa-route me-1"></i> Seguir Viagem
                                                </span>
                                            @else
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                                    <i class="fas fa-car me-1"></i> Utilização Normal
                                                </span>
                                            @endif
                                        </td>
                                        <td class="fw-medium">{{ $credencial->beneficiario_nome ?? '—' }}</td>
                                        <td>
                                            @php $viatura = \App\Models\Viatura::find($credencial->viatura_id); @endphp
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-car-side text-secondary me-2"></i>
                                                <div>
                                                    <div>{{ optional($viatura)->identificacao }}</div>
                                                    <small class="text-muted">{{ optional($viatura)->placa }}</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ $credencial->beneficiario_documento }}</td>
                                        <td>
                                            <span class="text-muted">
                                                <i class="far fa-calendar me-1"></i>
                                                {{ $credencial->created_at->format('d/m/Y') }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="{{ route('credenciais.show', $credencial->id) }}"
                                                    class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip"
                                                    title="Ver PDF">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <form action="{{ route('credenciais.destroy', $credencial->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta credencial?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Excluir">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $credenciais->appends(request()->query())->links() }}
                    </div>
                @else
                    <div class="text-center py-5">
                        <div class="mb-3 text-muted">
                            <i class="fas fa-id-card fa-3x"></i>
                        </div>
                        <h5 class="text-muted">Nenhuma credencial encontrada</h5>
                        <p class="text-muted mb-3">Tente ajustar os filtros ou crie uma nova credencial.</p>
                        <a href="{{ route('credenciais.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i> Nova Credencial
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
