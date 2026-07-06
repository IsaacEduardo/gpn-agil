{{--
    Painel de Decisão e Automação IA para uma página de documento.
    Variáveis esperadas:
      $doc — Objeto do DocumentoEntrada
      $departamentos — Coleção de Departamentos para o encaminhamento
--}}
<div class="card shadow-sm border-0 rounded-4 overflow-hidden mt-4" id="aiDecisionCard">
    <div class="card-header bg-white d-flex align-items-center justify-content-between py-3"
        role="button" data-bs-toggle="collapse" data-bs-target="#aiDecisionBody" aria-expanded="true">
        <h5 class="mb-0 fw-bold text-dark d-flex align-items-center gap-2">
            <span class="rounded-circle d-flex align-items-center justify-content-center"
                style="width:34px;height:34px;background:linear-gradient(135deg,#eff6ff,#e0e7ff);color:#4f46e5;">
                <i class="fas fa-wand-magic-sparkles"></i>
            </span>
            Decisão & Automação IA
        </h5>
        <i class="fas fa-chevron-down text-muted"></i>
    </div>
    
    <div class="collapse show" id="aiDecisionBody">
        <div class="card-body p-3">
            <ul class="nav nav-pills nav-fill mb-3 bg-light p-1 rounded-3" id="aiTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active py-2 rounded-2 fw-medium small" id="ai-nota-tab" data-bs-toggle="pill" data-bs-target="#ai-nota" type="button" role="tab">
                        <i class="fas fa-file-signature me-1"></i>Nota de Gabinete
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-2 rounded-2 fw-medium small" id="ai-tarefas-tab" data-bs-toggle="pill" data-bs-target="#ai-tarefas" type="button" role="tab">
                        <i class="fas fa-tasks me-1"></i>Ações & Tarefas
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="aiTabsContent">
                <!-- Tab 1: Nota de Gabinete -->
                <div class="tab-pane fade show active" id="ai-nota" role="tabpanel">
                    <div id="aiNotaContainer" class="p-3 bg-light rounded-3 border" style="min-height: 120px;">
                        <div id="aiNotaPlaceholder" class="text-center text-muted py-3 small">
                            <i class="fas fa-file-contract fa-2x opacity-25 d-block mb-2"></i>
                            Gere uma Nota Executiva resumida com contextos, riscos e propostas de despacho para o Super Chefe.
                            <button class="btn btn-primary btn-sm w-100 mt-3 rounded-pill" id="btnGerarNota">
                                <i class="fas fa-cogs me-1"></i>Gerar Nota de Gabinete
                            </button>
                        </div>
                        <div id="aiNotaContent" class="d-none">
                            <div class="ai-rendered-markdown small text-dark" style="max-height: 40vh; overflow-y: auto; background-color: #ffffff; padding: 12px; border-radius: 8px;"></div>
                            <hr class="my-2">
                            <div class="d-flex gap-2">
                                <button class="btn btn-outline-primary btn-sm w-100 rounded-pill" id="btnCopiarDespacho">
                                    <i class="fas fa-copy me-1"></i>Copiar Despacho
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tab 2: Ações & Tarefas -->
                <div class="tab-pane fade" id="ai-tarefas" role="tabpanel">
                    <div id="aiTarefasContainer" class="p-3 bg-light rounded-3 border" style="min-height: 120px;">
                        <div id="aiTarefasPlaceholder" class="text-center text-muted py-3 small">
                            <i class="fas fa-brain fa-2x opacity-25 d-block mb-2"></i>
                            Analise o documento para sugerir prioridade, departamento ideal de destino e tarefas automáticas.
                            <button class="btn btn-primary btn-sm w-100 mt-3 rounded-pill" id="btnGerarTarefas">
                                <i class="fas fa-cogs me-1"></i>Sugerir Ações & Tarefas
                            </button>
                        </div>
                        <div id="aiTarefasContent" class="d-none">
                            <div class="mb-3 d-flex flex-wrap gap-1">
                                <span class="badge bg-secondary">Assunto Sugerido: <strong id="aiAssuntoSug"></strong></span>
                                <span class="badge bg-warning text-dark">Prioridade: <strong id="aiPrioridadeSug"></strong></span>
                            </div>
                            
                            <!-- Forward Suggestions -->
                            <div id="aiEncaminhamentoBlock" class="mb-3 p-3 bg-white rounded-3 border d-none">
                                <span class="fw-bold text-dark d-block mb-1 small"><i class="fas fa-paper-plane text-primary me-1"></i>Encaminhamento Recomendado</span>
                                <p class="small text-muted mb-2" id="aiJustificativaEnc" style="font-size: 0.8rem;"></p>
                                <button class="btn btn-sm btn-outline-primary w-100 rounded-pill" id="btnPreencherEncaminhar">
                                    <i class="fas fa-share me-1"></i>Preparar Encaminhamento para <strong id="aiDepSugNome"></strong>
                                </button>
                            </div>
                            
                            <!-- Tasks Suggestions -->
                            <span class="fw-bold text-dark d-block mb-2 small"><i class="fas fa-list-check text-success me-1"></i>Tarefas Recomendadas</span>
                            <div id="aiTarefasList" class="d-flex flex-column gap-2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const docId = @json($doc->id);
            const routeNota = `/documentos-entradas/${docId}/gerar-nota-gab`;
            const routeAcoes = `/documentos-entradas/${docId}/sugerir-acoes`;
            
            let dispatchText = '';
            
            // Helper function to render Markdown to HTML
            function renderMarkdown(md) {
                return md
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/^### (.*$)/gim, '<h6 class="fw-bold text-primary mt-3">$1</h6>')
                    .replace(/^## (.*$)/gim, '<h5 class="fw-bold text-primary mt-3">$1</h5>')
                    .replace(/^# (.*$)/gim, '<h4 class="fw-bold text-primary mt-3">$1</h4>')
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\*(.*?)\*/g, '<em>$1</em>')
                    .replace(/^- (.*$)/gim, '<li class="ms-3" style="font-size: 0.85rem;">$1</li>')
                    .replace(/\n/g, '<br>');
            }

            // Extract Despacho from response
            function extractDespacho(text) {
                const marker = "Minuta de Despacho Sugerida:";
                const index = text.indexOf(marker);
                if (index !== -1) {
                    return text.substring(index + marker.length).trim().replace(/^>+/gm, '').trim();
                }
                return text;
            }

            // 1. Action: Generate Cabinet Note
            const btnGerarNota = document.getElementById('btnGerarNota');
            if (btnGerarNota) {
                btnGerarNota.addEventListener('click', async function () {
                    const container = document.getElementById('aiNotaContainer');
                    const placeholder = document.getElementById('aiNotaPlaceholder');
                    const content = document.getElementById('aiNotaContent');
                    
                    placeholder.innerHTML = '<div class="spinner-grow spinner-grow-sm text-primary me-2" role="status"></div> Analisando documento e gerando nota...';
                    btnGerarNota.disabled = true;
                    
                    try {
                        const resp = await fetch(routeNota, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            }
                        });
                        const data = await resp.json();
                        if (resp.ok && data.nota) {
                            placeholder.classList.add('d-none');
                            content.classList.remove('d-none');
                            content.querySelector('.ai-rendered-markdown').innerHTML = renderMarkdown(data.nota);
                            dispatchText = extractDespacho(data.nota);
                        } else {
                            placeholder.innerHTML = `<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> Erro: ${data.error || 'Falha ao processar.'}</span>`;
                            btnGerarNota.disabled = false;
                        }
                    } catch (e) {
                        placeholder.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> Falha ao conectar ao servidor.</span>';
                        btnGerarNota.disabled = false;
                    }
                });
            }

            // 2. Action: Suggest Actions / Tasks
            const btnGerarTarefas = document.getElementById('btnGerarTarefas');
            let suggestedAcoes = null;
            
            if (btnGerarTarefas) {
                btnGerarTarefas.addEventListener('click', async function () {
                    const placeholder = document.getElementById('aiTarefasPlaceholder');
                    const content = document.getElementById('aiTarefasContent');
                    
                    placeholder.innerHTML = '<div class="spinner-grow spinner-grow-sm text-primary me-2" role="status"></div> Analisando texto e sugerindo fluxos...';
                    btnGerarTarefas.disabled = true;
                    
                    try {
                        const resp = await fetch(routeAcoes, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            }
                        });
                        const data = await resp.json();
                        if (resp.ok) {
                            suggestedAcoes = data;
                            placeholder.classList.add('d-none');
                            content.classList.remove('d-none');
                            
                            // Fill Subject and Priority
                            document.getElementById('aiAssuntoSug').textContent = data.classificacao_sugerida || data.assunto_resumido || 'Indefinido';
                            document.getElementById('aiPrioridadeSug').textContent = (data.prioridade || 'media').toUpperCase();
                            
                            // Encaminhamento
                            if (data.encaminhar_para_departamento_id) {
                                // We need to match the name. The controller doesn't return the name directly in the JSON sometimes, but we can search for it in our list
                                const depSugNome = document.getElementById('aiDepSugNome');
                                // Let's search inside the combobox options or select for the name
                                let depName = 'Departamento Recomendado';
                                const option = document.querySelector(`#modalEncaminharDocumento button[data-id="${data.encaminhar_para_departamento_id}"]`);
                                if (option) {
                                    depName = option.dataset.nome;
                                }
                                depSugNome.textContent = depName;
                                document.getElementById('aiJustificativaEnc').textContent = data.justificativa_encaminhamento || 'Demanda identificada no documento.';
                                document.getElementById('aiEncaminhamentoBlock').classList.remove('d-none');
                            }
                            
                            // Render Tasks
                            const tasksList = document.getElementById('aiTarefasList');
                            tasksList.innerHTML = '';
                            
                            if (data.tarefas_sugeridas && data.tarefas_sugeridas.length > 0) {
                                data.tarefas_sugeridas.forEach((task, index) => {
                                    const card = document.createElement('div');
                                    card.className = 'p-3 bg-white border rounded-3';
                                    
                                    let userTag = '';
                                    if (task.assigned_to_user_id) {
                                        const userOpt = document.querySelector(`#modalDesignarTarefa select[name="destino_ids[]"] option[value="${task.assigned_to_user_id}"]`);
                                        if (userOpt) {
                                            userTag = `<span class="badge bg-light text-dark border small mt-1"><i class="fas fa-user text-muted me-1"></i>${userOpt.textContent}</span>`;
                                        }
                                    }
                                    
                                    card.innerHTML = `
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h6 class="fw-bold text-dark mb-1 small">${task.titulo}</h6>
                                            <span class="badge bg-info-subtle text-info small" style="font-size:0.75rem;">${task.prazo_dias_sugerido} dias</span>
                                        </div>
                                        <p class="small text-muted mb-2" style="font-size:0.8rem; line-height: 1.3;">${task.descricao || ''}</p>
                                        ${userTag}
                                        <button class="btn btn-xs btn-outline-success w-100 mt-2" onclick="prepararTarefaIA(${index})" style="font-size: 0.75rem; padding: 2px 8px;">
                                            <i class="fas fa-plus me-1"></i>Designar esta Tarefa
                                        </button>
                                    `;
                                    tasksList.appendChild(card);
                                });
                            } else {
                                tasksList.innerHTML = '<div class="text-center text-muted py-2 small">Nenhuma tarefa específica recomendada.</div>';
                            }
                        } else {
                            placeholder.innerHTML = `<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> Erro: ${data.error || 'Falha ao processar.'}</span>`;
                            btnGerarTarefas.disabled = false;
                        }
                    } catch (e) {
                        placeholder.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle me-1"></i> Falha ao conectar ao servidor.</span>';
                        btnGerarTarefas.disabled = false;
                    }
                });
            }

            // Copy dispatch to clipboard
            document.getElementById('btnCopiarDespacho').addEventListener('click', function () {
                if (dispatchText) {
                    navigator.clipboard.writeText(dispatchText).then(() => {
                        const originalHTML = this.innerHTML;
                        this.innerHTML = '<i class="fas fa-check me-1"></i> Copiado!';
                        setTimeout(() => {
                            this.innerHTML = originalHTML;
                        }, 2000);
                    });
                }
            });

            // Action: Pre-fill Forwarding modal
            const btnPreencherEnc = document.getElementById('btnPreencherEncaminhar');
            if (btnPreencherEnc) {
                btnPreencherEnc.addEventListener('click', function () {
                    if (suggestedAcoes && suggestedAcoes.encaminhar_para_departamento_id) {
                        const modalEl = document.getElementById('modalEncaminharDocumento');
                        const modal = new bootstrap.Modal(modalEl);
                        
                        // Select inside combobox
                        const depId = suggestedAcoes.encaminhar_para_departamento_id;
                        const option = modalEl.querySelector(`button[data-id="${depId}"]`);
                        if (option) {
                            const hidden = modalEl.querySelector('.dep-combobox-value');
                            const input = modalEl.querySelector('.dep-combobox-input');
                            if (hidden && input) {
                                hidden.value = depId;
                                input.value = option.dataset.nome;
                                hidden.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        }
                        
                        // Put explanation inside Dispatch text
                        const descField = document.getElementById('observacaoInput');
                        if (descField) {
                            descField.value = suggestedAcoes.justificativa_encaminhamento || '';
                        }
                        
                        modal.show();
                    }
                });
            }

            // Action: Pre-fill Task modal
            window.prepararTarefaIA = function (index) {
                if (suggestedAcoes && suggestedAcoes.tarefas_sugeridas && suggestedAcoes.tarefas_sugeridas[index]) {
                    const task = suggestedAcoes.tarefas_sugeridas[index];
                    const modalEl = document.getElementById('modalDesignarTarefa');
                    const modal = new bootstrap.Modal(modalEl);
                    
                    // Set title & desc
                    modalEl.querySelector('input[name="titulo"]').value = task.titulo || '';
                    modalEl.querySelector('textarea[name="descricao"]').value = task.descricao || '';
                    
                    // Set deadline
                    if (task.prazo_dias_sugerido) {
                        const targetDate = new Date();
                        targetDate.setDate(targetDate.getDate() + parseInt(task.prazo_dias_sugerido));
                        modalEl.querySelector('input[name="prazo_at"]').value = targetDate.toISOString().split('T')[0];
                    }
                    
                    // Set user assignment
                    if (task.assigned_to_user_id) {
                        const selectUser = modalEl.querySelector('select[name="destino_ids[]"]');
                        if (selectUser) {
                            // Find and select option
                            for (let option of selectUser.options) {
                                option.selected = (option.value == task.assigned_to_user_id);
                            }
                            selectUser.dispatchEvent(new Event('change'));
                        }
                    }
                    
                    modal.show();
                }
            };
        })();
    </script>
@endpush
