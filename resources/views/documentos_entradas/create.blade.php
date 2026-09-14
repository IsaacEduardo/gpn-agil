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

                @error('duplicado')
                    <div class="alert alert-warning border-warning shadow-sm mb-4">
                        <div class="d-flex align-items-start gap-2">
                            <i class="fas fa-triangle-exclamation mt-1"></i>
                            <div>
                                <div class="fw-bold mb-1">Possível registo duplicado</div>
                                <div class="small">{{ $message }}</div>
                                @if (session('duplicado_id'))
                                    <a href="{{ route('documentos-entradas.show', session('duplicado_id')) }}"
                                       target="_blank" class="small fw-semibold text-decoration-none">
                                        Ver o documento já registado <i class="fas fa-arrow-up-right-from-square ms-1"></i>
                                    </a>
                                @endif
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="confirmar_duplicado"
                                           value="1" id="confirmarDuplicado" form="formNovaEntrada">
                                    <label class="form-check-label small fw-semibold" for="confirmarDuplicado">
                                        É um documento diferente ou uma segunda via — registar mesmo assim.
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                @enderror

                <form id="formNovaEntrada" action="{{ route('documentos-entradas.store') }}" method="POST" enctype="multipart/form-data">
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

                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="date" name="data_entrada" class="form-control @error('data_entrada') is-invalid @enderror" id="floatingDataEntrada" max="{{ now()->toDateString() }}" value="{{ old('data_entrada', now()->toDateString()) }}">
                                        <label for="floatingDataEntrada">Data de Entrada (receção)</label>
                                        @error('data_entrada')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="form-text small">Altere se estiver a registar correspondência recebida em data anterior.</div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold text-muted small mb-1">Procedência / Origem</label>
                                    <x-procedencia-combobox name="procedencia_id" :procedencias="$procedencias ?? null" :selected="old('procedencia_id')" />
                                    @error('procedencia_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    @error('procedencia')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
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
                                        <select name="departamento_id" class="form-select @error('departamento_id') is-invalid @enderror" id="floatingDep" required
                                                data-sugestao-destino='@json($sugestoesDestino ?? [])'>
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
                                    <div id="avisoSugestaoDestino" class="alert alert-primary small py-2 mb-2 d-none">
                                        <i class="fas fa-wand-magic-sparkles me-1"></i>
                                        Destino preenchido a partir do histórico desta procedência. Altere se não for o caso.
                                    </div>

                                    <div class="alert alert-info small mb-0">
                                        <i class="fas fa-info-circle me-1"></i> A chefia do departamento selecionado e o responsável do gabinete são notificados assim que o registo for concluído.
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

    {{-- Componente Modal do Scanner Direct (WebScan Bridge) --}}
    <x-webscan-modal targetInputId="fileInput" />
@endsection

@push('scripts')
<script>
    // Ao escolher a procedencia, propoe o destino que o historico indica.
    // Nunca sobrepoe uma escolha ja feita pelo operador.
    document.addEventListener('DOMContentLoaded', function () {
        const destino = document.getElementById('floatingDep');
        const aviso = document.getElementById('avisoSugestaoDestino');
        if (!destino) return;

        let sugestoes = {};
        try { sugestoes = JSON.parse(destino.dataset.sugestaoDestino || '{}'); } catch (e) { return; }

        const combo = document.querySelector('[name="procedencia_id"]');
        if (!combo) return;

        let tocadoPeloOperador = false;
        destino.addEventListener('change', function () { tocadoPeloOperador = true; });

        combo.addEventListener('change', function () {
            if (tocadoPeloOperador) return;

            const sugerido = sugestoes[combo.value];
            if (!sugerido) { aviso.classList.add('d-none'); return; }

            destino.value = sugerido;
            aviso.classList.remove('d-none');
        });
    });
</script>
@endpush
