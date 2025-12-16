@extends('layouts.app')

@section('title', 'Requisições de Produtos')

@section('breadcrumbs')
    <div class="container py-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
                <li class="breadcrumb-item"><a href="{{ route('requisicoes.index') }}">Requisições</a></li>
                <li class="breadcrumb-item active" aria-current="page">Produtos</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-box-open me-2"></i>Requisições de Produtos</h5>
                <a href="{{ route('requisicoes.produtos.create.novo') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Nova Requisição
                </a>
            </div>
            
            <div class="card-body p-4">
                <div class="bg-light p-3 rounded mb-4 border">
                    <form id="filtrosForm" method="GET" action="{{ route('requisicoes.produtos.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="q" class="form-label fw-bold text-muted small text-uppercase">Buscar</label>
                            <input type="text" id="q" name="q" value="{{ request('q') }}"
                                class="form-control" placeholder="Código, empresa...">
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label fw-bold text-muted small text-uppercase">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="">Todos</option>
                                <option value="pendente" {{ request('status') == 'pendente' ? 'selected' : '' }}>
                                    Pendente</option>
                                <option value="aprovado" {{ request('status') == 'aprovado' ? 'selected' : '' }}>
                                    Aprovado</option>
                                <option value="rejeitado" {{ request('status') == 'rejeitado' ? 'selected' : '' }}>
                                    Rejeitado</option>
                                <option value="concluido" {{ request('status') == 'concluido' ? 'selected' : '' }}>
                                    Concluído</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="empresa" class="form-label fw-bold text-muted small text-uppercase">Empresa</label>
                            <input type="text" id="empresa" name="empresa" value="{{ request('empresa') }}"
                                class="form-control" placeholder="Nome da empresa">
                        </div>
                        <div class="col-md-2">
                            <label for="data_inicio" class="form-label fw-bold text-muted small text-uppercase">Data Início</label>
                            <input type="date" id="data_inicio" name="data_inicio"
                                value="{{ request('data_inicio') }}" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label for="data_fim" class="form-label fw-bold text-muted small text-uppercase">Data Fim</label>
                            <input type="date" id="data_fim" name="data_fim" value="{{ request('data_fim') }}"
                                class="form-control">
                        </div>
                        <div class="col-md-1 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100" title="Filtrar">
                                <i class="fas fa-search"></i>
                            </button>
                            <a href="{{ route('requisicoes.produtos.index') }}" class="btn btn-outline-secondary w-100" title="Limpar">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="text-uppercase small fw-bold text-muted">Código</th>
                                <th class="text-uppercase small fw-bold text-muted">Data</th>
                                <th class="text-uppercase small fw-bold text-muted">Empresa Destinatária</th>
                                <th class="text-uppercase small fw-bold text-muted">Status</th>
                                <th class="text-uppercase small fw-bold text-muted text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($requisicoes as $requisicao)
                                <tr>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            {{ $requisicao->codigo_sequencial }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            <i class="far fa-calendar me-1"></i>
                                            {{ \Carbon\Carbon::parse($requisicao->data_requisicao)->format('d/m/Y') }}
                                        </span>
                                    </td>
                                    <td class="fw-medium text-dark">
                                        {{ $requisicao->empresa_destinataria }}
                                    </td>
                                    <td>
                                        @php
                                            $statusLabel = $requisicao->status instanceof \App\Enums\StatusRequisicao ? $requisicao->status->label() : ucfirst($requisicao->status);
                                            $statusClass = match(strtolower($statusLabel)) {
                                                'pendente' => 'bg-warning text-dark bg-opacity-75',
                                                'aprovado' => 'bg-success bg-opacity-75',
                                                'rejeitado' => 'bg-danger bg-opacity-75',
                                                'concluido', 'concluído' => 'bg-info text-dark bg-opacity-75',
                                                default => 'bg-secondary bg-opacity-75'
                                            };
                                        @endphp
                                        <span class="badge {{ $statusClass }} px-3 py-2 rounded-pill">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a href="{{ route('requisicoes.show', $requisicao->id) }}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Ver Detalhes">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('requisicoes.produtos.pdf', $requisicao->id) }}" target="_blank" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Gerar PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="mb-3 text-muted">
                                            <i class="fas fa-box-open fa-3x"></i>
                                        </div>
                                        <h5 class="text-muted">Nenhuma requisição encontrada</h5>
                                        <p class="text-muted mb-3">Tente ajustar os filtros ou crie uma nova requisição.</p>
                                        <a href="{{ route('requisicoes.produtos.create.novo') }}" class="btn btn-primary">
                                            <i class="fas fa-plus me-1"></i> Nova Requisição
                                        </a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if (method_exists($requisicoes, 'links'))
                    <div class="mt-4 d-flex justify-content-center">
                        {{ $requisicoes->appends(request()->query())->links('pagination::bootstrap-5') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
