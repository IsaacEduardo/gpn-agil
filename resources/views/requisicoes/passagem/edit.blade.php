@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-ticket-alt me-2"></i>Editar Requisição de Passagem {{ $requisicao->codigo_sequencial }}</h5>
                        <a href="{{ route('requisicoes.show', $requisicao->id) }}" class="btn btn-sm btn-light">
                            <i class="fas fa-arrow-left me-1"></i> Voltar
                        </a>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('requisicoes.passagem.update', $requisicao->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="empresa_id" class="form-label">Empresa Destinatária <span class="text-danger">*</span></label>
                                    <select class="form-select" id="empresa_id" name="empresa_id" required>
                                        @foreach ($empresas as $empresa)
                                            <option value="{{ $empresa->id }}" {{ ($requisicao->empresa_id ?? null) == $empresa->id ? 'selected' : '' }}>{{ $empresa->nome }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="beneficiario_nome" class="form-label">Nome do Beneficiário <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="beneficiario_nome" name="beneficiario_nome" value="{{ old('beneficiario_nome', $requisicao->passagem->beneficiario_nome ?? '') }}" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="destino" class="form-label">Destino <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="destino" name="destino" value="{{ old('destino', $requisicao->passagem->destino ?? '') }}" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Ida e Volta</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="ida_volta" name="ida_volta" value="1" {{ old('ida_volta', ($requisicao->passagem->ida_volta ?? false)) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="ida_volta">Marque se for ida e volta</label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label for="data_partida" class="form-label">Data de Partida <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="data_partida" name="data_partida" value="{{ old('data_partida', optional($requisicao->passagem->data_partida)->format('Y-m-d')) }}" required>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label for="data_regresso" class="form-label">Data de Regresso</label>
                                    <input type="date" class="form-control" id="data_regresso" name="data_regresso" value="{{ old('data_regresso', optional($requisicao->passagem->data_regresso)->format('Y-m-d')) }}">
                                </div>
                                <div class="col-md-9">
                                    <label for="observacoes" class="form-label">Observações</label>
                                    <textarea class="form-control" id="observacoes" name="observacoes" rows="3">{{ old('observacoes', $requisicao->observacoes) }}</textarea>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('requisicoes.show', $requisicao->id) }}" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Salvar Alterações</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

