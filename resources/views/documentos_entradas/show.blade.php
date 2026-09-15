@extends('layouts.app')

@section('title', 'Detalhes do Documento')

@section('content')
    @include('documentos_entradas.partials.show._estilos')

    <div class="container-fluid px-4 py-4">

        @include('documentos_entradas.partials.show._cabecalho')

        @include('documentos_entradas.partials.show._mensagens')

        <div class="row g-4 mt-0">
            {{-- Esquerda: o documento em si. --}}
            <div class="col-lg-8">
                @include('documentos_entradas.partials.show._visualizador')
            </div>

            {{-- Direita: contexto e decisão. --}}
            <div class="col-lg-4">
                @include('documentos_entradas.partials.show._contexto')
            </div>
        </div>

        {{-- Percurso a toda a largura: substitui as 6 abas anteriores. --}}
        <div class="row g-4 mt-0">
            <div class="col-12">
                @include('documentos_entradas.partials.show._percurso')
            </div>
        </div>
    </div>

    <!-- Modals Section (Hidden) -->
    @include('documentos_entradas.partials.modals')

    <!-- Modal Detalhes da Tarefa (Show Page) -->
    <div class="modal fade" id="modalDetalheTarefaShow" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold text-dark" id="modalShowTarefaTitulo"></h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body pt-2">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge rounded-pill" id="modalShowTarefaStatus"></span>
                        <span class="badge bg-light text-dark border" id="modalShowTarefaPrazo"></span>
                    </div>

                    <div class="mb-3">
                        <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Descrição</label>
                        <div class="p-3 bg-light rounded border-start border-4 border-primary" id="modalShowTarefaDescricao" style="white-space: pre-line;"></div>
                    </div>

                    {{-- Parecer do técnico: o resultado da tarefa, que antes ficava
                         guardado sem chegar a quem a pediu. --}}
                    <div class="mb-3 d-none" id="modalShowTarefaParecerBloco">
                        <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Parecer do técnico</label>
                        <div class="p-3 bg-success-subtle rounded border-start border-4 border-success" id="modalShowTarefaParecer" style="white-space: pre-line;"></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Solicitado por</label>
                            <div class="fw-medium text-dark" id="modalShowTarefaSolicitante"></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Destino</label>
                            <div class="fw-medium text-dark" id="modalShowTarefaDestino"></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="text-muted small text-uppercase fw-semibold d-block mb-1">Criado em</label>
                            <div class="fw-medium text-dark" id="modalShowTarefaCriado"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Visualizar OCR do Anexo -->
    <div class="modal fade" id="modalVerOcrAnexo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white border-bottom-0">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                        <i class="fas fa-file-alt"></i>
                        <span>Texto Extraído & Indexação OCR</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3 pb-3 border-bottom">
                        <div>
                            <label class="small text-muted text-uppercase fw-semibold d-block mb-1">Arquivo Anexo</label>
                            <div class="fw-bold text-dark fs-5" id="modalOcrAnexoNome"></div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1">
                            <span id="modalOcrStatusBadge" class="badge bg-secondary">Pendente</span>
                            <small class="text-muted" id="modalOcrMetodoInfo"></small>
                        </div>
                    </div>

                    <!-- Alerta de Erro / Falha -->
                    <div id="modalOcrErrorAlert" class="alert alert-danger d-none mb-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Falha no Processamento:</strong>
                                <span id="modalOcrErrorMsg" class="d-block small mt-1"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Estado Carregando -->
                    <div id="modalOcrLoading" class="text-center py-5 text-muted d-none">
                        <span class="spinner-border spinner-border-sm me-2 text-primary"></span> A carregar os dados de OCR...
                    </div>

                    <!-- Conteúdo de Texto Extraído -->
                    <div id="modalOcrContentContainer" class="position-relative">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="small text-muted text-uppercase fw-semibold mb-0">Conteúdo Extraído</label>
                            <span class="badge bg-light text-secondary border" id="modalOcrWordCount">0 palavras</span>
                        </div>
                        <div class="p-3 bg-light rounded border border-secondary border-opacity-10 position-relative" style="max-height: 45vh; overflow-y: auto;">
                            <button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 m-2" id="btnCopiarOcr" title="Copiar Texto">
                                <i class="far fa-copy"></i> Copiar
                            </button>
                            <pre id="modalOcrAnexoConteudo" style="white-space: pre-wrap; font-family: inherit; font-size: 0.9rem; margin-top: 1.5rem;" class="text-dark mb-0"></pre>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0 d-flex justify-content-between">
                    <div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnReprocessarOcr">
                            <i class="fas fa-sync-alt me-1"></i> Reprocessar OCR
                        </button>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    @include('documentos_entradas.partials.show._scripts')

    @if (config('app.feature_assistente') && auth()->user()?->can('assistente.usar'))
        @include('assistente._documento', [
            'docId' => $doc->id,
            'docTipo' => 'ENTRADA',
            'ctxRoute' => route('assistente.entrada', $doc),
            'ctxTitulo' => 'este documento de entrada',
        ])
    @endif
    @include('documentos_entradas.partials.modal-despacho', ['doc' => $doc, 'departamentos' => $departamentos])
@endsection
