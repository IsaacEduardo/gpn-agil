@extends('layouts.app')

@section('title', 'Assistente IA')

@section('breadcrumbs')
    <div class="container py-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}"
                        class="text-decoration-none text-muted">Início</a></li>
                <li class="breadcrumb-item active text-primary fw-bold" aria-current="page">Assistente IA</li>
            </ol>
        </nav>
    </div>
@endsection

@section('styles')
    <style>
        .chat-shell { max-width: 920px; }
        .chat-window {
            height: 56vh;
            min-height: 340px;
            overflow-y: auto;
            background: #f8fafc;
            border-radius: 1rem;
            padding: 1.25rem;
        }
        .chat-msg { display: flex; gap: .65rem; margin-bottom: 1rem; }
        .chat-msg .bubble {
            padding: .75rem 1rem;
            border-radius: 1rem;
            max-width: 80%;
            white-space: pre-wrap;
            line-height: 1.55;
            font-size: .95rem;
        }
        .chat-msg.user { flex-direction: row-reverse; }
        .chat-msg.user .bubble { background: var(--primary-accent, #2563eb); color: #fff; border-bottom-right-radius: .25rem; }
        .chat-msg.bot .bubble { background: #fff; border: 1px solid #e2e8f0; color: #334155; border-bottom-left-radius: .25rem; }
        .chat-avatar {
            width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: .85rem;
        }
        .chat-avatar.bot { background: #eff6ff; color: #2563eb; }
        .chat-avatar.user { background: #e2e8f0; color: #475569; }
        .chat-sources { margin-top: .6rem; padding-top: .5rem; border-top: 1px dashed #e2e8f0; }
        .chat-source-link {
            display: inline-flex; align-items: center; gap: .35rem;
            font-size: .8rem; text-decoration: none; margin: .15rem .35rem .15rem 0;
            background: #f1f5f9; color: #334155; padding: .2rem .6rem; border-radius: 999px; border: 1px solid #e2e8f0;
        }
        .chat-source-link:hover { background: #e0e7ff; color: #1e40af; }
        .suggestion-chip { cursor: pointer; }
    </style>
@endsection

@section('content')
    <div class="container chat-shell pb-5">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center"
                style="width:48px;height:48px;background:linear-gradient(135deg,#eff6ff,#e0e7ff);color:#2563eb;">
                <i class="fas fa-robot fa-lg"></i>
            </div>
            <div>
                <h2 class="fw-bold text-dark mb-0">Assistente IA</h2>
                <p class="text-muted mb-0 small">Pesquise e pergunte sobre os documentos a que tem acesso. As respostas
                    respeitam as suas permissões de departamento/gabinete.</p>
            </div>
        </div>

        @unless ($configurado)
            <div class="alert alert-warning border-0 shadow-sm rounded-3 small" role="alert">
                <i class="fas fa-triangle-exclamation me-2"></i>O assistente ainda não está totalmente configurado
                (chave de IA ausente). As perguntas podem não ser respondidas até a configuração ser concluída.
            </div>
        @endunless

        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
            <div class="card-body p-3 p-md-4">
                <div id="chatWindow" class="chat-window">
                    <div id="chatEmpty" class="h-100 d-flex flex-column align-items-center justify-content-center text-center text-muted">
                        <i class="fas fa-comments fa-3x opacity-25 mb-3"></i>
                        <p class="mb-3">Como posso ajudar com os seus documentos?</p>
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <span class="badge bg-light text-secondary border suggestion-chip">Resumir os documentos recentes</span>
                            <span class="badge bg-light text-secondary border suggestion-chip">Quais documentos mencionam contratos?</span>
                            <span class="badge bg-light text-secondary border suggestion-chip">Há algo pendente de despacho?</span>
                        </div>
                    </div>
                </div>

                <form id="chatForm" class="mt-3" autocomplete="off">
                    <div class="input-group input-group-lg">
                        <input type="text" id="chatInput" class="form-control rounded-start-pill"
                            placeholder="Escreva a sua pergunta..." maxlength="2000" aria-label="Pergunta">
                        <button class="btn btn-primary rounded-end-pill px-4" type="submit" id="chatSend">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <div class="form-text small mt-2">
                        <i class="fas fa-shield-halved me-1"></i>O assistente só acede a documentos que você já pode ver e
                        responde com base no conteúdo deles.
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            const win = document.getElementById('chatWindow');
            const empty = document.getElementById('chatEmpty');
            const form = document.getElementById('chatForm');
            const input = document.getElementById('chatInput');
            const sendBtn = document.getElementById('chatSend');
            const endpoint = @json(route('assistente.perguntar'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            let historico = [];
            let busy = false;

            document.querySelectorAll('.suggestion-chip').forEach(chip => {
                chip.addEventListener('click', () => { input.value = chip.textContent.trim(); input.focus(); });
            });

            function scrollDown() { win.scrollTop = win.scrollHeight; }

            function addMessage(role, text, sources) {
                if (empty) empty.remove();
                const wrap = document.createElement('div');
                wrap.className = 'chat-msg ' + (role === 'user' ? 'user' : 'bot');

                const avatar = document.createElement('div');
                avatar.className = 'chat-avatar ' + (role === 'user' ? 'user' : 'bot');
                avatar.innerHTML = role === 'user' ? '<i class="fas fa-user"></i>' : '<i class="fas fa-robot"></i>';

                const bubble = document.createElement('div');
                bubble.className = 'bubble';
                bubble.textContent = text; // textContent evita injeção de HTML

                if (sources && sources.length) {
                    const box = document.createElement('div');
                    box.className = 'chat-sources';
                    const lbl = document.createElement('div');
                    lbl.className = 'small text-muted fw-semibold mb-1';
                    lbl.textContent = 'Fontes:';
                    box.appendChild(lbl);
                    sources.forEach(s => {
                        const a = document.createElement('a');
                        a.className = 'chat-source-link';
                        a.href = s.url;
                        a.target = '_blank';
                        a.rel = 'noopener';
                        const ic = document.createElement('i');
                        ic.className = 'fas ' + (s.tipo === 'interno' ? 'fa-file-signature' : 'fa-inbox');
                        a.appendChild(ic);
                        a.appendChild(document.createTextNode(' ' + (s.ref ? (s.ref + ' · ') : '') + s.titulo));
                        box.appendChild(a);
                    });
                    bubble.appendChild(box);
                }

                wrap.appendChild(avatar);
                wrap.appendChild(bubble);
                win.appendChild(wrap);
                scrollDown();
                return bubble;
            }

            function addTyping() {
                const wrap = document.createElement('div');
                wrap.className = 'chat-msg bot';
                wrap.id = 'typingIndicator';
                wrap.innerHTML = '<div class="chat-avatar bot"><i class="fas fa-robot"></i></div>' +
                    '<div class="bubble"><span class="spinner-grow spinner-grow-sm text-secondary"></span> A pensar...</div>';
                win.appendChild(wrap);
                scrollDown();
            }
            function removeTyping() { document.getElementById('typingIndicator')?.remove(); }

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const pergunta = input.value.trim();
                if (!pergunta || busy) return;

                busy = true;
                sendBtn.disabled = true;
                input.value = '';
                addMessage('user', pergunta);
                addTyping();

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
                    removeTyping();
                    const data = await resp.json().catch(() => ({}));

                    if (!resp.ok) {
                        addMessage('bot', data.erro || 'Ocorreu um erro ao processar a sua pergunta.');
                    } else {
                        addMessage('bot', data.resposta || 'Sem resposta.', data.fontes);
                        historico.push({ role: 'user', content: pergunta });
                        historico.push({ role: 'assistant', content: data.resposta || '' });
                        if (historico.length > 16) historico = historico.slice(-16);
                    }
                } catch (err) {
                    removeTyping();
                    addMessage('bot', 'Não foi possível contactar o assistente. Verifique a ligação e tente novamente.');
                } finally {
                    busy = false;
                    sendBtn.disabled = false;
                    input.focus();
                }
            });
        })();
    </script>
@endsection
