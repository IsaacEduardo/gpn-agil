{{--
    Combobox pesquisável e criável de Procedências / Origem (Creatable Combobox / Autocomplete).
    Vanilla JS + Bootstrap — guarda o procedencia_id selecionado num <input type="hidden"> com o `name` indicado,
    e envia requisição AJAX POST /api/procedencias se o utilizador optar por adicionar um novo termo.

    Props:
      name         → name do input hidden (ex.: "procedencia_id")
      procedencias → coleção ou array com id + nome (opcional)
      selected     → id da procedência previamente selecionada
      placeholder  → texto placeholder do campo de pesquisa
--}}
@props([
    'name' => 'procedencia_id',
    'procedencias' => null,
    'selected' => null,
    'placeholder' => 'Selecione ou digite para pesquisar procedência...'
])

@php
    $procedenciasList = $procedencias ?? \App\Models\Procedencia::where('ativo', true)->orderBy('nome')->get(['id', 'nome']);
    $selectedItem = null;
    if ($selected) {
        $selectedItem = $procedenciasList->firstWhere('id', $selected);
        if (!$selectedItem) {
            $selectedItem = \App\Models\Procedencia::find($selected);
        }
    }
@endphp

<div class="procedencia-combobox position-relative" data-procedencia-combobox>
    <input type="hidden" name="{{ $name }}" class="procedencia-combobox-value" value="{{ $selectedItem->id ?? '' }}">
    
    <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="fas fa-building text-muted"></i></span>
        <input type="text" 
               class="form-control border-start-0 ps-0 procedencia-combobox-input" 
               autocomplete="off"
               placeholder="{{ $placeholder }}" 
               value="{{ $selectedItem->nome ?? '' }}"
               role="combobox" 
               aria-expanded="false" 
               aria-autocomplete="list">
        <button type="button" class="btn btn-outline-secondary border-start-0 procedencia-combobox-toggle-btn" tabindex="-1">
            <i class="fas fa-chevron-down small text-muted"></i>
        </button>
    </div>

    <ul class="procedencia-combobox-list list-group position-absolute w-100 shadow-sm d-none mt-1"
        style="z-index: 1085; max-height: 240px; overflow-y: auto;" 
        role="listbox">
        
        <!-- Opções existentes renderizadas via Blade -->
        @foreach ($procedenciasList as $proc)
            <li class="procedencia-item" data-id="{{ $proc->id }}" data-nome="{{ $proc->nome }}">
                <button type="button"
                    class="list-group-item list-group-item-action procedencia-combobox-option d-flex align-items-center justify-content-between py-2"
                    data-id="{{ $proc->id }}" 
                    data-nome="{{ $proc->nome }}" 
                    role="option">
                    <span><i class="fas fa-landmark text-muted me-2 small"></i>{{ $proc->nome }}</span>
                    @if ($selectedItem && $selectedItem->id == $proc->id)
                        <i class="fas fa-check text-primary small"></i>
                    @endif
                </button>
            </li>
        @endforeach

        <!-- Opção dinâmica: Adicionar Novo Termo -->
        <li class="procedencia-add-item d-none">
            <button type="button" class="list-group-item list-group-item-action procedencia-combobox-add-btn text-primary fw-bold py-2 d-flex align-items-center">
                <i class="fas fa-plus-circle me-2"></i>Adicionar "<span class="new-term-text"></span>"
            </button>
        </li>
    </ul>
    
    <div class="form-text procedencia-combobox-feedback d-none text-danger small mt-1"></div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                function initProcedenciaCombobox(root) {
                    if (root.dataset.procCbReady) return;
                    root.dataset.procCbReady = '1';

                    const input = root.querySelector('.procedencia-combobox-input');
                    const hidden = root.querySelector('.procedencia-combobox-value');
                    const toggleBtn = root.querySelector('.procedencia-combobox-toggle-btn');
                    const list = root.querySelector('.procedencia-combobox-list');
                    const addItemLi = root.querySelector('.procedencia-add-item');
                    const addBtn = root.querySelector('.procedencia-combobox-add-btn');
                    const newTermSpan = root.querySelector('.new-term-text');
                    const feedback = root.querySelector('.procedencia-combobox-feedback');

                    let items = Array.from(root.querySelectorAll('.procedencia-item'));
                    let activeIndex = -1;
                    let isCreating = false;

                    function getOptions() {
                        return items.map(li => li.querySelector('.procedencia-combobox-option'));
                    }

                    function visibleElements() {
                        const opts = getOptions().filter(o => !o.parentElement.classList.contains('d-none'));
                        if (!addItemLi.classList.contains('d-none')) {
                            opts.push(addBtn);
                        }
                        return opts;
                    }

                    function open() {
                        list.classList.remove('d-none');
                        input.setAttribute('aria-expanded', 'true');
                    }

                    function close() {
                        list.classList.add('d-none');
                        input.setAttribute('aria-expanded', 'false');
                        activeIndex = -1;
                        highlight();
                    }

                    function highlight() {
                        const vis = visibleElements();
                        vis.forEach((o, i) => o.classList.toggle('active', i === activeIndex));
                        if (activeIndex >= 0 && vis[activeIndex]) {
                            vis[activeIndex].scrollIntoView({ block: 'nearest' });
                        }
                    }

                    function filter() {
                        const term = input.value.trim();
                        const termLower = term.toLowerCase();
                        let exactMatch = false;

                        items.forEach(li => {
                            const nome = li.dataset.nome || '';
                            const match = nome.toLowerCase().includes(termLower);
                            li.classList.toggle('d-none', !match);
                            if (nome.toLowerCase() === termLower) {
                                exactMatch = true;
                            }
                        });

                        // Se o termo não for vazio e não tiver match exato, exibe opção "Adicionar"
                        if (term.length > 0 && !exactMatch) {
                            newTermSpan.textContent = term;
                            addItemLi.classList.remove('d-none');
                        } else {
                            addItemLi.classList.add('d-none');
                        }

                        activeIndex = -1;
                        highlight();
                    }

                    function choose(id, nome) {
                        hidden.value = id;
                        input.value = nome;
                        close();
                        if (feedback) feedback.classList.add('d-none');
                        hidden.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    async function createAndChoose(nome) {
                        if (isCreating || !nome) return;
                        isCreating = true;
                        addBtn.disabled = true;
                        addBtn.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>A guardar "${nome}"...`;
                        if (feedback) feedback.classList.add('d-none');

                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                        try {
                            const response = await fetch('/api/procedencias', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: JSON.stringify({ nome: nome })
                            });

                            const data = await response.json();

                            if (response.ok && data.id) {
                                // Adiciona novo item à lista local
                                const newLi = document.createElement('li');
                                newLi.className = 'procedencia-item';
                                newLi.dataset.id = data.id;
                                newLi.dataset.nome = data.nome;
                                newLi.innerHTML = `
                                    <button type="button" class="list-group-item list-group-item-action procedencia-combobox-option d-flex align-items-center justify-content-between py-2" data-id="${data.id}" data-nome="${data.nome}" role="option">
                                        <span><i class="fas fa-landmark text-muted me-2 small"></i>${data.nome}</span>
                                    </button>
                                `;
                                list.insertBefore(newLi, addItemLi);
                                items.push(newLi);

                                const newOpt = newLi.querySelector('.procedencia-combobox-option');
                                newOpt.addEventListener('click', () => choose(data.id, data.nome));

                                choose(data.id, data.nome);
                            } else if (response.status === 422 && data.data && data.data.id) {
                                // Caso já existisse (case-insensitive), seleciona o existente
                                choose(data.data.id, data.data.nome);
                            } else {
                                const msg = data.errors?.nome?.[0] || data.message || 'Erro ao guardar procedência.';
                                if (feedback) {
                                    feedback.textContent = msg;
                                    feedback.classList.remove('d-none');
                                }
                            }
                        } catch (err) {
                            console.error('Erro na requisição /api/procedencias:', err);
                            if (feedback) {
                                feedback.textContent = 'Ocorreu um erro de rede ao criar a procedência.';
                                feedback.classList.remove('d-none');
                            }
                        } finally {
                            isCreating = false;
                            addBtn.disabled = false;
                            addBtn.innerHTML = `<i class="fas fa-plus-circle me-2"></i>Adicionar "<span class="new-term-text">${input.value.trim()}</span>"`;
                        }
                    }

                    // Event Listeners
                    input.addEventListener('focus', () => {
                        filter();
                        open();
                    });

                    input.addEventListener('input', () => {
                        hidden.value = '';
                        filter();
                        open();
                    });

                    if (toggleBtn) {
                        toggleBtn.addEventListener('click', () => {
                            if (list.classList.contains('d-none')) {
                                filter();
                                open();
                                input.focus();
                            } else {
                                close();
                            }
                        });
                    }

                    input.addEventListener('keydown', (e) => {
                        const vis = visibleElements();
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            open();
                            activeIndex = Math.min(activeIndex + 1, vis.length - 1);
                            highlight();
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            activeIndex = Math.max(activeIndex - 1, 0);
                            highlight();
                        } else if (e.key === 'Enter') {
                            if (activeIndex >= 0 && vis[activeIndex]) {
                                e.preventDefault();
                                const el = vis[activeIndex];
                                if (el.classList.contains('procedencia-combobox-add-btn')) {
                                    createAndChoose(input.value.trim());
                                } else {
                                    choose(el.dataset.id, el.dataset.nome);
                                }
                            } else if (!addItemLi.classList.contains('d-none')) {
                                e.preventDefault();
                                createAndChoose(input.value.trim());
                            }
                        } else if (e.key === 'Escape') {
                            close();
                        }
                    });

                    // Eventos de clique nas opções existentes
                    items.forEach(li => {
                        const opt = li.querySelector('.procedencia-combobox-option');
                        if (opt) {
                            opt.addEventListener('click', () => choose(opt.dataset.id, opt.dataset.nome));
                        }
                    });

                    // Evento de clique no botão adicionar
                    addBtn.addEventListener('click', () => {
                        createAndChoose(input.value.trim());
                    });

                    // Fechar dropdown ao clicar fora
                    document.addEventListener('click', (e) => {
                        if (!root.contains(e.target)) {
                            close();
                        }
                    });
                }

                function initAll(ctx) {
                    (ctx || document).querySelectorAll('[data-procedencia-combobox]').forEach(initProcedenciaCombobox);
                }

                document.addEventListener('DOMContentLoaded', () => initAll());
                window.initProcedenciaComboboxes = initAll;
            })();
        </script>
    @endpush
@endonce
