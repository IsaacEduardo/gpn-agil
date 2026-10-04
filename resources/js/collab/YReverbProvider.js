import * as Y from 'yjs';
import { Awareness, encodeAwarenessUpdate, applyAwarenessUpdate } from 'y-protocols/awareness';

/**
 * Provider Yjs sobre Laravel Reverb (protocolo Pusher) via Laravel Echo.
 *
 * As alterações ao documento vêm SÓ do servidor (evento collab.alteracao), depois de
 * ele verificar o nível de quem as escreveu e de as gravar no log. Antes iam entre
 * browsers por client events (whispers): qualquer membro do canal — mesmo com nível
 * Visualizar, ou já removido — enviava alterações que os outros aplicavam e gravavam.
 *
 * A awareness (cursores, seleção) continua por whisper: não altera o documento.
 *
 * Expõe `this.awareness` (compatível com a extensão CollaborationCursor do Tiptap).
 */
export default class YReverbProvider {
    /**
     * @param {Y.Doc} doc
     * @param {object} echo  instância window.Echo
     * @param {string} channel  nome do canal de presença (sem prefixo 'presence-')
     * @param {object} localUser  { id, name, color }
     * @param {object} handlers
     *   onPresence(users[]), onAlteracao({id, update?, user_id}),
     *   onEncerrada(message), onPermissoes(userId), onComentarios(), onVersao({versao, autor})
     */
    constructor(doc, echo, channel, localUser, handlers = {}) {
        this.doc = doc;
        this.echo = echo;
        this.channelName = channel;
        this.localUser = localUser;
        this.handlers = handlers;
        this.onlineUsers = [];
        this.awareness = new Awareness(doc);

        this.awareness.setLocalStateField('user', {
            name: localUser.name,
            color: localUser.color,
        });

        this._onAwarenessUpdate = this._onAwarenessUpdate.bind(this);
        this.awareness.on('update', this._onAwarenessUpdate);

        this._connect();
    }

    _emit(nome, ...args) {
        if (typeof this.handlers[nome] === 'function') this.handlers[nome](...args);
    }

    _connect() {
        this.presence = this.echo.join(this.channelName)
            .here((users) => {
                this.onlineUsers = users;
                this._emit('onPresence', users);
            })
            .joining((user) => {
                this.onlineUsers = [...this.onlineUsers, user];
                this._emit('onPresence', this.onlineUsers);
                // Quem entra carrega o documento do servidor; aqui só lhe falta o nosso cursor.
                this._broadcastAwareness([this.doc.clientID]);
            })
            .leaving((user) => {
                this.onlineUsers = this.onlineUsers.filter((u) => u.id !== user.id);
                this._emit('onPresence', this.onlineUsers);
            })
            .listen('.collab.alteracao', (payload) => this._emit('onAlteracao', payload || {}))
            .listen('.collab.encerrada', (payload) => this._emit('onEncerrada', payload?.message))
            .listen('.collab.permissoes', (payload) => this._emit('onPermissoes', payload?.user_id))
            .listen('.collab.comentarios', () => this._emit('onComentarios'))
            .listen('.collab.versao', (payload) => this._emit('onVersao', payload || {}))
            .listenForWhisper('yjs-awareness', (payload) => {
                if (!payload || !payload.state) return;
                applyAwarenessUpdate(this.awareness, fromBase64(payload.state), 'remote');
            })
            .error((e) => {
                console.error('[collab] erro no canal de presença', e);
            });
    }

    _onAwarenessUpdate({ added, updated, removed }, origin) {
        if (origin === 'remote') return;
        this._broadcastAwareness(added.concat(updated).concat(removed));
    }

    _broadcastAwareness(clients) {
        const update = encodeAwarenessUpdate(this.awareness, clients);
        if (this.presence && typeof this.presence.whisper === 'function') {
            this.presence.whisper('yjs-awareness', { state: toBase64(update) });
        }
    }

    /** Aplica updates vindos do servidor (estado inicial ou recuperação). */
    applyUpdates(base64Updates = []) {
        base64Updates.forEach((u) => Y.applyUpdate(this.doc, fromBase64(u), 'remote'));
    }

    destroy() {
        this.awareness.off('update', this._onAwarenessUpdate);
        this.awareness.setLocalState(null);
        if (this.echo) {
            this.echo.leave(this.channelName);
        }
    }
}

// --- Helpers base64 <-> Uint8Array ---

export function toBase64(bytes) {
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
    return btoa(binary);
}

export function fromBase64(str) {
    const binary = atob(str);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return bytes;
}
