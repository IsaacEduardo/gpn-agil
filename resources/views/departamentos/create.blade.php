@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-3 d-flex align-items-center justify-content-between">
        <h3>Novo Departamento</h3>
        <a href="{{ route('departamentos.index') }}" class="btn btn-secondary">Voltar</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('departamentos.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="nome" class="form-label">Nome</label>
                    <input type="text" name="nome" id="nome" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" required>
                    @error('nome')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="sigla" class="form-label">Sigla</label>
                    <input type="text" name="sigla" id="sigla" class="form-control @error('sigla') is-invalid @enderror" value="{{ old('sigla') }}" maxlength="10">
                    @error('sigla')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="gabinete_id" class="form-label">Gabinete</label>
                    <select name="gabinete_id" id="gabinete_id" class="form-select @error('gabinete_id') is-invalid @enderror" required>
                        <option value="">— Selecione —</option>
                        @foreach($gabinetes as $gabinete)
                            <option value="{{ $gabinete->id }}" {{ old('gabinete_id') == $gabinete->id ? 'selected' : '' }}>{{ $gabinete->nome }}</option>
                        @endforeach
                    </select>
                    @error('gabinete_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3 form-check form-switch p-3 bg-light rounded border">
                    <input class="form-check-input ms-0 me-2" type="checkbox" name="is_area_expediente" id="is_area_expediente" value="1" {{ old('is_area_expediente') ? 'checked' : '' }}>
                    <label class="form-check-label fw-bold" for="is_area_expediente">
                        <i class="fas fa-inbox me-1 text-primary"></i> É Área de Expediente do Gabinete?
                    </label>
                    <div class="form-text small">Usuários vinculados a este setor herdam privilégios operacionais para registrar protocolo de entrada e encaminhar documentos tratados.</div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="{{ route('departamentos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection