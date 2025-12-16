<div class="card mb-4">
    <div class="card-header bg-success text-white">
        <h6 class="mb-0"><i class="fas fa-concierge-bell me-2"></i>Detalhes da Requisição de Serviços</h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label for="empresa_id" class="form-label">Empresa Destinatária</label>
            <select class="form-select @error('empresa_id') is-invalid @enderror" id="empresa_id" name="empresa_id"
                required>
                <option value="">Selecione uma empresa</option>
                @foreach ($empresas as $empresa)
                    <option value="{{ $empresa->id }}"
                        {{ old('empresa_id', optional($requisicao ?? null)->empresa_id) == $empresa->id ? 'selected' : '' }}>
                        {{ $empresa->nome }}
                    </option>
                @endforeach
            </select>
            @error('empresa_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="tipo_servico" class="form-label">Tipo de Serviço</label>
            @php $srv = isset($requisicao) ? optional($requisicao->servicos) : null; @endphp
            <select class="form-select @error('tipo_servico') is-invalid @enderror" id="tipo_servico"
                name="tipo_servico" required>
                <option value="">Selecione</option>
                <option value="limpeza"
                    {{ old('tipo_servico', optional($srv)->tipo_servico) == 'limpeza' ? 'selected' : '' }}>Limpeza
                </option>
                <option value="manutencao_predial"
                    {{ old('tipo_servico', optional($srv)->tipo_servico) == 'manutencao_predial' ? 'selected' : '' }}>
                    Manutenção Predial</option>
                <option value="ti"
                    {{ old('tipo_servico', optional($srv)->tipo_servico) == 'ti' ? 'selected' : '' }}>Suporte de TI
                </option>
                <option value="transporte"
                    {{ old('tipo_servico', optional($srv)->tipo_servico) == 'transporte' ? 'selected' : '' }}>Transporte
                </option>
                <option value="seguranca"
                    {{ old('tipo_servico', optional($srv)->tipo_servico) == 'seguranca' ? 'selected' : '' }}>Segurança
                </option>
                <option value="outro"
                    {{ old('tipo_servico', optional($srv)->tipo_servico) == 'outro' ? 'selected' : '' }}>Outro</option>
            </select>
            @error('tipo_servico')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3" id="outroTipoContainer"
            style="{{ old('tipo_servico') == 'outro' ? '' : 'display: none;' }}">
            <label for="outro_tipo_servico" class="form-label">Especifique o Tipo de Serviço</label>
            <input type="text" class="form-control @error('outro_tipo_servico') is-invalid @enderror"
                id="outro_tipo_servico" name="outro_tipo_servico" value="{{ old('outro_tipo_servico') }}">
            @error('outro_tipo_servico')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="local_execucao" class="form-label">Local de Execução</label>
            <input type="text" class="form-control @error('local_execucao') is-invalid @enderror" id="local_execucao"
                name="local_execucao"
                value="{{ old('local_execucao', optional($srv)->local ?? optional($srv)->local_execucao) }}" required>
            <small class="form-text text-muted">Informe o local onde o serviço deverá ser executado.</small>
            @error('local_execucao')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="row mb-3">
            <div class="col-md-12">
                <label for="data_prevista" class="form-label">Data Prevista</label>
                @php $dataPrev = optional(optional($srv)->data_prevista ?? optional($srv)->data_hora_desejada)->format('Y-m-d'); @endphp
                <input type="date" class="form-control @error('data_prevista') is-invalid @enderror"
                    id="data_prevista" name="data_prevista" value="{{ old('data_prevista', $dataPrev) }}" required>
                @error('data_prevista')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-3">
            <label for="descricao_servico" class="form-label">Descrição Detalhada do Serviço</label>
            <textarea class="form-control @error('descricao_servico') is-invalid @enderror" id="descricao_servico"
                name="descricao_servico" rows="4" required>{{ old('descricao_servico', optional($srv)->descricao ?? optional($srv)->descricao_servico) }}</textarea>
            <small class="form-text text-muted">Descreva detalhadamente o serviço a ser realizado.</small>
            @error('descricao_servico')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="justificativa" class="form-label">Justificativa</label>
            <textarea class="form-control @error('justificativa') is-invalid @enderror" id="justificativa" name="justificativa"
                rows="3">{{ old('justificativa', optional($srv)->justificativa) }}</textarea>
            <small class="form-text text-muted">Justifique a necessidade deste serviço.</small>
            @error('justificativa')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="prioridade" class="form-label">Nível de Prioridade</label>
            @php $prior = optional($srv)->prioridade; @endphp
            <select class="form-select @error('prioridade') is-invalid @enderror" id="prioridade" name="prioridade"
                required>
                <option value="">Selecione</option>
                <option value="baixa" {{ old('prioridade', strtolower($prior)) == 'baixa' ? 'selected' : '' }}>Baixa
                </option>
                <option value="media" {{ old('prioridade', strtolower($prior)) == 'media' ? 'selected' : '' }}>Média
                </option>
                <option value="alta" {{ old('prioridade', strtolower($prior)) == 'alta' ? 'selected' : '' }}>Alta
                </option>
                <option value="urgente" {{ old('prioridade', strtolower($prior)) == 'urgente' ? 'selected' : '' }}>
                    Urgente</option>
            </select>
            @error('prioridade')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input @error('material_proprio') is-invalid @enderror" type="checkbox"
                id="material_proprio" name="material_proprio" value="1"
                {{ old('material_proprio') ? 'checked' : '' }}>
            <label class="form-check-label" for="material_proprio">
                O departamento fornecerá materiais para execução do serviço
            </label>
            @error('material_proprio')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div id="materiaisContainer" class="mb-3" style="{{ old('material_proprio') ? '' : 'display: none;' }}">
            <label for="materiais_fornecidos" class="form-label">Materiais a serem fornecidos</label>
            <textarea class="form-control @error('materiais_fornecidos') is-invalid @enderror" id="materiais_fornecidos"
                name="materiais_fornecidos" rows="3">{{ old('materiais_fornecidos') }}</textarea>
            <small class="form-text text-muted">Liste os materiais que serão fornecidos pelo departamento.</small>
            @error('materiais_fornecidos')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="observacoes" class="form-label">Observações</label>
            <textarea class="form-control @error('observacoes') is-invalid @enderror" id="observacoes" name="observacoes"
                rows="3">{{ old('observacoes', optional($requisicao ?? null)->observacoes) }}</textarea>
            <small class="form-text text-muted">Informações adicionais sobre a requisição.</small>
            @error('observacoes')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mostrar/ocultar campo para especificar outro tipo de serviço
            var tipoSel = document.getElementById('tipo_servico');
            if (tipoSel) tipoSel.addEventListener('change', function() {
                const outroTipoContainer = document.getElementById('outroTipoContainer');
                if (this.value === 'outro') {
                    outroTipoContainer.style.display = '';
                    document.getElementById('outro_tipo_servico').setAttribute('required', 'required');
                } else {
                    outroTipoContainer.style.display = 'none';
                    document.getElementById('outro_tipo_servico').removeAttribute('required');
                }
            });

            // Mostrar/ocultar campo para materiais fornecidos
            var mat = document.getElementById('material_proprio');
            if (mat) mat.addEventListener('change', function() {
                const materiaisContainer = document.getElementById('materiaisContainer');
                if (this.checked) {
                    materiaisContainer.style.display = '';
                    document.getElementById('materiais_fornecidos').setAttribute('required', 'required');
                } else {
                    materiaisContainer.style.display = 'none';
                    document.getElementById('materiais_fornecidos').removeAttribute('required');
                }
            });
        });
    </script>
@endsection
