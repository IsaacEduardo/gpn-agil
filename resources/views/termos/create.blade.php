@extends('layouts.app')

@section('title', 'Novo Termo de Entrega')

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

        .item-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            position: relative;
            transition: all 0.2s ease;
        }

        .item-card:hover {
            border-color: #adb5bd;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .remove-item-btn {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }
    </style>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-file-signature me-2"></i>Novo Termo de Entrega</h5>
                </div>
                <div class="card-body p-4">
                    <form id="termoForm" method="POST" action="{{ route('termos.store') }}">
                        @csrf

                        <div class="row g-4">
                            <!-- Seção 1: Informações Gerais -->
                            <div class="col-12">
                                <h6 class="form-section-title"><i class="fas fa-info-circle me-2"></i>Informações Gerais
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="form-floating">
                                            <select id="tipo" name="tipo" class="form-select" required
                                                onchange="toggleCamposViatura()">
                                                <option value="definitiva">Entrega Definitiva</option>
                                                <option value="devolutivo">A Título Devolutivo</option>
                                                <option value="viatura">Entrega de Viatura</option>
                                            </select>
                                            <label for="tipo">Tipo de Termo</label>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <!-- Espaço vazio ou info extra -->
                                    </div>
                                </div>
                            </div>

                            <!-- Seção 2: Beneficiário -->
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
                                            <input type="text" id="beneficiario_documento" name="beneficiario_documento"
                                                class="form-control" placeholder="BI/NIF">
                                            <label for="beneficiario_documento">Documento (BI/NIF)</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-floating">
                                            <input type="text" id="beneficiario_setor" name="beneficiario_setor"
                                                class="form-control" placeholder="Departamento/Setor">
                                            <label for="beneficiario_setor">Setor/Departamento</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Seção 3: Itens ou Viatura -->
                            <div class="col-12">
                                <div id="campos_item">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="form-section-title mb-0 border-0 pb-0"><i
                                                class="fas fa-boxes me-2"></i>Itens da Entrega</h6>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick="addItemRow()">
                                            <i class="fas fa-plus me-1"></i> Adicionar Item
                                        </button>
                                    </div>

                                    <div id="items-list">
                                        <div class="item-card item-row" data-index="0">
                                            <div class="row g-3 align-items-center">
                                                <div class="col-md-6">
                                                    <div class="form-floating">
                                                        <input type="text" name="items[0][descricao]"
                                                            class="form-control bg-white" placeholder="Descrição" required>
                                                        <label>Descrição do Item *</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-floating">
                                                        <input type="number" name="items[0][quantidade]"
                                                            class="form-control bg-white" placeholder="Qtd" min="0"
                                                            step="1">
                                                        <label>Qtd.</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-floating">
                                                        <select name="items[0][unidade]"
                                                            class="form-select bg-white unidade-select"
                                                            onchange="toggleUnidadeCustom(this)">
                                                            <option value="un">un</option>
                                                            <option value="cx">cx</option>
                                                            <option value="pacote">pacote</option>
                                                            <option value="kg">kg</option>
                                                            <option value="g">g</option>
                                                            <option value="l">l</option>
                                                            <option value="ml">ml</option>
                                                            <option value="m">m</option>
                                                            <option value="m²">m²</option>
                                                            <option value="m³">m³</option>
                                                            <option value="par">par</option>
                                                            <option value="saco">saco</option>
                                                            <option value="peça">peça</option>
                                                            <option value="serviço">serviço</option>
                                                            <option value="Outro">Outro...</option>
                                                        </select>
                                                        <label>Unidade</label>
                                                    </div>
                                                    <input type="text" class="form-control mt-2 unidade-custom d-none"
                                                        placeholder="Especifique a unidade"
                                                        oninput="syncUnidadeCustom(this)">
                                                </div>
                                                <div class="col-md-1 text-end">
                                                    <!-- Botão remover apenas visualmente placeholder para o primeiro item, mas funcional se houver mais -->
                                                    <button type="button"
                                                        class="btn btn-outline-danger btn-sm d-none remove-btn-first"
                                                        onclick="removeItemRow(this)" title="Remover">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="campos_viatura" style="display:none;">
                                    <h6 class="form-section-title"><i class="fas fa-car me-2"></i>Seleção de Viatura</h6>
                                    <div class="alert alert-info border-0 shadow-sm">
                                        <i class="fas fa-info-circle me-2"></i>Selecione as viaturas que serão entregues
                                        neste termo.
                                    </div>
                                    <div class="form-floating">
                                        <select id="viatura_ids" name="viatura_ids[]" class="form-select" multiple
                                            style="height: 150px">
                                            @foreach ($viaturas as $v)
                                                <option value="{{ $v->id }}">{{ $v->identificacao }} -
                                                    {{ $v->placa }} ({{ $v->marca }} {{ $v->modelo }})</option>
                                            @endforeach
                                        </select>
                                        <label for="viatura_ids">Viaturas Disponíveis</label>
                                    </div>
                                    <div class="form-text mt-2">Segure Ctrl (ou Cmd no Mac) para selecionar múltiplas
                                        viaturas.</div>
                                </div>
                            </div>

                            <!-- Seção 4: Observações -->
                            <div class="col-12">
                                <h6 class="form-section-title"><i class="fas fa-comment-alt me-2"></i>Observações</h6>
                                <div class="form-floating">
                                    <textarea id="observacoes" name="observacoes" class="form-control" style="height: 100px" placeholder="Observações"></textarea>
                                    <label for="observacoes">Informações adicionais, condições, prazos...</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                            <a href="{{ route('termos.index') }}" class="btn btn-light border">
                                <i class="fas fa-times me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-success px-4">
                                <i class="fas fa-file-pdf me-1"></i> Gerar Termo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@section('scripts')
    <script>
        function toggleCamposViatura() {
            const tipo = document.getElementById('tipo').value;
            const camposViatura = document.getElementById('campos_viatura');
            const camposItem = document.getElementById('campos_item');

            if (tipo === 'viatura') {
                camposViatura.style.display = 'block';
                camposItem.style.display = 'none';

                // Limpa campos required dos itens para não bloquear submit
                document.querySelectorAll('#items-list input[required]').forEach(el => el.required = false);
            } else {
                camposViatura.style.display = 'none';
                camposItem.style.display = 'block';

                // Restaura required
                document.querySelectorAll('#items-list input[name*="[descricao]"]').forEach(el => el.required = true);
            }
        }

        function addItemRow() {
            const list = document.getElementById('items-list');
            const last = list.querySelector('.item-row:last-of-type');
            const nextIndex = last ? (parseInt(last.dataset.index, 10) + 1) : 0;

            const row = document.createElement('div');
            row.className = 'item-card item-row';
            row.dataset.index = nextIndex;
            row.innerHTML = `
                <button type="button" class="btn btn-danger remove-item-btn" onclick="removeItemRow(this)" title="Remover">
                    <i class="fas fa-times fa-xs"></i>
                </button>
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" name="items[${nextIndex}][descricao]" class="form-control bg-white" placeholder="Descrição" required>
                            <label>Descrição do Item *</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-floating">
                            <input type="number" name="items[${nextIndex}][quantidade]" class="form-control bg-white" placeholder="Qtd" min="0" step="1">
                            <label>Qtd.</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating">
                            <select name="items[${nextIndex}][unidade]" class="form-select bg-white unidade-select" onchange="toggleUnidadeCustom(this)">
                                <option value="un">un</option>
                                <option value="cx">cx</option>
                                <option value="pacote">pacote</option>
                                <option value="kg">kg</option>
                                <option value="g">g</option>
                                <option value="l">l</option>
                                <option value="ml">ml</option>
                                <option value="m">m</option>
                                <option value="m²">m²</option>
                                <option value="m³">m³</option>
                                <option value="par">par</option>
                                <option value="saco">saco</option>
                                <option value="peça">peça</option>
                                <option value="serviço">serviço</option>
                                <option value="Outro">Outro...</option>
                            </select>
                            <label>Unidade</label>
                        </div>
                        <input type="text" class="form-control mt-2 unidade-custom d-none" placeholder="Especifique a unidade" oninput="syncUnidadeCustom(this)">
                    </div>
                </div>`;

            list.appendChild(row);
        }

        function removeItemRow(btn) {
            const row = btn.closest('.item-row');
            const list = document.getElementById('items-list');
            if (list.querySelectorAll('.item-row').length > 1) {
                row.remove();
            } else {
                // Se for o último, apenas limpa os valores
                row.querySelectorAll('input').forEach(input => input.value = '');
                row.querySelector('select').selectedIndex = 0;
            }
        }

        function toggleUnidadeCustom(selectEl) {
            const customInput = selectEl.closest('.col-md-3').querySelector('.unidade-custom');
            if (selectEl.value === 'Outro') {
                customInput.classList.remove('d-none');
                customInput.required = true;
                customInput.focus();
            } else {
                customInput.classList.add('d-none');
                customInput.required = false;
            }
        }

        function syncUnidadeCustom(inputEl) {
            // Lógica tratada no submit
        }

        document.getElementById('termoForm').addEventListener('submit', function() {
            const rows = document.querySelectorAll('.item-row');
            rows.forEach(row => {
                const select = row.querySelector('.unidade-select');
                const custom = row.querySelector('.unidade-custom');
                if (select && custom && !custom.classList.contains('d-none') && custom.value.trim()) {
                    const idx = row.dataset.index;
                    // Remove input hidden anterior se existir
                    const existingHidden = row.querySelector(
                        `input[name="items[${idx}][unidade]"][type="hidden"]`);
                    if (existingHidden) existingHidden.remove();

                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = `items[${idx}][unidade]`;
                    hidden.value = custom.value.trim();
                    row.appendChild(hidden);

                    // Desabilita o select para não enviar seu valor
                    select.removeAttribute('name');
                }
            });
        });

        document.addEventListener('DOMContentLoaded', toggleCamposViatura);
    </script>
@endsection
@endsection
