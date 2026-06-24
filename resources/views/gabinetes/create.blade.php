@extends('layouts.app')

@section('content')
<div class="container">
    <div class="mb-3 d-flex align-items-center justify-content-between">
        <h3>Novo Gabinete</h3>
        <a href="{{ route('gabinetes.index') }}" class="btn btn-secondary">Voltar</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('gabinetes.store') }}" method="POST">
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
                    <label for="responsavel_id" class="form-label">Responsável <span class="text-muted">(opcional)</span></label>
                    <select name="responsavel_id" id="responsavel_id" class="form-select @error('responsavel_id') is-invalid @enderror">
                        <option value="">— Selecione (opcional) —</option>
                        @foreach($usuarios as $usuario)
                            <option value="{{ $usuario->id }}" {{ old('responsavel_id') == $usuario->id ? 'selected' : '' }}>{{ $usuario->name }}</option>
                        @endforeach
                    </select>
                    @error('responsavel_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label for="super_chefe_id" class="form-label">Super Chefe de Gabinete <span class="text-muted">(opcional)</span></label>
                    <select name="super_chefe_id" id="super_chefe_id" class="form-select @error('super_chefe_id') is-invalid @enderror">
                        <option value="">— Selecione (opcional) —</option>
                        @foreach($usuarios as $usuario)
                            <option value="{{ $usuario->id }}" {{ old('super_chefe_id') == $usuario->id ? 'selected' : '' }}>{{ $usuario->name }}</option>
                        @endforeach
                    </select>
                    @error('super_chefe_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection