@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Nova Requisição de Produtos</h5>
                            <a href="{{ route('requisicoes.index') }}" class="btn btn-sm btn-light">
                                <i class="fas fa-arrow-left me-1"></i> Voltar
                            </a>
                        </div>
                    </div>

                    <div class="card-body">
                        @if (session('error'))
                            <div class="alert alert-danger">
                                {{ session('error') }}
                            </div>
                        @endif

                        <form action="{{ route('requisicoes.produtos.store.novo') }}" method="POST">
                            @csrf

                            <!-- Informações Gerais -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informações Gerais</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label for="empresa_id" class="form-label">Empresa Destinatária *</label>
                                            <select class="form-select @error('empresa_id') is-invalid @enderror"
                                                id="empresa_id" name="empresa_id" required>
                                                <option value="">Selecione uma empresa</option>
                                                @foreach ($empresas as $empresa)
                                                    <option value="{{ $empresa->id }}"
                                                        {{ old('empresa_id') == $empresa->id ? 'selected' : '' }}>
                                                        {{ $empresa->nome }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('empresa_id')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label for="observacoes" class="form-label">Observações</label>
                                            <textarea class="form-control @error('observacoes') is-invalid @enderror" id="observacoes" name="observacoes"
                                                rows="3">{{ old('observacoes') }}</textarea>
                                            @error('observacoes')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @include('requisicoes.produtos.form')

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('requisicoes.index') }}" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Criar Requisição
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
