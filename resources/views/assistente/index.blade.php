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
        .chat-shell { max-width: 960px; }
        .chat-window {
            height: 60vh;
            min-height: 380px;
            overflow-y: auto;
            background: #f8fafc;
            border-radius: 1rem;
            padding: 1.25rem;
            scroll-behavior: smooth;
        }
        .chat-msg { display: flex; gap: .75rem; margin-bottom: 1.25rem; }
        .chat-msg .bubble {
            padding: .85rem 1.15rem;
            border-radius: 1rem;
            max-width: 85%;
            line-height: 1.6;
            font-size: .95rem;
        }
        .chat-msg.user { flex-direction: row-reverse; }
        .chat-msg.user .bubble {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            border-bottom-right-radius: .25rem;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }
        .chat-msg.bot .bubble {
            background: #fff;
            border: 1px solid #e2e8f0;
            color: #1e293b;
            border-bottom-left-radius: .25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .chat-avatar {
            width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: .9rem;
        }
        .chat-avatar.bot { background: linear-gradient(135deg, #eff6ff, #dbeafe); color: #2563eb; border: 1px solid #bfdbfe; }
        .chat-avatar.user { background: #e2e8f0; color: #475569; }
        
        /* Markdown styling inside bubble */
        .bubble h1, .bubble h2, .bubble h3, .bubble h4 { font-size: 1.05rem; font-weight: 700; margin-top: .75rem; margin-bottom: .4rem; color: #0f172a; }
        .bubble ul, .bubble ol { padding-left: 1.2rem; margin-bottom: .6rem; }
        .bubble li { margin-bottom: .25rem; }
        .bubble blockquote { border-left: 3px solid #cbd5e1; padding-left: .75rem; margin: .5rem 0; color: #475569; font-style: italic; background: #f8fafc; padding-top: .25rem; padding-bottom: .25rem; border-radius: 0 .35rem .35rem 0; }
        .bubble p { margin-bottom: .6rem; }
        .bubble p:last-child { margin-bottom: 0; }
        .bubble strong { color: #0f172a; font-weight: 600; }
        
        /* Document Cards Grid */
        .chat-sources-container {
            margin-top: .9rem;
            padding-top: .75rem;
            border-top: 1px dashed #cbd5e1;
        }
        .doc-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: .6rem;
            margin-top: .5rem;
        }
        .doc-citation-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: .65rem;
            padding: .65rem .85rem;
            transition: all .2s ease;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .doc-citation-card:hover {
            background: #ffffff;
            border-color: #3b82f6;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.12);
            transform: translateY(-1px);
        }
        .doc-citation-badge {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding: .2rem .5rem;
            border-radius: 999px;
            display: inline-block;
        }
        .doc-citation-badge.entrada { background: #dbeafe; color: #1e40af; }
        .doc-citation-badge.interno { background: #ede9fe; color: #6b21a8; }
        
        .suggestion-chip {
            cursor: pointer;
            transition: all .15s ease;
            font-weight: 500;
            font-size: .82rem;
            padding: .4rem .75rem;
        }
        .suggestion-chip:hover {
            background: #e0e7ff !important;
            color: #1e40af !important;
            border-color: #818cf8 !important;
            transform: translateY(-1px);
        }
    </style>
@endsection

@section('content')
    <div class="container chat-shell pb-5">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm"
                    style="width:48px;height:48px;background:linear-gradient(135deg,#eff6ff,#dbeafe);color:#2563eb;border:1px solid #bfdbfe;">
                    <i class="fas fa-brain fa-lg"></i>
                </div>
                <div>
                    <h2 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        Assistente IA
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill small fw-semibold" style="font-size:0.7rem;">
                            <i class="fas fa-bolt me-1"></i>DeepSeek RAG
                        </span>
                    </h2>
                    <p class="text-muted mb-0 small">Pesquisa semântica, consulta de despachos e sumarização com isolamento por perfil institucional.</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" onclick="window.location.reload();">
                    <i class="fas fa-rotate-right me-1"></i> Nova Conversa
                </button>
            </div>
        </div>

        @unless ($configurado)
            <div class="alert alert-warning border-0 shadow-sm rounded-3 small" role="alert">
                <i class="fas fa-triangle-exclamation me-2"></i>O assistente ainda não está configurado. Verifique as credenciais no arquivo .env.
            </div>
        @endunless

        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
            <div class="card-body p-3 p-md-4">
                <div id="chatWindow" class="chat-window">
                    <div id="chatEmpty" class="h-100 d-flex flex-column align-items-center justify-content-center text-center text-muted py-4">
                        <div class="p-3 bg-white rounded-circle shadow-sm mb-3 text-primary border border-light">
                            <i class="fas fa-robot fa-3x"></i>
                        </div>
                        <h5 class="fw-bold text-dark mb-1">Como posso auxiliar na gestão documental hoje?</h5>
                        <p class="small text-muted mb-3" style="max-width: 480px;">Consulte assuntos, números de ofício, despachos de autoridade, pareceres jurídicos ou conteúdo extraído por OCR.</p>
                        
                        <div class="d-flex flex-wrap justify-content-center gap-2" style="max-width: 650px;">
                            <span class="badge bg-white text-secondary border suggestion-chip shadow-sm">
                                <i class="fas fa-clock-rotate-left me-1 text-primary"></i> Quais são os documentos mais recentes no sistema?
                            </span>
                            <span class="badge bg-white text-secondary border suggestion-chip shadow-sm">
                                <i class="fas fa-file-signature me-1 text-success"></i> Há despachos pendentes para o meu setor?
                            </span>
                            <span class="badge bg-white text-secondary border suggestion-chip shadow-sm">
                                <i class="fas fa-building-columns me-1 text-warning"></i> Quais documentos deram entrada com procedência do Ministério das Finanças?
                            </span>
                            <span class="badge bg-white text-secondary border suggestion-chip shadow-sm">
                                <i class="fas fa-magnifying-glass me-1 text-info"></i> Pesquisar ofícios que mencionam contratos ou faturas
                            </span>
                        </div>
                    </div>
                </div>

                <form id="chatForm" class="mt-3" autocomplete="off">
                    <div class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden border">
                        <input type="text" id="chatInput" class="form-control border-0 px-4"
                            placeholder="Escreva a sua pergunta em linguagem natural (ex: Qual o despacho do ofício 6024?)..." maxlength="2000" aria-label="Pergunta">
                        <button class="btn btn-primary px-4 fw-bold" type="submit" id="chatSend">
                            <i class="fas fa-paper-plane me-1"></i> Consultar
                        </button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 px-2 flex-wrap gap-1">
                        <div class="form-text small text-muted mb-0">
                            <i class="fas fa-shield-halved me-1 text-success"></i>Acesso restrito: O assistente respeita rigorosamente as permissões do seu departamento.
                        </div>
                        <div class="small text-muted">
                            <i class="fas fa-microchip me-1 text-primary"></i>Powered by DeepSeek AI
                        </div>
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
                chip.addEventListener('click', () => { 
                    input.value = chip.textContent.trim(); 
                    input.focus(); 
                });
            });

            function scrollDown() { 
                win.scrollTop = win.scrollHeight; 
            }

            // Função para converter Markdown básico em HTML seguro
            function parseMarkdown(md) {
                if (!md) return '';
                let text = md;
                
                // Escape básico de HTML perigoso
                text = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
                
                // Títulos ###
                text = text.replace(/^### (.*$)/gim, '<h3>$1</h3>');
                text = text.replace(/^## (.*$)/gim, '<h2>$1</h2>');
                text = text.replace(/^# (.*$)/gim, '<h1>$1</h1>');
                
                // Blockquotes >
                text = text.replace(/^\> (.*$)/gim, '<blockquote>$1</blockquote>');
                
                // Negrito **texto**
                text = text.replace(/\*\*(.*?)\*\*/gim, '<strong>$1</strong>');
                
                // Itálico *texto*
                text = text.replace(/\*(.*?)\*/gim, '<em>$1</em>');
                
                // Listas com marcadores (- item ou * item)
                text = text.replace(/^\s*[-*]\s+(.*$)/gim, '<li>$1</li>');
                text = text.replace(/(<li>.*<\/li>)/gims, '<ul>$1</ul>');
                text = text.replace(/<\/ul>\s*<ul>/gim, '');
                
                // Quebras de linha duplas viram parágrafos
                text = text.split(/\n\n+/).map(p => {
                    p = p.trim();
                    if (!p) return '';
                    if (p.startsWith('<h') || p.startsWith('<ul') || p.startsWith('<block')) return p;
                    return '<p>' + p.replace(/\n/g, '<br>') + '</p>';
                }).join('');
                
                return text;
            }

            function addMessage(role, text, sources) {
                if (empty && empty.parentNode) empty.remove();
                
                const wrap = document.createElement('div');
                wrap.className = 'chat-msg ' + (role === 'user' ? 'user' : 'bot');

                const avatar = document.createElement('div');
                avatar.className = 'chat-avatar ' + (role === 'user' ? 'user' : 'bot');
                avatar.innerHTML = role === 'user' ? '<i class="fas fa-user"></i>' : '<i class="fas fa-brain"></i>';

                const bubble = document.createElement('div');
                bubble.className = 'bubble';
                
                if (role === 'user') {
                    bubble.textContent = text;
                } else {
                    bubble.innerHTML = parseMarkdown(text);
                }

                // Renderizar Cards de Documentos Citados
                if (sources && sources.length > 0) {
                    const sourcesBox = document.createElement('div');
                    sourcesBox.className = 'chat-sources-container';
                    
                    const titleBox = document.createElement('div');
                    titleBox.className = 'd-flex align-items-center justify-content-between mb-2';
                    titleBox.innerHTML = `
                        <span class="small fw-bold text-dark text-uppercase" style="font-size:0.75rem; letter-spacing:0.5px;">
                            <i class="fas fa-folder-open me-1 text-primary"></i> Documentos Relacionados (${sources.length})
                        </span>
                        <span class="small text-muted" style="font-size:0.7rem;">Clique no card para abrir</span>
                    `;
                    sourcesBox.appendChild(titleBox);

                    const grid = document.createElement('div');
                    grid.className = 'doc-card-grid';

                    sources.forEach(s => {
                        const isInterno = (s.tipo === 'interno' || s.badge === 'Interno');
                        const card = document.createElement('a');
                        card.className = 'doc-citation-card';
                        card.href = s.url;
                        card.target = '_blank';
                        card.rel = 'noopener';

                        card.innerHTML = `
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-1.5">
                                    <span class="doc-citation-badge ${isInterno ? 'interno' : 'entrada'}">
                                        <i class="fas ${isInterno ? 'fa-file-signature' : 'fa-inbox'} me-1"></i>${isInterno ? 'Interno' : 'Entrada'}
                                    </span>
                                    <span class="small text-muted fw-semibold" style="font-size: 0.72rem;">${s.data || ''}</span>
                                </div>
                                <div class="fw-bold text-dark text-truncate mb-1" style="font-size: 0.85rem;" title="${s.numero || ''}">
                                    ${s.numero || s.titulo}
                                </div>
                                <div class="small text-secondary text-truncate mb-2" style="font-size: 0.78rem;" title="${s.assunto || ''}">
                                    ${s.assunto || 'Sem assunto'}
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top border-light mt-1">
                                <span class="small text-muted text-truncate" style="font-size: 0.7rem; max-width: 170px;">
                                    <i class="fas fa-building me-1 text-secondary"></i>${s.departamento || s.procedencia || 'Gabinete'}
                                </span>
                                <span class="btn btn-xs btn-primary py-0 px-2 rounded-pill" style="font-size: 0.68rem;">
                                    Ver <i class="fas fa-arrow-right ms-1"></i>
                                </span>
                            </div>
                        `;
                        grid.appendChild(card);
                    });

                    sourcesBox.appendChild(grid);
                    bubble.appendChild(sourcesBox);
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
                wrap.innerHTML = `
                    <div class="chat-avatar bot"><i class="fas fa-brain"></i></div>
                    <div class="bubble bg-white border d-flex align-items-center gap-2 text-muted py-2 px-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span class="small fw-medium">Consultando acervo e analisando permissões...</span>
                    </div>
                `;
                win.appendChild(wrap);
                scrollDown();
            }

            function removeTyping() { 
                document.getElementById('typingIndicator')?.remove(); 
            }

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
                        addMessage('bot', data.erro || 'Ocorreu um erro ao processar a consulta.');
                    } else {
                        addMessage('bot', data.resposta || 'Nenhum resultado retornado.', data.fontes);
                        historico.push({ role: 'user', content: pergunta });
                        historico.push({ role: 'assistant', content: data.resposta || '' });
                        if (historico.length > 16) historico = historico.slice(-16);
                    }
                } catch (err) {
                    removeTyping();
                    addMessage('bot', 'Não foi possível contactar o assistente. Verifique a ligação de rede e tente novamente.');
                } finally {
                    busy = false;
                    sendBtn.disabled = false;
                    input.focus();
                }
            });
        })();
    </script>
@endsection
