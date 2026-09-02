/**
 * WebScanBridge Client Library
 * Biblioteca de integração entre a aplicação EDMS Web e o agente local de digitalização (TWAIN/WIA/SANE).
 * Agente escuta por padrão em http://127.0.0.1:18090
 */

class WebScanBridge {
    constructor(baseUrl = 'http://127.0.0.1:18090') {
        this.baseUrl = baseUrl;
        this.isOnline = false;
        this.scanners = [];
        this.pages = []; // Lista de objetos { id, imageBase64, rotation, width, height }
        this.onProgressCallback = null;
        this.onPageCapturedCallback = null;
        this.onErrorCallback = null;
    }

    /**
     * Verifica o estado de comunicação (healthcheck) com o agente local.
     */
    async checkStatus() {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 2000);

            const response = await fetch(`${this.baseUrl}/status`, {
                method: 'GET',
                signal: controller.signal,
                headers: { 'Accept': 'application/json' }
            });
            clearTimeout(timeoutId);

            if (response.ok) {
                const data = await response.json();
                this.isOnline = data.status === 'online';
                return data;
            }
        } catch (e) {
            this.isOnline = false;
        }
        return { status: 'offline' };
    }

    /**
     * Obtém a lista de scanners conectados ao computador do utilizador.
     */
    async getScanners() {
        if (!this.isOnline) {
            const status = await this.checkStatus();
            if (status.status !== 'online') return [];
        }

        try {
            const response = await fetch(`${this.baseUrl}/scanners`);
            if (response.ok) {
                const data = await response.json();
                this.scanners = data.scanners || [];
                return this.scanners;
            }
        } catch (e) {
            console.warn('[WebScanBridge] Falha ao buscar scanners:', e);
        }
        return [];
    }

    /**
     * Executa o processo de digitalização de documentos.
     * @param {Object} options - Parâmetros { scanner_id, dpi, color_mode, source, duplex }
     */
    async startScan(options = {}) {
        const payload = {
            scanner_id: options.scanner_id || (this.scanners[0] ? this.scanners[0].id : 'default'),
            dpi: parseInt(options.dpi || 200),
            color_mode: options.color_mode || 'color', // bw, gray, color
            source: options.source || 'adf', // adf, flatbed
            duplex: !!options.duplex,
            auto_deskew: true,
            auto_crop: true
        };

        if (this.onProgressCallback) {
            this.onProgressCallback('Conectando ao scanner...');
        }

        try {
            const response = await fetch(`${this.baseUrl}/scan`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const errData = await response.json().catch(() => ({ message: 'Erro desconhecido no scanner.' }));
                throw new Error(errData.message || 'Erro durante a digitalização.');
            }

            const data = await response.json();

            if (data.status === 'success' && data.pages && data.pages.length > 0) {
                data.pages.forEach(p => {
                    this.addPage(p.image_base64, p.width, p.height);
                });

                if (this.onProgressCallback) {
                    this.onProgressCallback(`Digitalização concluída: ${data.pages.length} página(s) capturada(s).`);
                }
                return data;
            } else {
                throw new Error('Nenhuma página foi capturada pelo scanner.');
            }
        } catch (err) {
            console.error('[WebScanBridge] Erro no scan:', err);
            if (this.onErrorCallback) {
                this.onErrorCallback(err.message);
            }
            throw err;
        }
    }

    /**
     * Adiciona uma página digitalizada à lista de miniaturas.
     */
    addPage(imageBase64, width = 1654, height = 2339) {
        const pageObj = {
            id: 'page_' + Math.random().toString(36).substr(2, 9),
            imageBase64: imageBase64.startsWith('data:') ? imageBase64 : `data:image/jpeg;base64,${imageBase64}`,
            rotation: 0,
            width: width,
            height: height
        };
        this.pages.push(pageObj);
        if (this.onPageCapturedCallback) {
            this.onPageCapturedCallback(pageObj, this.pages);
        }
        return pageObj;
    }

    /**
     * Gira uma página específica em 90 graus.
     */
    rotatePage(pageId, degrees = 90) {
        const page = this.pages.find(p => p.id === pageId);
        if (page) {
            page.rotation = (page.rotation + degrees) % 360;
        }
        return page;
    }

    /**
     * Remove uma página da lista.
     */
    deletePage(pageId) {
        this.pages = this.pages.filter(p => p.id !== pageId);
        return this.pages;
    }

    /**
     * Limpa todo o lote de páginas capturadas.
     */
    clearPages() {
        this.pages = [];
    }

    /**
     * Compila todas as páginas em um único objeto File em formato PDF simulado / imagem.
     * Retorna um File pronto para ser atribuído a um input de formulário HTML.
     */
    async generateFile(filename = 'documento_digitalizado.pdf') {
        if (this.pages.length === 0) {
            throw new Error('Nenhuma página disponível para compilação.');
        }

        // Criar uma representação Blob em PDF/Imagem simples combinando as páginas
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        // Para simplificar a geração sem bibliotecas pesadas de terceiros,
        // geramos um arquivo combinando as imagens em alta resolução
        const firstPage = this.pages[0];
        const img = new Image();

        await new Promise((resolve, reject) => {
            img.onload = resolve;
            img.onerror = reject;
            img.src = firstPage.imageBase64;
        });

        canvas.width = img.width;
        canvas.height = img.height * this.pages.length;

        for (let i = 0; i < this.pages.length; i++) {
            const p = this.pages[i];
            const pageImg = new Image();
            await new Promise((resolve) => {
                pageImg.onload = resolve;
                pageImg.src = p.imageBase64;
            });

            ctx.save();
            if (p.rotation !== 0) {
                ctx.translate(canvas.width / 2, (i * img.height) + img.height / 2);
                ctx.rotate((p.rotation * Math.PI) / 180);
                ctx.drawImage(pageImg, -img.width / 2, -img.height / 2);
            } else {
                ctx.drawImage(pageImg, 0, i * img.height);
            }
            ctx.restore();
        }

        const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
        const res = await fetch(dataUrl);
        const blob = await res.blob();

        return new File([blob], filename, { type: 'application/pdf' });
    }
}

window.WebScanBridge = WebScanBridge;
