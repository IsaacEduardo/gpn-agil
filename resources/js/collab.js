import './bootstrap';
import * as Y from 'yjs';
import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableHeader from '@tiptap/extension-table-header';
import TableCell from '@tiptap/extension-table-cell';
import Collaboration from '@tiptap/extension-collaboration';
import CollaborationCursor from '@tiptap/extension-collaboration-cursor';
import YReverbProvider from './collab/YReverbProvider';

/**
 * Editor colaborativo de Documentos Internos (Tiptap + Yjs sobre Laravel Reverb).
 *
 * A extensão Collaboration liga o editor a um Y.Doc (CRDT — fusão automática sem perda);
 * CollaborationCursor mostra cursores/seleção identificados por utilizador. O provider trata
 * do transporte (presença + deltas) e o backend garante durabilidade e versionamento.
 */
document.addEventListener('DOMContentLoaded', () => {
    const mount = document.getElementById('collab-editor');
    if (!mount) return;

    const cfg = JSON.parse(mount.dataset.config);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const http = window.axios;

    const banner = (msg, type = 'warning') => {
        const el = document.getElementById('collab-status');
        if (el) {
            el.className = `alert alert-${type} py-2`;
            el.textContent = msg;
        }
    };

    // O título/assunto é editável independentemente do tempo real.
    wireTitulo();

    // Fallback: sem Reverb/Echo configurado, direciona para o editor clássico.
    if (!window.Echo) {
        banner('Edição colaborativa indisponível (servidor de tempo real não configurado). Use o editor clássico.', 'warning');
        const link = document.getElementById('collab-fallback-link');
        if (link) link.classList.remove('d-none');
        return;
    }

    // Reflete o estado REAL da ligação WebSocket (o Reverb tem de estar a correr).
    wireConnectionStatus();

    const ydoc = new Y.Doc();
    const provider = new YReverbProvider(
        ydoc,
        window.Echo,
        `documento.${cfg.documentoId}`,
        { id: cfg.user.id, name: cfg.user.name, color: cfg.user.color },
        renderPresence,
    );

    // Estado inicial durável (log persistido) antes de instanciar o editor.
    http.get(cfg.urls.state)
        .then(({ data }) => {
            provider.applyInitialUpdates(data.updates || []);
            initEditor(data);
        })
        .catch(() => {
            banner('Não foi possível carregar o estado inicial. A tentar mesmo assim…', 'danger');
            initEditor({ updates: [], html: '', hasState: false });
        });

    function initEditor(state) {
        const editor = new Editor({
            element: mount,
            editable: cfg.podeEditar,
            extensions: [
                // History é fornecido pelo Yjs (UndoManager) — desligar o do StarterKit.
                StarterKit.configure({ history: false }),
                Underline,
                TextAlign.configure({ types: ['heading', 'paragraph'] }),
                Table.configure({ resizable: true }),
                TableRow,
                TableHeader,
                TableCell,
                Collaboration.configure({ document: ydoc }),
                CollaborationCursor.configure({
                    provider,
                    user: { name: cfg.user.name, color: cfg.user.color },
                }),
            ],
        });

        // Seed inicial: só o primeiro a abrir (documento Yjs vazio) semeia a partir do HTML canónico.
        if (!state.hasState && editor.isEmpty && state.html) {
            editor.commands.setContent(state.html, false);
        }

        wireToolbar(editor);
        wireDurability(editor);
        wireCheckpoint(editor);
        // NÃO afirmamos "ligado" aqui: o estado real vem de wireConnectionStatus()/renderPresence().
    }

    // --- Barra de ferramentas de formatação (negrito, títulos, listas, alinhamento, tabela) ---
    function wireToolbar(editor) {
        const bar = document.getElementById('collab-toolbar');
        if (!bar) return; // só existe quando o utilizador pode editar

        const run = {
            undo: (c) => c.undo(),
            redo: (c) => c.redo(),
            paragraph: (c) => c.setParagraph(),
            h1: (c) => c.toggleHeading({ level: 1 }),
            h2: (c) => c.toggleHeading({ level: 2 }),
            h3: (c) => c.toggleHeading({ level: 3 }),
            bold: (c) => c.toggleBold(),
            italic: (c) => c.toggleItalic(),
            underline: (c) => c.toggleUnderline(),
            strike: (c) => c.toggleStrike(),
            left: (c) => c.setTextAlign('left'),
            center: (c) => c.setTextAlign('center'),
            right: (c) => c.setTextAlign('right'),
            justify: (c) => c.setTextAlign('justify'),
            bullet: (c) => c.toggleBulletList(),
            ordered: (c) => c.toggleOrderedList(),
            blockquote: (c) => c.toggleBlockquote(),
            table: (c) => c.insertTable({ rows: 3, cols: 3, withHeaderRow: true }),
        };

        bar.querySelectorAll('[data-cmd]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const fn = run[btn.dataset.cmd];
                if (fn) fn(editor.chain().focus()).run();
            });
        });

        const refresh = () => {
            const set = (cmd, on) => {
                const b = bar.querySelector(`[data-cmd="${cmd}"]`);
                if (b) b.classList.toggle('active', on);
            };
            set('bold', editor.isActive('bold'));
            set('italic', editor.isActive('italic'));
            set('underline', editor.isActive('underline'));
            set('strike', editor.isActive('strike'));
            set('h1', editor.isActive('heading', { level: 1 }));
            set('h2', editor.isActive('heading', { level: 2 }));
            set('h3', editor.isActive('heading', { level: 3 }));
            set('bullet', editor.isActive('bulletList'));
            set('ordered', editor.isActive('orderedList'));
            set('blockquote', editor.isActive('blockquote'));
            set('left', editor.isActive({ textAlign: 'left' }));
            set('center', editor.isActive({ textAlign: 'center' }));
            set('right', editor.isActive({ textAlign: 'right' }));
            set('justify', editor.isActive({ textAlign: 'justify' }));
        };
        editor.on('selectionUpdate', refresh);
        editor.on('transaction', refresh);
        refresh();
    }

    // --- Durabilidade: envia deltas (merge) + autosave do HTML, debounced e fora do caminho crítico ---
    function wireDurability(editor) {
        let pending = [];
        let timer = null;

        ydoc.on('update', (update, origin) => {
            if (origin === 'remote') return; // já é durável no par de origem
            pending.push(update);
            if (timer) return;
            timer = setTimeout(flush, 1500);
        });

        function flush() {
            timer = null;
            if (!pending.length) return;
            const merged = Y.mergeUpdates(pending);
            pending = [];
            http.post(cfg.urls.sync, {
                update: toBase64(merged),
                html: editor.getHTML(),
            }).catch(() => {/* re-tentado no próximo flush/checkpoint */});
        }

        // Flush final ao sair (best-effort) para não perder as últimas edições.
        window.addEventListener('beforeunload', () => {
            if (!pending.length) return;
            const merged = Y.mergeUpdates(pending);
            const payload = JSON.stringify({ update: toBase64(merged), html: editor.getHTML() });
            navigator.sendBeacon(
                cfg.urls.sync,
                new Blob([payload], { type: 'application/json' }),
            );
        });
    }

    // --- Checkpoint manual: cria uma versão no histórico e compacta o log Yjs ---
    function wireCheckpoint(editor) {
        const btn = document.getElementById('collab-checkpoint');
        if (!btn) return;
        btn.addEventListener('click', () => {
            if (!cfg.podeEditar) return;
            btn.disabled = true;
            http.post(cfg.urls.checkpoint, {
                html: editor.getHTML(),
                change_type: document.getElementById('collab-change-type')?.value || 'minor',
                change_log: document.getElementById('collab-change-log')?.value || null,
                snapshot: toBase64(Y.encodeStateAsUpdate(ydoc)),
            }).then(({ data }) => {
                banner(`Versão ${data.versao} guardada no histórico.`, 'success');
            }).catch(() => {
                banner('Falha ao guardar versão.', 'danger');
            }).finally(() => {
                btn.disabled = false;
            });
        });
    }

    // --- Campo de título/assunto (autosave debounced; não colaborativo — last-write-wins) ---
    function wireTitulo() {
        const input = document.getElementById('collab-titulo');
        if (!input || !cfg.urls.titulo) return;
        const header = document.getElementById('collab-doc-titulo');
        let timer = null;
        input.addEventListener('input', () => {
            if (header) header.textContent = input.value;
            if (timer) clearTimeout(timer);
            timer = setTimeout(() => {
                http.post(cfg.urls.titulo, { titulo: input.value }).catch(() => {/* re-tentado no próximo input */});
            }, 800);
        });
    }

    // --- Estado REAL da ligação em tempo real (o Reverb tem de estar a correr) ---
    let jaLigou = false;
    function wireConnectionStatus() {
        const conn = window.Echo?.connector?.pusher?.connection;
        if (!conn) return;
        const showFallback = () => {
            const link = document.getElementById('collab-fallback-link');
            if (link) link.classList.remove('d-none');
        };
        conn.bind('connecting', () => banner('A ligar ao servidor de tempo real…', 'secondary'));
        conn.bind('connected', () => { jaLigou = true; banner('Ligado ao servidor de tempo real.', 'success'); });
        conn.bind('unavailable', () => {
            banner('Servidor de tempo real indisponível. As suas alterações são guardadas, mas não vê as dos outros em direto — recarregue para sincronizar. (O servidor Reverb tem de estar a correr.)', 'warning');
            showFallback();
        });
        conn.bind('failed', () => {
            banner('Falha na ligação em tempo real. As alterações continuam a ser guardadas no servidor; recarregue para ver as dos outros.', 'danger');
            showFallback();
        });
        conn.bind('disconnected', () => {
            if (jaLigou) banner('Ligação em tempo real perdida — a tentar reconectar…', 'warning');
        });
    }

    function renderPresence(users) {
        const el = document.getElementById('collab-presence');
        if (!el) return;
        el.innerHTML = '';
        users.forEach((u) => {
            const badge = document.createElement('span');
            badge.className = 'badge rounded-pill me-1';
            badge.style.backgroundColor = u.cor || '#666';
            badge.textContent = u.nome || u.name || '—';
            el.appendChild(badge);
        });
        const count = document.getElementById('collab-presence-count');
        if (count) count.textContent = users.length;
        // A presença só popula quando o canal liga de facto → banner honesto com o total online.
        if (users.length > 0) {
            const n = users.length;
            banner(`Edição em tempo real ativa — ${n} ${n === 1 ? 'pessoa' : 'pessoas'} online.`, 'success');
        }
    }

    function toBase64(bytes) {
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
        return btoa(binary);
    }
});
