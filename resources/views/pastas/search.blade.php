@extends('layouts.app')

@section('title', 'Busca no Arquivo')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="{{ route('pastas.index') }}" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left"></i> Voltar para Pastas
            </a>
            <h3 class="d-inline-block">Resultados da Busca: "{{ $query }}"</h3>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ route('pastas.search') }}" method="GET" class="row g-2">
                <div class="col-md-10">
                    <input type="text" name="query" class="form-control" value="{{ $query }}" placeholder="Pesquisar documentos arquivados...">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">
                        <i class="fas fa-search me-1"></i> Pesquisar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @if($documentos->isEmpty())
                <p class="text-center text-muted my-5">Nenhum documento encontrado.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Assunto</th>
                                <th>Procedência</th>
                                <th>Pasta</th>
                                <th>Arquivado Em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documentos as $documento)
                                <tr>
                                    <td>{{ $documento->numero_sequencial }}</td>
                                    <td>{{ $documento->assunto }}</td>
                                    <td>{{ $documento->procedencia }}</td>
                                    <td>
                                        @if($documento->pasta)
                                            <a href="{{ route('pastas.show', $documento->pasta_id) }}">
                                                <i class="fas fa-folder text-warning me-1"></i> 
                                                @if($documento->pasta->departamento)
                                                    <span class="text-muted small">[{{ $documento->pasta->departamento->gabinete->sigla ?? '?' }}/{{ $documento->pasta->departamento->sigla ?? '?' }}]</span>
                                                @endif
                                                {{ $documento->pasta->nome }}
                                            </a>
                                        @else
                                            <span class="text-muted">Sem pasta</span>
                                        @endif
                                    </td>
                                    <td>{{ $documento->arquivado_em ? $documento->arquivado_em->format('d/m/Y H:i') : '-' }}</td>
                                    <td>
                                        <a href="{{ route('documentos-entradas.show', $documento->id) }}" class="btn btn-sm btn-info text-white" title="Visualizar">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <form action="{{ route('documentos-entradas.desarquivar', $documento->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Deseja desarquivar este documento?');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-warning text-white" title="Desarquivar">
                                                <i class="fas fa-box-open"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($documentos->hasPages())
                    <div class="d-flex justify-content-center mt-3">
                        {{ $documentos->links() }}
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
