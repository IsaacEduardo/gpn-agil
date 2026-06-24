{{--
    Componente de arquivamento por arrastar-e-soltar (drag-and-drop).

    Reutilizável nas listagens de Documentos Internos, Documentos de Entrada e EDMS.

    Como usar (componente anónimo Blade):
      x-archive-dropzone com document-type="interno"  → tipo fixo
      x-archive-dropzone sem atributo                 → tipo lido de cada linha (EDMS misto)

    Nas linhas arrastáveis da listagem, adicionar:
      draggable="true" data-doc-id="{{ $doc->id }}" data-doc-type="interno|entrada"
    e (opcional, para multi-seleção em lote):
      <input type="checkbox" class="archive-select" data-doc-id="{{ $doc->id }}" data-doc-type="interno">

    Arquitetura (decisão do projeto: Alpine via CDN, sem build):
      - Alpine.store('archive')  → seleção + ação de arquivar (POST, progresso, toasts);
      - Alpine.data('archiveDropZone') → zonas de drop (auto + por pasta) e dicas visuais;
      - Listeners delegados (vanilla) tratam dragstart/dragend/checkbox sem exigir x-data nas linhas.
    A autorização é SEMPRE reavaliada no servidor (Policy 'archive' + validação da pasta);
    as verificações de compatibilidade no cliente são apenas dicas visuais.
--}}
@props(['documentType' => null])

@php
    $user = auth()->user();

    // Espelha App\Services\PastaService::isFolderCompatibleWithType (apenas para
    // pré-filtrar/realçar; o servidor é a fonte de verdade).
    $tiposEntrada = ['entrada', 'entrada_ano', 'entrada_mes', 'outro', 'custom', 'public'];
    $tiposInterno = ['interno', 'interno_despachos', 'interno_pareceres', 'outro', 'custom', 'public'];

    // Apenas pastas acessíveis ao utilizador (nunca todas as do sistema).
    $archiveFolders = \App\Models\Pasta::accessibleBy($user)
        ->orderBy('nome')
        ->get(['id', 'nome', 'type'])
        ->filter(function ($p) use ($documentType, $tiposEntrada, $tiposInterno) {
            if (! $documentType) {
                return true; // tipo misto (EDMS): mostra todas, realce decide no cliente
            }
            return $documentType === 'entrada'
                ? in_array($p->type, $tiposEntrada)
                : in_array($p->type, $tiposInterno);
        })
        ->take(15)
        ->map(fn ($p) => ['id' => $p->id, 'nome' => $p->nome, 'type' => $p->type])
        ->values();
@endphp

{{-- Barra de destinos (aparece enquanto se arrasta ou durante o arquivamento) --}}
<div
    x-data="archiveDropZone(@js(['documentType' => $documentType, 'folders' => $archiveFolders]))"
    x-show="$store.archive.dragging || $store.archive.busy"
    x-cloak
    class="archive-dropbar border-top shadow-lg"
    role="region"
    aria-label="Destinos de arquivamento"
>
    <div class="container-fluid py-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="fw-bold text-primary text-nowrap"><i class="fas fa-archive me-2"></i>Arquivar em:</span>

            {{-- Progresso do lote --}}
            <template x-if="$store.archive.busy">
                <div class="flex-grow-1" style="min-width: 220px;">
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                            :style="`width: ${$store.archive.percent()}%`"
                            role="progressbar"></div>
                    </div>
                    <small class="text-muted"
                        x-text="`${$store.archive.progress.done}/${$store.archive.progress.total} processados`"></small>
                </div>
            </template>

            {{-- Zonas de drop --}}
            <template x-if="!$store.archive.busy">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    {{-- Auto / cronológico --}}
                    <div class="archive-zone archive-zone--auto"
                        @dragover.prevent="$event.dataTransfer.dropEffect = 'move'"
                        @drop.prevent="onDrop($event, 'status', null)"
                        title="Arquivamento cronológico automático">
                        <i class="fas fa-wand-magic-sparkles me-1"></i> Automático (cronológico)
                    </div>

                    {{-- Pastas acessíveis --}}
                    <template x-for="folder in folders" :key="folder.id">
                        <div class="archive-zone"
                            :class="compatible(folder.type) ? 'archive-zone--ok' : 'archive-zone--bad'"
                            @dragover.prevent="$event.dataTransfer.dropEffect = compatible(folder.type) ? 'move' : 'none'"
                            @drop.prevent="compatible(folder.type) ? onDrop($event, 'folder', folder.id) : null"
                            :title="compatible(folder.type) ? 'Arquivar nesta pasta' : 'Pasta incompatível com o tipo de documento'">
                            <i class="fas fa-folder me-1"></i><span x-text="folder.nome"></span>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>

