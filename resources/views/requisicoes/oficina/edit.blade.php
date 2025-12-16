@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-warning d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Editar Requisição de Oficina
                            {{ $requisicao->codigo_sequencial }}</h5>
                        <a href="{{ route('requisicoes.show', $requisicao->id) }}" class="btn btn-sm btn-light">
                            <i class="fas fa-arrow-left me-1"></i> Voltar
                        </a>
                    </div>

                    <div class="card-body">
                        @if (session('error'))
                            <div class="alert alert-danger">{{ session('error') }}</div>
                        @endif

                        <form action="{{ route('requisicoes.oficina.update', $requisicao->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            @include('requisicoes.oficina.form')

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('requisicoes.show', $requisicao->id) }}" class="btn btn-secondary">
                                    Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Salvar Alterações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
