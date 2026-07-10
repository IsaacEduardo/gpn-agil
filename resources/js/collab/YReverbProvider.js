import * as Y from 'yjs';
import { Awareness, encodeAwarenessUpdate, applyAwarenessUpdate } from 'y-protocols/awareness';

/**
 * Provider Yjs sobre Laravel Reverb (protocolo Pusher) via Laravel Echo.
 *
 * Estratégia de baixa latência: os deltas Yjs e a "awareness" (cursores/seleção/presença) são
 * trocados diretamente entre pares por client events (whispers) do canal de presença — sem
 * round-trip pelo backend por tecla. A durabilidade é assegurada em paralelo pelo backend
 * (endpoint /sync), fora do caminho crítico de latência.
 *
 * Expõe `this.awareness` (compatível com a extensão CollaborationCursor do Tiptap) e mantém
 * `this.onlineUsers` para o indicador de presença.
 */
export default class YReverbProvider {
    /**
     * @param {Y.Doc} doc
     * @param {object} echo  instância window.Echo
     * @param {string} channel  nome do canal de presença (sem prefixo 'presence-')
     * @param {object} localUser  { id, name, color }
     * @param {function} onPresenceChange  callback(onlineUsers[])
     */
    constructor(doc, echo, channel, localUser, onPresenceChange = () => {}) {
        this.doc = doc;
        this.echo = echo;
        this.channelName = channel;
        this.localUser = localUser;
        this.onPresenceChange = onPresenceChange;
        this.onlineUsers = [];
        this.awareness = new Awareness(doc);
        this.synced = false;

        this.awareness.setLocalStateField('user', {
            name: localUser.name,
            color: localUser.color,
        });

        this._onDocUpdate = this._onDocUpdate.bind(this);
        this._onAwarenessUpdate = this._onAwarenessUpdate.bind(this);

        this.doc.on('update', this._onDocUpdate);
        this.awareness.on('update', this._onAwarenessUpdate);

        this._connect();
    }

    _connect() {
        // Canal de presença: identidade/lista de online + relay de whispers entre membros.
        this.presence = this.echo.join(this.channelName)
            .here((users) => {
                this.onlineUsers = users;
                this.onPresenceChange(users);
            })
            .joining((user) => {
                this.onlineUsers = [...this.onlineUsers, user];
                this.onPresenceChange(this.onlineUsers);
                // Envia o estado completo do documento para quem acaba de entrar (catch-up).
                this._broadcastFullState();
                // E também a nossa awareness atual.
                this._broadcastAwareness([this.doc.clientID]);
            })
            .leaving((user) => {
                this.onlineUsers = this.onlineUsers.filter((u) => u.id !== user.id);
                this.onPresenceChange(this.onlineUsers);
            })
            .listenForWhisper('yjs-update', (payload) => {
                if (!payload || !payload.update) return;
                Y.applyUpdate(this.doc, this._fromBase64(payload.update), 'remote');
            })
            .listenForWhisper('yjs-awareness', (payload) => {
                if (!payload || !payload.state) return;
                applyAwarenessUpdate(this.awareness, this._fromBase64(payload.state), 'remote');
            })
            .error((e) => {
                console.error('[collab] erro no canal de presença', e);
            });
    }

    _onDocUpdate(update, origin) {
        // Não reemitir o que chegou de um par (evita loops); Yjs é idempotente de qualquer forma.
        if (origin === 'remote') return;
        this._whisper('yjs-update', { update: this._toBase64(update) });
    }

    _onAwarenessUpdate({ added, updated, removed }, origin) {
        if (origin === 'remote') return;
        const changed = added.concat(updated).concat(removed);
        this._broadcastAwareness(changed);
    }

    _broadcastAwareness(clients) {
        const update = encodeAwarenessUpdate(this.awareness, clients);
        this._whisper('yjs-awareness', { state: this._toBase64(update) });
    }

    _broadcastFullState() {
        const full = Y.encodeStateAsUpdate(this.doc);
        this._whisper('yjs-update', { update: this._toBase64(full) });
    }

    _whisper(event, payload) {
        if (this.presence && typeof this.presence.whisper === 'function') {
            this.presence.whisper(event, payload);
        }
    }

    /**
     * Aplica um estado inicial vindo do backend (log de updates persistido).
     * @param {string[]} base64Updates
     */
    applyInitialUpdates(base64Updates = []) {
        base64Updates.forEach((u) => Y.applyUpdate(this.doc, this._fromBase64(u), 'remote'));
        this.synced = true;
    }

    destroy() {
        this.doc.off('update', this._onDocUpdate);
        this.awareness.off('update', this._onAwarenessUpdate);
        this.awareness.setLocalState(null);
        if (this.echo) {
            this.echo.leave(this.channelName);
        }
    }

    // --- Helpers base64 <-> Uint8Array ---

    _toBase64(bytes) {
        let binary = '';
        const len = bytes.byteLength;
        for (let i = 0; i < len; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    _fromBase64(str) {
        const binary = atob(str);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes;
    }
}
