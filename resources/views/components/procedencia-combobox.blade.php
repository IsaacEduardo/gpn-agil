{{--
    Combobox pesquisável e criável de Procedências / Origem (Creatable Combobox / Autocomplete).
    Vanilla JS + Bootstrap — guarda o procedencia_id selecionado num <input type="hidden"> com o `name` indicado,
    e envia requisição AJAX POST /api/procedencias se o utilizador optar por adicionar um novo termo.

    As opções vêm do servidor à medida que se escreve (GET /api/procedencias, no
    máximo LIMITE de cada vez). Escrevê-las todas no HTML custava 3,6 MB por página
    com alguns milhares de procedências — sobretudo no telemóvel.

    Props:
      name         → name do input hidden (ex.: "procedencia_id")
      procedencias → aceite por compatibilidade; já não é usado para listar
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
    $selectedItem = $selected ? \App\Models\Procedencia::find($selected, ['id', 'nome']) : null;
@endphp

<div class="procedencia-combobox position-relative" data-procedencia-combobox
    data-url-pesquisa="{{ route('api.procedencias.index') }}"
    data-url-criar="{{ route('api.procedencias.store') }}">
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

        {{-- Estado da pesquisa (a pesquisar / sem resultados / erro) --}}
        <li class="procedencia-estado list-group-item small text-muted py-2 d-none"></li>

        {{-- Opção dinâmica: Adicionar Novo Termo --}}
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
                // Quantas procedências se pedem de cada vez.
                const LIMITE = 20;
                const ESPERA_MS = 250;

                function initProcedenciaCombobox(root) {
                    if (root.dataset.procCbReady) return;
                    root.dataset.procCbReady = '1';

                    const input = root.querySelector('.procedencia-combobox-input');
                    const hidden = root.querySelector('.procedencia-combobox-value');
                    const toggleBtn = root.querySelector('.procedencia-combobox-toggle-btn');
                    const list = root.querySelector('.procedencia-combobox-list');
                    const estadoLi = root.querySelector('.procedencia-estado');
                    const addItemLi = root.querySelector('.procedencia-add-item');
                    const addBtn = root.querySelector('.procedencia-combobox-add-btn');
                    const feedback = root.querySelector('.procedencia-combobox-feedback');
                    const urlPesquisa = root.dataset.urlPesquisa;
                    const urlCriar = root.dataset.urlCriar;

                    let items = [];
                    let activeIndex = -1;
                    let isCreating = false;
                    let espera = null;
                    let pedidoEmCurso = null;

                    function visibleElements() {
                        const opts = items.map(li => li.querySelector('.procedencia-combobox-option'));
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

                    function mostrarEstado(texto) {
                        estadoLi.textContent = texto || '';
                        estadoLi.classList.toggle('d-none', !texto);
                    }

                    // Os nomes vão por textContent: vêm de texto escrito por utilizadores.
                    function criarItem(proc) {
                        const li = document.createElement('li');
                        li.className = 'procedencia-item';
                        li.dataset.id = proc.id;
                        li.dataset.nome = proc.nome;

                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'list-group-item list-group-item-action procedencia-combobox-option d-flex align-items-center justify-content-between py-2';
                        btn.dataset.id = proc.id;
                        btn.dataset.nome = proc.nome;
                        btn.setAttribute('role', 'option');

                        const rotulo = document.createElement('span');
                        const icone = document.createElement('i');
                        icone.className = 'fas fa-landmark text-muted me-2 small';
                        rotulo.append(icone, document.createTextNode(proc.nome));
                        btn.appendChild(rotulo);

                        if (String(hidden.value) === String(proc.id)) {
                            const visto = document.createElement('i');
                            visto.className = 'fas fa-check text-primary small';
                            btn.appendChild(visto);
                        }

                        btn.addEventListener('click', () => choose(proc.id, proc.nome));
                        li.appendChild(btn);
                        return li;
                    }

                    function desenhar(resultados, term) {
                        items.forEach(li => li.remove());
                        items = resultados.map(criarItem);
                        items.forEach(li => list.insertBefore(li, addItemLi));

                        const termLower = term.toLowerCase();
                        const exactMatch = resultados.some(p => (p.nome || '').toLowerCase() === termLower);

                        // Termo novo: oferece "Adicionar". Se afinal já existir fora destes
                        // resultados, o servidor responde 422 com a existente e é essa a escolhida.
                        if (term.length > 0 && !exactMatch) {
                            rotuloAdicionarTermo(term);
                            addItemLi.classList.remove('d-none');
                        } else {
                            addItemLi.classList.add('d-none');
                        }

                        mostrarEstado(resultados.length === 0 && term.length === 0 ? 'Sem procedências registadas.' : '');
                        activeIndex = -1;
                        highlight();
                    }

                    // Só a resposta do último pedido conta: os anteriores são cancelados.
                    async function pesquisar() {
                        const term = input.value.trim();
                        if (pedidoEmCurso) pedidoEmCurso.abort();
                        const controlo = new AbortController();
                        pedidoEmCurso = controlo;

                        mostrarEstado('A pesquisar…');
                        open();

                        const url = new URL(urlPesquisa, window.location.origin);
                        url.searchParams.set('limite', LIMITE);
                        if (term) url.searchParams.set('q', term);

                        try {
                            const resposta = await fetch(url, {
                                headers: { 'Accept': 'application/json' },
                                signal: controlo.signal,
                            });
                            if (!resposta.ok) throw new Error('HTTP ' + resposta.status);
                            const dados = await resposta.json();
                            if (pedidoEmCurso !== controlo) return;
                            desenhar(Array.isArray(dados) ? dados : [], term);
                        } catch (err) {
                            if (err.name === 'AbortError') return;
                            console.error('Erro na pesquisa de procedências:', err);
                            mostrarEstado('Não foi possível pesquisar. Tente de novo.');
                        }
                    }

                    function pesquisarDepois() {
                        clearTimeout(espera);
                        espera = setTimeout(pesquisar, ESPERA_MS);
                    }

                    function choose(id, nome) {
                        hidden.value = id;
                        input.value = nome;
                        close();
                        if (feedback) feedback.classList.add('d-none');
                        hidden.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    function rotuloAdicionar(texto) {
                        addBtn.replaceChildren();
                        const icone = document.createElement('i');
                        icone.className = 'fas fa-plus-circle me-2';
                        addBtn.append(icone, document.createTextNode(texto));
                    }

                    // Adicionar "termo" — o termo vem do que o utilizador escreveu.
                    function rotuloAdicionarTermo(term) {
                        rotuloAdicionar('Adicionar "');
                        const termo = document.createElement('span');
                        termo.className = 'new-term-text';
                        termo.textContent = term;
                        addBtn.append(termo, document.createTextNode('"'));
                    }

                    async function createAndChoose(nome) {
                        if (isCreating || !nome) return;
                        isCreating = true;
                        addBtn.disabled = true;
                        rotuloAdicionar(`A guardar "${nome}"...`);
                        if (feedback) feedback.classList.add('d-none');

                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                        try {
                            const response = await fetch(urlCriar, {
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
                            rotuloAdicionarTermo(input.value.trim());
                        }
                    }

                    // Event Listeners
                    input.addEventListener('focus', () => {
                        pesquisar();
                    });

                    input.addEventListener('input', () => {
                        hidden.value = '';
                        open();
                        pesquisarDepois();
                    });

                    if (toggleBtn) {
                        toggleBtn.addEventListener('click', () => {
                            if (list.classList.contains('d-none')) {
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
