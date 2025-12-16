@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-edit me-2"></i>Editar Requisição {{ $requisicao->codigo_sequencial }}</h5>
                <a href="{{ route('requisicoes.show', $requisicao->id) }}" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
            </div>
            <div class="card-body">
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
                <form action="{{ route('requisicoes.update', $requisicao->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="empresa_destinataria" class="form-label">Empresa Destinatária</label>
                        <input type="text" class="form-control @error('empresa_destinataria') is-invalid @enderror"
                            id="empresa_destinataria" name="empresa_destinataria"
                            value="{{ old('empresa_destinataria', $requisicao->empresa_destinataria) }}" required>
                        @error('empresa_destinataria')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="observacoes" class="form-label">Observações</label>
                        <textarea class="form-control @error('observacoes') is-invalid @enderror" id="observacoes" name="observacoes"
                            rows="3">{{ old('observacoes', $requisicao->observacoes) }}</textarea>
                        @error('observacoes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('requisicoes.show', $requisicao->id) }}" class="btn btn-secondary">
                            Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
