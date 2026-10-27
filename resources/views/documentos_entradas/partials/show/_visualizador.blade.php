{{--
    O documento é o objeto do ecrã.
    Visualizador de PDF (Mozilla PDF.js) e de imagens. Abre no ficheiro
    principal e troca para qualquer outro anexo sem recarregar a página.
--}}
@php
    // Só PDF e imagens se pré-visualizam; o resto fica na lista para descarregar.
    $tipoParaVisualizar = function (?string $mime, ?string $caminho): ?string {
        $mime = strtolower((string) $mime);
        if ($mime === 'application/pdf') {
            return 'pdf';
        }
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return 'imagem';
        }
        $extensao = strtolower(pathinfo((string) $caminho, PATHINFO_EXTENSION));
        if ($extensao === 'pdf') {
            return 'pdf';
        }

        return in_array($extensao, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? 'imagem' : null;
    };

    // Sequência de visualização: o principal primeiro, depois os anexos por
    // `ordem`. Cada ficheiro físico entra uma vez — o 1.º anexo do registo é o
    // próprio principal, e a edição pode deixar dois anexos no mesmo caminho.
    $ficheirosVisualizaveis = [];
    $indicePorCaminho = [];
    $anexoPrincipal = $doc->arquivo_caminho
        ? $doc->anexos->firstWhere('caminho_arquivo', $doc->arquivo_caminho)
        : null;

    if ($doc->arquivo_caminho && ($tipo = $tipoParaVisualizar($anexoPrincipal?->mime_type, $doc->arquivo_caminho))) {
        $indicePorCaminho[$doc->arquivo_caminho] = count($ficheirosVisualizaveis);
        $ficheirosVisualizaveis[] = [
            'url' => route('documentos-entradas.arquivo.download', $doc),
            'tipo' => $tipo,
            'nome' => $anexoPrincipal?->nome_original ?? 'Ficheiro principal',
        ];
    }

    foreach ($doc->anexos as $an) {
        if (! $an->caminho_arquivo || isset($indicePorCaminho[$an->caminho_arquivo])) {
            continue;
        }
        $tipo = $tipoParaVisualizar($an->mime_type, $an->caminho_arquivo);
        if (! $tipo) {
            continue;
        }
        $indicePorCaminho[$an->caminho_arquivo] = count($ficheirosVisualizaveis);
        $ficheirosVisualizaveis[] = [
            'url' => route('documentos-entradas.anexos.download', [$doc, $an]),
            'tipo' => $tipo,
            'nome' => $an->nome_original ?? basename($an->caminho_arquivo),
        ];
    }

    $temFicheiro = count($ficheirosVisualizaveis) > 0;
    $temPdf = collect($ficheirosVisualizaveis)->contains('tipo', 'pdf');
    $varios = count($ficheirosVisualizaveis) > 1;
    $inicial = $ficheirosVisualizaveis[0] ?? null;
    // Prefixo dos IDs: o script recebe-o por configuração em vez de procurar IDs fixos.
    $visor = 'docViewer_'.$doc->id;
@endphp

