<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Etiqueta de Protocolo N.º {{ sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia) }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background-color: #f8fafc; }
        .etiqueta-painel { width: 520px; max-width: 100%; }
        .etiqueta-pdf {
            display: block;
            width: 100%;
            aspect-ratio: {{ $formato->larguraMm }} / {{ $formato->alturaMm }};
            border: 1px solid #cbd5e1;
            background: #fff;
        }
    </style>
</head>
<body>

@php
    $pdfUrl = route('documentos-entradas.protocolo.etiqueta.pdf', $documento);
@endphp

<div class="d-flex flex-column align-items-center justify-content-center min-vh-100 p-3">
    <div class="etiqueta-painel card shadow-sm border-0 mb-3 p-3 bg-white rounded-3">
        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-barcode fs-4 text-success"></i>
                <div>
                    <strong class="d-block text-dark small">Etiqueta Térmica Adesiva</strong>
                    <span class="text-muted" style="font-size: 0.72rem;">Formato: {{ $formato->rotulo }}</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" id="btn-imprimir" class="btn btn-success btn-sm fw-bold px-3 shadow-sm">
                    <i class="fas fa-print me-1"></i> Imprimir
                </button>
                <a href="{{ route('documentos-entradas.protocolo.pdf', $documento) }}" target="_blank" class="btn btn-outline-primary btn-sm fw-semibold" title="Ver Recibo A4">
                    <i class="fas fa-file-pdf me-1"></i> Recibo A4
                </a>
                <a href="{{ route('documentos-entradas.show', $documento) }}" class="btn btn-outline-secondary btn-sm" title="Voltar">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        </div>

        {{-- Saída para quando o browser não consegue imprimir o PDF daqui
             (leitor de PDF desligado, PDF tratado como download). --}}
        <div id="aviso-sem-impressao" class="alert alert-warning small mt-3 mb-0 d-none" role="alert">
            <i class="fas fa-exclamation-triangle me-1"></i>
            O browser não abriu a impressão automaticamente.
            <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="fw-semibold">Abrir a etiqueta em PDF</a>
            e imprima a partir daí (Ctrl+P).
        </div>

        <div class="text-muted mt-3" style="font-size: 0.72rem;">
            <i class="fas fa-info-circle me-1"></i>
            Na impressora: papel {{ (int) $formato->larguraMm }} × {{ (int) $formato->alturaMm }} mm, orientação Paisagem.
            No diálogo: Margens <strong>Nenhuma</strong>, Escala <strong>100% / Tamanho real</strong>.
        </div>
    </div>

    <div class="etiqueta-painel">
        {{-- O src é posto pelo script, depois de ligar o 'load': um PDF em cache
             podia acabar de carregar antes de haver quem o ouvisse. --}}
        <iframe id="etiqueta-pdf" class="etiqueta-pdf" title="Etiqueta do protocolo em PDF"
                data-src="{{ $pdfUrl }}#toolbar=0&navpanes=0&view=Fit"></iframe>
    </div>
</div>

<script>
    (function () {
        var frame = document.getElementById('etiqueta-pdf');
        var aviso = document.getElementById('aviso-sem-impressao');
        var autoPrint = @json($autoPrint);

        // Carimbo de impressão. O 'afterprint' não dispara quando quem imprime é
        // o leitor de PDF dentro do iframe, por isso carimba-se ao pedir o
        // diálogo: o carimbo diz "enviado para impressão". É um carimbo, não o
        // essencial — se falhar, não interrompe nada.
        function carimbar() {
            var token = document.querySelector('meta[name="csrf-token"]');
            if (!token) return;

            fetch(@json(route('documentos-entradas.protocolo.impresso', $documento)), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': token.getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: new URLSearchParams({ _method: 'PATCH' }),
            }).catch(function () { /* silencioso por desenho */ });
        }

        // Imprime o PDF, não esta página: o diálogo recebe o PDF com o tamanho
        // real da etiqueta e o utilizador só escolhe a impressora.
        function imprimir() {
            if (navigator.pdfViewerEnabled === false) {
                aviso.classList.remove('d-none');
                return;
            }

            try {
                carimbar();
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (e) {
                aviso.classList.remove('d-none');
            }
        }

        document.getElementById('btn-imprimir').addEventListener('click', imprimir);

        if (autoPrint) {
            frame.addEventListener('load', function () {
                // Folga para o leitor de PDF acabar de desenhar a página.
                setTimeout(imprimir, 400);
            }, { once: true });
        }

        frame.src = frame.getAttribute('data-src');
    })();
</script>

</body>
</html>
