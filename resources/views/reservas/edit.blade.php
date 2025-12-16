@extends('layouts.app')

@section('title', 'Editar Reserva')

@section('styles')
    <style>
        .availability-check {
            border: 2px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-top: 1rem;
            background-color: #f8f9fa;
        }

        .availability-success {
            border-color: #198754;
            background-color: #d1e7dd;
        }

        .availability-error {
            border-color: #dc3545;
            background-color: #f8d7da;
        }

        .form-floating>.form-control:focus~label,
        .form-floating>.form-control:not(:placeholder-shown)~label {
            opacity: .65;
            transform: scale(.85) translateY(-0.5rem) translateX(0.15rem);
        }
    </style>
@endsection

@section('content')
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Editar Reserva - {{ $reserva->codigo_reserva }}</h5>
        </div>
        <div class="card-body">
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <form action="{{ route('reservas.update', $reserva->id) }}" method="POST" id="reservaForm">
                @csrf
                @method('PUT')

                <div class="row">
                    <!-- Informações do Espaço -->
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3"><i class="fas fa-building me-2"></i>Informações do Espaço</h6>

                        <div class="form-floating mb-3">
                            <select class="form-select @error('tipo_espaco') is-invalid @enderror" id="tipo_espaco"
                                name="tipo_espaco" required>
                                <option value="">Selecione o espaço</option>
                                <option value="salao_nobre"
                                    {{ old('tipo_espaco', $reserva->tipo_espaco) == 'salao_nobre' ? 'selected' : '' }}>Salão
                                    Nobre</option>
                                <option value="anfiteatro"
                                    {{ old('tipo_espaco', $reserva->tipo_espaco) == 'anfiteatro' ? 'selected' : '' }}>
                                    Anfiteatro</option>
                            </select>
                            <label for="tipo_espaco">Tipo de Espaço *</label>
                            @error('tipo_espaco')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating mb-3">
                                    <input type="date" class="form-control @error('data_evento') is-invalid @enderror"
                                        id="data_evento" name="data_evento"
                                        value="{{ old('data_evento', $reserva->data_evento) }}" min="{{ date('Y-m-d') }}"
                                        required>
                                    <label for="data_evento">Data do Evento *</label>
                                    @error('data_evento')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating mb-3">
                                    <input type="time" class="form-control @error('hora_inicio') is-invalid @enderror"
                                        id="hora_inicio" name="hora_inicio"
                                        value="{{ old('hora_inicio', $reserva->hora_inicio) }}" required>
                                    <label for="hora_inicio">Hora Início *</label>
                                    @error('hora_inicio')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-floating mb-3">
                                    <input type="time" class="form-control @error('hora_fim') is-invalid @enderror"
                                        id="hora_fim" name="hora_fim" value="{{ old('hora_fim', $reserva->hora_fim) }}"
                                        required>
                                    <label for="hora_fim">Hora Fim *</label>
                                    @error('hora_fim')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Verificação de Disponibilidade -->
                        <div id="availability-result" class="availability-check d-none">
                            <div class="d-flex align-items-center">
                                <div class="spinner-border spinner-border-sm me-2 d-none" id="availability-loading"></div>
                                <i class="fas fa-check-circle text-success me-2 d-none" id="availability-success-icon"></i>
                                <i class="fas fa-times-circle text-danger me-2 d-none" id="availability-error-icon"></i>
                                <span id="availability-message"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Informações do Solicitante -->
                    <div class="col-md-6">
                        <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i>Informações do Solicitante</h6>

                        <div class="form-floating mb-3">
                            <input type="text" class="form-control @error('solicitante_nome') is-invalid @enderror"
                                id="solicitante_nome" name="solicitante_nome"
                                value="{{ old('solicitante_nome', $reserva->solicitante_nome) }}"
                                placeholder="Nome completo" required>
                            <label for="solicitante_nome">Nome Completo *</label>
                            @error('solicitante_nome')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-floating mb-3">
                            <input type="email" class="form-control @error('solicitante_email') is-invalid @enderror"
                                id="solicitante_email" name="solicitante_email"
                                value="{{ old('solicitante_email', $reserva->solicitante_email) }}"
                                placeholder="email@exemplo.com" required>
                            <label for="solicitante_email">E-mail *</label>
                            @error('solicitante_email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-floating mb-3">
                            <input type="tel" class="form-control @error('solicitante_telefone') is-invalid @enderror"
                                id="solicitante_telefone" name="solicitante_telefone"
                                value="{{ old('solicitante_telefone', $reserva->solicitante_telefone) }}"
                                placeholder="(11) 99999-9999">
                            <label for="solicitante_telefone">Telefone</label>
                            @error('solicitante_telefone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Informações do Evento -->
                <div class="row mt-3">
                    <div class="col-12">
                        <h6 class="text-primary mb-3"><i class="fas fa-calendar-check me-2"></i>Informações do Evento</h6>
                    </div>

                    <div class="col-md-8">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control @error('evento_titulo') is-invalid @enderror"
                                id="evento_titulo" name="evento_titulo"
                                value="{{ old('evento_titulo', $reserva->evento_titulo) }}"
                                placeholder="Título do evento" required>
                            <label for="evento_titulo">Título do Evento *</label>
                            @error('evento_titulo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-floating mb-3">
                            <input type="number" class="form-control @error('numero_participantes') is-invalid @enderror"
                                id="numero_participantes" name="numero_participantes"
                                value="{{ old('numero_participantes', $reserva->numero_participantes) }}" placeholder="0"
                                min="1">
                            <label for="numero_participantes">Nº de Participantes</label>
                            @error('numero_participantes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-floating mb-3">
                            <textarea class="form-control @error('evento_descricao') is-invalid @enderror" id="evento_descricao"
                                name="evento_descricao" style="height: 100px" placeholder="Descrição do evento">{{ old('evento_descricao', $reserva->evento_descricao) }}</textarea>
                            <label for="evento_descricao">Descrição do Evento</label>
                            @error('evento_descricao')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-floating mb-3">
                            <textarea class="form-control @error('observacoes') is-invalid @enderror" id="observacoes" name="observacoes"
                                style="height: 80px" placeholder="Observações adicionais">{{ old('observacoes', $reserva->observacoes) }}</textarea>
                            <label for="observacoes">Observações</label>
                            @error('observacoes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Informações de Status (somente leitura) -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="text-muted mb-3"><i class="fas fa-info-circle me-2"></i>Informações da Reserva
                                </h6>
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>Código:</strong> {{ $reserva->codigo_reserva }}
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Status:</strong>
                                        @if ($reserva->isPendente())
                                            <span class="badge bg-warning">{{ $reserva->status_nome }}</span>
                                        @elseif($reserva->isAprovada())
                                            <span class="badge bg-success">{{ $reserva->status_nome }}</span>
                                        @elseif($reserva->isRejeitada())
                                            <span class="badge bg-danger">{{ $reserva->status_nome }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ $reserva->status_nome }}</span>
                                        @endif
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Criado em:</strong> {{ $reserva->created_at->format('d/m/Y H:i') }}
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Criado por:</strong> {{ $reserva->usuario->name ?? 'N/A' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botões -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('reservas.show', $reserva->id) }}" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> Voltar
                            </a>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-save me-1"></i> Atualizar Reserva
                            </button>
                        </div>
                    </div>
                </div>
            </form>
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
                            hora_fim: horaFim.value,
                            exclude_id: {{ $reserva->id }}
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

            // Máscara para telefone
            const telefone = document.getElementById('solicitante_telefone');
            telefone.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 11) {
                    value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                    if (value.length < 14) {
                        value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
                    }
                }
                e.target.value = value;
            });

            // Verificar disponibilidade inicial
            checkAvailability();
        });
    </script>
@endsection