@extends('layouts.app')

@section('styles')
<style>
    #tab-preview {
        overflow-x: auto;
        background-color: #eef1f5;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        justify-content: center;
    }
    /* Só o separador activo é flex: um display no seletor de ID sobrepunha-se ao
       display:none do Bootstrap e a folha A4 ocupava ~2000px invisíveis. */
    #tab-preview.active {
        display: flex;
    }
    .doc-edit-actions {
        position: sticky;
        bottom: 0;
        z-index: 20;
        background: #fff;
        border-top: 1px solid #dee2e6;
        margin: 1rem -1rem -1rem;
        padding: .75rem 1rem;
        border-radius: 0 0 .375rem .375rem;
    }
</style>
@endsection

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Editar Documento Interno</span>
                        @if (config('app.feature_collab'))
                            @if (\App\Http\Controllers\DocumentoColaboracaoController::EDITOR_PRESERVA_ESTRUTURA || ! $documentoInterno->temConteudoEstruturado())
                                <a href="{{ route('documentos-internos.collab.editor', $documentoInterno) }}"
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-users me-1"></i> Editar em colaboração
                                </a>
                            @else
                                <span class="text-muted small" title="O editor colaborativo ainda não preserva os campos deste modelo">
                                    <i class="fas fa-users-slash me-1"></i> Colaboração indisponível para este modelo
                                </span>
                            @endif
                        @endif
                    </div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('documentos-internos.update', $documentoInterno) }}"
                            id="docForm">
                            @csrf
                            @method('PUT')

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="titulo" class="form-label">Título / Assunto</label>
                                        <input type="text" class="form-control" id="titulo" name="titulo"
                                            value="{{ $documentoInterno->titulo }}" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Espécie</label>
                                        <input type="text" class="form-control"
                                            value="{{ $documentoInterno->especie->nome }}" disabled>
                                        <input type="hidden" name="documento_especie_id"
                                            value="{{ $documentoInterno->documento_especie_id }}">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Referência</label>
                                        <input type="text" class="form-control"
                                            value="{{ $documentoInterno->numero_referencia }}" disabled>
                                    </div>

                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Dados do Destinatário</h6>
                                            <div class="mb-2">
                                                <label for="destinatario_nome" class="form-label small">Nome</label>
                                                <input type="text" class="form-control form-control-sm"
                                                    id="destinatario_nome" name="destinatario_nome"
                                                    value="{{ $documentoInterno->destinatario_nome }}">
                                            </div>
                                            <div class="mb-2">
                                                <label for="destinatario_cargo" class="form-label small">Cargo</label>
                                                <input type="text" class="form-control form-control-sm"
                                                    id="destinatario_cargo" name="destinatario_cargo"
                                                    value="{{ $documentoInterno->destinatario_cargo }}">
                                            </div>
                                            <div class="mb-2">
                                                <label for="destinatario_orgao"
                                                    class="form-label small">Instituição/Órgão</label>
                                                <input type="text" class="form-control form-control-sm"
                                                    id="destinatario_orgao" name="destinatario_orgao"
                                                    value="{{ $documentoInterno->destinatario_orgao }}">
                                            </div>
                                            <div class="mb-2">
                                                <label for="destinatario_local" class="form-label small">Local</label>
                                                <input type="text" class="form-control form-control-sm"
                                                    id="destinatario_local" name="destinatario_local"
                                                    value="{{ $documentoInterno->destinatario_local }}">
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Sem painel de variáveis: na edição os marcadores {{...}} não são
                                         resolvidos (o update() recusa-os), só na criação a partir do modelo. --}}
                                </div>

                                <!-- Right Column: Editor & Preview Tabs -->
                                <div class="col-md-8">
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
                                                <label for="conteudo_final" class="form-label">Conteúdo do Documento</label>
                                                @error('conteudo_final')
                                                    <div class="alert alert-danger py-2 small">{{ $message }}</div>
                                                @enderror
                                                <textarea class="form-control" id="conteudo_final" name="conteudo_final" rows="20">{{ $documentoInterno->conteudo_final }}</textarea>
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
                                                        <span id="preview-gabinete-nome">{{ mb_strtoupper($documentoInterno->gabineteEmissor()?->nome ?? ($documentoInterno->departamento->nome ?? 'GABINETE NÃO DEFINIDO')) }}</span>
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

                            <div class="doc-edit-actions d-flex justify-content-end gap-2">
                                <a href="{{ route('documentos-internos.index') }}" class="btn btn-secondary">Cancelar</a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-1"></i> Salvar Alterações
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>
    @include('documentos_internos.partials.campos-vinculados-js')
    <script>
        function updateLivePreview() {
            if (!tinymce.get('conteudo_final')) return;
            
            // Assunto e destinatário já estão no editor (marcadores sincronizados por
            // partials/campos-vinculados-js); aqui só se resolvem os restantes placeholders.
            let rawContent = tinymce.get('conteudo_final').getContent();

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

            Object.entries(dynamicReplacements).forEach(([key, val]) => {
                const regex = new RegExp(`\\{\\{${key}\\}\\}`, 'g');
                cleanContent = cleanContent.replace(regex, val);
            });

            document.getElementById('live-preview-content').innerHTML = cleanContent;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // O TinyMCE 6 é distribuído sob GPL e pode ser auto-alojado sem licença
            // comercial; o emblema "Upgrade" que mostrava é promocional, não um aviso
            // de licença em falta. Numa instalação governamental não tem lugar.
            tinymce.init({
                promotion: false,
                selector: 'textarea#conteudo_final',
                branding: false,
                plugins: 'link lists table',
                toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright alignjustify | indent outdent | bullist numlist | table | removeformat',
                height: '75vh',
                min_height: 500,
                content_style: '.campo-vazio { color: #b45309; background: #fef3c7; }',
                setup: function (editor) {
                    editor.on('NodeChange Change KeyUp', function () {
                        updateLivePreview();
                    });
                }
            });

            ligarCamposVinculados(updateLivePreview);
        });
    </script>
@endsection
