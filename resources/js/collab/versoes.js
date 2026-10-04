import * as Y from 'yjs';
import { toBase64 } from './YReverbProvider';

/**
 * "Guardar versão" no editor colaborativo.
 *
 * O botão diz o estado do documento antes do clique: activo só quando há alterações
 * desde a última versão ("Guardar versão"), desactivado quando não há ("Sem alterações
 * desde a v1.0.0"), "A guardar…" durante o pedido. Antes estava sempre activo, a
 * confirmação aparecia no topo da página, longe do botão, e cada clique repetido criava
 * uma versão igual à anterior.
 *
 * A confirmação aparece numa notificação de canto e no número da versão no cabeçalho;
 * os colegas recebem "Fulano guardou a v1.1.0" (evento collab.versao).
 */
export function ligarVersoes({ editor, ydoc, cfg, http, cabecalhos, aplicados, obterRevisao, porGravar, recusaDefinitiva, estaEncerrado }) {
    const botao = document.getElementById('collab-checkpoint');
    const cabecalho = document.getElementById('collab-versao-atual');
    if (!botao) return { aoVersaoDeOutro: (d) => mostrarVersao(d?.versao) };

    const rotulo = document.getElementById('collab-checkpoint-rotulo');
    const spinner = botao.querySelector('.spinner-border');
    const estado = document.getElementById('collab-versao-estado');
    const tipo = document.getElementById('collab-change-type');
    const descricao = document.getElementById('collab-change-log');

    let versao = cfg.versao;
    let porGuardar = !!cfg.porGuardar;
    let aGuardar = false;
    let mudouDuranteGravacao = false;

    const avisar = (tipoAviso, mensagem) => window.Toast?.[tipoAviso]?.(mensagem);

    function mostrarVersao(v) {
        if (v && cabecalho) cabecalho.textContent = `v${v}`;
    }

    function actualizar(textoEstado = null) {
        mostrarVersao(versao);
        spinner?.classList.toggle('d-none', !aGuardar);
        if (aGuardar) {
            rotulo.textContent = 'A guardar…';
            botao.disabled = true;
            return;
        }
        rotulo.textContent = porGuardar ? 'Guardar versão' : `Sem alterações desde a v${versao}`;
        botao.disabled = !porGuardar || estaEncerrado();
        if (estado) {
            estado.textContent = textoEstado
                ?? (porGuardar ? `Há alterações desde a v${versao} por guardar numa versão.` : `O documento está igual à v${versao}.`);
        }
    }

    // Qualquer alteração ao documento partilhado (do próprio ou de um colega) passa a
    // haver o que guardar. Os updates iniciais já foram aplicados antes desta ligação.
    ydoc.on('update', () => {
        if (aGuardar) mudouDuranteGravacao = true;
        if (!porGuardar) {
            porGuardar = true;
            actualizar();
        }
    });

    botao.addEventListener('click', () => {
        if (aGuardar || !porGuardar || estaEncerrado()) return;
        aGuardar = true;
        mudouDuranteGravacao = false;
        actualizar();

        const ids = Array.from(aplicados);
        http.post(cfg.urls.checkpoint, {
            html: editor.getHTML(),
            change_type: tipo?.value || 'minor',
            change_log: descricao?.value || null,
            snapshot: toBase64(Y.encodeStateAsUpdate(ydoc)),
            ids_aplicados: ids,
            revisao: obterRevisao(),
        }, { headers: cabecalhos() }).then(({ data }) => {
            ids.forEach((id) => aplicados.delete(id));
            if (data.snapshot_id) aplicados.add(data.snapshot_id);
            versao = data.versao;
            porGuardar = mudouDuranteGravacao;
            if (descricao) descricao.value = '';
            if (tipo) tipo.value = 'minor';
            avisar('success', `Versão ${versao} guardada no histórico.`);
            aGuardar = false;
            actualizar(`Versão ${versao} guardada às ${new Date().toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' })}.`);
        }).catch((e) => {
            aGuardar = false;
            const data = e.response?.data || {};
            if (e.response?.status === 409 && data.sem_alteracoes) {
                // O texto voltou a ficar igual à última versão (ex.: alteração desfeita).
                versao = data.versao || versao;
                porGuardar = false;
                avisar('info', data.message);
                actualizar();
                return;
            }
            if (recusaDefinitiva(e)) {
                actualizar();
                return;
            }
            avisar('error', data.message || 'Não foi possível guardar a versão. Tente de novo.');
            actualizar(data.message || 'Não foi possível guardar a versão.');
        });
    });

    actualizar();

    return {
        // Um colega guardou uma versão. O que este cliente ainda não enviou não está nela.
        aoVersaoDeOutro({ versao: v, autor } = {}) {
            if (!v) return;
            versao = v;
            if (!aGuardar && porGravar() === 0) porGuardar = false;
            avisar('info', `${autor || 'Um colega'} guardou a v${v}.`);
            actualizar();
        },
    };
}
