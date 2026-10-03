#!/usr/bin/env node
/**
 * Teste de carga da edição colaborativa: N editores no mesmo Documento Interno.
 *
 * Cada cliente inicia sessão, liga-se ao canal de presença pelo Reverb e envia
 * alterações Yjs reais ao /collab/sync; mede-se o tempo até cada alteração chegar aos
 * outros (relay pelo servidor) e quantas linhas o log ganhou.
 *
 * As alterações vão para um texto 'carga' do Y.Doc, que o editor ignora — mas o
 * documento fica com histórico de carga: usar SEMPRE um documento de teste, nunca em
 * produção.
 *
 * Uso:
 *   node scripts/collab-carga.mjs --base http://127.0.0.1:8010 --doc 123 \
 *     --utilizadores carga1@teste.local,carga2@teste.local,... --senha segredo \
 *     --ws-host 127.0.0.1 --ws-port 8080 --chave <REVERB_APP_KEY> \
 *     [--duracao 30] [--intervalo 1000]
 *
 * --base aceita vários endereços separados por vírgula (os clientes repartem-se por
 * eles): o servidor de desenvolvimento do PHP atende um pedido de cada vez.
 */
import * as Y from 'yjs';
import * as PusherModulo from 'pusher-js';

// No Node o pusher-js expõe a classe em .Pusher (no browser é o export por omissão).
const Pusher = PusherModulo.Pusher ?? PusherModulo.default?.Pusher ?? PusherModulo.default;

const args = Object.fromEntries(process.argv.slice(2).reduce((acc, a, i, todos) => {
    if (a.startsWith('--')) acc.push([a.slice(2), todos[i + 1]?.startsWith('--') ? true : todos[i + 1]]);
    return acc;
}, []));

if (args.help || !args.base || !args.doc || !args.utilizadores || !args.chave) {
    console.log('Uso: node scripts/collab-carga.mjs --base URL --doc ID --utilizadores a@x,b@x --senha S '
        + '--ws-host HOST --ws-port PORTA --chave REVERB_APP_KEY [--duracao 30] [--intervalo 1000] [--tls]');
    process.exit(args.help ? 0 : 1);
}

const bases = String(args.base).split(',').map((b) => b.trim().replace(/\/$/, '')).filter(Boolean);
const duracaoMs = Number(args.duracao || 30) * 1000;
const intervaloMs = Number(args.intervalo || 1000);
const emails = String(args.utilizadores).split(',').map((e) => e.trim()).filter(Boolean);

const enviadoEm = new Map(); // update base64 → instante do envio
const latencias = [];
const duracoesPedido = [];
const contadores = { enviados: 0, falhas: 0, recebidos: 0 };

class Sessao {
    constructor(email, base) {
        this.email = email;
        this.base = base;
        this.cookies = new Map();
        this.csrf = null;
    }

    guardarCookies(resposta) {
        for (const c of resposta.headers.getSetCookie?.() ?? []) {
            const [par] = c.split(';');
            const i = par.indexOf('=');
            this.cookies.set(par.slice(0, i), par.slice(i + 1));
        }
    }

    cabecalhoCookies() {
        return [...this.cookies].map(([k, v]) => `${k}=${v}`).join('; ');
    }

    async pedido(caminho, opcoes = {}) {
        const resposta = await fetch(this.base + caminho, {
            redirect: 'manual',
            ...opcoes,
            headers: { Cookie: this.cabecalhoCookies(), ...(opcoes.headers || {}) },
        });
        this.guardarCookies(resposta);
        return resposta;
    }

    static token(html) {
        return html.match(/name="csrf-token" content="([^"]+)"/)?.[1] ?? html.match(/name="_token" value="([^"]+)"/)?.[1];
    }

    async entrar(senha) {
        const login = await this.pedido('/login');
        const token = Sessao.token(await login.text());
        const r = await this.pedido('/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ _token: token, email: this.email, password: senha }),
        });
        if (r.status !== 302 || String(r.headers.get('location')).includes('/login')) {
            throw new Error(`login falhou para ${this.email} (${r.status})`);
        }
        const editor = await this.pedido(`/documentos-internos/${args.doc}/collab`);
        if (editor.status !== 200) throw new Error(`${this.email} não abre o editor (${editor.status})`);
        this.csrf = Sessao.token(await editor.text());
    }

    json(caminho, corpo, socketId) {
        return this.pedido(caminho, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': this.csrf,
                'X-Requested-With': 'XMLHttpRequest',
                ...(socketId ? { 'X-Socket-ID': socketId } : {}),
            },
            body: JSON.stringify(corpo),
        });
    }
}

