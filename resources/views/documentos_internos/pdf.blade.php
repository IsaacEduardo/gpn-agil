<!DOCTYPE html>
<html lang="pt-ao">

<head>
    <meta charset="UTF-8">
    <title>Documento {{ $documentoInterno->numero_referencia }}</title>
    <style>
        @page {
            margin: 20mm 20mm 35mm 30mm; /* Margens oficiais: Superior 2cm, Direita 2cm, Inferior 3.5cm, Esquerda 3cm */
            size: A4;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000;
        }

        .paper-container {
            width: 100%;
            position: relative;
        }

        .text-center {
            text-align: center;
        }

        .mb-4 {
            margin-bottom: 1.5rem;
        }

        .paper-content {
            min-height: auto;
        }

        .paper-footer {
            position: fixed;
            bottom: -30mm; /* Posicionado dentro da margem inferior de 35mm */
            left: 0;
            width: 100%;
            height: 30mm;
            z-index: 999;
        }

        img {
            max-width: 100%;
            height: auto;
            page-break-inside: avoid;
        }

        /* Gestão de tabelas e quebras de página */
        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        td,
        th {
            vertical-align: top;
        }

        .page-break {
            page-break-after: always;
            clear: both;
        }

        .no-break {
            page-break-inside: avoid;
        }

        /* Numeração de páginas dinâmica */
        .page-counter::after {
            content: counter(page);
        }

        .total-pages::after {
            content: counter(pages);
        }
    </style>
</head>

<body>
    @php
        function base64_encode_image($filename)
        {
            if ($filename && file_exists($filename)) {
                $type = pathinfo($filename, PATHINFO_EXTENSION);
                $data = file_get_contents($filename);
                $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                return $base64;
            }
            return null;
        }
    @endphp

    <div class="paper-container">
        <!-- Header / Insignia -->
        <div class="text-center mb-4">
            @if ($logoPath = $dadosInstituicao->logo_absolute_path)
                <img src="{{ base64_encode_image($logoPath) }}" alt="Insígnia"
                    style="width: 22mm; height: auto;">
            @endif
            <div style="margin-top: 10px; font-family: 'Times New Roman', serif; font-size: 12pt; font-weight: bold; text-transform: uppercase;">
                {{ $dadosInstituicao->cabecalho_linha1 }}<br>
                {{ $dadosInstituicao->cabecalho_linha2 }}<br>
                @if($dadosInstituicao->cabecalho_linha3)
                    {{ $dadosInstituicao->cabecalho_linha3 }}<br>
                @endif
                {{ mb_strtoupper($documentoInterno->departamento->gabinete->nome ?? ($documentoInterno->departamento->nome ?? 'GABINETE NÃO DEFINIDO')) }}
            </div>
        </div>

        <!-- Content -->
        <div class="paper-content">
            {!! \App\Support\Sanitizer::clean($documentoInterno->conteudo_final) !!}
        </div>

        <!-- Footer -->
        <div class="paper-footer">
            @if ($documentoInterno->assinado_em)
                <table style="width: 100%; border: none; background-color: #f8f9fa; border-top: 1px solid #dee2e6; margin-bottom: 3px; font-family: sans-serif; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 4px 10px; font-size: 7.5pt; color: #6c757d; text-align: left; vertical-align: middle; border: none; line-height: 1.3;">
                            <strong>Assinado Digitalmente por:</strong>
                            {{ $documentoInterno->assinadoPor->name ?? 'Desconhecido' }}<br>
                            <strong>em:</strong> {{ $documentoInterno->assinado_em->format('d/m/Y H:i:s') }} | 
                            <strong>Hash:</strong> <span style="font-family: monospace; font-size: 6.5pt;">{{ $documentoInterno->assinatura_hash }}</span>
                        </td>
                        <td style="width: 20mm; text-align: right; padding: 4px 10px 4px 4px; vertical-align: middle; border: none;">
                            <img src="data:image/svg+xml;base64,{{ base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(70)->margin(0)->generate(route('documentos-internos.verificar', $documentoInterno->assinatura_hash))) }}" style="width: 16mm; height: 16mm; display: block; float: right; border: none;">
                        </td>
                    </tr>
                </table>
            @endif

            @php
                $rodapeImg = $dadosInstituicao->rodape_absolute_path;
                if (!$rodapeImg) {
                    $rodapeCandidates = [
                        'rodape_estacionario.png',
                        'rodape_estacionario.jpg',
                        'Estacionariodoc.jpg',
                        'Estacionariodoc.jpeg',
                        'estacionario.png',
                        'estacionario.jpg',
                    ];
                    foreach ($rodapeCandidates as $candidate) {
                        if (file_exists(public_path('images/' . $candidate))) {
                            $rodapeImg = public_path('images/' . $candidate);
                            break;
                        }
                    }
                }
            @endphp

            @if ($rodapeImg)
                <img src="{{ base64_encode_image($rodapeImg) }}" alt="Rodapé" style="width: 100%; height: auto; max-height: 12mm; display: block; margin: 0 auto;">
            @elseif ($dadosInstituicao->rodape_texto)
                <div style="text-align: center; font-size: 8pt; color: #6c757d; font-family: sans-serif; border-top: 1px solid #dee2e6; padding-top: 3px; line-height: 1.2;">
                    {{ $dadosInstituicao->rodape_texto }}
                </div>
            @endif

            <div style="text-align: right; font-size: 8pt; color: #6c757d; margin-top: 3px; font-family: sans-serif;">
                Página <span class="page-counter"></span> de <span class="total-pages"></span>
            </div>
        </div>
    </div>
</body>

</html>
