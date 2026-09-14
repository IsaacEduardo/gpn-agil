@extends('layouts.app')

@section('title', 'Modelos de Despacho')

@section('breadcrumbs')
    <div class="container py-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-decoration-none text-muted">Início</a></li>
                <li class="breadcrumb-item active text-primary fw-bold" aria-current="page">Modelos de Despacho</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
    <div class="container pb-5">

        <div class="mb-4">
            <h2 class="fw-bold text-dark mb-1">Modelos de Despacho</h2>
            <p class="text-muted mb-0">
                Textos reutilizáveis, disponíveis no painel de ação rápida e no modal de despacho.
            </p>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $erro)
                        <li>{{ $erro }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-transparent py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-plus me-2 text-primary"></i>Novo modelo</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('modelos-despacho.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="novoTitulo" class="form-label small fw-bold text-muted text-uppercase">
                            Nome do modelo <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="titulo" id="novoTitulo" class="form-control"
                               value="{{ old('titulo') }}" maxlength="120" required
                               placeholder="Ex.: Encaminhar para parecer técnico">
                    </div>
                    <div class="mb-3">
                        <label for="novoTexto" class="form-label small fw-bold text-muted text-uppercase">
                            Texto do despacho <span class="text-danger">*</span>
                        </label>
                        <textarea name="texto" id="novoTexto" class="form-control" rows="4" required
                                  maxlength="5000">{{ old('texto') }}</textarea>
                    </div>

                    @if ($podeGerirInstitucionais)
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="institucional" value="1" id="novoInstitucional">
                            <label class="form-check-label small" for="novoInstitucional">
                                Modelo <strong>institucional</strong> — disponível a todos os que despacham.
                            </label>
                        </div>
                    @endif

                    <button type="submit" class="btn btn-primary fw-bold">Guardar modelo</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-transparent py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-user me-2 text-secondary"></i>Os meus modelos</h6>
                <span class="badge bg-secondary-subtle text-secondary rounded-pill">{{ $meus->count() }}</span>
            </div>
            <div class="card-body p-0">
                @forelse ($meus as $modelo)
                    <form action="{{ route('modelos-despacho.update', $modelo) }}" method="POST"
                          class="p-3 {{ ! $loop->last ? 'border-bottom' : '' }}">
                        @csrf @method('PUT')
                        <div class="row g-2 align-items-start">
                            <div class="col-md-4">
                                <input type="text" name="titulo" class="form-control form-control-sm fw-semibold"
                                       value="{{ $modelo->titulo }}" maxlength="120" required>
                                <div class="form-check mt-2">
                                    <input type="hidden" name="ativo" value="0">
                                    <input class="form-check-input" type="checkbox" name="ativo" value="1"
                                           id="ativo_{{ $modelo->id }}" {{ $modelo->ativo ? 'checked' : '' }}>
                                    <label class="form-check-label small text-muted" for="ativo_{{ $modelo->id }}">Ativo</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <textarea name="texto" class="form-control form-control-sm" rows="3"
                                          maxlength="5000" required>{{ $modelo->texto }}</textarea>
                            </div>
                            <div class="col-md-2 d-flex flex-column gap-2">
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                                    <i class="fas fa-save me-1"></i> Guardar
                                </button>
                            </div>
                        </div>
                    </form>
                    <form action="{{ route('modelos-despacho.destroy', $modelo) }}" method="POST"
                          class="px-3 pb-3 {{ ! $loop->last ? 'border-bottom' : '' }} text-end"
                          onsubmit="return confirm('Eliminar o modelo {{ $modelo->titulo }}?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-link text-danger text-decoration-none p-0">
                            <i class="fas fa-trash-alt me-1"></i> Eliminar
                        </button>
                    </form>
                @empty
                    <p class="text-center text-muted py-4 mb-0">Ainda não criou modelos.</p>
                @endforelse
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-transparent py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-landmark me-2 text-secondary"></i>Modelos institucionais</h6>
                <span class="badge bg-secondary-subtle text-secondary rounded-pill">{{ $institucionais->count() }}</span>
            </div>
            <div class="card-body p-0">
                @forelse ($institucionais as $modelo)
                    <div class="p-3 {{ ! $loop->last ? 'border-bottom' : '' }}">
                        <div class="fw-semibold text-dark">{{ $modelo->titulo }}</div>
                        <div class="small text-muted" style="white-space: pre-line;">{{ $modelo->texto }}</div>
                    </div>
                @empty
                    <p class="text-center text-muted py-4 mb-0">Não há modelos institucionais.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
