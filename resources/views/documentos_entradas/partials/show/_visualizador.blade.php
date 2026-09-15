{{--
    O documento é o objeto do ecrã.
    Visualizador universal de PDF baseado em Mozilla PDF.js.
    Garante renderização inline em 100% dos navegadores (desktop e mobile).
--}}
@php
    $temFicheiro = (bool) $doc->arquivo_caminho;
    $urlFicheiro = $temFicheiro ? route('documentos-entradas.arquivo.download', $doc) : null;
    $extensao = $temFicheiro ? strtolower(pathinfo($doc->arquivo_caminho, PATHINFO_EXTENSION)) : null;
    $ehImagem = in_array($extensao, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
@endphp

<div class="card shadow-sm border-0 rounded-3 mb-4 doc-viewer-card">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h2 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
            <i class="fas fa-file-alt text-primary"></i>
            <span>Documento Digitalizado</span>
        </h2>

        @if ($temFicheiro && !$ehImagem)
            {{-- Controles do PDF.js (Navegação & Zoom) --}}
            <div class="d-flex align-items-center gap-2 flex-wrap" id="pdfControls">
                {{-- Páginas --}}
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" id="pdfPrevPage" title="Página Anterior">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <span class="btn btn-outline-secondary disabled text-dark fw-bold px-2" id="pdfPageIndicator">
                        <span id="pdfPageNum">1</span> / <span id="pdfPageCount">-</span>
                    </span>
                    <button type="button" class="btn btn-outline-secondary" id="pdfNextPage" title="Próxima Página">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                {{-- Zoom --}}
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" id="pdfZoomOut" title="Reduzir Zoom">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="pdfZoomReset" title="Zoom Original (100%)">
                        <span id="pdfZoomLevel">100%</span>
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="pdfZoomIn" title="Aumentar Zoom">
                        <i class="fas fa-search-plus"></i>
                    </button>
                </div>

                {{-- Ações Gerais --}}
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary doc-viewer-zoom" data-alvo="docViewerFrame"
                            title="Aumentar a altura do visualizador">
                        <i class="fas fa-up-right-and-down-left-from-center"></i>
                        <span class="d-none d-md-inline ms-1">Expandir</span>
                    </button>
                    <a href="{{ $urlFicheiro }}" target="_blank" rel="noopener"
                       class="btn btn-sm btn-outline-primary" title="Abrir em separador novo">
                        <i class="fas fa-arrow-up-right-from-square"></i>
                        <span class="d-none d-md-inline ms-1">Abrir</span>
                    </a>
                </div>
            </div>
        @elseif ($temFicheiro && $ehImagem)
            <div class="d-flex align-items-center gap-1">
                <button type="button" class="btn btn-sm btn-outline-secondary doc-viewer-zoom" data-alvo="docViewerFrame"
                        title="Aumentar a altura do visualizador">
                    <i class="fas fa-up-right-and-down-left-from-center"></i>
                    <span class="d-none d-md-inline ms-1">Expandir</span>
                </button>
                <a href="{{ $urlFicheiro }}" target="_blank" rel="noopener"
                   class="btn btn-sm btn-outline-primary" title="Abrir em separador novo">
                    <i class="fas fa-arrow-up-right-from-square"></i>
                    <span class="d-none d-md-inline ms-1">Abrir</span>
                </a>
            </div>
        @endif
    </div>

    <div class="card-body p-0 position-relative">
        @if ($temFicheiro)
            @if ($ehImagem)
                <div class="doc-viewer-frame text-center bg-body-tertiary" id="docViewerFrame">
                    <img src="{{ $urlFicheiro }}" alt="Documento {{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }} digitalizado"
                         class="img-fluid">
                </div>
            @else
                {{-- Container de Renderização PDF.js --}}
                <div class="doc-viewer-frame w-100 bg-secondary-subtle overflow-auto text-center p-3" id="docViewerFrame" style="min-height: 550px; max-height: 750px;">
                    {{-- Spinner de Carregamento --}}
                    <div id="pdfLoadingSpinner" class="py-5 text-center text-muted">
                        <div class="spinner-border text-primary mb-3" role="status" style="width: 2.5rem; height: 2.5rem;">
                            <span class="visually-hidden">A carregar PDF...</span>
                        </div>
                        <p class="fw-semibold mb-0">A carregar documento...</p>
                    </div>

                    {{-- Canvas principal de renderização --}}
                    <canvas id="pdfRenderCanvas" class="shadow rounded d-none mx-auto bg-white"></canvas>

                    {{-- Mensagem de Erro / Fallback em caso de indisponibilidade --}}
                    <div id="pdfFallback" class="p-5 text-center text-muted d-none">
                        <i class="fas fa-exclamation-triangle fa-2x mb-3 text-warning"></i>
                        <p class="mb-3">Não foi possível carregar a pré-visualização inline do documento.</p>
                        <a href="{{ $urlFicheiro }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
                            <i class="fas fa-arrow-up-right-from-square me-1"></i> Abrir / Descarregar documento
                        </a>
                    </div>
                </div>
            @endif
        @else
            <div class="p-5 text-center text-muted">
                <i class="far fa-file fa-2x mb-3 opacity-50"></i>
                <p class="fw-semibold mb-1">Sem documento digitalizado</p>
                <p class="small mb-0">Este registo não tem ficheiro principal associado.</p>
            </div>
        @endif
    </div>
</div>

@if ($temFicheiro && !$ehImagem)
    {{-- Carregamento do PDF.js CDN da Mozilla --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof pdfjsLib === 'undefined') {
                document.getElementById('pdfLoadingSpinner')?.classList.add('d-none');
                document.getElementById('pdfFallback')?.classList.remove('d-none');
                return;
            }

            // Define o Worker do PDF.js
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

            const pdfUrl = @json($urlFicheiro);
            let pdfDoc = null;
            let pageNum = 1;
            let pageRendering = false;
            let pageNumPending = null;
            let scale = 1.25;

            const canvas = document.getElementById('pdfRenderCanvas');
            const ctx = canvas ? canvas.getContext('2d') : null;
            const spinner = document.getElementById('pdfLoadingSpinner');
            const fallback = document.getElementById('pdfFallback');

            if (!canvas || !ctx) return;

            /**
             * Renderiza uma página específica no Canvas
             */
            function renderPage(num) {
                pageRendering = true;

                pdfDoc.getPage(num).then(function (page) {
                    const viewport = page.getViewport({ scale: scale });

                    // Ajusta resoluções HD para telas com alta densidade de píxeis (Retina/Mobile)
                    const outputScale = window.devicePixelRatio || 1;

                    canvas.width = Math.floor(viewport.width * outputScale);
                    canvas.height = Math.floor(viewport.height * outputScale);
                    canvas.style.width = Math.floor(viewport.width) + "px";
                    canvas.style.height = Math.floor(viewport.height) + "px";

                    const transform = outputScale !== 1
                        ? [outputScale, 0, 0, outputScale, 0, 0]
                        : null;

                    const renderContext = {
                        canvasContext: ctx,
                        transform: transform,
                        viewport: viewport
                    };

                    const renderTask = page.render(renderContext);

                    renderTask.promise.then(function () {
                        pageRendering = false;

                        if (pageNumPending !== null) {
                            renderPage(pageNumPending);
                            pageNumPending = null;
                        }
                    });
                }).catch(function(err) {
                    console.error('Erro ao renderizar página:', err);
                });

                document.getElementById('pdfPageNum').textContent = num;
            }

            /**
             * Adiciona à fila a renderização de uma página
             */
            function queueRenderPage(num) {
                if (pageRendering) {
                    pageNumPending = num;
                } else {
                    renderPage(num);
                }
            }

            /**
             * Página Anterior
             */
            document.getElementById('pdfPrevPage')?.addEventListener('click', function () {
                if (pageNum <= 1) return;
                pageNum--;
                queueRenderPage(pageNum);
            });

            /**
             * Próxima Página
             */
            document.getElementById('pdfNextPage')?.addEventListener('click', function () {
                if (!pdfDoc || pageNum >= pdfDoc.numPages) return;
                pageNum++;
                queueRenderPage(pageNum);
            });

            /**
             * Controles de Zoom
             */
            document.getElementById('pdfZoomIn')?.addEventListener('click', function () {
                if (scale >= 3.0) return;
                scale += 0.25;
                updateZoomIndicator();
                queueRenderPage(pageNum);
            });

            document.getElementById('pdfZoomOut')?.addEventListener('click', function () {
                if (scale <= 0.5) return;
                scale -= 0.25;
                updateZoomIndicator();
                queueRenderPage(pageNum);
            });

            document.getElementById('pdfZoomReset')?.addEventListener('click', function () {
                scale = 1.25;
                updateZoomIndicator();
                queueRenderPage(pageNum);
            });

            function updateZoomIndicator() {
                const percent = Math.round((scale / 1.25) * 100);
                const zoomEl = document.getElementById('pdfZoomLevel');
                if (zoomEl) zoomEl.textContent = `${percent}%`;
            }

            /**
             * Carregamento assíncrono do documento PDF
             */
            pdfjsLib.getDocument(pdfUrl).promise.then(function (pdfDoc_) {
                pdfDoc = pdfDoc_;
                document.getElementById('pdfPageCount').textContent = pdfDoc.numPages;

                spinner?.classList.add('d-none');
                canvas?.classList.remove('d-none');

                renderPage(pageNum);
            }).catch(function (reason) {
                console.error('Erro no carregamento do PDF.js:', reason);
                spinner?.classList.add('d-none');
                fallback?.classList.remove('d-none');
            });
        });
    </script>
@endif

@include('documentos_entradas.partials.show._anexos')
