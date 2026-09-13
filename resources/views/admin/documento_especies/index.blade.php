@extends('layouts.app')

@section('title', 'Espécies de Documento')

@section('breadcrumbs')
    <div class="container py-3">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-decoration-none text-muted">Início</a></li>
                <li class="breadcrumb-item active text-primary fw-bold" aria-current="page">Espécies de Documento</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
    <div class="container pb-5">

        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1">Espécies de Documento</h2>
                <p class="text-muted mb-0">
                    O prazo de tratamento de cada espécie comanda os alertas de SLA dos documentos de entrada.
                </p>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-triangle-exclamation me-1"></i> {{ session('error') }}
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

        <div class="alert alert-info small">
            <i class="fas fa-circle-info me-1"></i>
            Espécies <strong>sem prazo definido</strong> usam o prazo global de <strong>{{ $prazoGlobal }} dias</strong>.
            O aviso de aproximação dispara a {{ (int) round($fracaoAviso * 100) }}% do prazo; o incumprimento, ao atingi-lo.
        </div>

        {{-- Nova espécie --}}
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-header bg-transparent py-3 border-bottom">
                <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-plus me-2 text-primary"></i>Nova espécie</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.documento-especies.store') }}" method="POST" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted text-uppercase">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control" value="{{ old('nome') }}" required maxlength="100">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Prazo (dias)</label>
                        <input type="number" name="prazo_tratamento_dias" class="form-control" value="{{ old('prazo_tratamento_dias') }}"
                               min="1" max="365" placeholder="{{ $prazoGlobal }} (global)">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted text-uppercase">Ordem</label>
                        <input type="number" name="ordem" class="form-control" value="{{ old('ordem') }}" min="0" max="999">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 fw-bold">Adicionar</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Lista --}}
        <div class="card shadow-sm border-0 rounded-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-uppercase small fw-bold text-muted">Espécie</th>
                            <th class="py-3 text-uppercase small fw-bold text-muted" style="width: 140px;">Prazo (dias)</th>
                            <th class="py-3 text-uppercase small fw-bold text-muted" style="width: 110px;">Ordem</th>
                            <th class="py-3 text-uppercase small fw-bold text-muted" style="width: 110px;">Ativa</th>
                            <th class="py-3 text-uppercase small fw-bold text-muted" style="width: 120px;">Em uso</th>
                            <th class="py-3 text-end pe-4 text-uppercase small fw-bold text-muted" style="width: 160px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($especies as $especie)
                            @php($uso = $emUso[$especie->nome] ?? 0)
                            <tr>
                                <form action="{{ route('admin.documento-especies.update', $especie) }}" method="POST"
                                      id="form-especie-{{ $especie->id }}">
                                    @csrf @method('PUT')
                                    <td class="ps-4">
                                        <input type="text" name="nome" class="form-control form-control-sm"
                                               value="{{ $especie->nome }}" required maxlength="100">
                                    </td>
                                    <td>
                                        <input type="number" name="prazo_tratamento_dias" class="form-control form-control-sm"
                                               value="{{ $especie->prazo_tratamento_dias }}" min="1" max="365"
                                               placeholder="{{ $prazoGlobal }}">
                                    </td>
                                    <td>
                                        <input type="number" name="ordem" class="form-control form-control-sm"
                                               value="{{ $especie->ordem }}" min="0" max="999">
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="ativo" value="0">
                                            <input class="form-check-input" type="checkbox" name="ativo" value="1"
                                                   {{ $especie->ativo ? 'checked' : '' }}>
                                        </div>
                                    </td>
                                </form>
                                <td>
                                    @if ($uso > 0)
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill">{{ $uso }} doc.</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <button type="submit" form="form-especie-{{ $especie->id }}"
                                            class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-save me-1"></i> Guardar
                                    </button>
                                    @if ($uso === 0)
                                        <form action="{{ route('admin.documento-especies.destroy', $especie) }}" method="POST"
                                              class="d-inline" onsubmit="return confirm('Eliminar a espécie {{ $especie->nome }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">Nenhuma espécie registada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection
