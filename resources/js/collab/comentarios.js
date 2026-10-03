/**
 * Painel de comentários do editor colaborativo (nível Comentar e acima).
 *
 * Um comentário cita o trecho seleccionado no texto; clicar na citação procura-o no
 * documento e selecciona-o. Todo o texto vindo do servidor entra por textContent.
 * Os outros participantes recarregam a lista quando o servidor avisa
 * (collab.comentarios).
 */
const MAX_TRECHO = 300;

export function ligarComentarios(editor, cfg, http, cabecalhos) {
    const lista = document.getElementById('collab-coment-lista');
    if (!lista || !cfg.urls.comentarios) return { recarregar() {}, bloquear() {} };

    const contagem = document.getElementById('collab-coment-count');
    const caixaTrecho = document.getElementById('collab-coment-trecho');
    const texto = document.getElementById('collab-coment-texto');
    const enviar = document.getElementById('collab-coment-enviar');
    let trecho = null;
    let podeComentar = false;
    let bloqueado = false; // sessão encerrada: só leitura, sem pedidos ao servidor
    let ultimas = [];

    // A selecção no texto passa a ser o trecho do próximo comentário.
    editor.on('selectionUpdate', () => {
        const { from, to, empty } = editor.state.selection;
        trecho = empty ? null : editor.state.doc.textBetween(from, to, ' ').trim().slice(0, MAX_TRECHO) || null;
        if (!caixaTrecho) return;
        caixaTrecho.classList.toggle('d-none', !trecho);
        caixaTrecho.textContent = trecho ? `Sobre: «${trecho}»` : '';
    });

    enviar?.addEventListener('click', () => {
        const valor = texto.value.trim();
        if (!valor) return;
        enviar.disabled = true;
        http.post(cfg.urls.comentarios, { texto: valor, trecho }, { headers: cabecalhos() })
            .then(() => { texto.value = ''; recarregar(); })
            .catch((e) => alert(e.response?.data?.message || 'Não foi possível comentar.'))
            .finally(() => { enviar.disabled = false; });
    });

    function recarregar() {
        if (bloqueado) return;
        http.get(cfg.urls.comentarios).then(({ data }) => {
            podeComentar = !!data.pode_comentar;
            render(data.conversas || []);
        }).catch(() => {});
    }

    function el(tag, classe, conteudo) {
        const n = document.createElement(tag);
        if (classe) n.className = classe;
        if (conteudo !== undefined) n.textContent = conteudo;
        return n;
    }

    function render(conversas) {
        ultimas = conversas;
        lista.innerHTML = '';
        const abertas = conversas.filter((c) => !c.resolvido).length;
        if (contagem) contagem.textContent = abertas;
        if (!conversas.length) {
            lista.appendChild(el('div', 'list-group-item text-muted', 'Sem comentários.'));
            return;
        }
        conversas.forEach((c) => lista.appendChild(conversa(c)));
    }

    function conversa(c) {
        const item = el('div', `list-group-item${c.resolvido ? ' bg-light opacity-75' : ''}`);

        if (c.trecho) {
            const citacao = el('button', 'btn btn-link btn-sm p-0 text-start fst-italic text-decoration-none mb-1', `«${c.trecho}»`);
            citacao.type = 'button';
            citacao.title = 'Mostrar no texto';
            citacao.addEventListener('click', () => localizar(c.trecho));
            item.appendChild(citacao);
        }
        item.appendChild(mensagem(c));
        (c.respostas || []).forEach((r) => {
            const resposta = mensagem(r);
            resposta.classList.add('ms-3', 'border-start', 'ps-2', 'mt-1');
            item.appendChild(resposta);
        });

        const accoes = el('div', 'd-flex gap-1 mt-1');
        if (c.resolvido) {
            accoes.appendChild(el('span', 'badge bg-success-subtle text-success-emphasis', `Resolvido${c.resolvido_por ? ` por ${c.resolvido_por}` : ''}`));
        }
        if (c.pode_resolver && !bloqueado) {
            const botao = el('button', 'btn btn-outline-secondary btn-sm py-0', c.resolvido ? 'Reabrir' : 'Resolver');
            botao.type = 'button';
            botao.addEventListener('click', () => {
                http.patch(`${cfg.urls.comentarios}/${c.id}/resolver`, { resolvido: !c.resolvido }, { headers: cabecalhos() })
                    .then(recarregar).catch(() => alert('Não foi possível actualizar o comentário.'));
            });
            accoes.appendChild(botao);
        }
        item.appendChild(accoes);

        if (podeComentar) item.appendChild(caixaResposta(c.id));

        return item;
    }

    function mensagem(m) {
        const bloco = el('div');
        const cabecalho = el('div', 'text-muted', `${m.autor || '—'} · ${m.criado_em || ''}`);
        cabecalho.style.fontSize = '.72rem';
        bloco.appendChild(cabecalho);
        const corpo = el('div', '', m.texto);
        corpo.style.whiteSpace = 'pre-line';
        bloco.appendChild(corpo);
        return bloco;
    }

    function caixaResposta(paiId) {
        const linha = el('div', 'input-group input-group-sm mt-1');
        const campo = el('input', 'form-control');
        campo.placeholder = 'Responder…';
        const botao = el('button', 'btn btn-outline-primary', 'Enviar');
        botao.type = 'button';
        botao.addEventListener('click', () => {
            const valor = campo.value.trim();
            if (!valor) return;
            botao.disabled = true;
            http.post(cfg.urls.comentarios, { texto: valor, parent_id: paiId }, { headers: cabecalhos() })
                .then(recarregar)
                .catch((e) => { botao.disabled = false; alert(e.response?.data?.message || 'Não foi possível responder.'); });
        });
        linha.append(campo, botao);
        return linha;
    }

    // Procura a citação no texto (dentro de um mesmo nó de texto) e selecciona-a.
    function localizar(citacao) {
        const alvo = citacao.slice(0, 60);
        let encontrado = null;
        editor.state.doc.descendants((node, pos) => {
            if (encontrado || !node.isText) return !encontrado;
            const i = node.text.indexOf(alvo);
            if (i >= 0) encontrado = { from: pos + i, to: pos + i + alvo.length };
            return true;
        });
        if (!encontrado) {
            alert('O trecho já não existe no texto (foi alterado depois do comentário).');
            return;
        }
        editor.chain().setTextSelection(encontrado).scrollIntoView().run();
    }

    // Sessão encerrada: o servidor já não serve a lista (403 fora de rascunho), por isso
    // redesenha-se a que está no ecrã, sem caixas de resposta nem botões.
    function bloquear() {
        bloqueado = true;
        podeComentar = false;
        render(ultimas);
    }

    recarregar();
    return { recarregar, bloquear };
}
