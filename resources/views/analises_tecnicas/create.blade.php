@extends('layouts.app')

@section('title', 'Registrar Laudo Técnico')

@section('content')
<div class="container-fluid py-4">
    <div class="mb-4">
        <a href="{{ route('solicitacoes.show', $solicitacao) }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="fas fa-arrow-left me-1"></i> Voltar ao Processo
        </a>
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-clipboard-check text-primary me-2"></i>Registrar Laudo de Vistoria Técnica</h1>
        <p class="text-muted">Processo Protocolo: <code>{{ $solicitacao->numero_protocolo }}</code> | Requerente: {{ $solicitacao->requerente->nome_razao_social }}</p>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('analises-tecnicas.store', $solicitacao) }}">
                @csrf

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label font-weight-bold">Data da Vistoria de Campo <span class="text-danger">*</span></label>
                        <input type="date" name="data_vistoria" class="form-control @error('data_vistoria') is-invalid @enderror" value="{{ old('data_vistoria', date('Y-m-d')) }}" required>
                        @error('data_vistoria')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label font-weight-bold">Avaliação de Viabilidade Técnica <span class="text-danger">*</span></label>
                        <select name="viabilidade" class="form-select @error('viabilidade') is-invalid @enderror" required>
                            <option value="FAVORAVEL" {{ old('viabilidade') === 'FAVORAVEL' ? 'selected' : '' }}>Favorável (Sem restrições)</option>
                            <option value="FAVORAVEL_COM_RESTRICOES" {{ old('viabilidade') === 'FAVORAVEL_COM_RESTRICOES' ? 'selected' : '' }}>Favorável com Restrições</option>
                            <option value="DESFAVORAVEL" {{ old('viabilidade') === 'DESFAVORAVEL' ? 'selected' : '' }}>Desfavorável / Inviável</option>
                        </select>
                        @error('viabilidade')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Coordenadas de Campo (GPS / Vértices)</label>
                        <input type="text" name="coordenadas_vistoria" class="form-control" value="{{ old('coordenadas_vistoria') }}" placeholder="Ex: Lat: -15.1961, Long: 12.1522">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label font-weight-bold">Parecer Técnico Detalhado <span class="text-danger">*</span></label>
                    <textarea name="parecer_tecnico" class="form-control @error('parecer_tecnico') is-invalid @enderror" rows="6" required placeholder="Relatório de vistoria: topografia, acesso, infraestrutura disponível (água, energia, esgoto), presença de benfeitorias, interferências ambientais ou litígios.">{{ old('parecer_tecnico') }}</textarea>
                    @error('parecer_tecnico')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('solicitacoes.show', $solicitacao) }}" class="btn btn-light border">Cancelar</a>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Salvar Laudo Técnico</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
