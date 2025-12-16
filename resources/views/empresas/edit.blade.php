@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Editar Empresa</span>
                        <a href="{{ route('empresas.index') }}" class="btn btn-outline-secondary">Voltar</a>
                    </div>
                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">{{ session('status') }}</div>
                        @endif
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('empresas.update', $empresa) }}">
                            @csrf
                            @method('PUT')
                            @include('empresas.form', ['empresa' => $empresa])
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">Salvar</button>
                                <a href="{{ route('empresas.show', $empresa) }}"
                                    class="btn btn-outline-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection