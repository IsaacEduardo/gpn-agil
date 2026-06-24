@extends('layouts.app')

@section('title', 'Acesso Negado')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <div class="mb-4">
                <span class="fa-stack fa-4x text-muted opacity-50">
                    <i class="fas fa-circle fa-stack-2x"></i>
                    <i class="fas fa-lock fa-stack-1x fa-inverse"></i>
                </span>
            </div>
            
            <h1 class="display-4 fw-bold text-dark mb-3">Acesso Restrito</h1>
            
            <div class="alert alert-warning border-0 shadow-sm d-inline-block px-4 py-3 mb-4">
                <i class="fas fa-exclamation-triangle me-2"></i>
                @if($exception->getMessage())
                    {{ $exception->getMessage() }}
                @else
                    Você não tem permissão para acessar esta página ou realizar esta ação.
                @endif
            </div>

            <p class="lead text-muted mb-5">
                Esta área é restrita a perfis específicos. Se você acredita que isso é um erro, 
                entre em contato com o administrador do sistema ou seu chefe de departamento.
            </p>

            <div class="d-flex justify-content-center gap-3">
                <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-lg px-4">
                    <i class="fas fa-arrow-left me-2"></i> Voltar
                </a>
                <a href="{{ route('home') }}" class="btn btn-primary btn-lg px-4">
                    <i class="fas fa-home me-2"></i> Ir para o Início
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
