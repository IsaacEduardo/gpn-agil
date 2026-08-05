@extends('layouts.app')

@section('title', 'Novo Requerente')

@section('content')
<div class="container-fluid py-4">
    <div class="mb-4">
        <a href="{{ route('requerentes.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="fas fa-arrow-left me-1"></i> Voltar à Lista
        </a>
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-user-plus text-primary me-2"></i>Novo Requerente</h1>
        <p class="text-muted">Cadastrar pessoa física ou jurídica para requisição de lote territorial.</p>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('requerentes.store') }}">
                @csrf

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label font-weight-bold">Tipo de Pessoa <span class="text-danger">*</span></label>
                        <select name="tipo_pessoa" class="form-select @error('tipo_pessoa') is-invalid @enderror" required>
                            <option value="FISICA" {{ old('tipo_pessoa') === 'FISICA' ? 'selected' : '' }}>Pessoa Física</option>
                            <option value="JURIDICA" {{ old('tipo_pessoa') === 'JURIDICA' ? 'selected' : '' }}>Pessoa Jurídica (Empresa/Entidade)</option>
                        </select>
                        @error('tipo_pessoa')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-5">
                        <label class="form-label font-weight-bold">Nome Completo / Razão Social <span class="text-danger">*</span></label>
                        <input type="text" name="nome_razao_social" class="form-control @error('nome_razao_social') is-invalid @enderror" value="{{ old('nome_razao_social') }}" required placeholder="Ex: Manuel António / Empresa XPTO Lda">
                        @error('nome_razao_social')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label font-weight-bold">NIF ou nº de BI <span class="text-danger">*</span></label>
                        <input type="text" name="nif_bi" class="form-control @error('nif_bi') is-invalid @enderror" value="{{ old('nif_bi') }}" required placeholder="Ex: 5001234567">
                        @error('nif_bi')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">E-mail</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="exemplo@dominio.ao">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Telefone Principal</label>
                        <input type="text" name="telefone" class="form-control @error('telefone') is-invalid @enderror" value="{{ old('telefone') }}" placeholder="+244 9XX XXX XXX">
                        @error('telefone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Telemóvel Alternativo</label>
                        <input type="text" name="telemovel_alternativo" class="form-control @error('telemovel_alternativo') is-invalid @enderror" value="{{ old('telemovel_alternativo') }}">
                        @error('telemovel_alternativo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="card-title text-secondary mb-3"><i class="fas fa-building me-1"></i>Representante Legal (Opcional para P. Jurídica)</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Nome do Representante Legal</label>
                        <input type="text" name="representante_nome" class="form-control" value="{{ old('representante_nome') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">NIF / BI do Representante Legal</label>
                        <input type="text" name="representante_nif_bi" class="form-control" value="{{ old('representante_nif_bi') }}">
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="card-title text-secondary mb-3"><i class="fas fa-map-marker-alt me-1"></i>Endereço e Localização</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Município</label>
                        <input type="text" name="municipio" class="form-control" value="{{ old('municipio', 'Namibe') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Comuna</label>
                        <input type="text" name="comuna" class="form-control" value="{{ old('comuna') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Bairro</label>
                        <input type="text" name="bairro" class="form-control" value="{{ old('bairro') }}">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Endereço Completo / Logradouro</label>
                        <textarea name="endereco_completo" class="form-control" rows="2" placeholder="Rua, Número da Casa, Referência">{{ old('endereco_completo') }}</textarea>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Observações Adicionais</label>
                    <textarea name="observacoes" class="form-control" rows="3">{{ old('observacoes') }}</textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('requerentes.index') }}" class="btn btn-light border">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Salvar Requerente</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
