@extends('layouts.app')

@section('title', 'Requisições')

@section('breadcrumbs')
    <div class="container py-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
                <li class="breadcrumb-item active" aria-current="page">Requisições</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-file-alt me-2"></i>Requisições</h5>
                <a href="{{ route('requisicoes.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Nova Requisição
                </a>
            </div>

            <div class="card-body p-4">
                <div class="bg-light p-3 rounded mb-4 border">
                    <form id="filtrosForm" method="GET" action="{{ route('requisicoes.index') }}" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="q" class="form-label fw-bold text-muted small text-uppercase">Buscar</label>
                            <input type="text" id="q" name="q" value="{{ request('q') }}"
                                class="form-control" placeholder="Código, empresa, observações">
                        </div>
                        <div class="col-md-2">
                            <label for="tipo" class="form-label fw-bold text-muted small text-uppercase">Tipo</label>
                            <select id="tipo" name="tipo" class="form-select">
                                <option value="">Todos</option>
                                <option value="produto" {{ request('tipo') == 'produto' ? 'selected' : '' }}>Produto</option>
                                <option value="oficina" {{ request('tipo') == 'oficina' ? 'selected' : '' }}>Oficina</option>
                                <option value="servico" {{ request('tipo') == 'servico' ? 'selected' : '' }}>Serviço</option>
                                <option value="passagem" {{ request('tipo') == 'passagem' ? 'selected' : '' }}>Passagem</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label fw-bold text-muted small text-uppercase">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="">Todos</option>
                                <option value="pendente" {{ request('status') == 'pendente' ? 'selected' : '' }}>Pendente</option>
                                <option value="aprovado" {{ request('status') == 'aprovado' ? 'selected' : '' }}>Aprovado</option>
                                <option value="rejeitado" {{ request('status') == 'rejeitado' ? 'selected' : '' }}>Rejeitado</option>
                                <option value="concluido" {{ request('status') == 'concluido' ? 'selected' : '' }}>Concluído</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="empresa" class="form-label fw-bold text-muted small text-uppercase">Empresa</label>
                            <input type="text" id="empresa" name="empresa" value="{{ request('empresa') }}"
                                class="form-control" placeholder="Empresa">
                        </div>
                        <div class="col-md-2">
                            <label for="per_page" class="form-label fw-bold text-muted small text-uppercase">Por página</label>
                            <select id="per_page" name="per_page" class="form-select">
                                @php $pp = (int) request('per_page', 10); @endphp
                                <option value="10" {{ $pp == 10 ? 'selected' : '' }}>10</option>
                                <option value="25" {{ $pp == 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ $pp == 50 ? 'selected' : '' }}>50</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="solicitante" class="form-label fw-bold text-muted small text-uppercase">Solicitante</label>
                            <input type="text" id="solicitante" name="solicitante"
                                value="{{ request('solicitante') }}" class="form-control" placeholder="Nome">
                        </div>
                        <div class="col-md-3">
                            <label for="data_inicio" class="form-label fw-bold text-muted small text-uppercase">Data início</label>
                            <input type="date" id="data_inicio" name="data_inicio"
                                value="{{ request('data_inicio') }}" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label for="data_fim" class="form-label fw-bold text-muted small text-uppercase">Data fim</label>
                            <input type="date" id="data_fim" name="data_fim" value="{{ request('data_fim') }}"
                                class="form-control">
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100" title="Filtrar">
                                <i class="fas fa-search me-1"></i> Filtrar
                            </button>
                            <a href="{{ route('requisicoes.index') }}" class="btn btn-outline-secondary w-100" title="Limpar">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </form>
                </div>

                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
                        <i class="fas fa-check-circle me-2"></i>{{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                @php $dir = request('direction') === 'asc' ? 'desc' : 'asc'; @endphp
                                <th class="text-uppercase small fw-bold text-muted">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'codigo_sequencial', 'direction' => $dir]) }}" class="text-decoration-none text-muted">
                                        Código <i class="fas fa-sort ms-1"></i>
                                    </a>
                                </th>
                                <th class="text-uppercase small fw-bold text-muted">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'tipo', 'direction' => $dir]) }}" class="text-decoration-none text-muted">
                                        Tipo <i class="fas fa-sort ms-1"></i>
                                    </a>
                                </th>
                                <th class="text-uppercase small fw-bold text-muted">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'data_requisicao', 'direction' => $dir]) }}" class="text-decoration-none text-muted">
                                        Data <i class="fas fa-sort ms-1"></i>
                                    </a>
                                </th>
                                <th class="text-uppercase small fw-bold text-muted">Solicitante</th>
                                <th class="text-uppercase small fw-bold text-muted">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'empresa_destinataria', 'direction' => $dir]) }}" class="text-decoration-none text-muted">
                                        Empresa <i class="fas fa-sort ms-1"></i>
                                    </a>
                                </th>
                                <th class="text-uppercase small fw-bold text-muted">
                                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'status', 'direction' => $dir]) }}" class="text-decoration-none text-muted">
                                        Status <i class="fas fa-sort ms-1"></i>
                                    </a>
                                </th>
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
                                        @php 
                                            $tipo = $requisicao->tipo;
                                            $tipoValue = $tipo instanceof \App\Enums\TipoRequisicao ? $tipo->value : $tipo;
                                            
                                            $tipoLabel = $tipo instanceof \App\Enums\TipoRequisicao ? $tipo->label() : match($tipoValue) {
                                                'produto' => 'Produto',
                                                'oficina' => 'Oficina',
                                                'servico' => 'Serviço',
                                                'passagem' => 'Passagem',
                                                default => ucfirst($tipoValue),
                                            };

                                            $icon = match($tipoValue) {
                                                'produto' => 'fa-box-open',
                                                'oficina' => 'fa-tools',
                                                'servico' => 'fa-hand-holding-usd',
                                                'passagem' => 'fa-ticket-alt',
                                                default => 'fa-file'
                                            };
                                            
                                            $badgeClass = match($tipoValue) {
                                                'produto' => 'bg-info bg-opacity-10 text-info',
                                                'oficina' => 'bg-warning bg-opacity-10 text-warning',
                                                'servico' => 'bg-primary bg-opacity-10 text-primary',
                                                'passagem' => 'bg-success bg-opacity-10 text-success',
                                                default => 'bg-secondary bg-opacity-10 text-secondary'
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }} border border-0">
                                            <i class="fas {{ $icon }} me-1"></i> {{ $tipoLabel }}
                                        </span>

                                        @php
                                            $of = $tipoValue === 'oficina' ? optional($requisicao->oficina) : null;
                                            $manut = $of && $of->tipo_servico === 'Preventivo' ? 'Preventiva' : ($of && $of->tipo_servico ? 'Corretiva' : null);
                                            $u = $of ? $of->urgencia ?? null : null;
                                        @endphp
                                        @if ($of && ($manut || $u))
                                            <div class="small text-muted mt-1">
                                                @if ($manut)
                                                    <span class="badge bg-light text-dark border">{{ $manut }}</span>
                                                @endif
                                                @if ($u)
                                                    @switch($u)
                                                        @case('baixa') <span class="badge bg-success bg-opacity-75">Baixa</span> @break
                                                        @case('media') <span class="badge bg-primary bg-opacity-75">Média</span> @break
                                                        @case('alta') <span class="badge bg-warning text-dark bg-opacity-75">Alta</span> @break
                                                        @case('critica') <span class="badge bg-danger bg-opacity-75">Urgente</span> @break
                                                    @endswitch
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            <i class="far fa-calendar me-1"></i>
                                            {{ date('d/m/Y', strtotime($requisicao->data_requisicao)) }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-2 bg-secondary-subtle text-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <div class="fw-medium">{{ $requisicao->usuario?->name ?? 'N/A' }}</div>
                                                <div class="small text-muted">{{ $requisicao->usuario?->departamento?->sigla ?? '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fw-medium text-dark">{{ $requisicao->empresa_destinataria }}</td>
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
                                            @php
                                                $pdfRoute = match($tipoValue) {
                                                    'passagem' => route('requisicoes.passagem.pdf', $requisicao->id),
                                                    'oficina' => route('requisicoes.oficina.pdf', $requisicao->id),
                                                    'produto' => route('requisicoes.produtos.pdf', $requisicao->id),
                                                    'servico' => route('requisicoes.servico.pdf', $requisicao->id),
                                                    default => '#'
                                                };
                                            @endphp
                                            @if($pdfRoute !== '#')
                                                <a href="{{ $pdfRoute }}" target="_blank" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Gerar PDF">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="mb-3 text-muted">
                                            <i class="fas fa-file-alt fa-3x"></i>
                                        </div>
                                        <h5 class="text-muted">Nenhuma requisição encontrada</h5>
                                        <p class="text-muted mb-3">Tente ajustar os filtros ou crie uma nova requisição.</p>
                                        <a href="{{ route('requisicoes.create') }}" class="btn btn-primary">
                                            <i class="fas fa-plus me-1"></i> Nova Requisição
                                        </a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 d-flex justify-content-center">
                    {{ $requisicoes->appends(request()->query())->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
@endsection
