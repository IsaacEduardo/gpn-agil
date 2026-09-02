{{--
    Cabeçalho institucional padronizado — fonte única.

    Ordem obrigatória da regra de negócio:
        [QR Code superior direito]
        [logo] → REPÚBLICA DE ANGOLA → Nome oficial da Instituição → Gabinete do criador

    Compatível com ecrã e com PDF (Dompdf): usa posicionamento inline simples
    e suporte a QR Code em SVG/Base64 para garantia de resolução máxima.
--}}
@php
    $inst = $dadosInstituicao ?? view()->shared('dadosInstituicao');
    $logo = ($logoSrc ?? null) ?: optional($inst)->logo_url;
    $republica = optional($inst)->cabecalho_linha1 ?: 'REPÚBLICA DE ANGOLA';
    $nomeOficial = ($nomeInstituicao ?? null) ?: (optional($inst)->nome_oficial ?: optional($inst)->cabecalho_linha2);
    $largura = $larguraLogo ?? '22mm';
    $gabinete = $gabineteNome ?? null;

    $exibirQrCode = $showQrCode ?? true;
    $targetQrUrl = $qrUrl ?? null;

    if (!$targetQrUrl) {
        if (isset($documentoInterno) && $documentoInterno->assinatura_hash) {
            $targetQrUrl = route('documentos-internos.verificar', $documentoInterno->assinatura_hash);
        } elseif (isset($documentoInterno) && isset($documentoInterno->id)) {
            $targetQrUrl = route('documentos-internos.verificar', $documentoInterno->id);
        } else {
            $targetQrUrl = url('/documentos-internos/verificar');
        }
    }

    try {
        $qrSvgBase64 = base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(65)->margin(0)->generate($targetQrUrl));
    } catch (\Exception $e) {
        $qrSvgBase64 = null;
    }
@endphp

<div class="doc-header-wrapper" style="position: relative; width: 100%;">
    @if ($exibirQrCode && $qrSvgBase64)
        <div class="qr-code-header" style="position: absolute; top: 0; right: 0; text-align: center; width: 75px; z-index: 10;">
            <img src="data:image/svg+xml;base64,{{ $qrSvgBase64 }}" alt="QR Code de Autenticidade" style="width: 60px; height: 60px; display: block; margin: 0 auto;">
            <span style="font-family: Arial, sans-serif; font-size: 6.5pt; color: #555555; text-transform: uppercase; font-weight: bold; display: block; margin-top: 2px;"></span>
        </div>
    @endif

    <div class="doc-header" style="text-align: center; line-height: 1.4;">
        @if ($logo)
            <img class="doc-header__logo" src="{{ $logo }}" alt="Insígnia da {{ $republica }}"
                 style="display: block; margin: 0 auto 3mm; width: {{ $largura }}; height: auto;">
        @endif

        <div class="doc-header__republica" style="font-weight: bold; text-transform: uppercase; font-size: 12pt;">
            {{ $republica }}
        </div>

        @if (filled($nomeOficial))
            <div class="doc-header__instituicao" style="font-weight: bold; text-transform: uppercase; font-size: 14pt;">
                {{ $nomeOficial }}
            </div>
        @endif

        @if (filled($gabinete))
            <div class="doc-header__gabinete" style="font-weight: bold; text-transform: uppercase; font-size: 12pt;">
                {{ $gabinete }}
            </div>
        @endif
    </div>
</div>