<div class="card shadow-sm border-0 rounded-3 mb-4 doc-viewer-card" id="{{ $visor }}">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h2 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2 text-truncate">
            <i class="fas fa-file-alt text-primary"></i>
            <span>Documento Digitalizado</span>
            @if ($temFicheiro)
                <span class="fw-normal text-muted small text-truncate" id="{{ $visor }}_nome"
                      title="{{ $inicial['nome'] }}">{{ $inicial['nome'] }}</span>
            @endif
        </h2>

        @if ($temFicheiro)
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if ($varios)
                    {{-- Ficheiros do documento --}}
                    <div class="btn-group btn-group-sm" role="group" aria-label="Ficheiros do documento">
                        <button type="button" class="btn btn-outline-primary" id="{{ $visor }}_fichAnterior" title="Ficheiro anterior" disabled>
                            <i class="fas fa-backward-step"></i><span class="d-none d-md-inline ms-1">Anterior</span>
                        </button>
                        <span class="btn btn-outline-primary disabled text-dark fw-semibold px-2" id="{{ $visor }}_fichPosicao" aria-live="polite">
                            1 de {{ count($ficheirosVisualizaveis) }}
                        </span>
                        <button type="button" class="btn btn-outline-primary" id="{{ $visor }}_fichSeguinte" title="Ficheiro seguinte">
                            <span class="d-none d-md-inline me-1">Seguinte</span><i class="fas fa-forward-step"></i>
                        </button>
                    </div>
                @endif

                {{-- Páginas e zoom: só para PDF; o script mostra-os conforme o ficheiro --}}
                <div class="d-flex align-items-center gap-2 flex-wrap {{ $inicial['tipo'] === 'pdf' ? '' : 'd-none' }}" id="{{ $visor }}_controlosPdf">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Páginas">
                        <button type="button" class="btn btn-outline-secondary" id="{{ $visor }}_paginaAnterior" title="Página Anterior">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span class="btn btn-outline-secondary disabled text-dark fw-bold px-2">
                            <span id="{{ $visor }}_pagina">1</span> / <span id="{{ $visor }}_paginas">-</span>
                        </span>
                        <button type="button" class="btn btn-outline-secondary" id="{{ $visor }}_paginaSeguinte" title="Próxima Página">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>

                    <div class="btn-group btn-group-sm" role="group" aria-label="Zoom">
                        <button type="button" class="btn btn-outline-secondary" id="{{ $visor }}_zoomOut" title="Reduzir Zoom">
                            <i class="fas fa-search-minus"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="{{ $visor }}_zoomReset" title="Zoom Original (100%)">
                            <span id="{{ $visor }}_zoomNivel">100%</span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="{{ $visor }}_zoomIn" title="Aumentar Zoom">
                            <i class="fas fa-search-plus"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="{{ $visor }}_expandir"
                            aria-pressed="false" aria-label="Ver em ecrã inteiro" title="Ver em ecrã inteiro">
                        <i class="fas fa-up-right-and-down-left-from-center"></i>
                        <span class="d-none d-md-inline ms-1">Expandir</span>
                    </button>
                    <a href="{{ $inicial['url'] }}" id="{{ $visor }}_abrir" target="_blank" rel="noopener"
                       class="btn btn-sm btn-outline-primary" title="Abrir em separador novo">
                        <i class="fas fa-arrow-up-right-from-square"></i>
                        <span class="d-none d-md-inline ms-1">Abrir</span>
                    </a>
                </div>
            </div>
        @endif
    </div>

    <div class="card-body p-0 position-relative">
        @if ($temFicheiro)
            {{-- Aviso de que um ficheiro não abriu e se passou ao seguinte --}}
            <div class="alert alert-warning small rounded-0 border-0 border-bottom mb-0 py-2 px-3 d-none"
                 id="{{ $visor }}_aviso" role="status"></div>

            {{-- A altura vem de .doc-viewer-frame em _estilos. Limites escritos
                 aqui sobrepunham-se ao CSS e anulavam o ecrã inteiro. --}}
            <div class="doc-viewer-frame w-100 bg-secondary-subtle overflow-auto text-center p-3" id="{{ $visor }}_frame">
                <div id="{{ $visor }}_carregando" class="py-5 text-center text-muted">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 2.5rem; height: 2.5rem;">
                        <span class="visually-hidden">A carregar documento...</span>
                    </div>
                    <p class="fw-semibold mb-0">A carregar documento...</p>
                </div>

                <canvas id="{{ $visor }}_canvas" class="shadow rounded d-none mx-auto bg-white"></canvas>

                <img id="{{ $visor }}_imagem" class="img-fluid d-none" alt="">

                <div id="{{ $visor }}_erro" class="p-5 text-center text-muted d-none">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3 text-warning"></i>
                    <p class="mb-3">Não foi possível carregar a pré-visualização inline do documento.</p>
                    <a href="{{ $inicial['url'] }}" id="{{ $visor }}_erroLink" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
                        <i class="fas fa-arrow-up-right-from-square me-1"></i> Abrir / Descarregar documento
                    </a>
                </div>
            </div>
        @else
            <div class="p-5 text-center text-muted">
                <i class="far fa-file fa-2x mb-3 opacity-50"></i>
                <p class="fw-semibold mb-1">Sem documento digitalizado</p>
                <p class="small mb-0">Este registo não tem ficheiro principal associado.</p>
            </div>
        @endif
    </div>
</div>

@if ($temFicheiro)
    @include('documentos_entradas.partials.show._visualizador-script')
@endif

@include('documentos_entradas.partials.show._anexos')
