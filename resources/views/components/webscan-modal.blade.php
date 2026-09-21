@props(['targetInputId' => 'fileInput', 'modalId' => 'webscanModal'])

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white py-3 rounded-top-4">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2" id="{{ $modalId }}Label"><i class="fas fa-print"></i> Digitalizar documento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-4">
                <div id="webscanAgentStatus" class="alert alert-light border d-flex align-items-center justify-content-between gap-3 mb-3" role="status">
                    <div><i id="webscanStatusIcon" class="fas fa-circle-notch fa-spin text-warning me-2"></i><strong id="webscanStatusText">A verificar o agente local…</strong><div id="webscanStatusHelp" class="small text-muted mt-1">O scanner é usado através do WebScan Bridge instalado neste computador.</div></div>
                    <button type="button" id="btnRetryWebscanAgent" class="btn btn-sm btn-outline-secondary">Tentar novamente</button>
                </div>
                <div id="webscanPairingPanel" class="alert alert-warning d-none">
                    <label for="webscanPairingToken" class="form-label fw-semibold">Código de pareamento do scanner</label>
                    <div class="input-group"><input id="webscanPairingToken" class="form-control" type="password" autocomplete="off" placeholder="Introduza o código configurado no agente"><button id="btnSaveWebscanPairing" class="btn btn-warning" type="button">Conectar</button></div>
                    <div class="form-text">O código fica apenas nesta sessão do navegador.</div>
                </div>
                <div class="card border-0 bg-light rounded-3 p-3 mb-3">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6"><label class="form-label small fw-bold">Scanner disponível</label><select id="webscanScannerSelect" class="form-select" disabled><option value="">Nenhum scanner detetado</option></select></div>
                        <div class="col-md-3"><label class="form-label small fw-bold">Resolução</label><select id="webscanDpiSelect" class="form-select"><option value="200">200 DPI</option></select></div>
                        <div class="col-md-3"><label class="form-label small fw-bold">Cor</label><select id="webscanColorModeSelect" class="form-select"><option value="color">Colorido</option></select></div>
                        <div class="col-md-6 d-flex gap-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="webscanAdfSwitch"><label class="form-check-label" for="webscanAdfSwitch">Alimentador (ADF)</label></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="webscanDuplexSwitch"><label class="form-check-label" for="webscanDuplexSwitch">Frente e verso</label></div></div>
                        <div class="col-md-6 text-md-end"><button type="button" id="btnStartScanAction" class="btn btn-primary px-4 fw-bold" disabled><i class="fas fa-play me-2"></i>Iniciar digitalização</button></div>
                    </div>
                </div>
                <div id="webscanProgressPanel" class="d-none mb-3"><div class="d-flex justify-content-between small mb-1"><span id="webscanProgressText">A preparar…</span><span id="webscanProgressValue">0%</span></div><div class="progress" style="height:8px"><div id="webscanProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%"></div></div></div>
                <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0 fw-bold">Pré-visualização</h6><span id="webscanPageBadge" class="badge bg-secondary">0 páginas</span></div>
                <div id="webscanThumbnailsContainer" class="border rounded-3 p-3 bg-light d-flex flex-wrap gap-2" style="min-height:120px"><p id="webscanEmptyPlaceholder" class="text-muted small w-100 text-center my-auto">Nenhuma página digitalizada.</p></div>
                <p id="webscanSizeMessage" class="small text-muted mt-2 mb-0"></p>
            </div>
            <div class="modal-footer border-0"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button type="button" id="btnConfirmAttachScan" class="btn btn-success px-4 fw-bold" disabled><i class="fas fa-paperclip me-2"></i>Anexar PDF</button></div>
        </div>
    </div>
</div>

<script src="{{ asset('js/webscan-bridge.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const maxBytes = {{ \App\Http\Requests\StoreDocumentoEntradaRequest::LIMITE_FICHEIRO_KB * 1024 }};
    const bridge = new WebScanBridge({ baseUrl: '{{ config('webscan.agent_url', 'http://127.0.0.1:18090') }}' });
    const el = id => document.getElementById(id);
    const scanner = el('webscanScannerSelect'), start = el('btnStartScanAction'), attach = el('btnConfirmAttachScan');
    const badge = el('mainScannerBadgeIndicator_{{ $targetInputId }}'), opener = el('btnOpenScannerModal_{{ $targetInputId }}');
    const setStatus = (kind, text, help = '') => { el('webscanStatusIcon').className = `fas ${kind === 'ok' ? 'fa-circle-check text-success' : kind === 'error' ? 'fa-circle-xmark text-danger' : 'fa-circle-notch fa-spin text-warning'} me-2`; el('webscanStatusText').textContent = text; el('webscanStatusHelp').textContent = help; };
    const setMain = online => { if (badge) badge.innerHTML = online ? '<i class="fas fa-circle text-success me-1 small"></i> Scanner ativo' : '<i class="fas fa-circle text-secondary me-1 small"></i> Scanner offline'; if (opener) opener.disabled = !online; };
    const selected = () => bridge.scanners.find(item => item.id === scanner.value);
    const populateCapabilities = () => { const item = selected(), caps = item?.capabilities || {}; const fill = (target, values, label) => { target.innerHTML = ''; (values || []).forEach(value => target.add(new Option(label(value), value))); }; fill(el('webscanDpiSelect'), caps.dpis || [], value => `${value} DPI`); fill(el('webscanColorModeSelect'), caps.color_modes || [], value => ({bw:'Preto e branco', gray:'Tons de cinza', color:'Colorido'}[value] || value)); const sources = caps.sources || []; el('webscanAdfSwitch').checked = sources.includes('adf'); el('webscanAdfSwitch').disabled = !sources.includes('adf'); el('webscanDuplexSwitch').checked = false; el('webscanDuplexSwitch').disabled = !caps.duplex_supported; };
    const render = () => { const pages = bridge.pages; el('webscanPageBadge').textContent = `${pages.length} página(s)`; el('webscanThumbnailsContainer').innerHTML = pages.length ? pages.map(page => `<img class="border rounded" style="width:96px;height:96px;object-fit:contain;background:#fff" alt="Página ${page.number}" src="${page.previewUrl}">`).join('') : '<p class="text-muted small w-100 text-center my-auto">Nenhuma página digitalizada.</p>'; const valid = bridge.lastPdf && bridge.lastPdf.size <= maxBytes; attach.disabled = !valid; el('webscanSizeMessage').textContent = bridge.lastPdf ? `PDF: ${(bridge.lastPdf.size / 1024 / 1024).toFixed(2)} MB de 10 MB permitidos.${valid ? '' : ' O ficheiro excede o limite.'}` : ''; };
    const load = async () => { start.disabled = true; scanner.disabled = true; const health = await bridge.checkStatus(); if (health.status !== 'online') { setMain(false); setStatus('error', 'Agente local indisponível', health.message || 'Inicie o WebScan Bridge neste computador.'); el('webscanPairingPanel').classList.toggle('d-none', health.error_code !== 'PAIRING_REQUIRED'); return; } try { const scanners = await bridge.getScanners(); setMain(true); if (!scanners.length) { setStatus('error', 'Nenhum scanner detetado', 'Verifique a ligação e o driver do equipamento.'); return; } scanner.innerHTML = scanners.map(item => `<option value="${item.id}">${item.name} (${item.driver})</option>`).join(''); scanner.disabled = false; start.disabled = false; populateCapabilities(); setStatus('ok', 'Scanner pronto', `${scanners.length} equipamento(s) disponível(is).`); } catch (error) { setMain(false); setStatus('error', 'Não foi possível consultar scanners', error.message); } };
    scanner.addEventListener('change', populateCapabilities);
    bridge.onProgressCallback = progress => { el('webscanProgressPanel').classList.remove('d-none'); const value = Math.max(0, Math.min(100, progress.percent || 0)); el('webscanProgressBar').style.width = `${value}%`; el('webscanProgressValue').textContent = `${value}%`; el('webscanProgressText').textContent = progress.message || 'A digitalizar…'; };
    start.addEventListener('click', async () => { start.disabled = true; bridge.clear(); render(); try { await bridge.startScan({ scanner_id: scanner.value, dpi: Number(el('webscanDpiSelect').value), color_mode: el('webscanColorModeSelect').value, source: el('webscanAdfSwitch').checked ? 'adf' : 'flatbed', duplex: el('webscanDuplexSwitch').checked, auto_deskew: true, auto_crop: true }); render(); } catch (error) { setStatus('error', 'Falha na digitalização', error.message); if (window.Toast) window.Toast.error('Scanner', error.message); } finally { start.disabled = !scanner.value; setTimeout(() => el('webscanProgressPanel').classList.add('d-none'), 1200); } });
    attach.addEventListener('click', () => { const input = el('{{ $targetInputId }}'); if (!bridge.lastPdf || bridge.lastPdf.size > maxBytes || !input) return; const files = new DataTransfer(); Array.from(input.files || []).forEach(file => files.items.add(file)); files.items.add(bridge.lastPdf); input.files = files.files; input.dispatchEvent(new Event('change', { bubbles: true })); bootstrap.Modal.getInstance(el('{{ $modalId }}'))?.hide(); });
    el('btnRetryWebscanAgent').addEventListener('click', load); el('btnSaveWebscanPairing').addEventListener('click', () => { bridge.setPairingToken(el('webscanPairingToken').value); load(); });

    // O botão "Tentar novamente" está dentro do modal, e o modal só abre pelo
    // botão que fica desativado enquanto o agente estiver offline. Quem arranque
    // o agente depois da página carregada ficaria preso sem recarregar (F5), por
    // isso reavaliamos sozinhos: ao voltar o foco à janela e, em pano de fundo,
    // enquanto continuar offline. Assim que fica online, a sondagem pára.
    let recheck = null;
    const scheduleRecheck = () => { if (!recheck) recheck = setInterval(refresh, 20000); };
    const stopRecheck = () => { if (recheck) { clearInterval(recheck); recheck = null; } };
    async function refresh() { await load(); if (bridge.isOnline) stopRecheck(); else scheduleRecheck(); }
    window.addEventListener('focus', () => { if (!bridge.isOnline) refresh(); });
    refresh();
});
</script>