function ligar(sessao) {
    return new Promise((resolve, reject) => {
        const pusher = new Pusher(args.chave, {
            wsHost: args['ws-host'] || '127.0.0.1',
            wsPort: Number(args['ws-port'] || 8080),
            wssPort: Number(args['ws-port'] || 443),
            forceTLS: !!args.tls,
            enabledTransports: ['ws', 'wss'],
            cluster: 'mt1',
            channelAuthorization: {
                customHandler: ({ socketId, channelName }, callback) => {
                    sessao.pedido('/broadcasting/auth', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': sessao.csrf, Accept: 'application/json' },
                        body: new URLSearchParams({ socket_id: socketId, channel_name: channelName }),
                    }).then(async (r) => (r.ok ? callback(null, await r.json()) : callback(new Error(`auth ${r.status}`), null)))
                        .catch((e) => callback(e, null));
                },
            },
        });
        const canal = pusher.subscribe(`presence-documento.${args.doc}`);
        canal.bind('pusher:subscription_succeeded', () => resolve(pusher));
        canal.bind('pusher:subscription_error', (e) => reject(new Error(`canal recusado: ${JSON.stringify(e)}`)));
        canal.bind('collab.alteracao', (dados) => {
            contadores.recebidos++;
            const t = dados?.update && enviadoEm.get(dados.update);
            if (t) latencias.push(Date.now() - t);
        });
    });
}

async function contarLog(sessao) {
    const r = await sessao.pedido(`/documentos-internos/${args.doc}/collab/updates`, { headers: { Accept: 'application/json' } });
    return r.ok ? (await r.json()).ids.length : NaN;
}

function percentil(valores, p) {
    if (!valores.length) return NaN;
    const ordenados = [...valores].sort((a, b) => a - b);
    return ordenados[Math.min(ordenados.length - 1, Math.floor((p / 100) * ordenados.length))];
}

const sessoes = emails.map((e, i) => new Sessao(e, bases[i % bases.length]));
console.log(`A iniciar sessão com ${sessoes.length} utilizadores…`);
for (const s of sessoes) await s.entrar(args.senha || 'password');
const clientes = await Promise.all(sessoes.map(async (s) => ({ sessao: s, pusher: await ligar(s), ydoc: new Y.Doc() })));
const linhasAntes = await contarLog(sessoes[0]);
console.log(`${clientes.length} clientes ligados. Log antes: ${linhasAntes} linhas. A enviar durante ${duracaoMs / 1000} s…`);

const fim = Date.now() + duracaoMs;
await Promise.all(clientes.map(async (c, n) => {
    await new Promise((r) => setTimeout(r, (intervaloMs / clientes.length) * n)); // espalha os envios
    while (Date.now() < fim) {
        let update = null;
        c.ydoc.once('update', (u) => { update = u; });
        c.ydoc.getText('carga').insert(0, `${n}`);
        const b64 = Buffer.from(update).toString('base64');
        enviadoEm.set(b64, Date.now());
        try {
            const inicio = Date.now();
            const r = await c.sessao.json(`/documentos-internos/${args.doc}/collab/sync`, { update: b64 }, c.pusher.connection.socket_id);
            duracoesPedido.push(Date.now() - inicio);
            if (r.ok) contadores.enviados++; else contadores.falhas++;
        } catch {
            contadores.falhas++;
        }
        await new Promise((r) => setTimeout(r, intervaloMs));
    }
}));

await new Promise((r) => setTimeout(r, 2000)); // últimas mensagens em trânsito
const linhasDepois = await contarLog(sessoes[0]);
clientes.forEach((c) => c.pusher.disconnect());

const esperados = contadores.enviados * (clientes.length - 1);
console.log('\n=== Resultado ===');
console.log(`Clientes: ${clientes.length} · servidores: ${bases.length} · duração: ${duracaoMs / 1000} s · intervalo por cliente: ${intervaloMs} ms`);
console.log(`Alterações enviadas: ${contadores.enviados} (falhas: ${contadores.falhas})`);
console.log(`Entregas aos outros: ${contadores.recebidos} de ${esperados} esperadas (${esperados ? ((100 * contadores.recebidos) / esperados).toFixed(1) : 0}%)`);
console.log(`Duração do pedido /sync: p50 ${percentil(duracoesPedido, 50)} ms · p95 ${percentil(duracoesPedido, 95)} ms`);
console.log(`Latência do relay (envio → recepção): p50 ${percentil(latencias, 50)} ms · p95 ${percentil(latencias, 95)} ms · máx ${Math.max(...latencias)} ms`);
console.log(`Log: ${linhasAntes} → ${linhasDepois} linhas (+${linhasDepois - linhasAntes})`);
process.exit(0);
