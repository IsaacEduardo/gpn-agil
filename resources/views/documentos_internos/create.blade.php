@extends('layouts.app')

@section('styles')
<style>
    .form-floating > label {
        color: #6c757d;
    }
    .sticky-sidebar {
        position: sticky;
        top: 20px;
        height: calc(100vh - 40px);
        overflow-y: auto;
    }
    .variable-tag {
        cursor: pointer;
        transition: all 0.2s;
    }
    .variable-tag:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .editor-container {
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 0 15px rgba(0,0,0,0.05);
    }
    .card-modern {
        border: none;
        box-shadow: 0 0 20px rgba(0,0,0,0.08);
        border-radius: 12px;
    }
    .card-header-modern {
        background: white;
        border-bottom: 1px solid #f0f0f0;
        padding: 1.5rem;
        border-radius: 12px 12px 0 0 !important;
    }
    #tab-preview {
        overflow-x: auto;
        background-color: #eef1f5;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        display: flex;
        justify-content: center;
    }
</style>
@endsection

@section('content')
    <div class="container-fluid px-4"> <!-- Expanded container for more space -->
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card card-modern">
                    <div class="card-header card-header-modern d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-0 fw-bold text-primary"><i class="fas fa-file-alt me-2"></i>Novo Documento Interno</h4>
                            <small class="text-muted">Preencha os dados e gere o documento a partir de modelos padronizados.</small>
                        </div>
                        @if ($documentoEntrada)
                            <span class="badge bg-info rounded-pill px-3 py-2">
                                <i class="fas fa-reply me-1"></i> Respondendo: {{ Str::limit($documentoEntrada->assunto, 40) }}
                            </span>
                        @endif
                    </div>

                    <div class="card-body p-4">
                        <form method="POST" action="{{ route('documentos-internos.store') }}" id="docForm">
                            @csrf

                            @if ($documentoEntrada)
                                <input type="hidden" name="documento_entrada_id" value="{{ $documentoEntrada->id }}">
                            @endif

                            <div class="row">
                                <!-- Left Column: Metadata & Configuration -->
                                <div class="col-lg-4">
                                    <div class="sticky-sidebar pe-2">
                                        
                                        <!-- Main Info Section -->
                                        <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.8rem; letter-spacing: 1px;">Informações Básicas</h6>
                                        
                                        <div class="form-floating mb-3">
                                            <input type="text" class="form-control" id="titulo" name="titulo" placeholder="Assunto do documento"
                                                value="{{ $documentoEntrada ? 'Re: ' . $documentoEntrada->assunto : '' }}" required>
                                            <label for="titulo">Título / Assunto</label>
                                        </div>

                                        <div class="row g-2 mb-3">
                                            <div class="col-md-6">
                                                <div class="form-floating">
                                                    <select class="form-select" id="documento_especie_id" name="documento_especie_id" required>
                                                        <option value="">Selecione...</option>
                                                        @foreach ($especies as $especie)
                                                            <option value="{{ $especie->id }}">{{ $especie->nome }}</option>
                                                        @endforeach
                                                    </select>
                                                    <label for="documento_especie_id">Espécie</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-floating">
                                                    <select class="form-select" id="modelo_documento_id" name="modelo_documento_id">
                                                        <option value="">Aguardando Espécie...</option>
                                                    </select>
                                                    <label for="modelo_documento_id">Modelo</label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Container para Campos Dinâmicos -->
                                        <div id="dynamicFieldsContainer" class="mb-3" style="display: none;">
                                            <h6 class="text-uppercase text-muted fw-bold mb-2" style="font-size: 0.75rem; letter-spacing: 1px;">
                                                <i class="fas fa-edit me-1"></i> Preenchimento Específico
                                            </h6>
                                            <div class="card bg-light border-0">
                                                <div class="card-body p-3" id="dynamicFieldsBody">
                                                    <!-- Inputs gerados via JS aqui -->
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-grid gap-2 mb-4">
                                            <button type="button" class="btn btn-primary py-2" id="btnPreview">
                                                <i class="fas fa-sync-alt me-2"></i> Carregar/Atualizar Template
                                            </button>
                                            <small id="previewHelp" class="text-warning text-center" style="display:none;">
                                                <i class="fas fa-exclamation-triangle"></i> Dados alterados. Recarregue o template.
                                            </small>
                                        </div>

                                        <hr class="my-4 text-muted">

                                        <!-- Recipient Data Section -->
                                        <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.8rem; letter-spacing: 1px;">Dados do Destinatário</h6>
                                        
                                        <div class="card bg-light border-0 mb-3">
                                            <div class="card-body">
                                                <div class="form-floating mb-2">
                                                    <input type="text" class="form-control form-control-sm" id="destinatario_nome" name="destinatario_nome" placeholder="Nome">
                                                    <label for="destinatario_nome">Nome do Destinatário</label>
                                                </div>
                                                <div class="form-floating mb-2">
                                                    <input type="text" class="form-control form-control-sm" id="destinatario_cargo" name="destinatario_cargo" placeholder="Cargo">
                                                    <label for="destinatario_cargo">Cargo</label>
                                                </div>
                                                <div class="form-floating mb-2">
                                                    <input type="text" class="form-control form-control-sm" id="destinatario_orgao" name="destinatario_orgao" placeholder="Instituição">
                                                    <label for="destinatario_orgao">Instituição/Órgão</label>
                                                </div>
                                                <div class="form-floating">
                                                    <input type="text" class="form-control form-control-sm" id="destinatario_local" name="destinatario_local" value="Moçâmedes" placeholder="Local">
                                                    <label for="destinatario_local">Local</label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Variables Helper -->
                                        <div class="accordion" id="accordionVariables">
                                            <div class="accordion-item border-0 shadow-sm">
                                                <h2 class="accordion-header" id="headingVariables">
                                                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseVariables">
                                                        <i class="fas fa-magic me-2 text-warning"></i> Variáveis Dinâmicas
                                                    </button>
                                                </h2>
                                                <div id="collapseVariables" class="accordion-collapse collapse" data-bs-parent="#accordionVariables">
                                                    <div class="accordion-body p-2 bg-light">
                                                        <p class="small text-muted mb-2">Clique para inserir no editor:</p>
                                                        <div class="d-flex flex-wrap gap-1">
                                                            <span class="badge bg-white text-dark border variable-tag" onclick="insertVariable('@{{DATA_ATUAL}}')">Data Atual</span>
                                                            <span class="badge bg-white text-dark border variable-tag" onclick="insertVariable('@{{DATA_EXTENSO}}')">Data Extenso</span>
                                                            <span class="badge bg-white text-dark border variable-tag" onclick="insertVariable('@{{USUARIO_NOME}}')">Seu Nome</span>
                                                            <span class="badge bg-white text-dark border variable-tag" onclick="insertVariable('@{{DEPARTAMENTO_NOME}}')">Seu Depto</span>
                                                            <span class="badge bg-white text-dark border variable-tag" onclick="insertVariable('@{{DESTINATARIO_NOME}}')">Dest. Nome</span>
                                                            <span class="badge bg-white text-dark border variable-tag" onclick="insertVariable('@{{DESTINATARIO_CARGO}}')">Dest. Cargo</span>
                                                            <span class="badge bg-white text-dark border variable-tag" onclick="insertVariable('@{{ASSUNTO}}')">Assunto</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4 d-grid gap-2">
                                            <button type="submit" class="btn btn-success btn-lg">
                                                <i class="fas fa-save me-2"></i> Salvar Documento
                                            </button>
                                            <a href="{{ route('documentos-internos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right Column: Editor & Preview Tabs -->
                                <div class="col-lg-8">
                                    <ul class="nav nav-tabs mb-3 d-print-none" id="editorTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active fw-bold" id="editor-tab" data-bs-toggle="tab" data-bs-target="#tab-editor" type="button" role="tab" aria-controls="tab-editor" aria-selected="true">
                                                <i class="fas fa-edit me-1"></i> Editor de Texto
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link fw-bold" id="preview-tab" data-bs-toggle="tab" data-bs-target="#tab-preview" type="button" role="tab" aria-controls="tab-preview" aria-selected="false" onclick="updateLivePreview()">
                                                <i class="fas fa-file-alt me-1"></i> Pré-visualização A4
                                            </button>
                                        </li>
                                    </ul>
                                    <div class="tab-content" id="editorTabsContent">
                                        <!-- Editor Tab -->
                                        <div class="tab-pane fade show active" id="tab-editor" role="tabpanel" aria-labelledby="editor-tab">
                                            <div class="editor-container">
                                                <textarea class="form-control" id="conteudo_final" name="conteudo_final" rows="25"></textarea>
                                            </div>
                                        </div>
                                        <!-- Preview Tab -->
                                        <div class="tab-pane fade" id="tab-preview" role="tabpanel" aria-labelledby="preview-tab">
                                            <div class="paper-container shadow-lg mb-4" id="live-paper-preview" style="background: white; width: 210mm; min-height: 297mm; padding: 20mm 20mm 35mm 30mm; position: relative; box-sizing: border-box; border-radius: 4px; border: 1px solid #dee2e6; text-align: left;">
                                                <!-- Insígnia/Logo da Instituição -->
                                                <div class="text-center mb-4">
                                                    <img src="{{ $dadosInstituicao->logo_url }}" alt="Insígnia" style="width: 22mm; height: auto;">
                                                    <div style="margin-top: 10px; font-family: 'Times New Roman', serif; font-size: 12pt; font-weight: bold; text-transform: uppercase;">
                                                        {{ $dadosInstituicao->cabecalho_linha1 }}<br>
                                                        {{ $dadosInstituicao->cabecalho_linha2 }}<br>
                                                        <span id="preview-gabinete-nome">{{ mb_strtoupper(auth()->user()->departamento->gabinete->nome ?? (auth()->user()->departamento->nome ?? 'GABINETE NÃO DEFINIDO')) }}</span>
                                                    </div>
                                                </div>
                                                
                                                <!-- Corpo do Documento -->
                                                <div class="paper-content" id="live-preview-content" style="font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; color: #000; min-height: 500px;">
                                                    <!-- Conteúdo reativo injetado via JS -->
                                                </div>

                                                <!-- Rodapé Oficial -->
                                                <div class="paper-footer" style="position: absolute; bottom: 0; left: 0; width: 100%; padding: 0; z-index: 10;">
                                                    @php
                                                        $rodapeImg = $dadosInstituicao->rodape_url;
                                                        if (!$dadosInstituicao->rodape_img_path) {
                                                            $rodapeCandidates = ['rodape_estacionario.png', 'rodape_estacionario.jpg', 'Estacionariodoc.jpg', 'Estacionariodoc.jpeg', 'estacionario.png', 'estacionario.jpg'];
                                                            foreach ($rodapeCandidates as $candidate) {
                                                                if (file_exists(public_path('images/' . $candidate))) {
                                                                    $rodapeImg = asset('images/' . $candidate);
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                    @endphp
                                                    @if ($rodapeImg)
                                                        <img src="{{ $rodapeImg }}" alt="Rodapé Oficial" style="width: 100%; height: auto; display: block;">
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        const modelos = @json($modelos);
        
        function updateLivePreview() {
            if (!tinymce.get('conteudo_final')) return;
            
            let rawContent = tinymce.get('conteudo_final').getContent();
            
            const tituloVal = document.getElementById('titulo') ? document.getElementById('titulo').value : '';
            const destNome = document.getElementById('destinatario_nome') ? document.getElementById('destinatario_nome').value : '';
            const destCargo = document.getElementById('destinatario_cargo') ? document.getElementById('destinatario_cargo').value : '';
            const destOrgao = document.getElementById('destinatario_orgao') ? document.getElementById('destinatario_orgao').value : '';
            const destLocal = document.getElementById('destinatario_local') ? document.getElementById('destinatario_local').value : 'Moçâmedes';
            
            const userName = "{{ auth()->user()->name }}";
            const userDept = "{{ auth()->user()->departamento->nome ?? 'Administração' }}";
            const userDeptSigla = "{{ auth()->user()->departamento->sigla ?? 'ADM' }}";
            
            const hoje = new Date();
            const dia = String(hoje.getDate()).padStart(2, '0');
            const mes = String(hoje.getMonth() + 1).padStart(2, '0');
            const ano = hoje.getFullYear();
            const dataAtual = `${dia}/${mes}/${ano}`;
            
            const mesesExtenso = [
                'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
            ];
            const dataExtenso = `${dia} de ${mesesExtenso[hoje.getMonth()]} de ${ano}`;
            
            const dynamicReplacements = {};
            document.querySelectorAll('.dynamic-input').forEach(input => {
                if (input.dataset.key) {
                    let key = input.dataset.key.toUpperCase();
                    dynamicReplacements[key] = input.value;
                }
            });

            let cleanContent = rawContent;
            
            cleanContent = cleanContent.replace(/\{\{DATA_ATUAL\}\}/g, dataAtual);
            cleanContent = cleanContent.replace(/\{\{DATA_EXTENSO\}\}/g, dataExtenso);
            cleanContent = cleanContent.replace(/\{\{USUARIO_NOME\}\}/g, userName);
            cleanContent = cleanContent.replace(/\{\{RESPONSAVEL_NOME\}\}/g, userName);
            cleanContent = cleanContent.replace(/\{\{DEPARTAMENTO_NOME\}\}/g, userDept);
            cleanContent = cleanContent.replace(/\{\{DEPARTAMENTO_SIGLA\}\}/g, userDeptSigla);
            cleanContent = cleanContent.replace(/\{\{ANO\}\}/g, ano);
            cleanContent = cleanContent.replace(/\{\{ASSUNTO\}\}/g, tituloVal);
            cleanContent = cleanContent.replace(/\{\{DESTINATARIO_NOME\}\}/g, destNome);
            cleanContent = cleanContent.replace(/\{\{DESTINATARIO_CARGO\}\}/g, destCargo);
            cleanContent = cleanContent.replace(/\{\{DESTINATARIO_ORGAO\}\}/g, destOrgao);
            cleanContent = cleanContent.replace(/\{\{DESTINATARIO_LOCAL\}\}/g, destLocal);
            
            Object.entries(dynamicReplacements).forEach(([key, val]) => {
                const regex = new RegExp(`\\{\\{${key}\\}\\}`, 'g');
                cleanContent = cleanContent.replace(regex, val);
            });

            document.getElementById('live-preview-content').innerHTML = cleanContent;
        }

        // Global function for variable insertion
        function insertVariable(variable) {
            tinymce.get('conteudo_final').insertContent(variable);
        }

        document.addEventListener('DOMContentLoaded', function() {
            tinymce.init({
                selector: 'textarea#conteudo_final',
                language: 'pt_BR', // Assuming pt_BR exists, otherwise default en
                plugins: 'preview importcss searchreplace autolink autosave save directionality code visualblocks visualchars fullscreen image link media template codesample table charmap pagebreak nonbreaking anchor insertdatetime advlist lists wordcount help charmap quickbars emoticons',
                menubar: 'file edit view insert format tools table help',
                toolbar: 'undo redo | bold italic underline strikethrough | fontfamily fontsize blocks | alignleft aligncenter alignright alignjustify | outdent indent |  numlist bullist | forecolor backcolor removeformat | pagebreak | charmap emoticons | fullscreen  preview print | insertfile image media template link anchor codesample | ltr rtl',
                toolbar_sticky: true,
                height: '85vh',
                quickbars_selection_toolbar: 'bold italic | quicklink h2 h3 blockquote quickimage quicktable',
                noneditable_noneditable_class: 'mceNonEditable',
                contextmenu: 'link image imagetools table',
                content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px; margin: 2rem; }',
                setup: function (editor) {
                    editor.on('init', function () {
                        // Optional: Load initial content if needed
                    });
                    editor.on('NodeChange Change KeyUp', function () {
                        updateLivePreview();
                    });
                }
            });

            const especieSelect = document.getElementById('documento_especie_id');
            const modeloSelect = document.getElementById('modelo_documento_id');
            const btnPreview = document.getElementById('btnPreview');
            const dynamicFieldsContainer = document.getElementById('dynamicFieldsContainer');
            const dynamicFieldsBody = document.getElementById('dynamicFieldsBody');

            const inputsParaMonitorar = [
                'titulo', 'destinatario_nome', 'destinatario_cargo',
                'destinatario_orgao', 'destinatario_local'
            ];

            // Helper para criar inputs dinâmicos
            function createDynamicInput(key, typeDef) {
                const wrapper = document.createElement('div');
                wrapper.className = 'form-floating mb-2';

                let input;
                let [type, options] = typeDef.split(':');
                const labelText = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());

                if (type === 'textarea') {
                    input = document.createElement('textarea');
                    input.className = 'form-control form-control-sm dynamic-input';
                    input.style.height = '80px';
                } else if (type === 'select') {
                    input = document.createElement('select');
                    input.className = 'form-select form-select-sm dynamic-input';
                    if (options) {
                        options.split(',').forEach(opt => {
                            const option = document.createElement('option');
                            option.value = opt.trim();
                            option.textContent = opt.trim();
                            input.appendChild(option);
                        });
                    }
                } else {
                    input = document.createElement('input');
                    input.type = type || 'text';
                    input.className = 'form-control form-control-sm dynamic-input';
                }

                input.id = 'dynamic_' + key;
                input.name = key; // Nome limpo para o payload
                input.placeholder = labelText;
                input.dataset.key = key;

                // Monitorar alterações nos campos dinâmicos também
                input.addEventListener('input', () => {
                    showUpdateHint();
                    updateLivePreview();
                });
                input.addEventListener('change', () => {
                    showUpdateHint();
                    updateLivePreview();
                });

                const label = document.createElement('label');
                label.htmlFor = input.id;
                label.textContent = labelText;

                wrapper.appendChild(input);
                wrapper.appendChild(label);
                return wrapper;
            }

            function showUpdateHint() {
                if (tinymce.get('conteudo_final') && tinymce.get('conteudo_final').getContent().trim() !== '') {
                    const help = document.getElementById('previewHelp');
                    if (help) {
                        help.style.display = 'block';
                        btnPreview.classList.remove('btn-primary');
                        btnPreview.classList.add('btn-warning');
                        btnPreview.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i> Atualizar Template';
                    }
                }
            }

            // Monitor changes to show update hint and update live preview
            inputsParaMonitorar.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('input', () => {
                        showUpdateHint();
                        updateLivePreview();
                    });
                }
            });

            // Lógica de Renderização ao mudar Modelo
            modeloSelect.addEventListener('change', function() {
                const modeloId = this.value;
                dynamicFieldsContainer.style.display = 'none';
                dynamicFieldsBody.innerHTML = '';

                if (!modeloId) return;

                // Encontrar o modelo selecionado em todos os grupos
                let modeloSelecionado = null;
                Object.values(modelos).forEach(lista => {
                    const found = lista.find(m => m.id == modeloId);
                    if (found) modeloSelecionado = found;
                });

                if (modeloSelecionado && modeloSelecionado.campos_dinamicos) {
                    // Se for string JSON, fazer parse
                    let campos = modeloSelecionado.campos_dinamicos;
                    if (typeof campos === 'string') {
                        try {
                            campos = JSON.parse(campos);
                        } catch (e) {
                            console.error('Erro ao parsear campos dinâmicos', e);
                            return;
                        }
                    }

                    // Se tiver campos, renderizar
                    if (campos && Object.keys(campos).length > 0) {
                        Object.entries(campos).forEach(([key, type]) => {
                            dynamicFieldsBody.appendChild(createDynamicInput(key, type));
                        });
                        dynamicFieldsContainer.style.display = 'block';
                    }
                }

                // Auto-carregar template se o editor estiver vazio ou não modificado
                if (tinymce.get('conteudo_final')) {
                    const editor = tinymce.get('conteudo_final');
                    if (editor.getContent().trim() === '' || !editor.isDirty()) {
                        setTimeout(() => {
                            btnPreview.click();
                        }, 150);
                    }
                }
            });

            especieSelect.addEventListener('change', function() {
                const especieId = this.value;
                modeloSelect.innerHTML = '<option value="">Selecione um modelo...</option>';
                dynamicFieldsContainer.style.display = 'none';
                dynamicFieldsBody.innerHTML = '';

                if (modelos[especieId]) {
                    modelos[especieId].forEach(modelo => {
                        const option = document.createElement('option');
                        option.value = modelo.id;
                        option.textContent = modelo.nome;
                        modeloSelect.appendChild(option);
                    });
                }
            });

            btnPreview.addEventListener('click', function() {
                const modeloId = modeloSelect.value;
                if (!modeloId) {
                    alert('Por favor, selecione um modelo (template) primeiro.');
                    modeloSelect.focus();
                    return;
                }

                // Confirmar se o editor contém alterações manuais
                if (tinymce.get('conteudo_final') && tinymce.get('conteudo_final').isDirty()) {
                    if (!confirm('Atenção: Você realizou alterações manuais no editor. Ao atualizar o modelo, suas alterações manuais serão substituídas pelo template padrão. Deseja prosseguir?')) {
                        return;
                    }
                }

                const originalBtnText = btnPreview.innerHTML;
                btnPreview.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Carregando...';
                btnPreview.disabled = true;

                // Reset visual cues
                const help = document.getElementById('previewHelp');
                if (help) help.style.display = 'none';
                btnPreview.classList.remove('btn-warning');
                btnPreview.classList.add('btn-primary');

                const payload = {
                    modelo_id: modeloId,
                    documento_entrada_id: '{{ $documentoEntrada ? $documentoEntrada->id : '' }}',
                    titulo: document.getElementById('titulo').value || '',
                    destinatario_nome: document.getElementById('destinatario_nome') ? document.getElementById('destinatario_nome').value : '',
                    destinatario_cargo: document.getElementById('destinatario_cargo') ? document.getElementById('destinatario_cargo').value : '',
                    destinatario_orgao: document.getElementById('destinatario_orgao') ? document.getElementById('destinatario_orgao').value : '',
                    destinatario_local: document.getElementById('destinatario_local') ? document.getElementById('destinatario_local').value : '',
                };

                // Coletar dados dos campos dinâmicos
                document.querySelectorAll('.dynamic-input').forEach(input => {
                    if (input.dataset.key) {
                        payload[input.dataset.key] = input.value;
                    }
                });

                fetch('{{ route('documentos-internos.preview') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(payload)
                    })
                    .then(response => response.json())
                    .then(data => {
                        tinymce.get('conteudo_final').setContent(data.content);
                        // Optional: Show success feedback
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Erro ao carregar template.');
                    })
                    .finally(() => {
                         btnPreview.innerHTML = '<i class="fas fa-sync-alt me-2"></i> Carregar/Atualizar Template';
                         btnPreview.disabled = false;
                    });
            });
        });
    </script>
@endsection
