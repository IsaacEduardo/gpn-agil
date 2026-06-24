{{--
    Painel de chat do Assistente IA para uma página de documento.
    Variáveis esperadas:
      $ctxRoute  — URL POST do endpoint (route assistente.entrada/interno)
      $ctxTitulo — rótulo amigável do documento (ex.: "este documento de entrada")
--}}
<div class="card shadow-sm border-0 rounded-4 overflow-hidden mt-4" id="assistantDocCard">
    <div class="card-header bg-white d-flex align-items-center justify-content-between py-3"
        role="button" data-bs-toggle="collapse" data-bs-target="#assistantDocBody" aria-expanded="false">
        <h5 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
            <span class="rounded-circle d-flex align-items-center justify-content-center"
                style="width:34px;height:34px;background:linear-gradient(135deg,#eff6ff,#e0e7ff);color:#2563eb;">
                <i class="fas fa-robot"></i>
            </span>
            Perguntar à IA sobre {{ $ctxTitulo ?? 'este documento' }}
        </h5>
        <i class="fas fa-chevron-down text-muted"></i>
    </div>
    <div class="collapse" id="assistantDocBody">
        <div class="card-body p-3 p-md-4">
            <div id="assistantDocWindow"
                style="max-height: 40vh; overflow-y:auto; background:#f8fafc; border-radius:.85rem; padding:1rem;">
                <div id="assistantDocEmpty" class="text-center text-muted py-3 small">
                    <i class="fas fa-comments fa-2x opacity-25 d-block mb-2"></i>
                    Faça uma pergunta sobre o conteúdo deste documento (resumir, extrair dados, esclarecer).
                </div>
            </div>
            <form id="assistantDocForm" class="mt-3" autocomplete="off">
                <div class="input-group">
                    <input type="text" id="assistantDocInput" class="form-control"
                        placeholder="Ex.: Qual é o assunto e o prazo deste documento?" maxlength="2000">
                    <button class="btn btn-primary px-3" type="submit" id="assistantDocSend">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <div class="form-text small mt-2">
                    <i class="fas fa-shield-halved me-1"></i>As respostas baseiam-se apenas no conteúdo deste documento.
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

            function add(role, text) {
                if (empty) empty.remove();
                const wrap = document.createElement('div');
                wrap.className = 'mb-2 d-flex ' + (role === 'user' ? 'justify-content-end' : 'justify-content-start');
                const b = document.createElement('div');
                b.style.maxWidth = '85%';
                b.style.whiteSpace = 'pre-wrap';
                b.style.lineHeight = '1.5';
                b.className = 'px-3 py-2 rounded-3 ' + (role === 'user'
                    ? 'bg-primary text-white'
                    : 'bg-white border text-dark');
                b.textContent = text;
                wrap.appendChild(b);
                win.appendChild(wrap);
                win.scrollTop = win.scrollHeight;
            }

            let typingEl = null;
            function typing(on) {
                if (on) {
                    typingEl = document.createElement('div');
                    typingEl.className = 'mb-2 d-flex justify-content-start';
                    typingEl.innerHTML = '<div class="px-3 py-2 rounded-3 bg-white border text-muted small">' +
                        '<span class="spinner-grow spinner-grow-sm"></span> A pensar...</div>';
                    win.appendChild(typingEl);
                    win.scrollTop = win.scrollHeight;
                } else if (typingEl) { typingEl.remove(); typingEl = null; }
            }

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const pergunta = input.value.trim();
                if (!pergunta || busy) return;
                busy = true; sendBtn.disabled = true; input.value = '';
                add('user', pergunta); typing(true);
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
                    busy = false; sendBtn.disabled = false; input.focus();
                }
            });
        })();
    </script>
@endpush
