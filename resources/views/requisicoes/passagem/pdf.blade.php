@php
    use Carbon\Carbon;
    $empresaNome = $empresa->nome ?? ($requisicao->empresa_destinataria ?? '');
    $codigo = $requisicao->codigo_sequencial ?? '';
    $dataReq = $requisicao->data_requisicao ?? now();
    $data = Carbon::parse($dataReq);
    $data->locale('pt_BR');
    $dataFormatada = $data->translatedFormat('d \de F \de Y');
    $anoOficio = $data->year;
    $local = $empresa->endereco ?? $dadosInstituicao->cidade;
    $insigniaSrc = $dadosInstituicao->logo_absolute_path;
    $rodapePath = $dadosInstituicao->rodape_absolute_path;
    if (!$rodapePath) {
        $rodapeCandidates = [
            'Estacionariodoc.jpg',
            'rodape_estacionario.png',
            'rodape_estacionario.jpg',
            'rodape_estacionario.jpeg',
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

    // Obter o nome do departamento do usuário que criou a requisição
    $usuario = $requisicao->usuario;
    $departamento = $usuario ? $usuario->departamento : null;
    $departamentoNome = $departamento ? $departamento->nome : 'Departamento de Logística e Património';
    $chefeNome = $departamento && $departamento->chefe ? $departamento->chefe->name : 'GILBERTO SIVELA DA SILVA';

    $chefeGabineteNome = 'Carlos Nelson Duarte da Silva';
    if (
        $usuario &&
        $usuario->departamento &&
        $usuario->departamento->gabinete &&
        $usuario->departamento->gabinete->responsavel
    ) {
        $chefeGabineteNome = $usuario->departamento->gabinete->responsavel->name;
    }

    $idaVolta = $passagem->ida_volta ?? false;
    $destino = $passagem->destino ?? '';
    $beneficiario = $passagem->beneficiario_nome ?? '';
    $partida = $passagem->data_partida
        ? Carbon::parse($passagem->data_partida)->locale('pt_BR')->translatedFormat('d \de F')
        : '';
    $regresso = $passagem->data_regresso
        ? Carbon::parse($passagem->data_regresso)->locale('pt_BR')->translatedFormat('d \de F \de Y')
        : '';
@endphp
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="utf-8">
    <title>Ofício - Requisição de Bilhete de Passagem</title>
    <style>
        @page {
            margin: 0mm 20mm 10mm 20mm;
        }

        body {
            font-family: 'Malgun Gothic', Arial, sans-serif;
            color: #111;
            font-size: 12pt;
        }

        .header {
            text-align: center;
            line-height: 1.4;
            margin-bottom: 8mm;
        }

        .header .title {
            font-weight: bold;
            font-size: 12pt;
        }

        .header .gov {
            font-weight: bold;
            font-size: 16pt;
        }

        .insignia {
            display: block;
            margin: 0 auto 3mm;
            width: 22mm;
            height: auto;
        }

        .meta {
            margin-top: 2mm;
            text-align: left;
            width: 250px;
            margin-left: auto;
            margin-right: 0;
            line-height: 1.2;
        }

        .meta .to {
            display: block;
            font-weight: bold;
            font-size: 18px;
            margin-top: 4px;
        }

        .meta .city {
            display: block;
            font-weight: bold;
            text-decoration: underline;
            font-size: 20px;
            text-decoration-thickness: 2px;
            text-underline-offset: 3px;
            margin-top: 2px;
        }

        .visto {
            position: absolute;
            top: 50mm;
            left: 2mm;
            text-align: center;
            font-size: 11pt;
            line-height: 1.3;
        }

        .visto .titulo {
            font-weight: bold;
            text-transform: uppercase;
        }

        .visto .cargo {
            font-weight: bold;
            text-transform: uppercase;
        }

        .visto .nome {
            margin-top: 2mm;
            font-style: italic;
        }

        .oficio {
            margin-top: 6mm;
            font-weight: bold;
        }

        .assunto {
            margin-top: 6mm;
            font-weight: bold;
        }

        .content {
            margin-top: 3mm;
            text-align: justify;
            line-height: 1.5;
        }

        .content p {
            margin: 2mm 0;
        }

        .footer {
            margin-top: 10mm;
        }

        .signature {
            margin-top: 12mm;
            text-align: center;
        }

        .signature .role {
            font-weight: bold;
        }

        .pdf-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
        }

        .footer-image {
            width: 100%;
            height: auto;
        }
    </style>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
</head>

<body>
    @if ($rodapeBase64)
        <div class="pdf-footer">
            <img class="footer-image" src="{{ $rodapeBase64 }}" alt="Rodapé">
        </div>
    @endif
    <div class="header">
        @include('partials.document-header', [
            'logoSrc' => $insigniaSrc,
            'gabineteNome' => \App\Support\CabecalhoDocumento::linhaGabinete(
                (isset($requisicao) && $requisicao->gabinete) ? $requisicao->gabinete : optional(auth()->user())->gabinete()
            ),
        ])
    </div>
    <div class="visto">
        <div class="titulo">VISTO</div>
        <div class="cargo">O SECRETÁRIO GERAL</div>
        <div class="nome">{{ $chefeGabineteNome }}</div>
    </div>
    <div class="meta">
        <div class="to">
            <div>À</div>
            <div class="to-company"><span class="inline">EMPRESA {{ mb_strtoupper($empresaNome, 'UTF-8') }}</span></div>
        </div>
        <div class="city">{{ mb_strtoupper($local, 'UTF-8') }}</div>
    </div>
    <div class="oficio">OFÍCIO N.º _______/08.03.05/00-06/GPN/SG/DLP/{{ $anoOficio }}.</div>
    <div class="assunto">ASSUNTO: REQUISIÇÃO.</div>
    <div class="content">
        <p>
            Para os devidos efeitos, remete-se a requisição de um (01) bilhete de
            passagem{{ $idaVolta ? ', ida e volta' : '' }}, com destino
            <strong>{{ $destino }}</strong>{{ $partida ? ' , com partida prevista para o dia ' . $partida : '' }}
            @if ($idaVolta && $regresso)
                e de regresso no dia {{ $regresso }}
            @endif, em nome de <strong>{{ $beneficiario }}</strong>.
        </p>
        <p>
            Sem outro assunto de momento, digne-se aceitar a nossa reiterada expressão de respeito e consideração.
        </p>
    </div>
    <div class="footer">
        <div>
            <strong>{{ mb_strtoupper($departamentoNome, 'UTF-8') }} – {{ mb_strtoupper($dadosInstituicao->cabecalho_linha2, 'UTF-8') }}</strong>,
            em {{ $dadosInstituicao->cidade }}, aos {{ $dataFormatada }}.
        </div>
    </div>
    <div class="signature">
        <div class="role">Chefe do Departamento</div>
        @if ($requisicao->assinado_em)
              <div style="margin-top: 10px; color: #198754;">
                  <strong>[ASSINADO DIGITALMENTE]</strong><br>
                  <span style="text-transform: uppercase;">{{ $requisicao->assinadoPor->name ?? $chefeNome }}</span><br>
                  <span style="font-size: 8pt;">Em: {{ $requisicao->assinado_em->format('d/m/Y H:i') }}</span><br>
                  <span style="font-size: 8pt; font-family: monospace;">Hash: {{ substr($requisicao->assinatura_hash, 0, 10) }}...</span>
              </div>
         @else
            <div class="muted"> <br> </div>
            <div class="role"> {{ mb_strtoupper($chefeNome, 'UTF-8') }}</div>
        @endif
    </div>
</body>

</html>