{{-- Indicador de seleção (multi-seleção em lote) --}}
<div x-data x-cloak
    x-show="$store.archive.count() > 0 && !$store.archive.dragging && !$store.archive.busy"
    class="archive-selpill shadow">
    <i class="fas fa-hand-pointer me-1"></i>
    <span x-text="$store.archive.count()"></span> selecionado(s) — arraste qualquer linha para arquivar
    <button type="button" class="btn-close btn-close-white ms-2" aria-label="Limpar seleção"
        @click="$store.archive.clearSelection(); document.querySelectorAll('.archive-select:checked').forEach(c => c.checked = false)"></button>
</div>

@once
    @push('scripts')
        <style>
            [x-cloak] { display: none !important; }

            .archive-dropbar {
                position: fixed; left: 0; right: 0; bottom: 0; z-index: 1080;
                background: #ffffff;
            }
            .archive-zone {
                border: 2px dashed #adb5bd;
                border-radius: 10px;
                padding: 10px 16px;
                font-weight: 600;
                color: #495057;
                background: #f8f9fa;
                cursor: copy;
                transition: all .15s ease-in-out;
                user-select: none;
            }
            .archive-zone--auto { border-color: #CE1126; color: #CE1126; background: rgba(206, 17, 38,.08); }
            .archive-zone--ok:hover, .archive-zone--auto:hover {
                transform: translateY(-2px);
                border-style: solid;
                box-shadow: 0 .5rem 1rem rgba(206, 17, 38,.15);
            }
            .archive-zone--ok { border-color: #198754; color: #198754; background: rgba(25,135,84,.06); }
            .archive-zone--bad { border-color: #D9534F; color: #D9534F; background: rgba(217, 83, 79,.04); opacity: .6; cursor: not-allowed; }

            .archive-selpill {
                position: fixed; right: 1rem; bottom: 1rem; z-index: 1079;
                background: #CE1126; color: #fff;
                padding: .5rem .9rem; border-radius: 999px;
                font-size: .85rem; font-weight: 600;
                display: flex; align-items: center;
            }
            .archive-archived { pointer-events: none; }
            tr[draggable="true"], [draggable="true"][data-doc-id] { cursor: grab; }
            [draggable="true"][data-doc-id]:active { cursor: grabbing; }
        </style>

        <script>
            // Registo do componente/loja ANTES do arranque do Alpine (padrão CDN).
            document.addEventListener('alpine:init', () => {
                Alpine.store('archive', {
                    selected: [],          // [{ id, type }]
                    dragging: null,        // { count, types: [] } enquanto arrasta
                    busy: false,
                    progress: { done: 0, total: 0 },
                    results: { dispatched: [], denied: [] },

                    toggle(id, type) {
                        const i = this.selected.findIndex(s => s.id === id && s.type === type);
                        if (i >= 0) this.selected.splice(i, 1);
                        else this.selected.push({ id, type });
                    },
                    has(id, type) { return this.selected.some(s => s.id === id && s.type === type); },
                    clearSelection() { this.selected = []; },
                    count() { return this.selected.length; },
                    percent() { return this.progress.total ? Math.round(this.progress.done / this.progress.total * 100) : 0; },

                    _csrf() { return document.querySelector('meta[name="csrf-token"]')?.content; },

                    // Executa o arquivamento de uma lista [{id,type}] para um destino.
                    async run(destinationType, destinationId, items) {
                        if (this.busy || !items || !items.length) return;

                        const token = this._csrf();
                        if (!token) {
                            this.toast('Token CSRF não encontrado. Recarregue a página.', 'danger');
                            return;
                        }

                        this.busy = true;
                        this.results = { dispatched: [], denied: [] };
                        this.progress = { done: 0, total: items.length };

                        // Agrupa por tipo: a listagem do EDMS pode misturar interno/entrada,
                        // e o endpoint aceita um 'document_type' por pedido.
                        const groups = {};
                        items.forEach(it => { (groups[it.type] = groups[it.type] || []).push(it.id); });

                        for (const [type, ids] of Object.entries(groups)) {
                            try {
                                const res = await fetch(@js(route('documents.archive.store')), {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': token,
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json',
                                    },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({
                                        document_type: type,
                                        document_ids: ids,
                                        destination_type: destinationType,        // 'status' | 'folder'
                                        destination_id: destinationType === 'folder' ? destinationId : null,
                                    }),
                                });
                                const data = await res.json().catch(() => ({}));
                                const dispatched = data.dispatched || [];
                                const denied = data.denied || [];

                                this.results.dispatched.push(...dispatched);
                                this.results.denied.push(...denied);

                                dispatched.forEach(id => this.fadeRow(id, type));

                                // Falha de pré-validação (pasta inacessível/incompatível) → 403/422 sem 'dispatched'.
                                if (!res.ok && dispatched.length === 0 && data.message) {
                                    this.results.denied.push(...ids.map(id => ({ id, reason: data.message })));
                                }
                            } catch (e) {
                                this.results.denied.push(...ids.map(id => ({ id, reason: 'Erro de comunicação.' })));
                            } finally {
                                this.progress.done += ids.length;
                            }
                        }

                        const ok = this.results.dispatched.length;
                        const ko = this.results.denied.length;
                        if (ok) this.toast(`${ok} documento(s) enviado(s) para arquivamento.`, 'success');
                        if (ko) {
                            const amostra = this.results.denied.slice(0, 3).map(d => `#${d.id}: ${d.reason}`).join('; ');
                            this.toast(`${ko} documento(s) não arquivado(s). ${amostra}`, 'warning');
                        }

                        this.clearSelection();
                        document.querySelectorAll('.archive-select:checked').forEach(c => c.checked = false);
                        this.busy = false;
                        this.dragging = null;
                    },

                    fadeRow(id, type) {
                        const row = document.querySelector(`[data-doc-id="${id}"][data-doc-type="${type}"]`)
                            || document.querySelector(`[data-doc-id="${id}"]`);
                        if (!row) return;
                        row.style.transition = 'opacity .6s ease';
                        row.style.opacity = '0.35';
                        row.classList.add('archive-archived');
                        const cb = row.querySelector('.archive-select');
                        if (cb) cb.checked = false;
                    },

                    toast(message, type = 'info') {
                        const container = document.querySelector('.toast-container');
                        const icons = {
                            success: 'fa-check-circle text-success',
                            danger: 'fa-exclamation-circle text-danger',
                            warning: 'fa-exclamation-triangle text-warning',
                            info: 'fa-info-circle text-info',
                        };
                        const icon = icons[type] || icons.info;
                        const el = document.createElement('div');
                        el.className = 'toast align-items-center border-0 shadow-lg';
                        el.setAttribute('role', 'alert');
                        el.setAttribute('aria-live', 'assertive');
                        el.setAttribute('aria-atomic', 'true');
                        el.innerHTML = `
                            <div class="d-flex">
                                <div class="toast-body d-flex align-items-center gap-2">
                                    <i class="fas ${icon}"></i><span>${message}</span>
                                </div>
                                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>
                            </div>`;
                        if (!container) { return; }
                        container.appendChild(el);
                        if (window.bootstrap && window.bootstrap.Toast) {
                            const t = new window.bootstrap.Toast(el, { delay: 6000 });
                            t.show();
                            el.addEventListener('hidden.bs.toast', () => el.remove());
                        } else {
                            el.style.display = 'block';
                            setTimeout(() => el.remove(), 6000);
                        }
                    },
                });

                Alpine.data('archiveDropZone', (config = {}) => ({
                    fixedType: config.documentType || null,
                    folders: config.folders || [],

                    onDrop(event, destinationType, destinationId) {
                        let items = [];
                        try {
                            items = JSON.parse(event.dataTransfer.getData('application/json') || '[]');
                        } catch (e) { items = []; }
                        if (!items.length) return;
                        this.$store.archive.run(destinationType, destinationId, items);
                    },

                    // Dica visual de compatibilidade (espelha o servidor; não é autoritativa).
                    compatible(folderType) {
                        const types = (this.$store.archive.dragging && this.$store.archive.dragging.types) || [];
                        if (!types.length) return true;
                        return types.every(t => this._fits(folderType, t));
                    },
                    _fits(folderType, docType) {
                        const entrada = ['entrada', 'entrada_ano', 'entrada_mes', 'outro', 'custom', 'public'];
                        const interno = ['interno', 'interno_despachos', 'interno_pareceres', 'outro', 'custom', 'public'];
                        if (docType === 'entrada') return entrada.includes(folderType);
                        if (docType === 'interno') return interno.includes(folderType);
                        return true;
                    },
                }));
            });

            // Listeners delegados (vanilla) — funcionam sem x-data nas linhas da tabela.
            (function () {
                const store = () => (window.Alpine ? Alpine.store('archive') : null);

                document.addEventListener('dragstart', (e) => {
                    const row = e.target.closest('[draggable="true"][data-doc-id]');
                    if (!row) return;
                    const s = store();
                    if (!s) return;
                    const id = parseInt(row.dataset.docId, 10);
                    const type = row.dataset.docType || 'interno';
                    const items = s.has(id, type) ? s.selected.slice() : [{ id, type }];
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('application/json', JSON.stringify(items));
                    s.dragging = { count: items.length, types: [...new Set(items.map(i => i.type))] };
                });

                document.addEventListener('dragend', () => {
                    const s = store();
                    if (!s) return;
                    // o 'drop' dispara antes do 'dragend'; só limpamos se não houve arquivamento.
                    setTimeout(() => { if (!s.busy) s.dragging = null; }, 250);
                });

                document.addEventListener('change', (e) => {
                    const cb = e.target.closest('.archive-select[data-doc-id]');
                    if (!cb) return;
                    const s = store();
                    if (!s) return;
                    const id = parseInt(cb.dataset.docId, 10);
                    const type = cb.dataset.docType || 'interno';
                    const has = s.has(id, type);
                    if (cb.checked && !has) s.toggle(id, type);
                    if (!cb.checked && has) s.toggle(id, type);
                });
            })();
        </script>
        {{-- Alpine (CDN). Carregado uma única vez por página onde o componente é usado. --}}
        <script src="//unpkg.com/alpinejs" defer></script>
    @endpush
@endonce
