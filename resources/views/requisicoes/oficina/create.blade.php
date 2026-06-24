@extends('layouts.app')

@section('title', 'Nova Requisição de Oficina')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-tools me-2"></i>Nova Requisição de Oficina</h2>
                    <a href="/" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Voltar
                    </a>
                </div>

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <form action="{{ route('requisicoes.oficina.store.novo') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @include('requisicoes.oficina.form')

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="/" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Criar Requisição
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection