@extends('layouts.app')

@section('title', 'Nova Reserva de Espaço')

@section('styles')
    <style>
        .availability-check {
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }

        .availability-success {
            border-color: #198754;
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .availability-error {
            border-color: #dc3545;
            background-color: #f8d7da;
            color: #842029;
        }

        .form-section-title {
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-plus-circle me-2"></i>Nova Reserva de Espaço</h5>
                </div>
                <div class="card-body p-4">
                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <form action="{{ route('reservas.store') }}" method="POST" id="reservaForm">
                        @csrf

                        <div class="row g-4">
                            <!-- Coluna Esquerda: Espaço e Horário -->
                            <div class="col-md-6">
                                <h6 class="form-section-title"><i class="fas fa-building me-2"></i>Dados do Espaço</h6>

                                <div class="form-floating mb-3">
                                    <select class="form-select @error('tipo_espaco') is-invalid @enderror" id="tipo_espaco"
                                        name="tipo_espaco" required>
                                        <option value="">Selecione o espaço</option>
                                        <option value="salao_nobre"
                                            {{ old('tipo_espaco') == 'salao_nobre' ? 'selected' : '' }}>
                                            Salão Nobre</option>
                                        <option value="anfiteatro"
                                            {{ old('tipo_espaco') == 'anfiteatro' ? 'selected' : '' }}>
                                            Anfiteatro</option>
                                    </select>
                                    <label for="tipo_espaco">Tipo de Espaço *</label>
                                    @error('tipo_espaco')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control @error('data_evento') is-invalid @enderror"
                                        id="data_evento" name="data_evento" value="{{ old('data_evento') }}"
                                        min="{{ date('Y-m-d') }}" required>
                                    <label for="data_evento">Data do Evento *</label>
                                    @error('data_evento')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="form-floating">
                                            <input type="time"
                                                class="form-control @error('hora_inicio') is-invalid @enderror"
                                                id="hora_inicio" name="hora_inicio" value="{{ old('hora_inicio') }}"
                                                required>
                                            <label for="hora_inicio">Início *</label>
                                            @error('hora_inicio')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-floating">
                                            <input type="time"
                                                class="form-control @error('hora_fim') is-invalid @enderror" id="hora_fim"
                                                name="hora_fim" value="{{ old('hora_fim') }}" required>
                                            <label for="hora_fim">Fim *</label>
                                            @error('hora_fim')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <!-- Verificação de Disponibilidade -->
                                <div id="availability-result" class="availability-check d-none">
                                    <div class="d-flex align-items-center">
                                        <div class="spinner-border spinner-border-sm me-2 d-none" id="availability-loading">
                                        </div>
                                        <i class="fas fa-check-circle text-success me-2 d-none fs-5"
                                            id="availability-success-icon"></i>
                                        <i class="fas fa-times-circle text-danger me-2 d-none fs-5"
                                            id="availability-error-icon"></i>
                                        <span id="availability-message" class="fw-medium"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Coluna Direita: Solicitante -->
                            <div class="col-md-6">
                                <h6 class="form-section-title"><i class="fas fa-user me-2"></i>Dados do Solicitante</h6>

                                <div class="form-floating mb-3">
                                    <input type="text"
                                        class="form-control @error('solicitante_nome') is-invalid @enderror"
                                        id="solicitante_nome" name="solicitante_nome"
                                        value="{{ old('solicitante_nome', Auth::user()->name) }}"
                                        placeholder="Nome completo" required>
                                    <label for="solicitante_nome">Nome Completo *</label>
                                    @error('solicitante_nome')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-floating mb-3">
                                    <input type="email"
                                        class="form-control @error('solicitante_email') is-invalid @enderror"
                                        id="solicitante_email" name="solicitante_email"
                                        value="{{ old('solicitante_email', Auth::user()->email) }}"
                                        placeholder="email@exemplo.com" required>
                                    <label for="solicitante_email">E-mail *</label>
                                    @error('solicitante_email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-floating mb-3">
                                    <input type="tel"
                                        class="form-control @error('solicitante_telefone') is-invalid @enderror"
                                        id="solicitante_telefone" name="solicitante_telefone"
                                        value="{{ old('solicitante_telefone') }}" placeholder="(11) 99999-9999">
                                    <label for="solicitante_telefone">Telefone</label>
                                    @error('solicitante_telefone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Linha Inferior: Evento -->
                            <div class="col-12">
                                <h6 class="form-section-title mt-2"><i class="fas fa-info-circle me-2"></i>Detalhes do
                                    Evento</h6>

                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <div class="form-floating">
                                            <input type="text"
                                                class="form-control @error('evento_titulo') is-invalid @enderror"
                                                id="evento_titulo" name="evento_titulo"
                                                value="{{ old('evento_titulo') }}" placeholder="Título do evento" required>
                                            <label for="evento_titulo">Título do Evento *</label>
                                            @error('evento_titulo')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="number"
                                                class="form-control @error('numero_participantes') is-invalid @enderror"
                                                id="numero_participantes" name="numero_participantes"
                                                value="{{ old('numero_participantes') }}" placeholder="0" min="1">
                                            <label for="numero_participantes">Nº de Participantes</label>
                                            @error('numero_participantes')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="form-floating">
                                            <textarea class="form-control @error('evento_descricao') is-invalid @enderror" id="evento_descricao"
                                                name="evento_descricao" style="height: 100px" placeholder="Descrição do evento">{{ old('evento_descricao') }}</textarea>
                                            <label for="evento_descricao">Descrição do Evento</label>
                                            @error('evento_descricao')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="form-floating">
                                            <textarea class="form-control @error('observacoes') is-invalid @enderror" id="observacoes" name="observacoes"
                                                style="height: 80px" placeholder="Observações adicionais">{{ old('observacoes') }}</textarea>
                                            <label for="observacoes">Observações</label>
                                            @error('observacoes')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                            <a href="{{ route('reservas.index') }}" class="btn btn-light border">
                                <i class="fas fa-arrow-left me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary px-4" id="submitBtn">
                                <i class="fas fa-check me-1"></i> Confirmar Reserva
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tipoEspaco = document.getElementById('tipo_espaco');
            const dataEvento = document.getElementById('data_evento');
            const horaInicio = document.getElementById('hora_inicio');
            const horaFim = document.getElementById('hora_fim');
            const availabilityResult = document.getElementById('availability-result');
            const availabilityMessage = document.getElementById('availability-message');
            const availabilityLoading = document.getElementById('availability-loading');
            const availabilitySuccessIcon = document.getElementById('availability-success-icon');
            const availabilityErrorIcon = document.getElementById('availability-error-icon');
            const submitBtn = document.getElementById('submitBtn');

            let checkTimeout;

            function checkAvailability() {
                if (!tipoEspaco.value || !dataEvento.value || !horaInicio.value || !horaFim.value) {
                    availabilityResult.classList.add('d-none');
                    return;
                }

                // Validação local de horário
                if (horaInicio.value >= horaFim.value) {
                    availabilityResult.classList.remove('d-none', 'availability-success');
                    availabilityResult.classList.add('availability-error');
                    availabilityLoading.classList.add('d-none');
                    availabilitySuccessIcon.classList.add('d-none');
                    availabilityErrorIcon.classList.remove('d-none');
                    availabilityMessage.textContent = 'A hora de fim deve ser posterior à hora de início.';
                    submitBtn.disabled = true;
                    return;
                }

                // Mostrar loading
                availabilityResult.classList.remove('d-none', 'availability-success', 'availability-error');
                availabilityLoading.classList.remove('d-none');
                availabilitySuccessIcon.classList.add('d-none');
                availabilityErrorIcon.classList.add('d-none');
                availabilityMessage.textContent = 'Verificando disponibilidade...';
                submitBtn.disabled = true;

                // Fazer requisição AJAX
                fetch('{{ route('reservas.verificar-disponibilidade') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            tipo_espaco: tipoEspaco.value,
                            data_evento: dataEvento.value,
                            hora_inicio: horaInicio.value,
                            hora_fim: horaFim.value
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        availabilityLoading.classList.add('d-none');

                        if (data.disponivel) {
                            availabilityResult.classList.add('availability-success');
                            availabilitySuccessIcon.classList.remove('d-none');
                            availabilityMessage.textContent = data.message;
                            submitBtn.disabled = false;
                        } else {
                            availabilityResult.classList.add('availability-error');
                            availabilityErrorIcon.classList.remove('d-none');
                            availabilityMessage.textContent = data.message;
                            submitBtn.disabled = true;
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        availabilityLoading.classList.add('d-none');
                        availabilityResult.classList.add('availability-error');
                        availabilityErrorIcon.classList.remove('d-none');
                        availabilityMessage.textContent = 'Erro ao verificar disponibilidade';
                        submitBtn.disabled = true;
                    });
            }

            function debounceCheck() {
                clearTimeout(checkTimeout);
                checkTimeout = setTimeout(checkAvailability, 500);
            }

            // Adicionar eventos
            tipoEspaco.addEventListener('change', debounceCheck);
            dataEvento.addEventListener('change', debounceCheck);
            horaInicio.addEventListener('change', debounceCheck);
            horaFim.addEventListener('change', debounceCheck);
        });
    </script>
@endsection
