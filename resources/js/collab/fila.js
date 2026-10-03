import * as Y from 'yjs';
import { toBase64 } from './YReverbProvider';

/**
 * Fila de envio das alterações locais para o servidor (/collab/sync).
 *
 * Regras, todas por causa de perdas reais:
 *  - Um update só sai da fila quando o servidor confirma (2xx). Antes a fila era
 *    esvaziada antes do pedido, e um /sync falhado perdia o delta para sempre.
 *  - Falhas são re-tentadas com espera crescente (1 s, 2 s, 4 s… até 30 s).
 *  - Um pedido de cada vez, pela ordem em que as alterações foram feitas.
 *  - Recusas definitivas (sessão encerrada, sem permissão, sessão desactualizada,
 *    token expirado) param a fila e são entregues a onRecusa.
 *  - O HTML (autosave do documento) segue com o delta no máximo a cada 3 s; parado de
 *    escrever, segue sozinho 3 s depois da última alteração.
 *  - Ao fechar a página, o que falta vai num fetch keepalive COM o token CSRF (o
 *    sendBeacon antigo ia sem ele e o servidor recusava-o com 419).
 */
const ATRASO_DELTA = 150;
const INTERVALO_HTML = 3000;
const ESPERA_MAXIMA = 30000;
const LIMITE_KEEPALIVE = 60000; // o browser recusa corpos keepalive acima de 64 KB

export function criarFilaDeEnvio({ ydoc, editor, http, url, csrf, obterRevisao, cabecalhos, onConfirmado, onRecusa, onEstado }) {
    let pendentes = [];
    let emVoo = 0;          // quantos updates de `pendentes` vão no pedido em curso
    let aEnviar = false;
    let timer = null;
    let tentativa = 0;
    let parada = false;
    let mudancas = 0;       // alterações locais desde o início
    let mudancasNoHtml = 0; // até onde o último HTML gravado vai
    let ultimoHtml = 0;

    const htmlSujo = () => mudancas !== mudancasNoHtml;
    const estado = (extra = {}) => onEstado?.({ porGravar: pendentes.length, falhou: tentativa > 0, ...extra });

    ydoc.on('update', (update, origin) => {
        if (origin === 'remote' || parada) return;
        pendentes.push(update);
        mudancas++;
        estado();
        agendar(ATRASO_DELTA);
    });

    function agendar(ms) {
        if (timer || aEnviar || parada) return;
        timer = setTimeout(enviar, ms);
    }

    function proximoAgendamento() {
        if (parada) return;
        if (tentativa > 0) {
            agendar(Math.min(ESPERA_MAXIMA, 1000 * 2 ** (tentativa - 1)));
        } else if (pendentes.length) {
            agendar(ATRASO_DELTA);
        } else if (htmlSujo()) {
            agendar(Math.max(0, INTERVALO_HTML - (Date.now() - ultimoHtml)));
        }
    }

    function enviar() {
        timer = null;
        if (parada || aEnviar) return;

        const lote = pendentes.slice();
        const comHtml = htmlSujo() && (lote.length === 0 || Date.now() - ultimoHtml >= INTERVALO_HTML);
        if (!lote.length && !comHtml) {
            proximoAgendamento();
            return;
        }

        const corpo = { revisao: obterRevisao() };
        if (lote.length) corpo.update = toBase64(Y.mergeUpdates(lote));
        const mudancasEnviadas = mudancas;
        if (comHtml) corpo.html = editor.getHTML();

        aEnviar = true;
        emVoo = lote.length;
        http.post(url, corpo, { headers: cabecalhos() })
            .then(({ data }) => {
                pendentes.splice(0, lote.length);
                tentativa = 0;
                if (comHtml) {
                    mudancasNoHtml = mudancasEnviadas;
                    ultimoHtml = Date.now();
                }
                onConfirmado?.(data || {});
            })
            .catch((e) => {
                if (onRecusa?.(e)) {
                    parada = true;
                    return;
                }
                tentativa++;
            })
            .finally(() => {
                aEnviar = false;
                emVoo = 0;
                estado();
                proximoAgendamento();
            });
    }

    // O que ainda não foi confirmado nem vai no pedido em curso segue num fetch
    // keepalive, que sobrevive ao fecho da página e leva o token.
    window.addEventListener('beforeunload', (evento) => {
        if (parada) return;
        const resto = pendentes.slice(emVoo);
        if (!resto.length && !htmlSujo()) return;

        const corpo = { revisao: obterRevisao() };
        if (resto.length) corpo.update = toBase64(Y.mergeUpdates(resto));
        let json = JSON.stringify({ ...corpo, html: editor.getHTML() });
        if (json.length > LIMITE_KEEPALIVE) json = JSON.stringify(corpo);

        if (json.length <= LIMITE_KEEPALIVE && (corpo.update || htmlSujo())) {
            fetch(url, {
                method: 'POST',
                keepalive: true,
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                    ...cabecalhos(),
                },
                body: json,
            }).catch(() => {});
        }

        // Ainda há alterações a caminho: o browser pergunta antes de fechar.
        if (resto.length || emVoo) {
            evento.preventDefault();
            evento.returnValue = '';
        }
    });

    return {
        parar() { parada = true; clearTimeout(timer); timer = null; },
        porGravar: () => pendentes.length,
    };
}
