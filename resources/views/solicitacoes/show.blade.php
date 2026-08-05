@extends('layouts.app')

@section('title', 'Processo ' . $solicitacao->numero_protocolo)

@section('content')
<div class="container-fluid py-4">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <a href="{{ route('solicitacoes.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
                <i class="fas fa-arrow-left me-1"></i> Voltar às Solicitações
            </a>
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-file-contract text-primary me-2"></i>Protocolo: {{ $solicitacao->numero_protocolo }}</h1>
            <p class="text-muted">Aberto em {{ $solicitacao->data_solicitacao->format('d/m/Y H:i') }} por {{ $solicitacao->createdBy->name ?? 'Sistema' }}</p>
        </div>
        <div class="d-flex gap-2">
            @if($solicitacao->status === 'APROVADO')
                @if($solicitacao->documento_interno_termo_id)
                    <a href="{{ route('documentos-internos.show', $solicitacao->documento_interno_termo_id) }}" class="btn btn-success">
                        <i class="fas fa-file-pdf me-1"></i> Ver Termo de Atribuição Emitido
                    </a>
                @else
                    <form method="POST" action="{{ route('solicitacoes.emitir-termo', $solicitacao) }}">
                        @csrf
                        <button type="submit" class="btn btn-success shadow-sm">
                            <i class="fas fa-certificate me-1"></i> Emitir Termo de Atribuição Oficial
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <!-- PAINEL DA ESQUERDA: STATUS E VINCULACÃO -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light fw-bold"><i class="fas fa-tasks me-1"></i> Status do Workflow</div>
                <div class="card-body text-center p-4">
                    <span class="badge {{ $solicitacao->status_badge }} fs-5 px-3 py-2 mb-3">{{ $solicitacao->status }}</span>
                    @if($solicitacao->motivo_rejeicao)
                        <div class="alert alert-danger text-start mt-2">
                            <strong>Motivo de Indefereimento:</strong><br>{{ $solicitacao->motivo_rejeicao }}
                        </div>
                    @endif
                    <hr>
                    <!-- TRANSIÇÃO DE STATUS -->
                    <form method="POST" action="{{ route('solicitacoes.transicionar', $solicitacao) }}" class="text-start">
                        @csrf
                        @method('PATCH')
                        <label class="form-label font-weight-bold">Avançar / Alterar Fase do Processo:</label>
                        <select name="novo_status" class="form-select mb-3" onchange="toggleMotivo(this.value)">
                            <option value="EM_TRIAGEM" {{ $solicitacao->status === 'EM_TRIAGEM' ? 'selected' : '' }}>1. Em Triagem Documental</option>
                            <option value="EM_VISTORIA" {{ $solicitacao->status === 'EM_VISTORIA' ? 'selected' : '' }}>2. Em Vistoria Técnica de Campo</option>
                            <option value="EM_ANALISE_JURIDICA" {{ $solicitacao->status === 'EM_ANALISE_JURIDICA' ? 'selected' : '' }}>3. Em Análise Jurídica</option>
                            <option value="AGUARDANDO_HOMOLOGACAO" {{ $solicitacao->status === 'AGUARDANDO_HOMOLOGACAO' ? 'selected' : '' }}>4. Aguardando Homologação da Direção</option>
                            <option value="APROVADO" {{ $solicitacao->status === 'APROVADO' ? 'selected' : '' }}>5. Aprovar e Homologar Atribuição</option>
                            <option value="REJEITADO" {{ $solicitacao->status === 'REJEITADO' ? 'selected' : '' }}>X. Indeferir / Rejeitar Solicitação</option>
                            <option value="CANCELADO" {{ $solicitacao->status === 'CANCELADO' ? 'selected' : '' }}>X. Cancelar Processo</option>
                        </select>
                        
                        <div id="divMotivo" class="mb-3 d-none">
                            <label class="form-label text-danger font-weight-bold">Descreva o Motivo da Rejeição:</label>
                            <textarea name="motivo_rejeicao" class="form-control" rows="2">{{ $solicitacao->motivo_rejeicao }}</textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-sync-alt me-1"></i> Atualizar Status</button>
                    </form>
                </div>
            </div>

            <!-- VINCULAÇÃO DE LOTE -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light fw-bold"><i class="fas fa-layer-group me-1"></i> Lote Territorial</div>
                <div class="card-body">
                    @if($solicitacao->lote)
                        <div class="p-3 bg-light rounded border mb-3">
                            <h5 class="mb-1"><a href="{{ route('lotes.show', $solicitacao->lote) }}" class="text-decoration-none">{{ $solicitacao->lote->codigo_lote }}</a></h5>
                            <p class="mb-1"><strong>Área:</strong> {{ number_format($solicitacao->lote->area_m2, 2, ',', '.') }} m²</p>
                            <p class="mb-1"><strong>Zoneamento:</strong> {{ $solicitacao->lote->zoneamento }}</p>
                            <p class="mb-0"><strong>Localização:</strong> {{ $solicitacao->lote->bairro_distrito ?? $solicitacao->lote->municipio }}</p>
                        </div>
                    @else
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-circle me-1"></i> Nenhum lote vinculado a este pedido.
                        </div>
                    @endif

                    @if($solicitacao->status !== 'APROVADO' && $solicitacao->status !== 'REJEITADO')
                        <form method="POST" action="{{ route('solicitacoes.vincular-lote', $solicitacao) }}">
                            @csrf
                            <label class="form-label font-weight-bold">Reservar/Alterar Lote:</label>
                            <select name="lote_id" class="form-select mb-2" required>
                                <option value="">-- Selecione o Lote --</option>
                                @foreach($lotesDisponiveis as $lote)
                                    <option value="{{ $lote->id }}" {{ $solicitacao->lote_id == $lote->id ? 'selected' : '' }}>
                                        Lote {{ $lote->codigo_lote }} - {{ number_format($lote->area_m2, 2, ',', '.') }} m² ({{ $lote->bairro_distrito ?? $lote->municipio }})
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-link me-1"></i> Vincular Lote ao Pedido</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <!-- PAINEL DA DIREITA: REQUERENTE E VISTORIAS TÉCNICAS -->
        <div class="col-md-8">
            <!-- REQUERENTE -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light fw-bold"><i class="fas fa-user me-1"></i> Requerente Beneficiário</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Nome:</strong> <a href="{{ route('requerentes.show', $solicitacao->requerente) }}">{{ $solicitacao->requerente->nome_razao_social }}</a></p>
                            <p class="mb-1"><strong>NIF / BI:</strong> <code>{{ $solicitacao->requerente->nif_bi }}</code></p>
                            <p class="mb-0"><strong>Tipo:</strong> {{ $solicitacao->requerente->tipo_pessoa === 'JURIDICA' ? 'Pessoa Jurídica' : 'Pessoa Física' }}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Finalidade Pretendida:</strong> <span class="badge bg-primary">{{ $solicitacao->finalidade_uso }}</span></p>
                            <p class="mb-1"><strong>Modalidade Jurídica:</strong> {{ $solicitacao->modalidade_atribuicao }}</p>
                            <p class="mb-0"><strong>Contato:</strong> {{ $solicitacao->requerente->telefone ?? 'N/A' }} | {{ $solicitacao->requerente->email ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VISTORIAS E LAUDOS TÉCNICOS -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-clipboard-check me-1"></i> Laudos e Vistorias Técnicas de Campo</span>
                    @if($solicitacao->status !== 'APROVADO' && $solicitacao->status !== 'REJEITADO')
                        <a href="{{ route('analises-tecnicas.create', $solicitacao) }}" class="btn btn-sm btn-success">
                            <i class="fas fa-plus me-1"></i> Novo Laudo Técnico
                        </a>
                    @endif
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Data Vistoria</th>
                                    <th>Técnico Responsável</th>
                                    <th>Viabilidade</th>
                                    <th>Parecer Técnico</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($solicitacao->analisesTecnicas as $analise)
                                    <tr>
                                        <td>{{ $analise->data_vistoria->format('d/m/Y') }}</td>
                                        <td><strong>{{ $analise->tecnico->name ?? 'Técnico' }}</strong></td>
                                        <td>
                                            <span class="badge {{ $analise->viabilidade === 'FAVORAVEL' ? 'bg-success' : ($analise->viabilidade === 'FAVORAVEL_COM_RESTRICOES' ? 'bg-warning' : 'bg-danger') }}">
                                                {{ $analise->viabilidade }}
                                            </span>
                                        </td>
                                        <td>{{ Str::limit($analise->parecer_tecnico, 120) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            Nenhum laudo técnico de vistoria registrado até o momento.
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
</div>

<script>
function toggleMotivo(status) {
    var div = document.getElementById('divMotivo');
    if (status === 'REJEITADO' || status === 'CANCELADO') {
        div.classList.remove('d-none');
    } else {
        div.classList.add('d-none');
    }
}
</script>
@endsection
