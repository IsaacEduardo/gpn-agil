import './bootstrap';
import * as Y from 'yjs';
import { Editor } from '@tiptap/core';
import Collaboration from '@tiptap/extension-collaboration';
import CollaborationCursor from '@tiptap/extension-collaboration-cursor';
import YReverbProvider, { toBase64, fromBase64 } from './collab/YReverbProvider';
import { extensoesDoDocumento } from './collab/extensoes';
import { ligarCamposColaborativos, ligarMapaDeCampos } from './collab/campos';
import { criarFilaDeEnvio } from './collab/fila';
import { ligarComentarios } from './collab/comentarios';
import { ligarVersoes } from './collab/versoes';

/**
 * Editor colaborativo de Documentos Internos (Tiptap + Yjs sobre Laravel Reverb).
 *
 * Caminho de uma alteração: editor → Y.Doc → fila de envio (collab/fila.js) → /sync,
 * onde o servidor verifica o nível e o estado do documento, grava no log e a
 * retransmite aos outros (collab.alteracao). Cada linha do log tem um id; o cliente
 * guarda os que já aplicou, para a compactação no checkpoint só apagar esses.
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

    // Sem Reverb/Echo configurado, direciona para o editor clássico.
    if (!window.Echo) {
        wireTitulo();
        banner('Edição colaborativa indisponível (servidor de tempo real não configurado). Use o editor clássico.', 'warning');
        document.getElementById('collab-fallback-link')?.classList.remove('d-none');
        return;
    }

    // Identifica esta ligação ao servidor, para ele não nos devolver o que enviámos.
    const cabecalhos = () => {
        const id = window.Echo?.socketId?.();
        return id ? { 'X-Socket-ID': id } : {};
    };

    const ydoc = new Y.Doc();
    const aplicados = new Set(); // ids do log já aplicados a este Y.Doc
    let revisao = null;
    let encerrado = false;
    let editor = null;
    let fila = null;
    let comentarios = null;
    let versoes = null;

    const provider = new YReverbProvider(
        ydoc,
        window.Echo,
        `documento.${cfg.documentoId}`,
        { id: cfg.user.id, name: cfg.user.name, color: cfg.user.color },
        {
            onPresence: renderPresence,
            onAlteracao: receberAlteracao,
            onEncerrada: (msg) => encerrar(msg || 'A edição colaborativa deste documento terminou.'),
            onComentarios: () => comentarios?.recarregar(),
            onVersao: (dados) => versoes?.aoVersaoDeOutro(dados),
            onPermissoes: (userId) => {
                if (Number(userId) !== Number(cfg.user.id)) return;
                encerrar('As suas permissões neste documento mudaram. A recarregar…', 'warning');
                setTimeout(() => window.location.reload(), 1500);
            },
        },
    );

    wireTitulo();
    wireConnectionStatus();

    // Estado inicial durável (log persistido) antes de instanciar o editor.
    http.get(cfg.urls.state)
        .then(({ data }) => {
            aplicarDoServidor(data);
            initEditor(data);
        })
        .catch(() => {
            banner('Não foi possível carregar o documento. Recarregue a página.', 'danger');
        });

    function aplicarDoServidor(data) {
        const updates = data.updates || [];
        const ids = data.ids || [];
        updates.forEach((u, i) => {
            if (ids[i] !== undefined && aplicados.has(ids[i])) return;
            Y.applyUpdate(ydoc, fromBase64(u), 'remote');
            if (ids[i] !== undefined) aplicados.add(ids[i]);
        });
    }

    // Update retransmitido pelo servidor. Grande demais para a mensagem, vem só o id:
    // vai-se buscar o que falta.
    function receberAlteracao({ id, update }) {
        if (id && aplicados.has(id)) return;
        if (!update) {
            recuperar();
            return;
        }
        Y.applyUpdate(ydoc, fromBase64(update), 'remote');
        if (id) aplicados.add(id);
    }

    // Traz do servidor o que este cliente ainda não aplicou (ligação perdida, update grande).
    function recuperar() {
        http.get(cfg.urls.updates).then(({ data }) => aplicarDoServidor(data)).catch(() => {});
    }

    function encerrar(msg, tipo = 'danger') {
        if (encerrado) return;
        encerrado = true;
        fila?.parar();
        editor?.setEditable(false);
        banner(msg, tipo);
        desactivarControlos();
        comentarios?.bloquear();
    }

    // O texto fica só de leitura, mas a barra, o título, o destinatário, a versão, os
    // comentários e os convites continuavam com aspecto activo depois de a sessão
    // encerrar (o servidor recusava tudo; ficava só a confusão).
    function desactivarControlos() {
        document.querySelectorAll([
            '#collab-toolbar button',
            '#collab-titulo',
            '[id^="collab-destinatario_"]',
            '#collab-change-type', '#collab-change-log', '#collab-checkpoint',
            '#collab-coment-texto', '#collab-coment-enviar',
            '#collab-invite-user', '#collab-invite-nivel', '#collab-invite-btn', '.collab-remove',
        ].join(',')).forEach((el) => { el.disabled = true; });
        document.getElementById('collab-toolbar')?.classList.add('opacity-50');
        document.getElementById('collab-coment-trecho')?.classList.add('d-none');
    }

    // Recusas definitivas do servidor: param a fila e o editor fica só de leitura.
    function recusaDefinitiva(e) {
        const status = e.response?.status;
        const data = e.response?.data || {};
        if ((status === 409 && (data.sessao_encerrada || data.versao_desactualizada)) || (status === 403 && data.sem_permissao)) {
            encerrar(data.message);
            return true;
        }
        if (status === 419) {
            encerrar('A sua sessão expirou. Copie o texto que escreveu nos últimos segundos e recarregue a página.', 'warning');
            return true;
        }
        return false;
    }

    function initEditor(state) {
        revisao = Number.isInteger(state.revisao_classica) ? state.revisao_classica : null;
        editor = new Editor({
            element: mount,
            editable: cfg.podeEditar,
            extensions: [
                // Esquema que preserva o HTML dos modelos (ver collab/extensoes.js).
                ...extensoesDoDocumento(),
                Collaboration.configure({ document: ydoc }),
                CollaborationCursor.configure({
                    provider,
                    user: { name: cfg.user.name, color: cfg.user.color },
                }),
            ],
        });

        // Seed inicial: só o primeiro a abrir (documento Yjs vazio) semeia a partir do HTML
        // canónico. O update do seed é emitido de forma síncrona, antes de a fila escutar o
        // ydoc; por isso é enviado aqui, explicitamente e SEM html (abrir não altera o documento).
        if (!state.hasState && editor.isEmpty && state.html) {
            editor.commands.setContent(state.html, false);
            enviarSeed();
        }

        wireToolbar(editor);
        if (cfg.podeEditar) {
            fila = criarFilaDeEnvio({
                ydoc,
                editor,
                http,
                url: cfg.urls.sync,
                csrf,
                obterRevisao: () => revisao,
                cabecalhos,
                onConfirmado: (data) => {
                    if (data.id) aplicados.add(data.id);
                    if (data.html_rejeitado) avisarHtmlRejeitado();
                    if (data.compactar) compactarAutomatico();
                },
                onRecusa: recusaDefinitiva,
                onEstado: mostrarPorGravar,
            });
        }
        ligarCamposColaborativos(editor, cfg, http, () => revisao);
        // Depois da fila: os valores iniciais que se escrevam no mapa têm de ser gravados.
        ligarMapaDeCampos(ydoc, cfg);
        comentarios = ligarComentarios(editor, cfg, http, cabecalhos);
        // Por último: as escritas iniciais do mapa de campos não contam como alterações.
        versoes = ligarVersoes({
            editor,
            ydoc,
            cfg,
            http,
            cabecalhos,
            aplicados,
            obterRevisao: () => revisao,
            porGravar: () => fila?.porGravar() ?? 0,
            recusaDefinitiva,
            estaEncerrado: () => encerrado,
        });
    }

    // Regista o estado inicial no log durável, para quem abrir depois partir da mesma base.
    // O servidor só o aceita com o log vazio: se outra pessoa semeou ao mesmo tempo, recarrega-se.
    function enviarSeed() {
        if (!cfg.podeEditar) return;
        http.post(cfg.urls.sync, { update: toBase64(Y.encodeStateAsUpdate(ydoc)), seed: true, revisao }, { headers: cabecalhos() })
            .then(({ data }) => { if (data?.id) aplicados.add(data.id); })
            .catch((e) => {
                if (recusaDefinitiva(e)) return;
                if (e.response?.data?.seed_rejeitado) {
                    banner('Outra pessoa abriu este documento ao mesmo tempo. A recarregar…', 'warning');
                    setTimeout(() => window.location.reload(), 1500);
                }
            });
    }

    // O servidor pediu compactação (log acima do limite): substitui as linhas que este
    // cliente já aplicou pelo estado dele, sem criar versão. No máximo uma vez por 30 s.
    let ultimaCompactacao = 0;
    function compactarAutomatico() {
        const ids = Array.from(aplicados);
        if (encerrado || !ids.length || Date.now() - ultimaCompactacao < 30000) return;
        ultimaCompactacao = Date.now();
        http.post(cfg.urls.compactar, {
            snapshot: toBase64(Y.encodeStateAsUpdate(ydoc)),
            ids_aplicados: ids,
            revisao,
        }, { headers: cabecalhos() }).then(({ data }) => {
            ids.forEach((id) => aplicados.delete(id));
            if (data.snapshot_id) aplicados.add(data.snapshot_id);
        }).catch((e) => { recusaDefinitiva(e); });
    }

    function avisarHtmlRejeitado() {
        banner('As últimas alterações não foram gravadas no documento: removeriam campos do modelo '
            + '(Assunto, destinatário ou referência). Use o editor clássico para alterar esses campos.', 'danger');
    }

    function mostrarPorGravar({ porGravar, falhou }) {
        const el = document.getElementById('collab-por-gravar');
        if (!el) return;
        if (!porGravar) {
            el.textContent = 'Tudo gravado';
            el.className = 'small text-success';
        } else {
            el.textContent = falhou
                ? `${porGravar} alteração(ões) por gravar — a tentar de novo…`
                : `A gravar ${porGravar} alteração(ões)…`;
            el.className = falhou ? 'small text-danger' : 'small text-muted';
        }
    }

    // --- Barra de ferramentas de formatação (negrito, títulos, listas, alinhamento, tabela) ---
    function wireToolbar(ed) {
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
                if (fn && !encerrado) fn(ed.chain().focus()).run();
            });
        });

        const refresh = () => {
            const set = (cmd, on) => bar.querySelector(`[data-cmd="${cmd}"]`)?.classList.toggle('active', on);
            set('bold', ed.isActive('bold'));
            set('italic', ed.isActive('italic'));
            set('underline', ed.isActive('underline'));
            set('strike', ed.isActive('strike'));
            set('h1', ed.isActive('heading', { level: 1 }));
            set('h2', ed.isActive('heading', { level: 2 }));
            set('h3', ed.isActive('heading', { level: 3 }));
            set('bullet', ed.isActive('bulletList'));
            set('ordered', ed.isActive('orderedList'));
            set('blockquote', ed.isActive('blockquote'));
            set('left', ed.isActive({ textAlign: 'left' }));
            set('center', ed.isActive({ textAlign: 'center' }));
            set('right', ed.isActive({ textAlign: 'right' }));
            set('justify', ed.isActive({ textAlign: 'justify' }));
        };
        ed.on('selectionUpdate', refresh);
        ed.on('transaction', refresh);
        refresh();
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
                if (encerrado) return;
                http.post(cfg.urls.titulo, { titulo: input.value, revisao }, { headers: cabecalhos() })
                    .catch((e) => { recusaDefinitiva(e); /* outros erros: re-tentado no próximo input */ });
            }, 800);
        });
    }

    // --- Estado REAL da ligação em tempo real (o Reverb tem de estar a correr) ---
    function wireConnectionStatus() {
        const conn = window.Echo?.connector?.pusher?.connection;
        if (!conn) return;
        let jaLigou = false;
        let perdeu = false;
        const showFallback = () => document.getElementById('collab-fallback-link')?.classList.remove('d-none');
        conn.bind('connecting', () => { if (!encerrado) banner('A ligar ao servidor de tempo real…', 'secondary'); });
        conn.bind('connected', () => {
            // Religado depois de uma falha: pode ter perdido alterações dos outros.
            if (perdeu) recuperar();
            jaLigou = true;
            perdeu = false;
            if (!encerrado) banner('Ligado ao servidor de tempo real.', 'success');
        });
        conn.bind('unavailable', () => {
            perdeu = jaLigou;
            if (encerrado) return;
            banner('Servidor de tempo real indisponível. As suas alterações são guardadas, mas não vê as dos outros em direto — recarregue para sincronizar. (O servidor Reverb tem de estar a correr.)', 'warning');
            showFallback();
        });
        conn.bind('failed', () => {
            if (encerrado) return;
            banner('Falha na ligação em tempo real. As alterações continuam a ser guardadas no servidor; recarregue para ver as dos outros.', 'danger');
            showFallback();
        });
        conn.bind('disconnected', () => {
            if (!jaLigou) return;
            perdeu = true;
            if (!encerrado) banner('Ligação em tempo real perdida — a tentar reconectar…', 'warning');
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
        if (users.length > 0 && !encerrado) {
            const n = users.length;
            banner(`Edição em tempo real ativa — ${n} ${n === 1 ? 'pessoa' : 'pessoas'} online.`, 'success');
        }
    }
});
