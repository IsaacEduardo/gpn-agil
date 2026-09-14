{{--
    O documento é o objeto do ecrã. Antes era um link ("Visualizar") repetido em
    dois sítios — o banner do cartão de informações e o cartão da barra lateral.
    Passa a ocupar a coluna principal, com os anexos por baixo.
--}}
@php
    $temFicheiro = (bool) $doc->arquivo_caminho;
    $urlFicheiro = $temFicheiro ? route('documentos-entradas.arquivo.download', $doc) : null;
    $extensao = $temFicheiro ? strtolower(pathinfo($doc->arquivo_caminho, PATHINFO_EXTENSION)) : null;
    $ehImagem = in_array($extensao, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
@endphp

<div class="card shadow-sm border-0 rounded-3 mb-4 doc-viewer-card">
    <div class="card-header bg-white py-2 px-3 border-bottom d-flex align-items-center justify-content-between gap-2">
        <h2 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
            <i class="fas fa-file-alt text-primary"></i>
            <span>Documento Digitalizado</span>
        </h2>
        @if ($temFicheiro)
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

    <div class="card-body p-0">
        @if ($temFicheiro)
            @if ($ehImagem)
                <div class="doc-viewer-frame text-center bg-body-tertiary" id="docViewerFrame">
                    <img src="{{ $urlFicheiro }}" alt="Documento {{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }} digitalizado"
                         class="img-fluid">
                </div>
            @else
                <object data="{{ $urlFicheiro }}" type="application/pdf"
                        class="doc-viewer-frame w-100 d-block" id="docViewerFrame"
                        aria-label="Documento {{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }} digitalizado">
                    {{-- Navegadores sem visualizador de PDF embutido caem aqui. --}}
                    <div class="p-5 text-center text-muted">
                        <i class="fas fa-file-pdf fa-2x mb-3 text-danger opacity-50"></i>
                        <p class="mb-3">O seu navegador não mostra PDF embutido.</p>
                        <a href="{{ $urlFicheiro }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
                            <i class="fas fa-arrow-up-right-from-square me-1"></i> Abrir o documento
                        </a>
                    </div>
                </object>
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

@include('documentos_entradas.partials.show._anexos')
