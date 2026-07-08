<!DOCTYPE html>
<html lang="pt-BR">

<head>
    @php
        $insigniaSrc = $dadosInstituicao->logo_absolute_path;
        $footerImg = '';
        $rodapePath = $dadosInstituicao->rodape_absolute_path;
        if (!$rodapePath) {
            $rodapeCandidates = [
                public_path('images/rodape_estacionario.png'),
                public_path('images/rodape_estacionario.jpg'),
                public_path('images/Estacionariodoc.jpg'),
                public_path('images/Estacionariodoc.png'),
                public_path('images/estacionario.png'),
                public_path('images/estacionario.jpg'),
            ];
            foreach ($rodapeCandidates as $candidate) {
                if (file_exists($candidate)) {
                    $rodapePath = $candidate;
                    break;
                }
            }
        }
        if ($rodapePath && file_exists($rodapePath)) {
            $mime = mime_content_type($rodapePath);
            if (in_array($mime, ['image/png', 'image/jpeg', 'image/jpg'])) {
                $footerImg = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($rodapePath));
            }
        }

        $viatura = $viaturas->first();
        \Carbon\Carbon::setLocale('pt_BR');
    @endphp
    <meta charset="UTF-8">
    <title>Credencial de Viatura</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            /* Adjusted to match image density */
            color: #000;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .insignia {
            width: 20mm;
            /* Slightly smaller to match */
            height: auto;
            margin-bottom: 5px;
        }

        .republica {
            font-weight: bold;
            font-size: 11pt;
            text-transform: uppercase;
        }

        .dashed-line {
            margin: 2px 0;
            font-weight: normal;
        }

        .governo {
            font-size: 13pt;
        }

        .secretaria {
            font-weight: bold;
            font-size: 10pt;
            text-transform: uppercase;
            margin-top: 2px;
            letter-spacing: 1px;
        }

        .doc-title {
            text-align: center;
            font-weight: bold;
            font-size: 16pt;
            text-transform: uppercase;
            margin: 50px 0;
        }

        .content {
            text-align: justify;
            margin-bottom: 10px;
            line-height: 1.8;
            font-size: 12pt;
        }

        .separator {
            width: 100%;
            overflow-wrap: break-word;
            word-wrap: break-word;
            margin: 10px 0 20px 0;
            font-family: monospace;
            font-size: 12pt;
            line-height: 1.2;
            letter-spacing: -1px;
        }

        .footer-text {
            text-align: justify;
            margin-bottom: 40px;
            font-size: 12pt;
            line-height: 1.6;
        }

        .date-location {
            font-size: 12pt;
            margin-bottom: 60px;
        }

        .date-location strong {
            font-weight: bold;
            text-transform: uppercase;
        }

        .signature {
            text-align: center;
            margin-top: 60px;
        }

        .signature-title {
            margin-bottom: 60px;
            font-size: 12pt;
        }

        .signature-name {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12pt;
        }

        .pdf-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            text-align: center;
            width: 100%;
        }

        .pdf-footer img {
            width: 100%;
            height: auto;
            max-height: 120px;
        }
    </style>
</head>

<body>
    <div class="header">
        <img class="insignia" src="{{ $insigniaSrc }}" alt="Insígnia">
        <div class="republica">{{ $dadosInstituicao->cabecalho_linha1 }}</div>
        <div class="dashed-line">------.........------</div>
        <div class="governo">{{ $dadosInstituicao->cabecalho_linha2 }}</div>
        @php($gabineteCred = \App\Support\CabecalhoDocumento::linhaGabineteDeUser(auth()->user()))
        @if($gabineteCred)
            <div class="secretaria">{{ $gabineteCred }}</div>
        @endif
    </div>

    <div class="doc-title">CREDENCIAL</div>

    <div class="content">
        Está devidamente credenciado por este Governo Provincial, o (a) Sr(a).
        <strong>{{ $termo->beneficiario_nome }}</strong>, {{ $termo->beneficiario_setor }}, portadora do BI n.º
        {{ $termo->beneficiario_documento }}, passado pelo {{ $termo->beneficiario_documento_emitido_local }} aos
        {{ \Carbon\Carbon::parse($termo->beneficiario_documento_emitido_em)->translatedFormat('d \d\e F \d\e Y') }}, a
        circular com a Viatura de marca {{ $viatura->marca ?? 'N/A' }} {{ $viatura->modelo ?? '' }},
        <strong>{{ $viatura->placa ?? 'N/A' }}</strong>, dentro e fora das localidades, incluindo aos finais de semana.
    </div>

    <div class="separator">
        ================================================================================================================================================================================================================================================================
    </div>

    <div class="footer-text">
        E, para que não haja impedimentos na sua circulação, passou-se a presente credencial que vai por mim devidamente
        assinada e autenticada com o carimbo a óleo em uso neste Governo Provincial.
    </div>

    <div class="date-location">
        <strong>{{ mb_strtoupper($dadosInstituicao->cabecalho_linha3 ?? 'SECRETARIA GERAL', 'UTF-8') }} DO {{ mb_strtoupper($dadosInstituicao->cabecalho_linha2, 'UTF-8') }}</strong>, em {{ $dadosInstituicao->cidade }} aos
        {{ now()->translatedFormat('d \d\e F \d\e Y') }}.
    </div>

    <div class="signature">
        <div class="signature-title">O Secretário Geral</div>
        <div class="signature-name">CARLOS NELSON DUARTE DA SILVA</div>
    </div>

    @if ($footerImg)
        <div class="pdf-footer">
            <img src="{{ $footerImg }}" alt="Rodapé">
        </div>
    @endif
</body>

</html>
