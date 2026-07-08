@php
    use Carbon\Carbon;
    
    // Resize and encode insignia logo to prevent DomPDF memory/silent failure on large image (1918x2194)
    $insigniaSrc = $dadosInstituicao->logo_absolute_path;
    $insigniaBase64 = null;
    if ($insigniaSrc && file_exists($insigniaSrc)) {
        try {
            $info = @getimagesize($insigniaSrc);
            if ($info) {
                $origWidth = $info[0];
                $origHeight = $info[1];
                $mime = $info['mime'];
                
                // Target width 100px (maintain aspect ratio)
                $targetWidth = 100;
                $targetHeight = (int)(($origHeight / $origWidth) * $targetWidth);
                
                $srcImg = null;
                if ($mime === 'image/png') {
                    $srcImg = @imagecreatefrompng($insigniaSrc);
                } elseif ($mime === 'image/jpeg' || $mime === 'image/jpg') {
                    $srcImg = @imagecreatefromjpeg($insigniaSrc);
                }
                
                if ($srcImg) {
                    $dstImg = imagecreatetruecolor($targetWidth, $targetHeight);
                    if ($mime === 'image/png') {
                        imagealphablending($dstImg, false);
                        imagesavealpha($dstImg, true);
                        $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
                        imagefilledrectangle($dstImg, 0, 0, $targetWidth, $targetHeight, $transparent);
                    }
                    imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $targetWidth, $targetHeight, $origWidth, $origHeight);
                    
                    ob_start();
                    imagepng($dstImg);
                    $imgData = ob_get_clean();
                    
                    $insigniaBase64 = 'data:image/png;base64,' . base64_encode($imgData);
                    
                    imagedestroy($srcImg);
                    imagedestroy($dstImg);
                }
            }
        } catch (\Exception $e) {
            // Fallback
        }
    }
    
    $rodapePath = $dadosInstituicao->rodape_absolute_path;
    if (!$rodapePath) {
        $rodapeCandidates = [
            'rodape_estacionario.png',
            'rodape_estacionario.jpg',
            'rodape_estacionario.jpeg',
            'Estacionariodoc.jpg',
            'Estacionariodoc.jpeg',
            'estacionario.png',
            'estacionario.jpg',
            'estacionario.jpeg',
        ];
        foreach ($rodapeCandidates as $candidate) {
            $p = public_path('images/' . $candidate);
            if (file_exists($p)) {
                $rodapePath = $p;
                break;
            }
        }
    }
    $rodapeBase64 = null;
    if ($rodapePath && file_exists($rodapePath)) {
        $ext = strtolower(pathinfo($rodapePath, PATHINFO_EXTENSION));
        $mime = $ext === 'svg' ? 'image/svg+xml' : ($ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : 'image/' . $ext);
        $rodapeBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($rodapePath));
    }
