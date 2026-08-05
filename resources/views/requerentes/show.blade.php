@extends('layouts.app')

@section('title', 'Detalhes do Requerente')

@section('content')
<div class="container-fluid py-4">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <a href="{{ route('requerentes.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
                <i class="fas fa-arrow-left me-1"></i> Voltar à Lista
            </a>
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-user text-primary me-2"></i>{{ $requerente->nome_razao_social }}</h1>
            <p class="text-muted">NIF/BI: <code>{{ $requerente->nif_bi }}</code> | Cadastrado em {{ $requerente->created_at->format('d/m/Y H:i') }}</p>
        </div>
        <div>
            <a href="{{ route('solicitacoes.create') }}?requerente_id={{ $requerente->id }}" class="btn btn-success me-2">
                <i class="fas fa-plus-circle me-1"></i> Nova Solicitação de Lote
            </a>
            <a href="{{ route('requerentes.edit', $requerente) }}" class="btn btn-outline-warning">
                <i class="fas fa-edit me-1"></i> Editar
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light fw-bold"><i class="fas fa-info-circle me-1"></i> Dados Gerais</div>
                <div class="card-body">
                    <p class="mb-2"><strong>Tipo:</strong> 
                        <span class="badge {{ $requerente->tipo_pessoa === 'JURIDICA' ? 'bg-indigo text-white' : 'bg-info text-dark' }}">
                            {{ $requerente->tipo_pessoa === 'JURIDICA' ? 'Pessoa Jurídica' : 'Pessoa Física' }}
                        </span>
                    </p>
                    <p class="mb-2"><strong>E-mail:</strong> {{ $requerente->email ?? 'Não informado' }}</p>
                    <p class="mb-2"><strong>Telefone:</strong> {{ $requerente->telefone ?? 'Não informado' }}</p>
                    <p class="mb-2"><strong>Telemóvel Alt.:</strong> {{ $requerente->telemovel_alternativo ?? 'N/A' }}</p>
                    @if($requerente->representante_nome)
                        <hr>
                        <p class="mb-1"><strong>Representante:</strong> {{ $requerente->representante_nome }}</p>
                        <p class="mb-0"><strong>BI/NIF Rep.:</strong> {{ $requerente->representante_nif_bi }}</p>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-light fw-bold"><i class="fas fa-map-marker-alt me-1"></i> Endereço</div>
                <div class="card-body">
                    <p class="mb-1"><strong>Município:</strong> {{ $requerente->municipio }}</p>
                    <p class="mb-1"><strong>Comuna:</strong> {{ $requerente->comuna ?? 'N/A' }}</p>
                    <p class="mb-1"><strong>Bairro:</strong> {{ $requerente->bairro ?? 'N/A' }}</p>
                    <p class="mb-0"><strong>Endereço Completo:</strong><br><span class="text-muted">{{ $requerente->endereco_completo ?? 'Não detalhado' }}</span></p>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-file-contract me-1"></i> Histórico de Solicitações de Lote</span>
                    <span class="badge bg-primary">{{ $requerente->solicitacoes->count() }} solicitações</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Protocolo</th>
                                <th>Lote</th>
                                <th>Finalidade</th>
                                <th>Modalidade</th>
                                <th>Status</th>
                                <th class="text-end">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($requerente->solicitacoes as $sol)
                                <tr>
                                    <td><code>{{ $sol->numero_protocolo }}</code></td>
                                    <td>
                                        @if($sol->lote)
                                            <a href="{{ route('lotes.show', $sol->lote) }}" class="badge bg-light text-dark border text-decoration-none">
                                                <i class="fas fa-layer-group me-1"></i>{{ $sol->lote->codigo_lote }}
                                            </a>
                                        @else
                                            <span class="text-muted">Aguardando vinculação</span>
                                        @endif
                                    </td>
                                    <td>{{ $sol->finalidade_uso }}</td>
                                    <td>{{ $sol->modalidade_atribuicao }}</td>
                                    <td><span class="badge {{ $sol->status_badge }}">{{ $sol->status }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('solicitacoes.show', $sol) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        Nenhuma solicitação de lote vinculada a este requerente.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
