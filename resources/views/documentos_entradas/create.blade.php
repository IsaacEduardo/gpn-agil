@extends('layouts.app')

@section('title', 'Novo Documento de Entrada')

@section('breadcrumbs')
    <div class="container py-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-decoration-none text-muted">Início</a>
                </li>
                <li class="breadcrumb-item"><a href="{{ route('documentos-entradas.index') }}"
                        class="text-decoration-none text-muted">Entradas</a></li>
                <li class="breadcrumb-item active text-primary fw-bold" aria-current="page">Novo Registro</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
    <div class="container pb-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div>
                        <h2 class="fw-bold text-dark mb-1">Novo Documento</h2>
                        <p class="text-muted mb-0">Preencha os dados abaixo para registrar uma nova entrada.</p>
                    </div>
                    <a href="{{ route('documentos-entradas.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Cancelar
                    </a>
                </div>

                <form action="{{ route('documentos-entradas.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    {{-- Card 1: Informações Principais --}}
                    <div class="card shadow-sm border-0 rounded-3 mb-4">
                        <div class="card-body p-4">
                            <h5 class="card-title fw-bold text-primary mb-4 border-bottom pb-2">
                                <i class="fas fa-info-circle me-2"></i>Informações Principais
                            </h5>
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select name="classificacao_especie" class="form-select @error('classificacao_especie') is-invalid @enderror" id="floatingEspecie" required>
                                            <option value="" selected disabled>Selecione...</option>
                                            @foreach ($especies ?? [] as $opt)
                                                <option value="{{ $opt }}" {{ old('classificacao_especie') == $opt ? 'selected' : '' }}>{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                        <label for="floatingEspecie">Espécie do Documento <span class="text-danger">*</span></label>
                                        @error('classificacao_especie')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" name="classificacao_ref_numero" class="form-control @error('classificacao_ref_numero') is-invalid @enderror" id="floatingRef" placeholder="123/2025" value="{{ old('classificacao_ref_numero') }}">
                                        <label for="floatingRef">Número de Referência</label>
                                        @error('classificacao_ref_numero')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="date" name="data_documento" class="form-control @error('data_documento') is-invalid @enderror" id="floatingData" value="{{ old('data_documento') }}">
                                        <label for="floatingData">Data do Documento</label>
                                        @error('data_documento')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-floating">
                                        <input type="text" name="procedencia" class="form-control @error('procedencia') is-invalid @enderror" id="floatingProcedencia" placeholder="Origem" value="{{ old('procedencia') }}">
                                        <label for="floatingProcedencia">Procedência / Origem</label>
                                        @error('procedencia')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-floating">
                                        <input type="text" name="assunto" class="form-control @error('assunto') is-invalid @enderror" id="floatingAssunto" placeholder="Assunto" value="{{ old('assunto') }}" required>
                                        <label for="floatingAssunto">Assunto do Documento <span class="text-danger">*</span></label>
                                        @error('assunto')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-floating">
                                        <textarea name="observacoes" class="form-control @error('observacoes') is-invalid @enderror" id="floatingObs" placeholder="Obs" style="height: 100px">{{ old('observacoes') }}</textarea>
                                        <label for="floatingObs">Observações Adicionais</label>
                                        @error('observacoes')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label small fw-bold text-muted text-uppercase mb-1">Tags</label>
                                    <input type="text" name="tags" class="form-control" placeholder="Separe as tags por vírgula (ex: urgente, financeiro)" value="{{ old('tags') }}">
                                    <div class="form-text small">Use tags para facilitar a busca futura.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Card 2: Encaminhamento Inicial & Arquivos --}}
                    <div class="card shadow-sm border-0 rounded-3 mb-4">
                        <div class="card-body p-4">
                            <h5 class="card-title fw-bold text-primary mb-4 border-bottom pb-2">
                                <i class="fas fa-paperclip me-2"></i>Encaminhamento & Anexos
                            </h5>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <select name="departamento_id" class="form-select @error('departamento_id') is-invalid @enderror" id="floatingDep" required>
                                            <option value="" selected disabled>Selecione...</option>
                                            @foreach ($departamentos as $dep)
                                                <option value="{{ $dep->id }}" {{ (old('departamento_id') ?? ($userDepartamentoId ?? '')) == $dep->id ? 'selected' : '' }}>
                                                    {{ $dep->nome }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <label for="floatingDep">Departamento de Destino <span class="text-danger">*</span></label>
                                        @error('departamento_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="alert alert-info small mb-0">
                                        <i class="fas fa-info-circle me-1"></i> O documento será encaminhado automaticamente para a caixa de entrada do departamento selecionado.
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-muted">Arquivos Digitais</label>
                                    <x-file-upload name="anexos[]" id="fileInput" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-3">
                        <a href="{{ route('documentos-entradas.index') }}" class="btn btn-light btn-lg px-4">Cancelar</a>
                        <button type="submit" class="btn btn-primary btn-lg px-5 fw-bold shadow-sm">
                            <i class="fas fa-check me-2"></i> Registrar Documento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
