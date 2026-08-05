@extends('layouts.app')

@section('title', 'Solicitações de Atribuição de Lotes')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-contract text-primary me-2"></i>Solicitações de Atribuição de Terra</h1>
            <p class="text-muted">Workflow de triagem, vistorias técnicas, pareceres jurídicos e homologação de concessões.</p>
        </div>
        <a href="{{ route('solicitacoes.create') }}" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus-circle me-1"></i> Nova Solicitação
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- FILTROS -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('solicitacoes.index') }}" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Buscar Protocolo, Requerente ou NIF..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">-- Todos os Status do Workflow --</option>
                        <option value="SUBMETIDO" {{ request('status') === 'SUBMETIDO' ? 'selected' : '' }}>Submetido</option>
                        <option value="EM_TRIAGEM" {{ request('status') === 'EM_TRIAGEM' ? 'selected' : '' }}>Em Triagem</option>
                        <option value="EM_VISTORIA" {{ request('status') === 'EM_VISTORIA' ? 'selected' : '' }}>Em Vistoria Técnica</option>
                        <option value="EM_ANALISE_JURIDICA" {{ request('status') === 'EM_ANALISE_JURIDICA' ? 'selected' : '' }}>Em Análise Jurídica</option>
                        <option value="AGUARDANDO_HOMOLOGACAO" {{ request('status') === 'AGUARDANDO_HOMOLOGACAO' ? 'selected' : '' }}>Aguardando Homologação</option>
                        <option value="APROVADO" {{ request('status') === 'APROVADO' ? 'selected' : '' }}>Aprovado / Atribuído</option>
                        <option value="REJEITADO" {{ request('status') === 'REJEITADO' ? 'selected' : '' }}>Rejeitado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="finalidade_uso" class="form-select">
                        <option value="">-- Todas as Finalidades --</option>
                        <option value="RESIDENCIAL" {{ request('finalidade_uso') === 'RESIDENCIAL' ? 'selected' : '' }}>Residencial</option>
                        <option value="COMERCIAL" {{ request('finalidade_uso') === 'COMERCIAL' ? 'selected' : '' }}>Comercial</option>
                        <option value="AGROPECUARIA" {{ request('finalidade_uso') === 'AGROPECUARIA' ? 'selected' : '' }}>Agropecuária</option>
                        <option value="INDUSTRIAL" {{ request('finalidade_uso') === 'INDUSTRIAL' ? 'selected' : '' }}>Industrial</option>
                        <option value="SOCIAL_INSTITUCIONAL" {{ request('finalidade_uso') === 'SOCIAL_INSTITUCIONAL' ? 'selected' : '' }}>Social / Institucional</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-outline-primary"><i class="fas fa-filter me-1"></i> Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- TABELA KANBAN DE PROCESSOS -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nº Protocolo</th>
                        <th>Requerente</th>
                        <th>Lote Pretendido</th>
                        <th>Finalidade / Modalidade</th>
                        <th>Data Entrada</th>
                        <th>Status do Workflow</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($solicitacoes as $sol)
                        <tr>
                            <td><code>{{ $sol->numero_protocolo }}</code></td>
                            <td>
                                <strong>{{ $sol->requerente->nome_razao_social ?? 'N/A' }}</strong>
                                <br><small class="text-muted">NIF: {{ $sol->requerente->nif_bi ?? 'N/A' }}</small>
                            </td>
                            <td>
                                @if($sol->lote)
                                    <span class="badge bg-light text-dark border"><i class="fas fa-layer-group me-1"></i>{{ $sol->lote->codigo_lote }} ({{ number_format($sol->lote->area_m2, 2, ',', '.') }} m²)</span>
                                @else
                                    <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i>Pendente de escolha</span>
                                @endif
                            </td>
                            <td>
                                <small class="fw-bold d-block">{{ $sol->finalidade_uso }}</small>
                                <small class="text-muted">{{ $sol->modalidade_atribuicao }}</small>
                            </td>
                            <td>{{ $sol->data_solicitacao->format('d/m/Y H:i') }}</td>
                            <td><span class="badge {{ $sol->status_badge }} px-2 py-1">{{ $sol->status }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('solicitacoes.show', $sol) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-tasks me-1"></i> Gerenciar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fas fa-folder-open fa-2x mb-2"></i><br>
                                Nenhuma solicitação de atribuição de terra encontrada.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($solicitacoes->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $solicitacoes->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
