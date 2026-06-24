@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5>Imprimir Ofício da Requisição</h5>
                <a href="{{ $redirectUrl ?? route('requisicoes.index') }}" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left"></i> Concluir
                </a>
            </div>
            <div class="card-body">
                <p>
                    O PDF foi gerado. Utilize os botões abaixo para visualizar ou imprimir o documento.
                </p>
                <div class="mb-3">
                    <a id="openPdfBtn" href="{{ $pdfUrl }}" target="_blank" class="btn btn-outline-primary me-2">
                        <i class="fas fa-file-pdf"></i> Abrir PDF em nova aba
                    </a>
                    <button id="printPdfBtn" type="button" class="btn btn-primary">
                        <i class="fas fa-print"></i> Imprimir agora
                    </button>
                </div>

                <iframe id="pdfFrame" src="{{ $pdfUrl }}"
                    style="width:100%;height:75vh;border:1px solid #ddd;border-radius:.25rem"></iframe>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            const pdfUrl = @json($pdfUrl);
            const frame = document.getElementById('pdfFrame');
            const printBtn = document.getElementById('printPdfBtn');
            const redirectUrl = @json($redirectUrl ?? route('requisicoes.index'));
            let redirected = false;

            function tryPrint() {
                try {
                    const w = frame && frame.contentWindow;
                    if (w) {
                        w.focus();
                        w.print();
                    } else {
                        // Fallback: abrir em nova aba para o usuário imprimir
                        window.open(pdfUrl, '_blank');
                    }
                } catch (e) {
                    // Fallback em caso de erro de política do navegador
                    window.open(pdfUrl, '_blank');
                }
            }

            function scheduleRedirect() {
                if (redirected) return;
                redirected = true;
                setTimeout(function() {
                    window.location.href = redirectUrl;
                }, 400);
            }

            // Tenta imprimir automaticamente quando o iframe carregar
            // if (frame) {
            //     frame.addEventListener('load', function() {
            //         // Pequeno atraso para garantir que o visualizador carregou
            //         // setTimeout(tryPrint, 400);
            //     });
            // }

            // Botão manual para imprimir
            printBtn && printBtn.addEventListener('click', tryPrint);

            try {
                window.addEventListener('afterprint', scheduleRedirect);
                if (frame && frame.contentWindow) {
                    frame.contentWindow.addEventListener('afterprint', scheduleRedirect);
                }
                var mm = window.matchMedia('print');
                if (mm) {
                    if (mm.addEventListener) {
                        mm.addEventListener('change', function(e) {
                            if (!e.matches) scheduleRedirect();
                        });
                    } else {
                        mm.onchange = function(e) {
                            if (!e.matches) scheduleRedirect();
                        };
                    }
                }
            } catch (_) {}

            // Tenta automaticamente mesmo sem evento de load (alguns navegadores)
            // setTimeout(tryPrint, 1200);
            // setTimeout(function() {
            //    if (!redirected) scheduleRedirect();
            // }, 6000);
        })();
    </script>
@endpush
