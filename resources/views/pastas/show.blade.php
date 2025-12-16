@extends('layouts.app')

@section('title', 'Pasta: ' . $pasta->nome)

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="{{ route('pastas.index') }}" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <h3 class="d-inline-block">
                @if($pasta->departamento)
                    <span class="text-muted fs-5">[{{ $pasta->departamento->gabinete->sigla ?? '?' }}/{{ $pasta->departamento->sigla ?? '?' }}]</span>
                @endif
                {{ $pasta->nome }}
            </h3>
        </div>
    </div>
    <p class="text-muted">{{ $pasta->descricao }}</p>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">Documentos Arquivados</h5>
        </div>
        <div class="card-body">
            @if($pasta->documentos->isEmpty())
                <p class="text-center text-muted my-5">Nenhum documento arquivado nesta pasta.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Assunto</th>
                                <th>Procedência</th>
                                <th>Data Documento</th>
                                <th>Arquivado Em</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pasta->documentos as $documento)
                                <tr>
                                    <td>{{ $documento->numero_sequencial }}</td>
                                    <td>{{ $documento->assunto }}</td>
                                    <td>{{ $documento->procedencia }}</td>
                                    <td>{{ $documento->data_documento ? $documento->data_documento->format('d/m/Y') : '-' }}</td>
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
            @endif
        </div>
    </div>
</div>
@endsection
