@props(['targetInputId' => 'fileInput', 'modalId' => 'webscanModal'])

{{-- Componente Modal de Digitalização Direta (WebScan Bridge) --}}
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            
            {{-- Header --}}
            <div class="modal-header bg-primary text-white py-3 rounded-top-4">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="{{ $modalId }}Label">
                    <i class="fas fa-print fs-4"></i>
                    <span>Digitalizar Documentos (Scanner Direct)</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Body --}}
            <div class="modal-body p-4">

                {{-- Status Alert do Agente --}}
                <div id="webscanAgentStatus" class="alert alert-light border d-flex align-items-center justify-content-between p-3 rounded-3 mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <span id="webscanStatusIndicator" class="spinner-grow spinner-grow-sm text-warning" role="status"></span>
                        <div>
                            <strong id="webscanStatusText" class="d-block text-dark">Verificando comunicação com scanner local...</strong>
                            <small class="text-muted">Serviço WebScan Bridge (127.0.0.1:18090)</small>
                        </div>
                    </div>
                    <button type="button" id="btnRetryWebscanAgent" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        <i class="fas fa-sync-alt me-1"></i> Reconectar
                    </button>
                </div>

                {{-- Painel de Configurações do Scanner --}}
                <div class="card border-0 bg-light rounded-3 p-3 mb-4">
                    <div class="row g-3 align-items-center">
                        
                        {{-- Seletor de Scanner --}}
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted mb-1">Scanner Conectado</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="fas fa-scanner text-primary"></i></span>
                                <select id="webscanScannerSelect" class="form-select bg-white fw-semibold" disabled>
                                    <option value="">Aguardando detecção de hardware...</option>
                                </select>
                            </div>
                        </div>

                        {{-- Resolução DPI --}}
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Resolução (DPI)</label>
                            <select id="webscanDpiSelect" class="form-select form-select-sm bg-white">
                                <option value="200" selected>200 DPI (Padrão/Leve)</option>
                                <option value="300">300 DPI (Alta Qualidade)</option>
                            </select>
                        </div>

                        {{-- Modo de Cor --}}
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-muted mb-1">Modo de Cor</label>
                            <select id="webscanColorModeSelect" class="form-select form-select-sm bg-white">
                                <option value="color" selected>Colorido</option>
                                <option value="bw">Preto e Branco (Rápido)</option>
                                <option value="gray">Tons de Cinza</option>
                            </select>
                        </div>

                        {{-- Opções Rápidas (ADF / Duplex) --}}
                        <div class="col-md-6">
                            <div class="d-flex gap-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="webscanAdfSwitch" checked>
                                    <label class="form-check-label small fw-semibold text-dark" for="webscanAdfSwitch">Alimentador Automático (ADF)</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="webscanDuplexSwitch">
                                    <label class="form-check-label small fw-semibold text-dark" for="webscanDuplexSwitch">Frente e Verso (Duplex)</label>
                                </div>
                            </div>
                        </div>

                        {{-- Botão de Disparo --}}
                        <div class="col-md-6 text-end">
                            <button type="button" id="btnStartScanAction" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm" disabled>
                                <i class="fas fa-play me-2"></i> Iniciar Digitalização
                            </button>
                        </div>

                    </div>
                </div>

                {{-- Barra de Progresso do Escaneamento --}}
                <div id="webscanProgressBarContainer" class="d-none mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span id="webscanProgressStatusText" class="small fw-bold text-primary">Capturando páginas do alimentador...</span>
                        <span id="webscanProgressPercent" class="small text-muted">0%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div id="webscanProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>

                {{-- Área de Visualização de Miniaturas das Páginas Capturadas --}}
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="fas fa-images text-secondary"></i>
                            <span>Páginas Capturadas</span>
                            <span id="webscanPageBadge" class="badge bg-secondary rounded-pill">0 páginas</span>
                        </h6>
                        <button type="button" id="btnClearAllPages" class="btn btn-link btn-sm text-danger text-decoration-none d-none">
                            <i class="fas fa-trash me-1"></i> Limpar Tudo
                        </button>
                    </div>

                    {{-- Container das Miniaturas --}}
                    <div id="webscanThumbnailsContainer" class="border rounded-4 p-3 bg-light d-flex flex-wrap gap-3 align-items-center justify-content-start" style="min-height: 180px; max-height: 320px; overflow-y: auto;">
                        <div id="webscanEmptyPlaceholder" class="text-center w-100 py-4 text-muted">
                            <i class="fas fa-scanner fs-1 opacity-25 d-block mb-2"></i>
                            <p class="mb-0 small">Nenhuma página capturada ainda. Clique em "Iniciar Digitalização" acima.</p>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Footer --}}
            <div class="modal-footer border-0 pt-0 pb-4 px-4 d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btnConfirmAttachScan" class="btn btn-success rounded-pill px-5 fw-bold shadow" disabled>
                    <i class="fas fa-check-circle me-2"></i> Confirmar e Anexar ao Documento
                </button>
            </div>

        </div>
    </div>
