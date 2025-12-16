@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Detalhes da Empresa</span>
                        <div class="d-flex gap-2">
                            <a href="{{ route('empresas.index') }}" class="btn btn-outline-secondary">Voltar</a>
                            <a href="{{ route('empresas.edit', $empresa) }}" class="btn btn-warning">Editar</a>
                        </div>
                    </div>
                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">{{ session('status') }}</div>
                        @endif
                        <dl class="row">
                            <dt class="col-sm-3">Nome</dt>
                            <dd class="col-sm-9">{{ $empresa->nome }}</dd>

                            <dt class="col-sm-3">Contacto</dt>
                            <dd class="col-sm-9">{{ $empresa->contacto ?? '-' }}</dd>

                            <dt class="col-sm-3">Endereço</dt>
                            <dd class="col-sm-9">{{ $empresa->endereco ?? '-' }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection