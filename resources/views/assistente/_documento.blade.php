{{--
    Painel de chat do Assistente IA para uma página de documento.
    Variáveis esperadas:
      $docId     — ID do documento
      $docTipo   — 'ENTRADA' ou 'INTERNO'
      $ctxRoute  — URL POST do endpoint (route assistente.entrada/interno)
      $ctxTitulo — rótulo amigável do documento (ex.: "este documento de entrada")
--}}
<div class="card shadow-sm border-0 rounded-4 overflow-hidden mt-4" id="assistantDocCard" style="border: 1px solid #e2e8f0;">
    <div class="card-header bg-white d-flex align-items-center justify-content-between py-3 px-4"
        role="button" data-bs-toggle="collapse" data-bs-target="#assistantDocBody" aria-expanded="false">
        <div class="d-flex align-items-center gap-2.5">
            <span class="rounded-circle d-flex align-items-center justify-content-center shadow-xs"
                style="width:36px;height:36px;background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#2563eb;border:1px solid #bfdbfe;">
                <i class="fas fa-brain"></i>
            </span>
            <div>
                <h5 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2" style="font-size:1rem;">
                    Assistente IA · {{ $ctxTitulo ?? 'este documento' }}
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill small fw-semibold" style="font-size:0.68rem;">
                        <i class="fas fa-bolt me-1"></i>DeepSeek
                    </span>
                </h5>
                <span class="small text-muted" style="font-size:0.75rem;">Consulte o conteúdo, despachos, notas ou gere o resumo executivo instantâneo.</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            @if(isset($docId))
                <button type="button" class="btn btn-sm btn-primary py-1 px-2.5 small fw-bold shadow-xs d-flex align-items-center gap-1"
                    onclick="event.stopPropagation(); window.gerarResumoIaDocWidget({{ $docId }}, '{{ $docTipo ?? 'ENTRADA' }}')">
                    <i class="fas fa-sparkles"></i> ✨ Resumir com IA
                </button>
            @endif
            <i class="fas fa-chevron-down text-muted ms-1"></i>
        </div>
    </div>

    <div class="collapse" id="assistantDocBody">
        <div class="card-body p-3 p-md-4 bg-light bg-opacity-50">
            {{-- Painel de Resumo Executivo Instantâneo --}}
            <div id="docIaResumoPanel" class="card border-primary border-opacity-25 shadow-sm rounded-3 mb-3 d-none" style="background:#ffffff;">
                <div class="card-header bg-primary bg-opacity-10 border-0 d-flex justify-content-between align-items-center py-2 px-3">
                    <span class="fw-bold text-primary small text-uppercase mb-0 d-flex align-items-center gap-1.5">
                        <i class="fas fa-file-contract text-primary"></i> Resumo Executivo em 3 Blocos (DeepSeek)
                    </span>
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 small" onclick="copiarResumoDocWidget(this)">
                            <i class="fas fa-copy me-1"></i>Copiar
                        </button>
                        <button type="button" class="btn-close btn-close-sm ms-1" onclick="document.getElementById('docIaResumoPanel').classList.add('d-none')" aria-label="Fechar"></button>
                    </div>
                </div>
                <div class="card-body p-3" id="docIaResumoContent"></div>
            </div>

            <div id="assistantDocWindow"
                style="max-height: 45vh; overflow-y:auto; background:#ffffff; border:1px solid #e2e8f0; border-radius:.85rem; padding:1.15rem; scroll-behavior: smooth;">
                <div id="assistantDocEmpty" class="text-center text-muted py-4 small">
                    <div class="p-2.5 bg-light rounded-circle d-inline-block mb-2 text-primary border">
                        <i class="fas fa-comments fa-2x"></i>
                    </div>
                    <div class="fw-semibold text-dark mb-1">Faça uma pergunta contextual sobre este documento</div>
                    <p class="text-muted small mb-3" style="max-width: 420px; margin: 0 auto;">
                        Pergunte sobre prazos, solicitante, despacho exarado, valores, fundamentação ou tarefas atribuídas.
                    </p>
                    <div class="d-flex flex-wrap justify-content-center gap-1.5">
                        <span class="badge bg-light text-secondary border suggestion-chip-doc" style="cursor:pointer;" onclick="preencherDocPrompt('Qual é o assunto principal e quem é o remetente deste documento?')">
                            📄 Assunto e Procedência
                        </span>
                        <span class="badge bg-light text-secondary border suggestion-chip-doc" style="cursor:pointer;" onclick="preencherDocPrompt('Quais foram os despachos e encaminhamentos efetuados?')">
                            ✍️ Despachos e Trâmites
                        </span>
                        <span class="badge bg-light text-secondary border suggestion-chip-doc" style="cursor:pointer;" onclick="preencherDocPrompt('Existe alguma ação urgente ou prazo a cumprir?')">
                            ⏳ Ações Pendentes e Prazos
                        </span>
                    </div>
                </div>
            </div>

            <form id="assistantDocForm" class="mt-3" autocomplete="off">
                <div class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden border">
                    <input type="text" id="assistantDocInput" class="form-control border-0 px-3 fs-6"
                        placeholder="Escreva a sua dúvida sobre este documento..." maxlength="2000">
                    <button class="btn btn-primary px-4 fw-bold" type="submit" id="assistantDocSend">
                        <i class="fas fa-paper-plane me-1"></i> Perguntar
                    </button>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 px-2">
                    <div class="form-text small text-muted mb-0">
                        <i class="fas fa-shield-halved me-1 text-success"></i>Respostas isoladas e baseadas exclusivamente no teor deste processo.
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            const win = document.getElementById('assistantDocWindow');
            const empty = document.getElementById('assistantDocEmpty');
            const form = document.getElementById('assistantDocForm');
            const input = document.getElementById('assistantDocInput');
            const sendBtn = document.getElementById('assistantDocSend');
            const endpoint = @json($ctxRoute);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            let historico = [];
            let busy = false;

            window.preencherDocPrompt = function(texto) {
                if (input) {
                    input.value = texto;
                    input.focus();
                }
            };

            function parseDocMarkdown(md) {
                if (!md) return '';
                let text = md;
                text = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
                text = text.replace(/^### (.*$)/gim, '<h6 class="fw-bold text-dark mt-2 mb-1" style="font-size:0.9rem;">$1</h6>');
                text = text.replace(/^## (.*$)/gim, '<h6 class="fw-bold text-dark mt-2 mb-1" style="font-size:0.95rem;">$1</h6>');
                text = text.replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>');
                text = text.replace(/^\> (.*$)/gim, '<div class="p-2 my-1 bg-white border-start border-3 border-primary rounded-end small text-muted fst-italic">$1</div>');
                text = text.replace(/^\s*[-*]\s+(.*$)/gim, '<li class="small text-secondary mb-1">$1</li>');
                text = text.replace(/(<li.*<\/li>)/gims, '<ul class="ps-3 mb-2">$1</ul>');
                text = text.replace(/<\/ul>\s*<ul>/gim, '');
                text = text.split(/\n\n+/).map(p => {
                    p = p.trim();
                    if (!p) return '';
                    if (p.startsWith('<h') || p.startsWith('<ul') || p.startsWith('<div')) return p;
                    return '<p class="mb-1.5 small text-dark">' + p.replace(/\n/g, '<br>') + '</p>';
                }).join('');
                return text;
            }

            function add(role, text) {
                if (empty && empty.parentNode) empty.remove();
                const wrap = document.createElement('div');
                wrap.className = 'mb-2.5 d-flex ' + (role === 'user' ? 'justify-content-end' : 'justify-content-start');
                
                const b = document.createElement('div');
                b.style.maxWidth = '85%';
                b.style.lineHeight = '1.55';
                b.className = 'px-3 py-2 rounded-3 shadow-xs ' + (role === 'user'
                    ? 'bg-primary text-white'
                    : 'bg-white border text-dark');
                
                if (role === 'user') {
                    b.textContent = text;
                } else {
                    b.innerHTML = parseDocMarkdown(text);
                }

                wrap.appendChild(b);
                win.appendChild(wrap);
                win.scrollTop = win.scrollHeight;
            }

            let typingEl = null;
            function typing(on) {
                if (on) {
                    typingEl = document.createElement('div');
                    typingEl.className = 'mb-2 d-flex justify-content-start';
                    typingEl.innerHTML = '<div class="px-3 py-2 rounded-3 bg-white border text-muted small d-flex align-items-center gap-2">' +
                        '<span class="spinner-border spinner-border-sm text-primary"></span> A consultar DeepSeek...</div>';
                    win.appendChild(typingEl);
                    win.scrollTop = win.scrollHeight;
                } else if (typingEl) { 
                    typingEl.remove(); 
                    typingEl = null; 
                }
            }

            form?.addEventListener('submit', async function (e) {
                e.preventDefault();
                const pergunta = input.value.trim();
                if (!pergunta || busy) return;
                busy = true; 
                sendBtn.disabled = true; 
                input.value = '';
                add('user', pergunta); 
                typing(true);

                try {
                    const resp = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ pergunta, historico }),
                    });
                    typing(false);
                    const data = await resp.json().catch(() => ({}));
                    if (!resp.ok) {
                        add('bot', data.erro || 'Ocorreu um erro ao processar a pergunta.');
                    } else {
                        add('bot', data.resposta || 'Sem resposta.');
                        historico.push({ role: 'user', content: pergunta });
                        historico.push({ role: 'assistant', content: data.resposta || '' });
                        if (historico.length > 12) historico = historico.slice(-12);
                    }
                } catch (err) {
                    typing(false);
                    add('bot', 'Não foi possível contactar o assistente. Tente novamente.');
                } finally {
                    busy = false; 
                    sendBtn.disabled = false; 
                    input.focus();
                }
            });

            // Resumo Executivo em 3 Blocos
            window.gerarResumoIaDocWidget = function(docId, tipo) {
                const collapseEl = document.getElementById('assistantDocBody');
                if (collapseEl && !collapseEl.classList.contains('show')) {
                    const bsCollapse = new bootstrap.Collapse(collapseEl, { toggle: true });
                }

                const panel = document.getElementById('docIaResumoPanel');
                const content = document.getElementById('docIaResumoContent');
                if (!panel || !content) return;

                panel.classList.remove('d-none');
                content.innerHTML = `
                    <div class="text-center py-3 text-muted">
                        <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                        <div class="small fw-semibold">Processando metadados, despachos, pareceres e anexos OCR com DeepSeek...</div>
                    </div>
                `;

                fetch('{{ route("api.ia.resumir") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ documento_id: docId, tipo: tipo || 'ENTRADA' })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.resumo) {
                        content.innerHTML = `
                            <div class="small text-dark" style="line-height:1.6;" id="textoResumoExecutivo">
                                ${parseDocMarkdown(data.resumo)}
                            </div>
                        `;
                    } else {
                        content.innerHTML = `<div class="alert alert-warning p-2 small mb-0">${data.erro || 'Não foi possível gerar o resumo.'}</div>`;
                    }
                })
                .catch(err => {
                    content.innerHTML = `<div class="alert alert-danger p-2 small mb-0">Erro ao comunicar com a IA: ${err.message}</div>`;
                });
            };

            window.copiarResumoDocWidget = function(btn) {
                const el = document.getElementById('textoResumoExecutivo');
                if (!el) return;
                navigator.clipboard.writeText(el.innerText).then(() => {
                    const original = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-check text-success me-1"></i>Copiado!';
                    setTimeout(() => btn.innerHTML = original, 2000);
                });
            };
        })();
    </script>
@endpush
