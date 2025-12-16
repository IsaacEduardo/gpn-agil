@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-gradient-primary">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-ticket-alt me-2"></i>Nova Requisição de Bilhete de Passagem
                            </h5>
                            <a href="{{ route('requisicoes.index') }}" class="btn btn-sm btn-light">
                                <i class="fas fa-arrow-left me-1"></i> Voltar
                            </a>
                        </div>
                    </div>

                    <div class="card-body">
                        <form action="{{ route('requisicoes.passagem.store.novo') }}" method="POST">
                            @csrf

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="empresa_id" class="form-label">Empresa Destinatária <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-building"></i></span>
                                        <select class="form-select @error('empresa_id') is-invalid @enderror"
                                            id="empresa_id" name="empresa_id" required>
                                            <option value="">Selecione uma empresa</option>
                                            @foreach ($empresas as $empresa)
                                                <option value="{{ $empresa->id }}"
                                                    {{ old('empresa_id') == $empresa->id ? 'selected' : '' }}>
                                                    {{ $empresa->nome }}</option>
                                            @endforeach
                                        </select>
                                        @error('empresa_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="beneficiario_nome" class="form-label">Nome do Beneficiário <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text"
                                            class="form-control @error('beneficiario_nome') is-invalid @enderror"
                                            id="beneficiario_nome" name="beneficiario_nome"
                                            value="{{ old('beneficiario_nome') }}" required placeholder="Nome completo">
                                        @error('beneficiario_nome')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="destino" class="form-label">Destino <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                        <input type="text" class="form-control @error('destino') is-invalid @enderror"
                                            id="destino" name="destino" value="{{ old('destino') }}" required
                                            placeholder="Ex.: Namibe-Luanda-Namibe">
                                        @error('destino')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="data_partida" class="form-label">Data de Partida <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-plane-departure"></i></span>
                                        <input type="date"
                                            class="form-control @error('data_partida') is-invalid @enderror"
                                            id="data_partida" name="data_partida" value="{{ old('data_partida') }}"
                                            required>
                                        @error('data_partida')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Ida e Volta</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="ida_volta" name="ida_volta"
                                            value="1" {{ old('ida_volta') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="ida_volta">Marque se for ida e volta</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label for="data_regresso" class="form-label">Data de Regresso</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-plane-arrival"></i></span>
                                        <input type="date"
                                            class="form-control @error('data_regresso') is-invalid @enderror"
                                            id="data_regresso" name="data_regresso" value="{{ old('data_regresso') }}">
                                        @error('data_regresso')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="observacoes" class="form-label">Observações</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-comment-alt"></i></span>
                                    <textarea class="form-control @error('observacoes') is-invalid @enderror" id="observacoes" name="observacoes"
                                        rows="3" placeholder="Informações adicionais">{{ old('observacoes') }}</textarea>
                                    @error('observacoes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Salvar
                                    Requisição</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
