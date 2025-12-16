@php($editing = isset($empresa))

<div class="row g-3">
    <div class="col-md-6">
        <label for="nome" class="form-label">Nome da empresa</label>
        <input type="text" id="nome" name="nome" class="form-control"
            value="{{ old('nome', $empresa->nome ?? '') }}" required>
        @error('nome')
            <div class="text-danger small">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-3">
        <label for="contacto" class="form-label">Contacto</label>
        <input type="text" id="contacto" name="contacto" class="form-control"
            value="{{ old('contacto', $empresa->contacto ?? '') }}">
        @error('contacto')
            <div class="text-danger small">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-9">
        <label for="endereco" class="form-label">Endereço</label>
        <input type="text" id="endereco" name="endereco" class="form-control"
            value="{{ old('endereco', $empresa->endereco ?? '') }}">
        @error('endereco')
            <div class="text-danger small">{{ $message }}</div>
        @enderror
    </div>
</div>