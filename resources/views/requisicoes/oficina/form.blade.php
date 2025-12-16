<div class="card mb-4">
    <div class="card-header bg-warning text-white">
        <h6 class="mb-0"><i class="fas fa-tools me-2"></i>Detalhes da Requisição de Oficina</h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label for="empresa_id" class="form-label">Empresa Destinatária</label>
            <select class="form-select @error('empresa_id') is-invalid @enderror" id="empresa_id" name="empresa_id"
                required>
                <option value="">Selecione uma empresa</option>
                @foreach ($empresas as $empresa)
                    <option value="{{ $empresa->id }}"
                        {{ old('empresa_id', $requisicao->empresa_id ?? '') == $empresa->id ? 'selected' : '' }}>
                        {{ $empresa->nome }}
                    </option>
                @endforeach
            </select>
            @error('empresa_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="viatura_id" class="form-label">Viatura</label>
                <select class="form-select @error('viatura_id') is-invalid @enderror" id="viatura_id" name="viatura_id"
                    required>
                    <option value="">Selecione uma viatura</option>
                    @foreach ($viaturas as $viatura)
                        <option value="{{ $viatura->id }}"
                            {{ old('viatura_id', optional($requisicao->oficina ?? null)->viatura_id) == $viatura->id ? 'selected' : '' }}>
                            {{ $viatura->placa }} - {{ $viatura->modelo }} ({{ $viatura->marca }})
                        </option>
                    @endforeach
                </select>
                @error('viatura_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="quilometragem_atual" class="form-label">Quilometragem Atual</label>
                <div class="input-group">
                    <input type="number" class="form-control @error('quilometragem_atual') is-invalid @enderror"
                        id="quilometragem_atual" name="quilometragem_atual"
                        value="{{ old('quilometragem_atual', optional($requisicao->oficina ?? null)->quilometragem_atual) }}">
                    <span class="input-group-text">km</span>
                    @error('quilometragem_atual')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="descricao_problema" class="form-label">Descrição do Problema</label>
            <textarea class="form-control @error('descricao_problema') is-invalid @enderror" id="descricao_problema"
                name="descricao_problema" rows="4" required>{{ old('descricao_problema', optional($requisicao->oficina ?? null)->descricao_problema) }}</textarea>
            @error('descricao_problema')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="servicos_solicitados" class="form-label">Serviços Solicitados</label>
            <textarea class="form-control @error('servicos_solicitados') is-invalid @enderror" id="servicos_solicitados"
                name="servicos_solicitados" rows="4">{{ old('servicos_solicitados', optional($requisicao->oficina ?? null)->servicos_solicitados) }}</textarea>
            <small class="form-text text-muted">Liste todos os serviços que devem ser realizados na viatura.</small>
            @error('servicos_solicitados')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <label for="tipo_manutencao" class="form-label">Tipo de Manutenção</label>
                @php $tipoSrv = optional($requisicao->oficina ?? null)->tipo_servico; @endphp
                @php $tipoManutencaoPrefill = $tipoSrv === 'Preventivo' ? 'Preventiva' : 'Corretiva'; @endphp
                <select class="form-select @error('tipo_manutencao') is-invalid @enderror" id="tipo_manutencao"
                    name="tipo_manutencao" required>
                    <option value="">Selecione</option>
                    <option value="Preventiva"
                        {{ old('tipo_manutencao', $tipoManutencaoPrefill) == 'Preventiva' ? 'selected' : '' }}>
                        Preventiva</option>
                    <option value="Corretiva"
                        {{ old('tipo_manutencao', $tipoManutencaoPrefill) == 'Corretiva' ? 'selected' : '' }}>Corretiva
                    </option>
                    <option value="Revisão" {{ old('tipo_manutencao') == 'Revisão' ? 'selected' : '' }}>Revisão
                    </option>
                </select>
                @error('tipo_manutencao')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="prioridade" class="form-label">Prioridade</label>
                @php
                    $urg = old('prioridade') ? null : optional($requisicao->oficina ?? null)->urgencia;
                    $map = ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta', 'critica' => 'Urgente'];
                    $priorSel = old('prioridade') ?? ($map[$urg] ?? null);
                @endphp
                <select class="form-select @error('prioridade') is-invalid @enderror" id="prioridade" name="prioridade"
                    required>
                    <option value="">Selecione</option>
                    <option value="Baixa" {{ $priorSel == 'Baixa' ? 'selected' : '' }}>Baixa</option>
                    <option value="Média" {{ $priorSel == 'Média' ? 'selected' : '' }}>Média</option>
                    <option value="Alta" {{ $priorSel == 'Alta' ? 'selected' : '' }}>Alta</option>
                    <option value="Urgente" {{ $priorSel == 'Urgente' ? 'selected' : '' }}>Urgente
                    </option>
                </select>
                @error('prioridade')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>



        <div class="form-check mb-3">
            <input class="form-check-input @error('autorizacao_pecas') is-invalid @enderror" type="checkbox"
                id="autorizacao_pecas" name="autorizacao_pecas" value="1"
                {{ old('autorizacao_pecas') ? 'checked' : '' }}>
            <label class="form-check-label" for="autorizacao_pecas">
                Autorizo a substituição de peças caso necessário
            </label>
            @error('autorizacao_pecas')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Carregar informações da viatura quando selecionada
            var viSel = document.getElementById('viatura_id');
            if (viSel) viSel.addEventListener('change', function() {
                const viaturaId = this.value;
                if (viaturaId) {
                    // Aqui você pode implementar uma chamada AJAX para buscar os dados da viatura
                    // e preencher automaticamente a quilometragem atual
                    // Exemplo simplificado:
                    /*
                    fetch(`/api/viaturas/${viaturaId}`)
                        .then(response => response.json())
                        .then(data => {
                            document.getElementById('quilometragem_atual').value = data.quilometragem;
                        });
                    */
                }
            });
        });
    </script>
@endsection
