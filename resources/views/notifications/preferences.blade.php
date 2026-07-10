@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0"><i class="fas fa-sliders-h me-2"></i>Preferências de notificação</h1>
        <a href="{{ route('notifications.page') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Voltar às notificações
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <p class="text-muted">
                Escolha em que canais quer receber cada tipo de notificação. As notificações
                <strong>no sistema (sino)</strong> são sempre mantidas, para garantir o registo e a rastreabilidade.
            </p>

            <form method="POST" action="{{ route('notifications.preferences.update') }}">
                @csrf
                @method('PUT')

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th scope="col">Categoria</th>
                                @foreach($channels as $channelKey => $channelLabel)
                                    <th scope="col" class="text-center">{{ $channelLabel }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($categories as $categoryKey => $categoryLabel)
                                <tr>
                                    <td>{{ $categoryLabel }}</td>
                                    @foreach($channels as $channelKey => $channelLabel)
                                        <td class="text-center">
                                            <div class="form-check d-flex justify-content-center">
                                                <input class="form-check-input" type="checkbox"
                                                    name="prefs[{{ $categoryKey }}][{{ $channelKey }}]"
                                                    value="1"
                                                    aria-label="{{ $categoryLabel }} — {{ $channelLabel }}"
                                                    @checked($matrix[$categoryKey][$channelKey] ?? true)>
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Guardar preferências
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
