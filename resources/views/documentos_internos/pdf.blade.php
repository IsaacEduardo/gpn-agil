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

    // O estacionário ocupa a largura útil do texto (210 - 30 - 20 = 160mm), com
    // a altura dada pela proporção da imagem: forçar a largura e cortar a altura
    // achatava-o, e limitar a altura estreitava-o. É a margem inferior que cede:
    // fica nos 35mm oficiais e só cresce quando o rodapé (mais o bloco da
    // assinatura digital) não cabe nela.
    $larguraUtil = 160;
    $folgaPapel = 8;     // distância do rodapé à borda do papel (zona não imprimível)
    $blocoAssinatura = $documentoInterno->assinado_em ? 15 : 0;
    $rodapeDim = null;
    if ($rodapeImg && ($info = @getimagesize($rodapeImg)) && $info[0] > 0) {
        $largura = $larguraUtil;
        $altura = $largura * $info[1] / $info[0];
        if ($altura > 40) { // imagem desproporcionada: não come a página
            $altura = 40;
            $largura = $altura * $info[0] / $info[1];
        }
        $rodapeDim = ['largura' => round($largura, 1), 'altura' => round($altura, 1)];
    }
    $alturaRodape = ($rodapeDim['altura'] ?? 0) + $blocoAssinatura;
    $margemInferior = (int) ceil(max(35, $folgaPapel + $alturaRodape + 2));
    $caixaRodape = $margemInferior - $folgaPapel;
@endphp
<!DOCTYPE html>
<html lang="pt-ao">

<head>
    <meta charset="UTF-8">
    <title>Documento {{ $documentoInterno->numero_referencia }}</title>
    <style>
        @page {
            /* Margens oficiais: Superior 2cm, Direita 2cm, Inferior 3.5cm (ou o que o
               rodapé precisar — ver o cálculo no topo), Esquerda 3cm */
            margin: 20mm 20mm {{ $margemInferior }}mm 30mm;
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

        /* Rodapé dentro da margem inferior: a caixa vai de 8mm da borda do papel
           (fora da zona que as impressoras não imprimem) até ao fim do texto, e o
           conteúdo assenta no fundo dela — antes ficava colado ao texto, com um
           vazio por baixo. */
        .paper-footer {
            position: fixed;
            bottom: -{{ $caixaRodape }}mm;
            left: 0;
            width: 100%;
            height: {{ $caixaRodape }}mm;
            z-index: 999;
        }

        .paper-footer-grelha {
            width: 100%;
            height: {{ $caixaRodape }}mm;
            border-collapse: collapse;
        }

        .paper-footer-grelha td {
            vertical-align: bottom;
            text-align: center;
            padding: 0;
        }

        img {
            max-width: 100%;
            height: auto;
            page-break-inside: avoid;
            break-inside: avoid-page;
        }

        /* Gestão de tipografia, órfãos e viúvas */
        .paper-content p {
            orphans: 3;
            widows: 3;
        }

        h1, h2, h3, h4, h5, h6 {
            page-break-after: avoid;
            break-after: avoid-page;
        }

        /* Gestão de tabelas e quebras de página */
        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }

        tr {
            page-break-inside: avoid;
            break-inside: avoid-page;
            page-break-after: auto;
        }

        td,
        th {
            vertical-align: top;
        }

        /* O editor colaborativo (Tiptap) põe um <p> em cada célula; sem margem, as tabelas
           ficam iguais às do editor clássico. */
        .paper-content td > p,
        .paper-content th > p {
            margin: 0;
        }

        .page-break {
            page-break-after: always;
            break-after: page;
            clear: both;
        }

        .no-break,
        .signature-block {
            page-break-inside: avoid;
            break-inside: avoid-page;
        }

        /* Sem numeração de páginas (2026-10-03): o Dompdf não calcula o total e o
           rodapé dizia "Página 1 de 0". */
    </style>
</head>

<body>
    @php
        // Guarda: a view pode ser renderizada várias vezes no mesmo processo (exportação em
        // lote, testes); redeclarar a função era erro fatal a partir do segundo documento.
        if (! function_exists('base64_encode_image')) {
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
        }
    @endphp

    <div class="paper-container">
        <!-- Header / Insignia (cabeçalho padronizado) -->
        <div class="text-center mb-4">
            @include('partials.document-header', [
                'logoSrc' => base64_encode_image($dadosInstituicao->logo_absolute_path),
                'gabineteNome' => \App\Support\CabecalhoDocumento::linhaGabinete($documentoInterno->gabineteEmissor()),
                'documentoInterno' => $documentoInterno,
            ])
        </div>

        <!-- Content -->
        <div class="paper-content">
            {!! $documentoInterno->conteudoParaApresentacao() !!}
        </div>

        <!-- Footer -->
        <div class="paper-footer">
          <table class="paper-footer-grelha"><tr><td>
            @if ($documentoInterno->assinado_em)
                <table class="signature-block" style="width: 100%; border: none; background-color: #f8f9fa; border-top: 1px solid #dee2e6; margin-bottom: 3px; font-family: sans-serif; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 4px 10px; font-size: 7.5pt; color: #6c757d; text-align: left; vertical-align: middle; border: none; line-height: 1.3;">
                            <strong>Assinado Digitalmente por:</strong>
                            {{ $documentoInterno->assinadoPor->name ?? 'Desconhecido' }}<br>
                            <strong>em:</strong> {{ $documentoInterno->assinado_em->format('d/m/Y H:i:s') }} | 
                            <strong>Hash:</strong> <span style="font-family: monospace; font-size: 6.5pt;">{{ $documentoInterno->assinatura_hash }}</span>
                        </td>
                        <td style="width: 15mm; text-align: right; padding: 2px 6px 2px 2px; vertical-align: middle; border: none;">
                            <img src="data:image/svg+xml;base64,{{ base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(70)->margin(0)->generate(route('documentos-internos.verificar', $documentoInterno->assinatura_hash))) }}" style="width: 11mm; height: 11mm; display: block; margin-left: auto; border: none;">
                        </td>
                    </tr>
                </table>
            @endif

            @if ($rodapeImg && $rodapeDim)
                <img src="{{ base64_encode_image($rodapeImg) }}" alt="Rodapé"
                    style="width: {{ $rodapeDim['largura'] }}mm; height: {{ $rodapeDim['altura'] }}mm; display: block; margin: 0 auto;">
            @elseif ($dadosInstituicao->rodape_texto)
                <div style="text-align: center; font-size: 8pt; color: #6c757d; font-family: sans-serif; border-top: 1px solid #dee2e6; padding-top: 3px; line-height: 1.2;">
                    {{ $dadosInstituicao->rodape_texto }}
                </div>
            @endif
          </td></tr></table>
        </div>
    </div>
</body>

</html>
