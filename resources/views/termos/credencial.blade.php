<!DOCTYPE html>
<html lang="pt-AO">

<head>
    @php
        $insigniaSrc = '';
        if (isset($dadosInstituicao) && $dadosInstituicao->logo_absolute_path && file_exists($dadosInstituicao->logo_absolute_path)) {
            $mime = mime_content_type($dadosInstituicao->logo_absolute_path);
            $insigniaSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($dadosInstituicao->logo_absolute_path));
        }

        $footerImg = '';
        $rodapePath = optional($dadosInstituicao ?? null)->rodape_absolute_path;
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

        $viaturaObj = $viatura ?? (isset($viaturas) ? $viaturas->first() : null);
        $tipoCredencial = $termo->tipo_credencial ?? 'utilizacao_normal';
        $userSignatario = auth()->user();
        $responsavel = $userSignatario?->departamento?->gabinete?->responsavel
            ?? $userSignatario?->departamento?->gabinete?->superChefe
            ?? $userSignatario?->departamento?->responsavel
            ?? $userSignatario;
        
        $nomeSecretarioGeral = $termo->nome_signatario ?? ($responsavel?->name ?? 'Dr. Anselmo Cristiano José Vasco');
        $cargoSecretarioGeral = $termo->cargo_signatario ?? 'O Secretário Geral';

        // URL para QR Code de Autenticidade
        $verifyUrl = url('/documentos-internos/verificar/' . md5(($termo->id ?? rand()) . ($termo->beneficiario_nome ?? '')));
        try {
            $qrSvgBase64 = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(65)->margin(0)->generate($verifyUrl));
        } catch (\Exception $e) {
            $qrSvgBase64 = null;
        }
    @endphp
    <meta charset="UTF-8">
    <title>Credencial Oficial de Viatura</title>
    <style>
        @page {
            margin: 20mm 20mm 35mm 25mm;
            size: A4;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            color: #000;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .header-wrapper {
            position: relative;
            width: 100%;
            text-align: center;
            margin-bottom: 35px;
        }

        .qr-header {
            position: absolute;
            top: 0;
            right: 0;
            text-align: center;
            width: 75px;
        }

        .insignia {
            width: 22mm;
            height: auto;
            margin-bottom: 6px;
        }

        .republica {
            font-weight: bold;
            font-size: 11pt;
            text-transform: uppercase;
        }

        .governo {
            font-weight: bold;
            font-size: 13pt;
            text-transform: uppercase;
            margin-top: 3px;
        }

        .secretaria {
            font-weight: bold;
            font-size: 11pt;
            text-transform: uppercase;
            margin-top: 3px;
            letter-spacing: 0.5px;
        }

        .doc-title {
            text-align: center;
            font-weight: bold;
            font-size: 18pt;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 40px 0 35px 0;
        }

        .content {
            text-align: justify;
            margin-bottom: 25px;
            line-height: 1.8;
            font-size: 12pt;
        }

        .separator-lines {
            width: 100%;
            margin: 15px 0 25px 0;
            font-family: monospace;
            font-size: 11pt;
            letter-spacing: -1px;
            word-break: break-all;
            overflow-wrap: break-word;
        }

        .footer-text {
            text-align: justify;
            margin-bottom: 40px;
            font-size: 12pt;
            line-height: 1.7;
        }

        .date-location {
            font-size: 11.5pt;
            margin-bottom: 50px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .signature-block {
            text-align: center;
            margin-top: 40px;
            page-break-inside: avoid;
        }

        .signature-title {
            font-weight: bold;
            margin-bottom: 45px;
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
            bottom: -25mm;
            text-align: center;
            width: 100%;
        }

        .pdf-footer img {
            width: 100%;
            height: auto;
            max-height: 14mm;
        }
    </style>
</head>

<body>
    <div class="header-wrapper">
        @if ($qrSvgBase64)
            <div class="qr-header">
                <img src="data:image/svg+xml;base64,{{ $qrSvgBase64 }}" alt="QR Code de Autenticidade" style="width: 55px; height: 55px; display: block; margin: 0 auto;">
                <span style="font-family: Arial, sans-serif; font-size: 6pt; color: #444; text-transform: uppercase; font-weight: bold; display: block; margin-top: 2px;"></span>
            </div>
        @endif

        @if ($insigniaSrc)
            <img class="insignia" src="{{ $insigniaSrc }}" alt="Insígnia da República">
        @endif
        <div class="republica">{{ $dadosInstituicao->cabecalho_linha1 ?? 'REPÚBLICA DE ANGOLA' }}</div>
        <div class="governo">{{ $dadosInstituicao->cabecalho_linha2 ?? 'GOVERNO PROVINCIAL DA HUÍLA' }}</div>
        <div class="secretaria">{{ $dadosInstituicao->cabecalho_linha3 ?? 'SECRETARIA GERAL' }}</div>
    </div>

    <div class="doc-title">CREDENCIAL</div>

    @if ($tipoCredencial === 'seguir_viagem')
        <!-- TIPO B: SEGUIR VIAGEM (MISSÃO OFICIAL INTERPROVINCIAL) -->

        <div class="content">
            Para os devidos efeitos, credencia-se o Senhor <strong>{{ $termo->beneficiario_nome }}</strong>, funcionário do <strong>{{ $termo->instituicao_vinculo ?? ($termo->beneficiario_setor ?? 'Governo Provincial da Huíla') }}</strong>, portador do B.I nº <strong>{{ $termo->beneficiario_documento }}</strong>, passado pelo {{ $termo->beneficiario_documento_emitido_local ?? 'Arquivo de Identificação Nacional' }}, aos {{ \Carbon\Carbon::parse($termo->beneficiario_documento_emitido_em)->translatedFormat('d \d\e F \d\e Y') }}, que está autorizado a seguir viagem da <strong>{{ $termo->origem_viagem ?? 'Província da Huíla' }}</strong> para <strong>{{ $termo->destino_viagem ?? 'Destino Oficial' }}</strong>, em missão de serviço oficial, conduzindo a viatura de marca <strong>{{ $viaturaObj->marca ?? 'N/A' }}</strong>, modelo <strong>{{ $viaturaObj->modelo ?? 'N/A' }}</strong>, matrícula <strong>{{ $viaturaObj->placa ?? 'N/A' }}</strong>, motor nº <strong>{{ $termo->motor_numero ?? ($viaturaObj->motor_numero ?? 'S/N') }}</strong>, de cor <strong>{{ $termo->cor_viatura ?? ($viaturaObj->cor ?? 'N/D') }}</strong>.
        </div>

        <div class="separator-lines">
            ========================================================================================================================================================================================================
        </div>
    @else
        <!-- TIPO A: UTILIZAÇÃO NORMAL / CIRCULAÇÃO LOCAL -->
        <div class="content">
            Está devidamente credenciado por este Governo Provincial, o (a) Sr(a). <strong>{{ $termo->beneficiario_nome }}</strong>, {{ $termo->beneficiario_setor ?? 'Funcionário' }}, portador(a) do BI n.º <strong>{{ $termo->beneficiario_documento }}</strong>, passado pelo {{ $termo->beneficiario_documento_emitido_local ?? 'Arquivo de Identificação Nacional' }} aos {{ \Carbon\Carbon::parse($termo->beneficiario_documento_emitido_em)->translatedFormat('d \d\e F \d\e Y') }}, a circular com a Viatura de marca <strong>{{ $viaturaObj->marca ?? 'N/A' }} {{ $viaturaObj->modelo ?? '' }}</strong>, matrícula <strong>{{ $viaturaObj->placa ?? 'N/A' }}</strong>, dentro e fora das localidades, incluindo aos finais de semana.
        </div>

        <div class="separator-lines">
            ========================================================================================================================================================================================================
        </div>
    @endif

    <div class="footer-text">
        E, para que não haja impedimentos na sua circulação, passou-se a presente credencial que vai por mim devidamente assinada e autenticada com o carimbo a óleo em uso neste Governo Provincial.
    </div>

    <div class="date-location">
        SECRETARIA GERAL DO GOVERNO PROVINCIAL DA HUÍLA, no Lubango, aos {{ now()->translatedFormat('d \d\e F \d\e Y') }}.
    </div>

    <div class="signature-block">
        <div class="signature-title">{{ $cargoSecretarioGeral }}</div>
        <div class="signature-name">{{ $nomeSecretarioGeral }}</div>
    </div>

    @if ($footerImg)
        <div class="pdf-footer">
            <img src="{{ $footerImg }}" alt="Rodapé Institucional">
        </div>
    @endif
</body>

</html>