@endphp
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Protocolo de Entrada</title>
    <style>
        @page {
            size: A5 landscape;
            margin: 0mm;
        }
        body {
            margin: 0;
            padding: 8mm 12mm 15mm 12mm;
            font-family: 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #212529;
            background-color: #ffffff;
            line-height: 1.4;
        }
        
        /* Institutional Header */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }
        .header-logo-cell {
            width: 45px;
            vertical-align: middle;
            padding-right: 10px;
        }
        .header-logo {
            width: 38px;
            height: auto;
        }
        .header-text-cell {
            vertical-align: middle;
            text-align: left;
        }
        .inst-title {
            font-size: 7.5px;
            font-weight: bold;
            color: #6c757d;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin: 0;
        }
        .inst-gov {
            font-size: 11px;
            font-weight: bold;
            color: #212529;
            margin: 1px 0;
            text-transform: uppercase;
        }
        .inst-sub {
            font-size: 8px;
            color: #495057;
            margin: 0;
        }
        
        /* Divider Line */
        .divider {
            border-bottom: 1.5px dashed #dee2e6;
            margin: 6px 0 10px 0;
            height: 0px;
        }
        
        /* Subheader with Title & Protocol Number */
        .subheader-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .comprovativo-badge {
            background-color: #f8f9fa;
            color: #6c757d;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 2px 5px;
            border: 1px solid #dee2e6;
            border-radius: 3px;
            display: inline-block;
        }
        .receipt-title {
            font-size: 12px;
            font-weight: bold;
            color: #212529;
            margin: 3px 0 1px 0;
        }
        .receipt-date {
            font-size: 7.5px;
            color: #868e96;
        }
        .protocol-number-label {
            font-size: 7px;
            color: #868e96;
            text-transform: uppercase;
            font-weight: bold;
            margin: 0;
        }
        .protocol-number {
            font-size: 16px;
            font-weight: bold;
            color: #0d6efd;
            margin: 2px 0 0 0;
        }

        /* 2-Column Info Grid */
        .grid-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #dee2e6;
            background-color: #ffffff;
        }
        .grid-table td {
            vertical-align: top;
            padding: 0;
            width: 50%;
        }
        .border-right {
            border-right: 1px solid #dee2e6;
        }
        .info-item {
            padding: 6px 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .label {
            font-size: 6.5px;
            font-weight: bold;
            color: #868e96;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1px;
            display: block;
        }
        .value {
            font-size: 8.5px;
            color: #212529;
        }
        .value-medium {
            font-weight: bold;
        }
        .text-primary {
            color: #0d6efd;
        }
        .font-monospace {
            font-family: 'Courier New', Courier, monospace;
        }

        /* Subject Block */
        .subject-section {
            border: 1px solid #dee2e6;
            border-top: none;
            padding: 8px 10px;
            background-color: #ffffff;
        }
        .subject-box {
            font-size: 9px;
            font-weight: bold;
            color: #212529;
            border-left: 2.5px solid #0d6efd;
            padding-left: 6px;
            margin-top: 3px;
        }

        /* Observations Block */
        .obs-section {
            border: 1px solid #dee2e6;
            border-top: none;
            padding: 6px 10px;
            background-color: #fdfdfd;
        }
        .obs-box {
            font-size: 7.5px;
            color: #6c757d;
            margin-top: 1px;
        }

        /* Validation Footer */
        .validation-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #dee2e6;
            background-color: #f8f9fa;
            margin-top: 8px;
        }
        .validation-table td {
            padding: 6px 10px;
            vertical-align: middle;
        }
        .validation-code {
            font-size: 10.5px;
            font-family: 'Courier New', Courier, monospace;
            font-weight: bold;
            color: #212529;
        }
        .qr-code-frame {
            background-color: #ffffff;
            border: 1px solid #dee2e6;
            padding: 2px;
            display: inline-block;
            border-radius: 3px;
            width: 44px;
            height: 44px;
        }
        .validation-url {
            font-size: 7.5px;
            font-family: 'Courier New', Courier, monospace;
            color: #0d6efd;
            word-break: break-all;
        }

        /* Fixed Footer Image */
        .pdf-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: auto;
            z-index: -1000;
        }
        .pdf-footer img {
            width: 100%;
            max-height: 15mm;
        }
    </style>
