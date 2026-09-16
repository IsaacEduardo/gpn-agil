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
        /* Regras Gerais & Reset de Margens para Impressão Térmica (100mm x 50mm) */
        @page {
            size: 100mm 50mm;
            margin: 0;
        }

        @media print {
            html, body {
                width: 100mm;
                height: 50mm;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden;
                background: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            .etiqueta-wrapper {
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
            }
        }

        body {
            background-color: #f8fafc;
            color: #000000;
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        /* Container Visualizador / Preview em Tela */
        .preview-screen-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* Etiqueta Térmica Adesiva Exacta (100mm x 50mm) */
        .etiqueta-wrapper {
            width: 100mm;
            height: 50mm;
            box-sizing: border-box;
            padding: 2.5mm 3.5mm;
            background: #ffffff;
            color: #000000;
            border: 1px solid #cbd5e1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        /* Topo: Insígnia e Identificação Institucional */
        .etiqueta-header {
            display: flex;
            align-items: center;
            gap: 2.5mm;
            border-bottom: 1px dashed #000000;
            padding-bottom: 1.2mm;
        }

        .etiqueta-logo {
            width: 9.5mm;
            height: 9.5mm;
            object-fit: contain;
            filter: grayscale(100%) contrast(200%);
        }

        .etiqueta-titulos {
            flex-grow: 1;
            line-height: 1.1;
        }

        .etiqueta-titulos .pais {
            font-size: 6.5pt;
            font-weight: 800;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .etiqueta-titulos .gov {
            font-size: 5.8pt;
            font-weight: 700;
            text-transform: uppercase;
        }

        .etiqueta-titulos .gabinete {
            font-size: 5.2pt;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* Corpo Central: Número do Protocolo & QR Code Side-by-Side */
        .etiqueta-body {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-grow: 1;
            padding: 1.2mm 0;
            gap: 2mm;
        }

        .etiqueta-info {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .etiqueta-rotulo {
            font-size: 6.5pt;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .etiqueta-numero {
            font-size: 16pt;
            font-weight: 900;
            line-height: 1;
            margin: 0.5mm 0;
            letter-spacing: -0.5px;
        }

        .etiqueta-meta {
            font-size: 5.8pt;
            line-height: 1.2;
            font-weight: 600;
        }

        .etiqueta-qrcode-container {
            width: 22mm;
            height: 22mm;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
        }

        .etiqueta-qrcode-container svg,
        .etiqueta-qrcode-container img {
            width: 22mm !important;
            height: 22mm !important;
        }

        /* Rodapé com Código de Autenticidade */
        .etiqueta-footer {
            border-top: 1px dashed #000000;
            padding-top: 1mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 5.5pt;
            font-weight: 700;
        }

        .etiqueta-codigo {
            font-family: 'Courier New', Courier, monospace;
            font-size: 6pt;
            font-weight: 800;
        }
    </style>
</head>
<body>

@php
    $dadosInstituicao = \App\Support\CabecalhoDocumento::instituicao();
    $gabLinha = \App\Support\CabecalhoDocumento::linhaGabinete(optional($documento->departamento)->gabinete, $dadosInstituicao);
@endphp

<div class="preview-screen-container">
    {{-- Barra de Ações (Visível apenas em tela) --}}
    <div class="no-print card shadow-sm border-0 mb-4 p-3 d-flex flex-row align-items-center justify-content-between gap-3 bg-white rounded-3" style="width: 100mm; max-width: 100%;">
        <div class="d-flex align-items-center gap-2">
            <i class="fas fa-barcode fs-4 text-success"></i>
            <div>
                <strong class="d-block text-dark small">Etiqueta Térmica Adesiva</strong>
                <span class="text-muted" style="font-size: 0.72rem;">Padrão: 100mm x 50mm</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button onclick="window.print()" class="btn btn-success btn-sm fw-bold px-3 shadow-sm">
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

    {{-- Etiqueta Térmica 100mm x 50mm --}}
    <div class="etiqueta-wrapper shadow-lg">
        {{-- Header --}}
        <div class="etiqueta-header">
            @if(!empty($dadosInstituicao->logo_url))
                <img src="{{ $dadosInstituicao->logo_url }}" class="etiqueta-logo" alt="Insígnia">
            @else
                <img src="{{ asset('images/insignia.png') }}" class="etiqueta-logo" alt="Insígnia" onerror="this.style.display='none'">
            @endif
            <div class="etiqueta-titulos">
                <div class="pais">{{ $dadosInstituicao->cabecalho_linha1 ?: 'REPÚBLICA DE ANGOLA' }}</div>
                <div class="gov">{{ $dadosInstituicao->cabecalho_linha2 ?: ($dadosInstituicao->nome_oficial ?: 'GOVERNO PROVINCIAL') }}</div>
                @if($gabLinha)
                    <div class="gabinete">{{ $gabLinha }}</div>
                @endif
            </div>
        </div>

        {{-- Body --}}
        <div class="etiqueta-body">
            <div class="etiqueta-info">
                <span class="etiqueta-rotulo">ENTRADA N.º / PROTOCOLO</span>
                <div class="etiqueta-numero">{{ sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia) }}</div>
                <div class="etiqueta-meta">
                    <div><strong>REGISTRO:</strong> {{ optional($documento->data_entrada ?? $protocolo->gerado_em)->format('d/m/Y H:i') }}</div>
                    <div><strong>PROCEDÊNCIA:</strong> {{ Str::limit($documento->procedencia ?? '—', 30) }}</div>
                </div>
            </div>

            <div class="etiqueta-qrcode-container">
                {!! $qrCodeSvg !!}
            </div>
        </div>

        {{-- Footer --}}
        <div class="etiqueta-footer">
            <span>AUTENTICIDADE: <span class="etiqueta-codigo">{{ $protocolo->codigo }}</span></span>
            <span>EDMS GPN-ÁGIL</span>
        </div>
    </div>
</div>

@if($autoPrint)
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 300);
        });

        // Carimbo de impressão. Quando a etiqueta é aberta pela página de
        // protocolo, quem marca é o markAsPrinted() de lá, pelo onclick. Chamada
        // diretamente — pelo iframe do registo ou pela ligação de recurso — não
        // há esse onclick, e o carimbo perdia-se.
        //
        // É um carimbo, não o essencial: se falhar, não interrompe nada.
        window.addEventListener('afterprint', function() {
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
            }).catch(function() { /* silencioso por desenho */ });
        }, { once: true });
    </script>
@endif

</body>
</html>
