@extends('layouts.app')

@section('title', 'Nova Requisição')

@section('content')
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-file-alt me-2"></i>
                @if ($tipo == 'produto')
                    Nova Requisição de Produtos
                @elseif($tipo == 'oficina')
                    Nova Requisição de Oficina
                @else
                    Nova Requisição de Serviços
                @endif
            </h5>
        </div>
        <div class="card-body">
            @if ($tipo == 'produto')
                <form action="{{ route('requisicoes.produtos.store.novo') }}" method="POST">
                @elseif($tipo == 'oficina')
                    <form action="{{ route('requisicoes.oficina.store.novo') }}" method="POST">
                    @else
                        <form action="{{ route('requisicoes.servico.store.novo') }}" method="POST">
            @endif
            @csrf
            <input type="hidden" name="tipo" value="{{ $tipo }}">

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="empresa_id" class="form-label"><i class="fas fa-building me-1"></i> Empresa Destinatária
                        <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-building"></i></span>
                        <select class="form-select @error('empresa_id') is-invalid @enderror" id="empresa_id"
                            name="empresa_id" required>
                            <option value="">Selecione uma empresa</option>
                            @foreach ($empresas as $empresa)
                                <option value="{{ $empresa->id }}"
                                    {{ old('empresa_id') == $empresa->id ? 'selected' : '' }}>
                                    {{ $empresa->nome }}
                                </option>
                            @endforeach
                        </select>
                        @error('empresa_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                
            </div>

            <div class="mb-3">
                <label for="observacoes" class="form-label"><i class="fas fa-comment-alt me-1"></i> Observações
                    Gerais</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-comment"></i></span>
                    <textarea class="form-control @error('observacoes') is-invalid @enderror" id="observacoes" name="observacoes"
                        rows="3" placeholder="Informações adicionais sobre a requisição">{{ old('observacoes') }}</textarea>
                    @error('observacoes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <small class="form-text text-muted">Adicione informações relevantes para esta requisição</small>
            </div>

            <hr class="my-4">

            <!-- Campos específicos para cada tipo de requisição -->
            @if ($tipo == 'produto')
                @include('requisicoes.produtos.form')
            @elseif($tipo == 'oficina')
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-car me-2"></i>Detalhes da Requisição de Oficina</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="viatura_id" class="form-label"><i class="fas fa-truck me-1"></i> Viatura <span
                                        class="text-danger">*</span></label>
                                <select class="form-select @error('viatura_id') is-invalid @enderror" id="viatura_id"
                                    name="viatura_id" required>
                                    <option value="">Selecione uma viatura...</option>
                                    @foreach ($viaturas as $viatura)
                                        <option value="{{ $viatura->id }}"
                                            {{ old('viatura_id') == $viatura->id ? 'selected' : '' }}>
                                            {{ $viatura->prefixo }} - {{ $viatura->placa }} ({{ $viatura->modelo }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('viatura_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="tipo_manutencao" class="form-label"><i class="fas fa-tools me-1"></i> Tipo de
                                    Manutenção <span class="text-danger">*</span></label>
                                <select class="form-select @error('tipo_manutencao') is-invalid @enderror"
                                    id="tipo_manutencao" name="tipo_manutencao" required>
                                    <option value="">Selecione...</option>
                                    <option value="Preventiva"
                                        {{ old('tipo_manutencao') == 'Preventiva' ? 'selected' : '' }}>Preventiva</option>
                                    <option value="Corretiva"
                                        {{ old('tipo_manutencao') == 'Corretiva' ? 'selected' : '' }}>Corretiva</option>
                                    <option value="Revisão" {{ old('tipo_manutencao') == 'Revisão' ? 'selected' : '' }}>
                                        Revisão</option>
                                </select>
                                @error('tipo_manutencao')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="descricao_problema" class="form-label"><i
                                        class="fas fa-exclamation-triangle me-1"></i> Descrição do Problema <span
                                        class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-comment-alt"></i></span>
                                    <textarea class="form-control @error('descricao_problema') is-invalid @enderror" id="descricao_problema"
                                        name="descricao_problema" rows="3" placeholder="Descreva detalhadamente o problema da viatura" required>{{ old('descricao_problema') }}</textarea>
                                    @error('descricao_problema')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <small class="text-muted">Forneça detalhes específicos sobre o problema para facilitar o
                                    diagnóstico.</small>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="prioridade" class="form-label"><i class="fas fa-flag me-1"></i> Prioridade
                                    <span class="text-danger">*</span></label>
                                <select class="form-select @error('prioridade') is-invalid @enderror" id="prioridade"
                                    name="prioridade" required>
                                    <option value="">Selecione...</option>
                                    <option value="Baixa" {{ old('prioridade') == 'Baixa' ? 'selected' : '' }}>Baixa
                                    </option>
                                    <option value="Média" {{ old('prioridade') == 'Média' ? 'selected' : '' }}>Média
                                    </option>
                                    <option value="Alta" {{ old('prioridade') == 'Alta' ? 'selected' : '' }}>Alta
                                    </option>
                                    <option value="Urgente" {{ old('prioridade') == 'Urgente' ? 'selected' : '' }}>Urgente
                                    </option>
                                </select>
                                @error('prioridade')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-wrench me-2"></i>Detalhes do Serviço</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tipo_servico" class="form-label"><i class="fas fa-cogs me-1"></i> Tipo de
                                    Serviço <span class="text-danger">*</span></label>
                                <select class="form-select @error('tipo_servico') is-invalid @enderror" id="tipo_servico"
                                    name="tipo_servico" required>
                                    <option value="">Selecione...</option>
                                    <option value="Limpeza" {{ old('tipo_servico') == 'Limpeza' ? 'selected' : '' }}>
                                        Limpeza</option>
                                    <option value="Manutenção Predial"
                                        {{ old('tipo_servico') == 'Manutenção Predial' ? 'selected' : '' }}>Manutenção
                                        Predial</option>
                                    <option value="Suporte de TI"
                                        {{ old('tipo_servico') == 'Suporte de TI' ? 'selected' : '' }}>Suporte de TI
                                    </option>
                                    <option value="Transporte"
                                        {{ old('tipo_servico') == 'Transporte' ? 'selected' : '' }}>Transporte</option>
                                    <option value="Segurança" {{ old('tipo_servico') == 'Segurança' ? 'selected' : '' }}>
                                        Segurança</option>
                                    <option value="Outro" {{ old('tipo_servico') == 'Outro' ? 'selected' : '' }}>Outro
                                    </option>
                                </select>
                                @error('tipo_servico')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 outro-tipo-servico" style="display: none;">
                                <label for="outro_tipo_servico" class="form-label"><i class="fas fa-edit me-1"></i>
                                    Especifique o Tipo de Serviço <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-pencil-alt"></i></span>
                                    <input type="text"
                                        class="form-control @error('outro_tipo_servico') is-invalid @enderror"
                                        id="outro_tipo_servico" name="outro_tipo_servico"
                                        value="{{ old('outro_tipo_servico') }}"
                                        placeholder="Especifique o tipo de serviço">
                                    @error('outro_tipo_servico')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="local_execucao" class="form-label"><i class="fas fa-map-marker-alt me-1"></i>
                                    Local de Execução <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-building"></i></span>
                                    <input type="text"
                                        class="form-control @error('local_execucao') is-invalid @enderror"
                                        id="local_execucao" name="local_execucao" value="{{ old('local_execucao') }}"
                                        required placeholder="Local onde o serviço será executado">
                                    @error('local_execucao')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="data_prevista" class="form-label"><i class="fas fa-calendar-alt me-1"></i>
                                    Data Prevista <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                    <input type="date"
                                        class="form-control @error('data_prevista') is-invalid @enderror"
                                        id="data_prevista" name="data_prevista" value="{{ old('data_prevista') }}"
                                        required>
                                    @error('data_prevista')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="descricao_servico" class="form-label"><i class="fas fa-file-alt me-1"></i>
                                Descrição do Serviço <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-comment-alt"></i></span>
                                <textarea class="form-control @error('descricao_servico') is-invalid @enderror" id="descricao_servico"
                                    name="descricao_servico" rows="3" required placeholder="Descreva detalhadamente o serviço necessário">{{ old('descricao_servico') }}</textarea>
                                @error('descricao_servico')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <small class="text-muted">Forneça detalhes específicos sobre o serviço para facilitar a
                                execução.</small>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="prioridade" class="form-label"><i class="fas fa-flag me-1"></i> Prioridade
                                    <span class="text-danger">*</span></label>
                                <select class="form-select @error('prioridade') is-invalid @enderror" id="prioridade"
                                    name="prioridade" required>
                                    <option value="">Selecione...</option>
                                    <option value="baixa" {{ old('prioridade') == 'baixa' ? 'selected' : '' }}>Baixa
                                    </option>
                                    <option value="media" {{ old('prioridade') == 'media' ? 'selected' : '' }}>Média
                                    </option>
                                    <option value="alta" {{ old('prioridade') == 'alta' ? 'selected' : '' }}>Alta
                                    </option>
                                    <option value="urgente" {{ old('prioridade') == 'urgente' ? 'selected' : '' }}>Urgente
                                    </option>
                                </select>
                                @error('prioridade')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="material_proprio"
                                        name="material_proprio" value="1"
                                        {{ old('material_proprio') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="material_proprio">
                                        <i class="fas fa-box me-1"></i> Material próprio (a instituição fornecerá o
                                        material)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="d-flex justify-content-between mt-4">
                <a href="{{ route('requisicoes.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Voltar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Salvar Requisição
                </button>
            </div>
            </form>
        </div>
    </div>

    {{-- Script removido: a lógica de linhas dinâmicas já vem do partial requisicoes.produtos.form --}}
@endsection
