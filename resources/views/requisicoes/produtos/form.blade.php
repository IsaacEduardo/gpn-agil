<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h6 class="mb-0"><i class="fas fa-boxes me-2"></i>Detalhes dos Produtos</h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <div class="d-flex justify-content-between mb-2">
                <h6>Lista de Produtos</h6>
                <button type="button" class="btn btn-sm btn-primary" id="addProdutoBtn">
                    <i class="fas fa-plus me-1"></i> Adicionar Produto
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered" id="produtosTable">
                    <thead class="table-light">
                        <tr>
                            <th>Nome do Produto</th>
                            <th>Quantidade</th>
                            <th>Unidade</th>
                            <th>Observações</th>
                            <th>Prioridade</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="produtosTableBody">
                        @if (old('produtos'))
                            @foreach (old('produtos') as $index => $produto)
                                <tr>
                                    <td>
                                        <input type="text" class="form-control"
                                            name="produtos[{{ $index }}][nome_produto]"
                                            value="{{ $produto['nome_produto'] ?? '' }}" required>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control"
                                            name="produtos[{{ $index }}][quantidade]"
                                            value="{{ $produto['quantidade'] ?? '' }}" min="1" required>
                                    </td>
                                    <td>
                                        <select class="form-select" name="produtos[{{ $index }}][unidade_medida]"
                                            required>
                                            <option value="">Selecione</option>
                                            <option value="unidade"
                                                {{ isset($produto['unidade_medida']) && $produto['unidade_medida'] == 'unidade' ? 'selected' : '' }}>
                                                Unidade</option>
                                            <option value="caixa"
                                                {{ isset($produto['unidade_medida']) && $produto['unidade_medida'] == 'caixa' ? 'selected' : '' }}>
                                                Caixa</option>
                                            <option value="pacote"
                                                {{ isset($produto['unidade_medida']) && $produto['unidade_medida'] == 'pacote' ? 'selected' : '' }}>
                                                Pacote</option>
                                            <option value="kg"
                                                {{ isset($produto['unidade_medida']) && $produto['unidade_medida'] == 'kg' ? 'selected' : '' }}>
                                                Kg</option>
                                            <option value="litro"
                                                {{ isset($produto['unidade_medida']) && $produto['unidade_medida'] == 'litro' ? 'selected' : '' }}>
                                                Litro</option>
                                            <option value="metro"
                                                {{ isset($produto['unidade_medida']) && $produto['unidade_medida'] == 'metro' ? 'selected' : '' }}>
                                                Metro</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control"
                                            name="produtos[{{ $index }}][observacoes]"
                                            value="{{ $produto['observacoes'] ?? '' }}">
                                    </td>
                                    <td>
                                        <select class="form-select" name="produtos[{{ $index }}][prioridade]"
                                            required>
                                            <option value="">Selecione</option>
                                            <option value="Baixa"
                                                {{ isset($produto['prioridade']) && $produto['prioridade'] == 'Baixa' ? 'selected' : '' }}>
                                                Baixa</option>
                                            <option value="Média"
                                                {{ isset($produto['prioridade']) && $produto['prioridade'] == 'Média' ? 'selected' : '' }}>
                                                Média</option>
                                            <option value="Alta"
                                                {{ isset($produto['prioridade']) && $produto['prioridade'] == 'Alta' ? 'selected' : '' }}>
                                                Alta</option>
                                            <option value="Urgente"
                                                {{ isset($produto['prioridade']) && $produto['prioridade'] == 'Urgente' ? 'selected' : '' }}>
                                                Urgente</option>
                                        </select>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger removerProduto">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        @elseif(isset($requisicao) && $requisicao && $requisicao->produtos && $requisicao->produtos->count())
                            @foreach ($requisicao->produtos as $index => $p)
                                <tr>
                                    <td>
                                        <input type="text" class="form-control"
                                            name="produtos[{{ $index }}][nome_produto]"
                                            value="{{ $p->nome_produto ?? ($p->nome ?? ($p->descricao ?? '')) }}"
                                            required>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control"
                                            name="produtos[{{ $index }}][quantidade]"
                                            value="{{ $p->quantidade ?? '' }}" min="1" required>
                                    </td>
                                    <td>
                                        @php $unMed = $p->unidade_medida ?? $p->unidade; @endphp
                                        <select class="form-select"
                                            name="produtos[{{ $index }}][unidade_medida]" required>
                                            <option value="">Selecione</option>
                                            <option value="unidade" {{ $unMed == 'unidade' ? 'selected' : '' }}>Unidade
                                            </option>
                                            <option value="caixa" {{ $unMed == 'caixa' ? 'selected' : '' }}>Caixa
                                            </option>
                                            <option value="pacote" {{ $unMed == 'pacote' ? 'selected' : '' }}>Pacote
                                            </option>
                                            <option value="kg" {{ $unMed == 'kg' ? 'selected' : '' }}>Kg</option>
                                            <option value="litro" {{ $unMed == 'litro' ? 'selected' : '' }}>Litro
                                            </option>
                                            <option value="metro" {{ $unMed == 'metro' ? 'selected' : '' }}>Metro
                                            </option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control"
                                            name="produtos[{{ $index }}][observacoes]"
                                            value="{{ $p->observacoes ?? ($p->finalidade ?? '') }}">
                                    </td>
                                    <td>
                                        @php $prior = $p->prioridade ?? ''; @endphp
                                        <select class="form-select" name="produtos[{{ $index }}][prioridade]"
                                            required>
                                            <option value="">Selecione</option>
                                            <option value="Baixa" {{ $prior == 'Baixa' ? 'selected' : '' }}>Baixa
                                            </option>
                                            <option value="Média" {{ $prior == 'Média' ? 'selected' : '' }}>Média
                                            </option>
                                            <option value="Alta" {{ $prior == 'Alta' ? 'selected' : '' }}>Alta
                                            </option>
                                            <option value="Urgente" {{ $prior == 'Urgente' ? 'selected' : '' }}>Urgente
                                            </option>
                                        </select>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-danger removerProduto">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td>
                                    <input type="text" class="form-control" name="produtos[0][nome_produto]"
                                        required>
                                </td>
                                <td>
                                    <input type="number" class="form-control" name="produtos[0][quantidade]"
                                        min="1" required>
                                </td>
                                <td>
                                    <select class="form-select" name="produtos[0][unidade_medida]" required>
                                        <option value="">Selecione</option>
                                        <option value="unidade">Unidade</option>
                                        <option value="caixa">Caixa</option>
                                        <option value="pacote">Pacote</option>
                                        <option value="kg">Kg</option>
                                        <option value="litro">Litro</option>
                                        <option value="metro">Metro</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control" name="produtos[0][observacoes]">
                                </td>
                                <td>
                                    <select class="form-select" name="produtos[0][prioridade]" required>
                                        <option value="">Selecione</option>
                                        <option value="Baixa">Baixa</option>
                                        <option value="Média">Média</option>
                                        <option value="Alta">Alta</option>
                                        <option value="Urgente">Urgente</option>
                                    </select>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-danger removerProduto">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            <div id="noProdutosMessage" class="alert alert-warning {{ old('produtos') || true ? 'd-none' : '' }}">
                <i class="fas fa-exclamation-triangle me-2"></i> Nenhum produto adicionado. Clique em "Adicionar
                Produto" para incluir itens na requisição.
            </div>
        </div>
    </div>