</div>

{{-- Scripts do Modal --}}
<script src="{{ asset('js/webscan-bridge.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const bridge = new WebScanBridge();
    const targetInputId = '{{ $targetInputId }}';
    
    // Elementos DOM
    const statusIndicator = document.getElementById('webscanStatusIndicator');
    const statusText = document.getElementById('webscanStatusText');
    const scannerSelect = document.getElementById('webscanScannerSelect');
    const btnStartScan = document.getElementById('btnStartScanAction');
    const btnConfirmAttach = document.getElementById('btnConfirmAttachScan');
    const thumbnailsContainer = document.getElementById('webscanThumbnailsContainer');
    const emptyPlaceholder = document.getElementById('webscanEmptyPlaceholder');
    const pageBadge = document.getElementById('webscanPageBadge');
    const btnClearAll = document.getElementById('btnClearAllPages');
    const progressBarContainer = document.getElementById('webscanProgressBarContainer');
    const progressBar = document.getElementById('webscanProgressBar');
    const progressStatusText = document.getElementById('webscanProgressStatusText');

    // Elementos Externos de Status na Tela Principal
    const mainScannerBadge = document.getElementById('mainScannerBadgeIndicator');
    const btnOpenScannerModal = document.getElementById('btnOpenScannerModal');

    // Healthcheck Inicial
    async function checkAgentHealth() {
        statusIndicator.className = 'spinner-grow spinner-grow-sm text-warning';
        statusText.innerText = 'Conectando ao agente de digitalização local...';
        
        const health = await bridge.checkStatus();
        
        if (health.status === 'online') {
            statusIndicator.className = 'fas fa-check-circle text-success fs-5';
            statusText.innerHTML = '<span class="text-success fw-bold">Agente WebScan Bridge Conectado</span>';
            
            if (mainScannerBadge) {
                mainScannerBadge.className = 'badge bg-success-subtle text-success border border-success rounded-pill px-2 py-1';
                mainScannerBadge.innerHTML = '<i class="fas fa-circle text-success me-1 small"></i> Scanner Ativo';
            }
            if (btnOpenScannerModal) {
                btnOpenScannerModal.classList.remove('disabled');
            }

            // Carregar lista de scanners
            loadScanners();
        } else {
            statusIndicator.className = 'fas fa-exclamation-circle text-danger fs-5';
            statusText.innerHTML = '<span class="text-danger fw-bold">Agente Local Desconectado</span> <br><small class="text-muted">Certifique-se de que o WebScan Bridge está em execução em 127.0.0.1:18090</small>';
            scannerSelect.disabled = true;
            btnStartScan.disabled = true;
            
            if (mainScannerBadge) {
                mainScannerBadge.className = 'badge bg-light text-muted border rounded-pill px-2 py-1';
                mainScannerBadge.innerHTML = '<i class="fas fa-circle text-secondary me-1 small"></i> Scanner Offline';
            }
        }
    }

    async function loadScanners() {
        const scanners = await bridge.getScanners();
        scannerSelect.innerHTML = '';
        
        if (scanners.length > 0) {
            scanners.forEach(sc => {
                const opt = document.createElement('option');
                opt.value = sc.id;
                opt.innerText = `${sc.name} (${sc.driver})`;
                scannerSelect.appendChild(opt);
            });
            scannerSelect.disabled = false;
            btnStartScan.disabled = false;
        } else {
            scannerSelect.innerHTML = '<option value="virtual_default">Scanner Padrão TWAIN/WIA (Genérico)</option>';
            scannerSelect.disabled = false;
            btnStartScan.disabled = false;
        }
    }

    // Iniciar Digitalização
    btnStartScan.addEventListener('click', async function() {
        const options = {
            scanner_id: scannerSelect.value,
            dpi: document.getElementById('webscanDpiSelect').value,
            color_mode: document.getElementById('webscanColorModeSelect').value,
            source: document.getElementById('webscanAdfSwitch').checked ? 'adf' : 'flatbed',
            duplex: document.getElementById('webscanDuplexSwitch').checked
        };

        btnStartScan.disabled = true;
        progressBarContainer.classList.remove('d-none');
        progressBar.style.width = '30%';
        progressStatusText.innerText = 'Comunicando com o scanner físico...';

        try {
            await bridge.startScan(options);
            progressBar.style.width = '100%';
            progressStatusText.innerText = 'Digitalização concluída com sucesso!';
            setTimeout(() => progressBarContainer.classList.add('d-none'), 1500);
        } catch (err) {
            if (window.Toast) {
                window.Toast.error('Falha no Scanner', 'Falha na digitalização: ' + err.message);
            }
            progressBarContainer.classList.add('d-none');
        } finally {
            btnStartScan.disabled = false;
            renderThumbnails();
        }
    });

    // Evento de captura de página
    bridge.onPageCapturedCallback = function() {
        renderThumbnails();
    };

    function renderThumbnails() {
        const pages = bridge.pages;
        pageBadge.innerText = `${pages.length} página(s)`;
        
        if (pages.length > 0) {
            emptyPlaceholder.classList.add('d-none');
            btnClearAll.classList.remove('d-none');
            btnConfirmAttach.disabled = false;
        } else {
            emptyPlaceholder.classList.remove('d-none');
            btnClearAll.classList.add('d-none');
            btnConfirmAttach.disabled = true;
        }

        // Renderizar miniaturas
        thumbnailsContainer.innerHTML = '';
        if (pages.length === 0) {
            thumbnailsContainer.appendChild(emptyPlaceholder);
            return;
        }

        pages.forEach((p, index) => {
            const card = document.createElement('div');
            card.className = 'card border shadow-sm rounded-3 overflow-hidden position-relative';
            card.style.width = '130px';
            
            card.innerHTML = `
                <div class="position-absolute top-0 start-0 bg-primary text-white small px-2 py-1 rounded-bottom-end fw-bold" style="font-size: 0.7rem;">
                    Pág ${index + 1}
                </div>
                <div class="p-2 text-center bg-white" style="height: 140px; display: flex; align-items: center; justify-content: center;">
                    <img src="${p.imageBase64}" style="max-width: 100%; max-height: 100%; transform: rotate(${p.rotation}deg); transition: transform 0.2s ease;">
                </div>
                <div class="card-footer p-1 bg-light d-flex justify-content-around">
                    <button type="button" class="btn btn-sm btn-link text-dark p-0 btn-rotate" data-id="${p.id}" title="Girar 90°">
                        <i class="fas fa-redo-alt"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-link text-danger p-0 btn-delete" data-id="${p.id}" title="Excluir">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;

            // Botão Rotação
            card.querySelector('.btn-rotate').addEventListener('click', () => {
                bridge.rotatePage(p.id, 90);
                renderThumbnails();
            });

            // Botão Excluir
            card.querySelector('.btn-delete').addEventListener('click', () => {
                bridge.deletePage(p.id);
                renderThumbnails();
            });

            thumbnailsContainer.appendChild(card);
        });
    }

    // Limpar tudo
    btnClearAll.addEventListener('click', function() {
        if (confirm('Deseja descartar todas as páginas digitalizadas?')) {
            bridge.clearPages();
            renderThumbnails();
        }
    });

    // Confirmar e Anexar ao Formulário
    btnConfirmAttach.addEventListener('click', async function() {
        btnConfirmAttach.disabled = true;
        btnConfirmAttach.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Anexando...';

        try {
            const pdfFile = await bridge.generateFile('documento_digitalizado_' + new Date().getTime() + '.pdf');
            const targetInput = document.getElementById(targetInputId);

            if (targetInput) {
                const dataTransfer = new DataTransfer();
                
                // Manter arquivos já existentes no input se houver
                if (targetInput.files && targetInput.files.length > 0) {
                    Array.from(targetInput.files).forEach(f => dataTransfer.items.add(f));
                }
                
                // Adicionar o PDF digitalizado
                dataTransfer.items.add(pdfFile);
                targetInput.files = dataTransfer.files;

                // Disparar evento de alteração para atualizar a lista do componente file-upload
                targetInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // Fechar modal
            const modalEl = document.getElementById('{{ $modalId }}');
            const bsModal = bootstrap.Modal.getInstance(modalEl);
            if (bsModal) bsModal.hide();

            if (window.Toast) {
                window.Toast.success('Digitalização Concluída', 'Documento digitalizado anexado ao formulário com sucesso!');
            }
        } catch (err) {
            if (window.Toast) {
                window.Toast.error('Erro na Digitalização', 'Falha ao compilar documento: ' + err.message);
            }
        } finally {
            btnConfirmAttach.disabled = false;
            btnConfirmAttach.innerHTML = '<i class="fas fa-check-circle me-2"></i> Confirmar e Anexar ao Documento';
        }
    });

    // Reconectar Agente
    document.getElementById('btnRetryWebscanAgent').addEventListener('click', checkAgentHealth);

    // Checagem ao carregar a página
    checkAgentHealth();
});
</script>
