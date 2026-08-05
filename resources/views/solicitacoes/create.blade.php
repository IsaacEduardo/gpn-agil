@extends('layouts.app')

@section('title', 'Nova Solicitação de Atribuição')

@section('content')
<div class="container-fluid py-4">
    <div class="mb-4">
        <a href="{{ route('solicitacoes.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="fas fa-arrow-left me-1"></i> Voltar à Lista
        </a>
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-plus-circle text-primary me-2"></i>Abrir Processo de Atribuição de Terra</h1>
        <p class="text-muted">Dar início à solicitação administrativa para concessão ou direito real de uso.</p>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('solicitacoes.store') }}">
                @csrf

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label font-weight-bold">Selecione o Requerente <span class="text-danger">*</span></label>
                        <select name="requerente_id" class="form-select @error('requerente_id') is-invalid @enderror" required>
                            <option value="">-- Selecione o Requerente --</option>
                            @foreach($requerentes as $req)
                                <option value="{{ $req->id }}" {{ (old('requerente_id', request('requerente_id')) == $req->id) ? 'selected' : '' }}>
                                    {{ $req->nome_razao_social }} (NIF/BI: {{ $req->nif_bi }})
                                </option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Não encontrou? <a href="{{ route('requerentes.create') }}" target="_blank">Cadastre um novo requerente</a>.</small>
                        @error('requerente_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Lote Pretendido (Opcional na Abertura)</label>
                        <select name="lote_id" class="form-select @error('lote_id') is-invalid @enderror">
                            <option value="">-- Vincular Lote Posteriormente / Em Triagem --</option>
                            @foreach($lotesDisponiveis as $lote)
                                <option value="{{ $lote->id }}" {{ old('lote_id') == $lote->id ? 'selected' : '' }}>
                                    Lote {{ $lote->codigo_lote }} - {{ number_format($lote->area_m2, 2, ',', '.') }} m² ({{ $lote->bairro_distrito ?? $lote->municipio }})
                                </option>
                            @endforeach
                        </select>
                        @error('lote_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label font-weight-bold">Finalidade do Uso da Terra <span class="text-danger">*</span></label>
                        <select name="finalidade_uso" class="form-select @error('finalidade_uso') is-invalid @enderror" required>
                            <option value="RESIDENCIAL" {{ old('finalidade_uso') === 'RESIDENCIAL' ? 'selected' : '' }}>Habitacional / Residencial</option>
                            <option value="COMERCIAL" {{ old('finalidade_uso') === 'COMERCIAL' ? 'selected' : '' }}>Comercial / Serviços</option>
                            <option value="AGROPECUARIA" {{ old('finalidade_uso') === 'AGROPECUARIA' ? 'selected' : '' }}>Agropecuária / Agrícola</option>
                            <option value="INDUSTRIAL" {{ old('finalidade_uso') === 'INDUSTRIAL' ? 'selected' : '' }}>Industrial</option>
                            <option value="SOCIAL_INSTITUCIONAL" {{ old('finalidade_uso') === 'SOCIAL_INSTITUCIONAL' ? 'selected' : '' }}>Social / Equipamento Público</option>
                        </select>
                        @error('finalidade_uso')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label font-weight-bold">Modalidade de Atribuição Pretendida <span class="text-danger">*</span></label>
                        <select name="modalidade_atribuicao" class="form-select @error('modalidade_atribuicao') is-invalid @enderror" required>
                            <option value="CDRU" {{ old('modalidade_atribuicao') === 'CDRU' ? 'selected' : '' }}>Concessão do Direito Real de Uso (CDRU)</option>
                            <option value="COMPRA_VENDA" {{ old('modalidade_atribuicao') === 'COMPRA_VENDA' ? 'selected' : '' }}>Compra e Venda</option>
                            <option value="PERMISSAO_USO" {{ old('modalidade_atribuicao') === 'PERMISSAO_USO' ? 'selected' : '' }}>Permissão Precária de Uso</option>
                            <option value="ARRENDAMENTO" {{ old('modalidade_atribuicao') === 'ARRENDAMENTO' ? 'selected' : '' }}>Arrendamento</option>
                        </select>
                        @error('modalidade_atribuicao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('solicitacoes.index') }}" class="btn btn-light border">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> Abrir Solicitação</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
