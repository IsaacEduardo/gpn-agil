{{--
    Combobox pesquisável de Departamentos (substitui o <select> "enorme").

    Vanilla JS + Bootstrap (sem framework novo); funciona como progressive enhancement
    — guarda o id selecionado num <input type="hidden"> com o `name` indicado.

    Props:
      name        → name do input hidden (ex.: "destino_departamento_id")
      departamentos → coleção com id + nome
      placeholder → texto do campo de pesquisa

    Exclusão dinâmica: defina data-exclude="<id>" no contentor [data-dep-combobox]
    (ex.: o departamento atual do documento) para o ocultar das opções.
--}}
@props(['name', 'departamentos', 'placeholder' => 'Pesquisar departamento...', 'excludeId' => null])

<div class="dep-combobox position-relative" data-dep-combobox @if (! is_null($excludeId)) data-exclude="{{ $excludeId }}" @endif>
    <input type="hidden" name="{{ $name }}" class="dep-combobox-value">
    <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
        <input type="text" class="form-control border-start-0 ps-0 dep-combobox-input" autocomplete="off"
            placeholder="{{ $placeholder }}" role="combobox" aria-expanded="false" aria-autocomplete="list">
    </div>
    <ul class="dep-combobox-list list-group position-absolute w-100 shadow-sm d-none"
        style="z-index: 1085; max-height: 240px; overflow-y: auto;" role="listbox">
        @foreach ($departamentos as $dep)
            <li>
                <button type="button"
                    class="list-group-item list-group-item-action dep-combobox-option d-flex align-items-center gap-2"
                    data-id="{{ $dep->id }}" data-nome="{{ $dep->nome }}" role="option">
                    <i class="fas fa-building text-muted small"></i>{{ $dep->nome }}
                </button>
            </li>
        @endforeach
    </ul>
    <div class="form-text dep-combobox-empty d-none text-muted">Nenhum departamento corresponde à pesquisa.</div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                function initCombobox(root) {
                    if (root.dataset.cbReady) return;
                    root.dataset.cbReady = '1';

                    const input = root.querySelector('.dep-combobox-input');
                    const hidden = root.querySelector('.dep-combobox-value');
                    const list = root.querySelector('.dep-combobox-list');
                    const empty = root.querySelector('.dep-combobox-empty');
                    const options = Array.from(root.querySelectorAll('.dep-combobox-option'));
                    let activeIndex = -1;

                    function visibleOptions() {
                        return options.filter(o => !o.parentElement.classList.contains('d-none'));
                    }

                    function open() { list.classList.remove('d-none'); input.setAttribute('aria-expanded', 'true'); }
                    function close() { list.classList.add('d-none'); input.setAttribute('aria-expanded', 'false'); activeIndex = -1; highlight(); }

                    function highlight() {
                        const vis = visibleOptions();
                        vis.forEach((o, i) => o.classList.toggle('active', i === activeIndex));
                        if (activeIndex >= 0 && vis[activeIndex]) vis[activeIndex].scrollIntoView({ block: 'nearest' });
                    }

                    function filter() {
                        const term = input.value.trim().toLowerCase();
                        const excludeId = root.dataset.exclude || null;
                        let shown = 0;
                        options.forEach(o => {
                            const isExcluded = excludeId && o.dataset.id === String(excludeId);
                            const match = !isExcluded && o.dataset.nome.toLowerCase().includes(term);
                            o.parentElement.classList.toggle('d-none', !match);
                            if (match) shown++;
                        });
                        empty.classList.toggle('d-none', shown !== 0);
                        activeIndex = -1;
                        highlight();
                    }

                    function choose(opt) {
                        hidden.value = opt.dataset.id;
                        input.value = opt.dataset.nome;
                        close();
                        hidden.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    input.addEventListener('focus', () => { filter(); open(); });
                    input.addEventListener('input', () => { hidden.value = ''; filter(); open(); });
                    input.addEventListener('keydown', (e) => {
                        const vis = visibleOptions();
                        if (e.key === 'ArrowDown') { e.preventDefault(); open(); activeIndex = Math.min(activeIndex + 1, vis.length - 1); highlight(); }
                        else if (e.key === 'ArrowUp') { e.preventDefault(); activeIndex = Math.max(activeIndex - 1, 0); highlight(); }
                        else if (e.key === 'Enter') { if (activeIndex >= 0 && vis[activeIndex]) { e.preventDefault(); choose(vis[activeIndex]); } }
                        else if (e.key === 'Escape') { close(); }
                    });

                    options.forEach(o => o.addEventListener('click', () => choose(o)));
                    document.addEventListener('click', (e) => { if (!root.contains(e.target)) close(); });

                    // API mínima para o resto da página (ex.: limpar ao abrir o modal).
                    root.comboboxReset = function () { hidden.value = ''; input.value = ''; filter(); close(); };
                }

                function initAll(ctx) { (ctx || document).querySelectorAll('[data-dep-combobox]').forEach(initCombobox); }
                document.addEventListener('DOMContentLoaded', () => initAll());
                // Exponho para inicialização sob demanda (conteúdo injetado dinamicamente).
                window.initDepartamentoComboboxes = initAll;
            })();
        </script>
    @endpush
@endonce
