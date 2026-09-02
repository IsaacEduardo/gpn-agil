@extends('layouts.app')

@section('title', 'Nova Credencial de Viatura')

@section('styles')
    <style>
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

        .type-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.2rem;
            cursor: pointer;
            transition: all 0.25s ease-in-out;
            background-color: #fff;
        }

        .type-card:hover {
            border-color: #0d6efd;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.08);
            transform: translateY(-2px);
        }

        .type-card.active {
            border-color: #0d6efd;
            background-color: #f0f6ff;
        }

        .type-card .form-check-input {
            width: 1.25em;
            height: 1.25em;
            margin-top: 0.1em;
            cursor: pointer;
        }

        .animate-fade-in {
            animation: fadeIn 0.35s ease-in-out forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-id-card me-2"></i>Emitir Nova Credencial de Viatura</h5>
                    <a href="{{ route('credenciais.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Voltar à Lista
                    </a>
                </div>
                <div class="card-body p-4">
                    <form id="credencialForm" method="POST" action="{{ route('credenciais.store') }}">
                        @csrf

                        <div class="row g-4">
                            <!-- SEÇÃO 1: SELETOR DO TIPO DE CREDENCIAL -->
                            <div class="col-12">
                                <h6 class="form-section-title"><i class="fas fa-list-check me-2"></i>Tipo de Credencial *</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="type-card d-flex align-items-start gap-3 w-100 active" id="card_utilizacao_normal">
                                            <input type="radio" name="tipo_credencial" value="utilizacao_normal" class="form-check-input mt-1" checked required>
                                            <div>
                                                <div class="fw-bold text-dark fs-6"><i class="fas fa-car text-primary me-2"></i>Utilização Normal / Circulação Local</div>
                                                <small class="text-muted d-block mt-1">Autoriza circulação de rotina dentro e fora da localidade, incluindo finais de semana.</small>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="type-card d-flex align-items-start gap-3 w-100" id="card_seguir_viagem">
                                            <input type="radio" name="tipo_credencial" value="seguir_viagem" class="form-check-input mt-1" required>
                                            <div>
                                                <div class="fw-bold text-dark fs-6"><i class="fas fa-route text-success me-2"></i>Seguir Viagem (Missão Oficial)</div>
                                                <small class="text-muted d-block mt-1">Autoriza deslocamento entre províncias/municípios em missão oficial de serviço.</small>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- SEÇÃO 2: DADOS DO BENEFICIÁRIO -->
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="form-section-title mb-0 flex-grow-1"><i class="fas fa-user me-2"></i>Dados do Beneficiário</h6>
                                </div>

                                {{-- Autopreenchimento por Funcionário existente --}}
                                @if(isset($users) && $users->count() > 0)
                                    <div class="mb-3 p-3 bg-light rounded border">
                                        <label for="select_funcionario_auto" class="form-label small fw-bold text-muted mb-1">
                                            <i class="fas fa-magic me-1 text-primary"></i> Preencher dados por Funcionário Cadastrado (Opcional):
                                        </label>
                                        <select id="select_funcionario_auto" class="form-select form-select-sm">
                                            <option value="">Selecione para autopreencher dados pessoais...</option>
                                            @foreach($users as $u)
                                                <option value="{{ $u->id }}"
                                                        data-nome="{{ $u->name }}"
                                                        data-cargo="{{ $u->cargo ?? ($u->departamento?->nome ?? '') }}"
                                                        data-setor="{{ $u->departamento?->gabinete?->nome ?? ($u->departamento?->nome ?? '') }}">
                                                    {{ $u->name }} - {{ $u->cargo ?? ($u->departamento?->nome ?? 'Funcionário') }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" id="beneficiario_nome" name="beneficiario_nome"
                                                class="form-control" required placeholder="Nome completo" value="{{ old('beneficiario_nome') }}">
                                            <label for="beneficiario_nome">Nome Completo do Beneficiário *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <input type="text" id="beneficiario_setor" name="beneficiario_setor"
                                                class="form-control" placeholder="Cargo/Função" value="{{ old('beneficiario_setor') }}">
                                            <label for="beneficiario_setor">Cargo / Função *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <input type="text" id="beneficiario_documento" name="beneficiario_documento"
                                                class="form-control" required placeholder="BI/NIF" value="{{ old('beneficiario_documento') }}">
                                            <label for="beneficiario_documento">Nº do BI *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <input type="date" id="beneficiario_documento_emitido_em" name="beneficiario_documento_emitido_em" class="form-control" required value="{{ old('beneficiario_documento_emitido_em') }}">
                                            <label for="beneficiario_documento_emitido_em">Data de Emissão do BI *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="form-floating">
                                            <input type="text" id="beneficiario_documento_emitido_local" name="beneficiario_documento_emitido_local" class="form-control" placeholder="Local de Emissão" value="{{ old('beneficiario_documento_emitido_local', 'Arquivo de Identificação Nacional') }}" required>
                                            <label for="beneficiario_documento_emitido_local">Local de Emissão do BI *</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SEÇÃO 3: SELEÇÃO E DADOS DA VIATURA -->
                            <div class="col-12">
                                <h6 class="form-section-title"><i class="fas fa-car me-2"></i>Dados da Viatura</h6>
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <div class="form-floating">
                                            <select id="viatura_id" name="viatura_id" class="form-select" required>
                                                <option value="">Selecione a viatura da frota...</option>
                                                @foreach ($viaturas as $v)
                                                    <option value="{{ $v->id }}"
                                                            data-marca="{{ $v->marca }}"
                                                            data-modelo="{{ $v->modelo }}"
                                                            data-placa="{{ $v->placa }}"
                                                            data-motor="{{ $v->motor_numero }}"
                                                            data-cor="{{ $v->cor }}">
                                                        {{ $v->identificacao }} - {{ $v->placa }} ({{ $v->marca }} {{ $v->modelo }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            <label for="viatura_id">Viatura *</label>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" id="motor_numero" name="motor_numero" class="form-control" placeholder="Nº do Motor" value="{{ old('motor_numero') }}">
                                            <label for="motor_numero">Nº do Motor da Viatura</label>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" id="cor_viatura" name="cor_viatura" class="form-control" placeholder="Cor da Viatura" value="{{ old('cor_viatura') }}">
                                            <label for="cor_viatura">Cor da Viatura</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SEÇÃO 4: DADOS EXCLUSIVOS DE VIAGEM (CONDICIONAL PARA SEGUIR VIAGEM) -->
                            <div class="col-12 d-none animate-fade-in" id="secao_dados_viagem">
                                <div class="card border-primary border-2 bg-light">
                                    <div class="card-body">
                                        <h6 class="form-section-title text-primary border-primary mb-3"><i class="fas fa-map-marked-alt me-2"></i>Dados da Viagem em Missão Oficial</h6>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="form-floating">
                                                    <input type="text" id="origem_viagem" name="origem_viagem" class="form-control bg-white" placeholder="Origem" value="{{ old('origem_viagem', 'Província da Huíla') }}">
                                                    <label for="origem_viagem">Localidade / Província de Origem *</label>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-floating">
                                                    <input type="text" id="destino_viagem" name="destino_viagem" class="form-control bg-white" placeholder="Destino" value="{{ old('destino_viagem') }}">
                                                    <label for="destino_viagem">Localidade / Província de Destino *</label>
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <div class="form-floating">
                                                    <input type="text" id="instituicao_vinculo" name="instituicao_vinculo" class="form-control bg-white" placeholder="Instituição / Órgão" value="{{ old('instituicao_vinculo', 'Secretaria Geral do Governo Provincial da Huíla') }}">
                                                    <label for="instituicao_vinculo">Instituição / Órgão de Origem do Funcionário</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SEÇÃO 5: OBSERVAÇÕES -->
                            <div class="col-12">
                                <h6 class="form-section-title"><i class="fas fa-comment-alt me-2"></i>Observações Adicionais</h6>
                                <div class="form-floating">
                                    <textarea id="observacoes" name="observacoes" class="form-control" style="height: 90px"
                                        placeholder="Observações">{{ old('observacoes') }}</textarea>
                                    <label for="observacoes">Observações (Opcional)</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                            <a href="{{ route('credenciais.index') }}" class="btn btn-light border">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-success px-4 py-2 fw-bold">
                                <i class="fas fa-file-pdf me-2"></i> Emitir Credencial Oficial
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
        document.addEventListener('DOMContentLoaded', function () {
            const radioNormal = document.querySelector('input[name="tipo_credencial"][value="utilizacao_normal"]');
            const radioViagem = document.querySelector('input[name="tipo_credencial"][value="seguir_viagem"]');
            const cardNormal = document.getElementById('card_utilizacao_normal');
            const cardViagem = document.getElementById('card_seguir_viagem');
            const secaoViagem = document.getElementById('secao_dados_viagem');
            const inputOrigem = document.getElementById('origem_viagem');
            const inputDestino = document.getElementById('destino_viagem');

            const selectViatura = document.getElementById('viatura_id');
            const inputMotor = document.getElementById('motor_numero');
            const inputCor = document.getElementById('cor_viatura');

            const selectFuncionario = document.getElementById('select_funcionario_auto');
            const inputNome = document.getElementById('beneficiario_nome');
            const inputSetor = document.getElementById('beneficiario_setor');

            // 1. Alternância dos Tipos de Credencial
            function toggleTipoCredencial() {
                if (radioViagem.checked) {
                    cardViagem.classList.add('active');
                    cardNormal.classList.remove('active');
                    secaoViagem.classList.remove('d-none');
                    inputOrigem.setAttribute('required', 'required');
                    inputDestino.setAttribute('required', 'required');
                } else {
                    cardNormal.classList.add('active');
                    cardViagem.classList.remove('active');
                    secaoViagem.classList.add('d-none');
                    inputOrigem.removeAttribute('required');
                    inputDestino.removeAttribute('required');
                }
            }

            radioNormal.addEventListener('change', toggleTipoCredencial);
            radioViagem.addEventListener('change', toggleTipoCredencial);
            toggleTipoCredencial();

            // 2. Autopreenchimento de Metadados da Viatura
            selectViatura.addEventListener('change', function () {
                const opt = this.options[this.selectedIndex];
                if (opt && opt.value) {
                    const motor = opt.getAttribute('data-motor');
                    const cor = opt.getAttribute('data-cor');

                    if (motor && !inputMotor.value) {
                        inputMotor.value = motor;
                    }
                    if (cor && !inputCor.value) {
                        inputCor.value = cor;
                    }
                }
            });

            // 3. Autopreenchimento por Funcionário
            if (selectFuncionario) {
                selectFuncionario.addEventListener('change', function () {
                    const opt = this.options[this.selectedIndex];
                    if (opt && opt.value) {
                        if (opt.getAttribute('data-nome')) inputNome.value = opt.getAttribute('data-nome');
                        if (opt.getAttribute('data-cargo')) inputSetor.value = opt.getAttribute('data-cargo');
                    }
                });
            }
        });
    </script>
@endsection
