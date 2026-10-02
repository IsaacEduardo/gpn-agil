@php
    /*
     * Etiqueta do protocolo para o dompdf, com o tamanho exacto do rolo
     * ($formato). O dompdf não suporta flexbox: as zonas vão em posição absoluta,
     * em mm, e o conteúdo de cada uma em tabelas. Nada entra na margem de
     * segurança, que absorve o desvio de posicionamento das térmicas.
     *
     * Tudo tem de caber numa página: os textos variáveis são cortados à medida
     * do formato e as zonas têm altura fixa.
     */
    $instituicao = \App\Support\CabecalhoDocumento::instituicao();
    $gabLinha = $formato->compacto
        ? null
        : \App\Support\CabecalhoDocumento::linhaGabinete(optional($documento->departamento)->gabinete, $instituicao);

    $m = \App\Support\FormatoEtiqueta::MARGEM_MM;
    $larguraUtil = $formato->larguraMm - 2 * $m;
    $alturaUtil = $formato->alturaMm - 2 * $m;
    $alturaCabecalho = $formato->compacto ? 0 : ($formato->alturaMm >= 60 ? 11 : 10);
    $alturaRodape = 4;
    $qr = $formato->ladoQrMm();

    $topoCorpo = $m + ($alturaCabecalho ? $alturaCabecalho + 1 : 0);
    $alturaCorpo = $alturaUtil - ($alturaCabecalho ? $alturaCabecalho + 1 : 0) - $alturaRodape - 1;

    $numero = sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia);
    $registo = optional($documento->data_entrada ?? $protocolo->gerado_em)->format('d/m/Y H:i');
    $sigla = filled($instituicao->sigla) ? mb_strtoupper(trim($instituicao->sigla)) : 'EDMS GPN-ÁGIL';
@endphp
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #000; }

        .zona { position: absolute; left: {{ $m }}mm; width: {{ $larguraUtil }}mm; overflow: hidden; }
        table { border-collapse: collapse; width: 100%; }
        td { padding: 0; vertical-align: middle; }
        .linha { white-space: nowrap; overflow: hidden; }

        .cabecalho { top: {{ $m }}mm; height: {{ $alturaCabecalho }}mm; border-bottom: 0.3mm dashed #000; }
        .logo { width: 9mm; height: 9mm; }
        .pais { font-size: 6.5pt; font-weight: bold; text-transform: uppercase; line-height: 1.15; }
        .gov { font-size: 5.8pt; font-weight: bold; text-transform: uppercase; line-height: 1.15; }
        .gabinete { font-size: 5.2pt; text-transform: uppercase; line-height: 1.15; }

        .corpo { top: {{ $topoCorpo }}mm; height: {{ $alturaCorpo }}mm; }
        .rotulo { font-size: {{ $formato->compacto ? '5.5pt' : '6.5pt' }}; font-weight: bold; text-transform: uppercase; }
        .numero { font-size: {{ $formato->compacto ? '13pt' : '16pt' }}; font-weight: bold; line-height: 1.1; margin: 0.6mm 0; }
        .meta { font-size: {{ $formato->compacto ? '5.5pt' : '5.8pt' }}; line-height: 1.3; }
        .qr { width: {{ $qr }}mm; height: {{ $qr }}mm; }

        .rodape { top: {{ $m + $alturaUtil - $alturaRodape }}mm; height: {{ $alturaRodape }}mm; border-top: 0.3mm dashed #000; }
        .rodape td { font-size: 5.5pt; font-weight: bold; padding-top: 0.6mm; }
        .codigo { font-family: 'DejaVu Sans Mono', monospace; font-size: 5.8pt; }
    </style>
</head>
<body>

@unless ($formato->compacto)
    <div class="zona cabecalho">
        <table>
            <tr>
                @if ($logoSrc)
                    <td style="width: 11mm;"><img src="{{ $logoSrc }}" class="logo" alt=""></td>
                @endif
                <td>
                    <div class="pais linha">{{ Str::limit($instituicao->cabecalho_linha1 ?: 'REPÚBLICA DE ANGOLA', 60, '') }}</div>
                    <div class="gov linha">{{ Str::limit($instituicao->cabecalho_linha2 ?: ($instituicao->nome_oficial ?: 'GOVERNO PROVINCIAL'), 60, '') }}</div>
                    @if ($gabLinha)
                        <div class="gabinete linha">{{ Str::limit($gabLinha, 70) }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>
@endunless

<div class="zona corpo">
    {{-- A altura vai nas células: na tabela, o dompdf ignora-a e o corpo
         encostava ao cabeçalho em vez de ficar centrado. --}}
    <table>
        <tr>
            <td style="height: {{ $alturaCorpo }}mm;">
                <div class="rotulo linha">{{ $formato->compacto ? 'Protocolo' : 'Entrada N.º / Protocolo' }}</div>
                <div class="numero linha">{{ $numero }}</div>
                <div class="meta linha"><strong>{{ $formato->compacto ? 'REG.:' : 'REGISTO:' }}</strong> {{ $registo }}</div>
                @unless ($formato->compacto)
                    <div class="meta linha"><strong>PROCEDÊNCIA:</strong> {{ Str::limit($documento->procedencia ?? '—', 40) }}</div>
                @endunless
            </td>
            <td style="width: {{ $qr + 1 }}mm; text-align: right;">
                @if ($qrCodeSrc)
                    <img src="{{ $qrCodeSrc }}" class="qr" alt="">
                @endif
            </td>
        </tr>
    </table>
</div>

<div class="zona rodape">
    <table>
        <tr>
            <td class="linha">{{ $formato->compacto ? '' : 'AUTENTICIDADE: ' }}<span class="codigo">{{ $protocolo->codigo }}</span></td>
            <td class="linha" style="text-align: right;">{{ $sigla }}</td>
        </tr>
    </table>
</div>

</body>
</html>
