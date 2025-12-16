@extends('layouts.app')

@section('title', 'Nova Credencial')

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
    </style>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-id-card me-2"></i>Nova Credencial de Viatura</h5>
                </div>
                <div class="card-body p-4">
                    <form id="credencialForm" method="POST" action="{{ route('credenciais.store') }}">
                        @csrf
                        
                        <div class="row g-4">
                            <!-- Seção: Beneficiário -->
                            <div class="col-12">
                                <h6 class="form-section-title"><i class="fas fa-user me-2"></i>Dados do Beneficiário</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-floating">
                                            <input type="text" id="beneficiario_nome" name="beneficiario_nome"
                                                class="form-control" required placeholder="Nome completo">
                                            <label for="beneficiario_nome">Nome Completo *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <input type="text" id="beneficiario_setor" name="beneficiario_setor"
                                                class="form-control" placeholder="Cargo/Função">
                                            <label for="beneficiario_setor">Cargo/Função</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <input type="text" id="beneficiario_documento" name="beneficiario_documento"
                                                class="form-control" required placeholder="BI/NIF">
                                            <label for="beneficiario_documento">Nº do BI *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <input type="date" id="beneficiario_documento_emitido_em" name="beneficiario_documento_emitido_em" class="form-control" required>
                                            <label for="beneficiario_documento_emitido_em">Data de Emissão do BI *</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <input type="text" id="beneficiario_documento_emitido_local" name="beneficiario_documento_emitido_local" class="form-control" placeholder="Local de Emissão" value="Arquivo de Identificação Nacional" required>
                                            <label for="beneficiario_documento_emitido_local">Local de Emissão *</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Seção: Viatura -->
                            <div class="col-12">
                                <h6 class="form-section-title"><i class="fas fa-car me-2"></i>Seleção de Viatura</h6>
                                <div class="alert alert-info border-0 shadow-sm">
                                    <i class="fas fa-info-circle me-2"></i>Selecione a viatura para a qual a credencial será emitida.
                                </div>
                                <div class="form-floating">
                                    <select id="viatura_id" name="viatura_id" class="form-select" required>
                                        <option value="">Selecione uma viatura...</option>
                                        @foreach ($viaturas as $v)
                                            <option value="{{ $v->id }}">{{ $v->identificacao }} - {{ $v->placa }} ({{ $v->marca }} {{ $v->modelo }})</option>
                                        @endforeach
                                    </select>
                                    <label for="viatura_id">Viatura *</label>
                                </div>
                            </div>

                            <!-- Seção: Observações -->
                            <div class="col-12">
                                <h6 class="form-section-title"><i class="fas fa-comment-alt me-2"></i>Observações</h6>
                                <div class="form-floating">
                                    <textarea id="observacoes" name="observacoes" class="form-control" style="height: 100px"
                                        placeholder="Observações"></textarea>
                                    <label for="observacoes">Informações adicionais (Opcional)</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                            <a href="{{ route('credenciais.index') }}" class="btn btn-light border">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-success px-4">
                                <i class="fas fa-file-pdf me-1"></i> Gerar Credencial
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