</div>

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const addBtn = document.getElementById('addProdutoBtn');
            if (addBtn) {
                // Adicionar produto
                addBtn.addEventListener('click', function() {
                    const tbody = document.getElementById('produtosTableBody');
                    const rowCount = tbody.rows.length;
                    const newRow = document.createElement('tr');

                    newRow.innerHTML = `
                <td>
                    <input type="text" class="form-control" name="produtos[${rowCount}][nome_produto]" required>
                </td>
                <td>
                    <input type="number" class="form-control" name="produtos[${rowCount}][quantidade]" min="1" required>
                </td>
                <td>
                    <select class="form-select" name="produtos[${rowCount}][unidade_medida]" required>
                        <option value="">Selecione</option>
                        <option value="unidade">Unidade</option>
                        <option value="caixa">Caixa</option>
                        <option value="pacote">Pacote</option>
                        <option value="kg">Kg</option>
                        <option value="litro">Litro</option>
                        <option value="metro">Metro</option>
                    </select>
                </td>
                <td>
                    <input type="text" class="form-control" name="produtos[${rowCount}][observacoes]">
                </td>
                <td>
                    <select class="form-select" name="produtos[${rowCount}][prioridade]" required>
                        <option value="">Selecione</option>
                        <option value="Baixa">Baixa</option>
                        <option value="Média">Média</option>
                        <option value="Alta">Alta</option>
                        <option value="Urgente">Urgente</option>
                    </select>
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger removerProduto">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;

                    tbody.appendChild(newRow);
                    document.getElementById('noProdutosMessage').classList.add('d-none');

                    // Adicionar evento ao novo botão de remover
                    const removeButton = newRow.querySelector('.removerProduto');
                    if (removeButton) {
                        removeButton.addEventListener('click', removerProdutoHandler);
                    }
                });
            }

            // Função para remover produto
            function removerProdutoHandler() {
                const tbody = document.getElementById('produtosTableBody');
                this.closest('tr').remove();

                // Reindexar os campos para manter a sequência
                const rows = tbody.querySelectorAll('tr');
                rows.forEach((row, index) => {
                    const inputs = row.querySelectorAll('input, select');
                    inputs.forEach(input => {
                        const name = input.getAttribute('name');
                        if (name) {
                            input.setAttribute('name', name.replace(/produtos\[\d+\]/,
                                `produtos[${index}]`));
                        }
                    });
                });

                // Mostrar mensagem se não houver produtos
                if (tbody.rows.length === 0) {
                    document.getElementById('noProdutosMessage').classList.remove('d-none');
                }
            }

            // Adicionar evento aos botões de remover existentes
            const existingRemoveButtons = document.querySelectorAll('.removerProduto');
            if (existingRemoveButtons && existingRemoveButtons.length) {
                existingRemoveButtons.forEach(button => {
                    button.addEventListener('click', removerProdutoHandler);
                });
            }
        });
    </script>
@endsection
