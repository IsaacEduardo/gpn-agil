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

                <hr class="my-4">

                <h2 class="h6 mb-3"><i class="fas fa-moon me-2"></i>Horário de silêncio e resumo</h2>
                <div class="row g-3 align-items-end">
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                id="quietHoursEnabled" name="quiet_hours_enabled" value="1"
                                @checked($settings->quiet_hours_enabled)>
                            <label class="form-check-label" for="quietHoursEnabled">
                                Silenciar e-mail e tempo real durante um período (as urgências e o sino não são afetados)
                            </label>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="quietStart" class="form-label small">Início</label>
                        <input type="time" class="form-control" id="quietStart" name="quiet_start"
                            value="{{ \Illuminate\Support\Str::of($settings->quiet_start)->substr(0, 5) }}">
                    </div>
                    <div class="col-6 col-md-3">
                        <label for="quietEnd" class="form-label small">Fim</label>
                        <input type="time" class="form-control" id="quietEnd" name="quiet_end"
                            value="{{ \Illuminate\Support\Str::of($settings->quiet_end)->substr(0, 5) }}">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="timezone" class="form-label small">Fuso horário</label>
                        <select class="form-select" id="timezone" name="timezone">
                            @foreach($timezones as $tz)
                                <option value="{{ $tz }}" @selected($settings->timezone === $tz)>{{ $tz }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch"
                                id="digestEnabled" name="digest_enabled" value="1"
                                @checked($settings->digest_enabled)>
                            <label class="form-check-label" for="digestEnabled">
                                Receber um resumo diário por e-mail das notificações não lidas
                            </label>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Guardar preferências
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