</head>
<body>

    @if($rodapeBase64)
    <div class="pdf-footer">
        <img src="{{ $rodapeBase64 }}">
    </div>
    @endif

    <!-- Institutional Header -->
    <table class="header-table">
        <tr>
            <td class="header-logo-cell">
                @if($insigniaBase64)
                    <img src="{{ $insigniaBase64 }}" alt="Insígnia Oficial" class="header-logo">
                @else
                    <img src="{{ $insigniaSrc }}" alt="Insígnia Oficial" class="header-logo">
                @endif
            </td>
            <td class="header-text-cell">
                <div class="inst-title">{{ $dadosInstituicao->cabecalho_linha1 }}</div>
                <div class="inst-gov">{{ $dadosInstituicao->cabecalho_linha2 }}</div>
                @php($gabDestino = \App\Support\CabecalhoDocumento::linhaGabinete(optional($documento->departamento)->gabinete))
                @if($gabDestino)
                    <div class="inst-sub">{{ $gabDestino }}</div>
                @endif
            </td>
        </tr>
    </table>

    <div class="divider"></div>

    <!-- Receipt Header -->
    <table class="subheader-table">
        <tr>
            <td>
                <div class="comprovativo-badge">Comprovativo de Entrada</div>
                <div class="receipt-title">PROTOCOLO DE REGISTO</div>
                <div class="receipt-date">Gerado em {{ optional($protocolo->gerado_em)->format('d/m/Y \à\s H:i') }}</div>
            </td>
            <td style="text-align: right; vertical-align: bottom;">
                <div class="protocol-number-label">Nº Protocolo</div>
                <div class="protocol-number">Nº {{ sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia) }}</div>
            </td>
        </tr>
    </table>

    <!-- Main Data Grid -->
    <table class="grid-table">
        <tr>
            <td class="border-right">
                <div class="info-item">
                    <span class="label">Data de Entrada</span>
                    <span class="value">{{ optional(\Carbon\Carbon::parse($documento->data_entrada))->format('d/m/Y') }}</span>
                </div>
                <div class="info-item">
                    <span class="label">Espécie de Documento</span>
                    <span class="value value-medium">{{ $documento->classificacao_especie ?? 'Não especificado' }}</span>
                </div>
                <div class="info-item">
                    <span class="label">Origem / Procedência</span>
                    <span class="value">{{ $documento->procedencia ?? '—' }}</span>
                </div>
                <div class="info-item">
                    <span class="label">Registado Por</span>
                    <span class="value value-medium text-primary">{{ optional($documento->usuario)->name ?? 'N/D' }}</span>
                </div>
            </td>
            <td>
                <div class="info-item">
                    <span class="label">Nº de Referência do Ofício</span>
                    <span class="value font-monospace">{{ $documento->classificacao_ref_numero ?? 'S/Nº' }}</span>
                </div>
                <div class="info-item">
                    <span class="label">Data do Documento</span>
                    <span class="value">{{ $documento->data_documento ? \Carbon\Carbon::parse($documento->data_documento)->format('d/m/Y') : '—' }}</span>
                </div>
                <div class="info-item">
                    <span class="label">Departamento Destinatário</span>
                    <span class="value value-medium">{{ optional($documento->departamento)->nome ?? '—' }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Subject Block -->
    <div class="subject-section">
        <span class="label">Assunto</span>
        <div class="subject-box">{{ $documento->assunto }}</div>
    </div>

    <!-- Observations Block -->
    @if(!empty($documento->observacoes))
    <div class="obs-section">
        <span class="label">Observações</span>
        <div class="obs-box">{{ $documento->observacoes }}</div>
    </div>
    @endif

    <!-- Validation Footer Card -->
    <table class="validation-table">
        <tr>
            <td style="width: 40%;">
                <span class="label">Código de Validação</span>
                <span class="validation-code">{{ $protocolo->codigo }}</span>
            </td>
            <td style="width: 15%; text-align: center;">
                <div class="qr-code-frame">
                    @if(isset($tempQrCodePath) && file_exists($tempQrCodePath))
                        <img src="{{ $tempQrCodePath }}" style="width: 44px; height: 44px; display: block; margin: 0 auto;">
                    @else
                        {!! QrCode::size(44)->generate($consultaUrl) !!}
                    @endif
                </div>
            </td>
            <td style="width: 45%; text-align: right;">
                <span class="label">Consulta de Autenticidade</span>
                <span class="validation-url">{{ $consultaUrl }}</span>
            </td>
        </tr>
    </table>

</body>
</html>

