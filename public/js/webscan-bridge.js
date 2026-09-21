/**
 * Cliente do WebScan Bridge. O navegador nunca fala directamente com TWAIN/WIA:
 * comunica apenas com o agente local, limitado ao loopback.
 */
(function (global) {
    'use strict';

    const PDF_MIME = 'application/pdf';

    class WebScanError extends Error {
        constructor(message, code = 'AGENT_ERROR') {
            super(message);
            this.name = 'WebScanError';
            this.code = code;
        }
    }

    class WebScanBridge {
        constructor(options = {}) {
            this.baseUrl = (options.baseUrl || 'http://127.0.0.1:18090').replace(/\/$/, '');
            this.timeoutMs = options.timeoutMs || 8000;
            this.storage = global.sessionStorage;
            this.pairingToken = options.pairingToken || this.storage?.getItem('webscan.pairing-token') || '';
            this.isOnline = false;
            this.scanners = [];
            this.pages = [];
            this.lastPdf = null;
            this.onProgressCallback = null;
        }

        setPairingToken(token) {
            this.pairingToken = (token || '').trim();
            if (this.pairingToken) this.storage?.setItem('webscan.pairing-token', this.pairingToken);
            else this.storage?.removeItem('webscan.pairing-token');
        }

        async request(path, options = {}) {
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), options.timeoutMs || this.timeoutMs);
            const headers = new Headers(options.headers || {});
            headers.set('Accept', 'application/json');
            if (this.pairingToken) headers.set('X-WebScan-Pairing', this.pairingToken);

            try {
                const response = await fetch(`${this.baseUrl}${path}`, {
                    ...options,
                    headers,
                    signal: controller.signal,
                });
                const body = await response.json().catch(() => ({}));
                if (!response.ok) throw new WebScanError(body.message || 'O agente local recusou o pedido.', body.error_code || `HTTP_${response.status}`);
                return body;
            } catch (error) {
                if (error.name === 'AbortError') throw new WebScanError('Tempo limite ao comunicar com o scanner.', 'TIMEOUT');
                if (error instanceof WebScanError) throw error;
                throw new WebScanError('Não foi possível comunicar com o agente local.', 'OFFLINE');
            } finally {
                clearTimeout(timeout);
            }
        }

        async checkStatus() {
            try {
                const status = await this.request('/status', { method: 'GET', timeoutMs: 2500 });
                this.isOnline = status.status === 'online';
                return status;
            } catch (error) {
                this.isOnline = false;
                return { status: 'offline', error_code: error.code, message: error.message };
            }
        }

        async getScanners() {
            const status = await this.checkStatus();
            if (status.status !== 'online') return [];
            const data = await this.request('/scanners', { method: 'GET' });
            this.scanners = Array.isArray(data.scanners) ? data.scanners : [];
            return this.scanners;
        }

        /**
         * Sonda o agente enquanto a digitalização decorre.
         *
         * O progresso é acessório: se a sondagem falhar, a digitalização segue e
         * apenas a barra deixa de avançar. Devolve a função que a interrompe.
         */
        trackProgress(intervalMs = 700) {
            let running = true;
            const poll = async () => {
                while (running) {
                    await new Promise(resolve => setTimeout(resolve, intervalMs));
                    if (!running) return;
                    try {
                        const progress = await this.request('/progress', { method: 'GET', timeoutMs: 2500 });
                        if (running && progress.active) {
                            this.onProgressCallback?.({ percent: progress.percent, message: progress.message });
                        }
                    } catch (_) {
                        // Silencioso por desenho: não há nada a comunicar ao operador.
                    }
                }
            };
            poll();
            return () => { running = false; };
        }

        async startScan(options) {
            if (!options || !options.scanner_id) throw new WebScanError('Selecione um scanner disponível.', 'SCANNER_REQUIRED');
            this.onProgressCallback?.({ percent: 5, message: 'A iniciar digitalização…' });
            const stopTracking = this.trackProgress();
            let data;
            try {
                data = await this.request('/scan', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(options),
                    timeoutMs: 300000,
                });
            } finally {
                stopTracking();
            }
            this.lastPdf = this.pdfFileFromResponse(data);
            this.pages.forEach(page => URL.revokeObjectURL(page.previewUrl));
            this.pages = this.previewPages(data.pages || []);
            this.onProgressCallback?.({ percent: 100, message: 'Digitalização concluída.' });
            return { ...data, file: this.lastPdf, pages: this.pages };
        }

        pdfFileFromResponse(data) {
            const encoded = data.pdf_base64;
            if (!encoded || typeof encoded !== 'string') throw new WebScanError('O agente não devolveu um PDF.', 'PDF_MISSING');
            const base64 = encoded.includes(',') ? encoded.split(',', 2)[1] : encoded;
            let bytes;
            try {
                const binary = atob(base64);
                bytes = Uint8Array.from(binary, char => char.charCodeAt(0));
            } catch (_) {
                throw new WebScanError('O PDF devolvido pelo agente é inválido.', 'PDF_INVALID');
            }
            const signature = new TextDecoder().decode(bytes.slice(0, 5));
            if (signature !== '%PDF-') throw new WebScanError('O agente devolveu conteúdo que não é PDF.', 'PDF_INVALID');
            return new File([bytes], data.filename || `documento_digitalizado_${Date.now()}.pdf`, { type: PDF_MIME });
        }

        previewPages(pages) {
            return pages.slice(0, 100).flatMap((page, index) => {
                const encoded = page.preview_base64 || page.image_base64;
                if (!encoded || typeof encoded !== 'string') return [];
                try {
                    const base64 = encoded.includes(',') ? encoded.split(',', 2)[1] : encoded;
                    const binary = atob(base64);
                    const bytes = Uint8Array.from(binary, char => char.charCodeAt(0));
                    const mime = page.mime_type || 'image/jpeg';
                    return [{ number: page.page_number || index + 1, previewUrl: URL.createObjectURL(new Blob([bytes], { type: mime })) }];
                } catch (_) { return []; }
            });
        }

        clear() {
            this.pages.forEach(page => URL.revokeObjectURL(page.previewUrl));
            this.pages = [];
            this.lastPdf = null;
        }
    }

    global.WebScanBridge = WebScanBridge;
    global.WebScanError = WebScanError;
})(window);
