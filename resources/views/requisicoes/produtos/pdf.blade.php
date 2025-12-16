@php
    use Carbon\Carbon;
    $empresaNome = $empresa->nome ?? ($requisicao->empresa_destinataria ?? '');
    $codigo = $requisicao->codigo_sequencial ?? '';
    $dataReq = $requisicao->data_requisicao ?? now();
    $data = Carbon::parse($dataReq);
    $data->locale('pt_BR');
    $dataFormatada = $data->translatedFormat('d \de F \de Y');
    $anoOficio = $data->year;
    $local = $empresa->endereco ?? 'Moçâmedes';
    $observacoesGerais = $requisicao->observacoes ?? null;
    $insigniaLocal = public_path('images/insignia.png');
    $insigniaSrc = file_exists($insigniaLocal)
        ? $insigniaLocal
        : 'https://upload.wikimedia.org/wikipedia/commons/1/11/Emblem_of_Angola.svg';
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
    $rodapePath = null;
    foreach ($rodapeCandidates as $candidate) {
        $p = public_path('images/' . $candidate);
        if (file_exists($p)) {
            $rodapePath = $p;
            break;
        }
    }
    $rodapeBase64 = null;
    if ($rodapePath) {
        $ext = strtolower(pathinfo($rodapePath, PATHINFO_EXTENSION));
        $mime = $ext === 'svg' ? 'image/svg+xml' : ($ext === 'jpg' || $ext === 'jpeg' ? 'image/jpeg' : 'image/' . $ext);
        $rodapeBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($rodapePath));
    }

    // Obter o nome do departamento do usuário que criou a requisição
    $usuario = $requisicao->usuario;
    $departamento = $usuario ? $usuario->departamento : null;
    $departamentoNome = $departamento ? $departamento->nome : 'Departamento de Logística e Património';
    $chefeNome = ($departamento && $departamento->chefe) ? $departamento->chefe->name : 'GILBERTO SIVELA DA SILVA';
@endphp
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="utf-8">
    <title>Ofício - Requisição de Produtos</title>
    <style>
        /* Espaçamento: margem inferior ajustada para 1cm */
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

        .insignia {
            display: block;
            margin: 0 auto 3mm;
            width: 22mm;
            height: auto;
        }

        .header .title {
            font-weight: bold;
            font-size: 12pt;
        }

        .header .gov {
            font-weight: bold;
            font-size: 16pt;
        }

        .meta {
            margin-top: 2mm;
            text-align: left;
            /* Alinha o conteúdo à direita */
            width: 250px;
            /* Reduz a largura para formar a coluna como no exemplo */
            margin-left: auto;
            /* Empurra o bloco para o lado direito */
            margin-right: 0;
            font-family: 'Malgun Gothic', Arial, sans-serif;
            line-height: 1.2;
            /* Aproxima as linhas */
        }

        .meta .to {
            display: block;
            font-weight: bold;
            font-size: 18px;
            /* Tamanho ligeiramente maior */
            margin-top: 4px;
        }

        .meta .city {
            display: block;
            font-weight: bold;
            text-decoration: underline;
            font-size: 20px;
            /* Maior e destacado */
            text-decoration-thickness: 2px;
            /* Sublinhado mais forte */
            text-underline-offset: 3px;
            /* Espaço entre o texto e o sublinhado */
            margin-top: 2px;
        }

        .visto {
            position: absolute;
            top: 50mm;
            /* Ajuste conforme o espaçamento desejado em relação ao topo */
            left: 2mm;
            /* Mantém o alinhamento com a margem esquerda da página */
            text-align: center;
            font-family: 'Malgun Gothic', Arial, sans-serif;
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

        .items {
            margin: 4mm 0 2mm 2mm;
        }

        .items li {
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

        .muted {
            color: #555;
        }

        .inline {
            display: inline-block;
            min-width: 20mm;
        }

        .hr {
            border-top: 1px solid #ccc;
            margin-top: 3mm;
        }

        /* Rodapé estacionário (fixo no fundo da página) */
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
    {{-- Rodapé estacionário fixo: usa somente a imagem oficial --}}
    @if ($rodapeBase64)
        <div class="pdf-footer">
            <img class="footer-image" src="{{ $rodapeBase64 }}" alt="Estacionário / Rodapé oficial">
        </div>
    @endif
    <div class="header">
        <img class="insignia" src="{{ $insigniaSrc }}" alt="Insígnia da República de Angola">
        <div class="title">REPÚBLICA DE ANGOLA</div>
        <div class="gov">Governo Provincial do Namibe</div>
        <div class="title">SECRETARIA GERAL</div>
        <div class="title">DLP</div>
    </div>

    <div class="visto">
        <div class="titulo">VISTO</div>
        <div class="cargo">O SECRETÁRIO GERAL</div>
        <div class="nome">Carlos Nelson Duarte da Silva</div>
    </div>



    <div class="meta">
        <div class="to">
            <div>À</div>
            <div class="to-company"><span class="inline">EMPRESA {{ mb_strtoupper($empresaNome, 'UTF-8') }}</span></div>
        </div>
        <div class="city">{{ mb_strtoupper($local, 'UTF-8') }}</div>
    </div>

    <div class="oficio">OFÍCIO N.º _______/08.03.05/00-06/GPN/SG/DLP/{{ $anoOficio }}. </div>

    <div class="assunto">ASSUNTO: REQUISIÇÃO.</div>

    <div class="content">
        <p>Para os devidos efeitos, remete-se a requisição dos seguintes
            produtos{{ !empty($observacoesGerais) ? ', ' . $observacoesGerais : '' }}:</p>
        @if (!empty($produtos) && count($produtos) > 0)
            <ul class="items">
                @foreach ($produtos as $p)
                    @php
                        $nome = $p->nome_produto ?? ($p->nome ?? ($p->descricao ?? 'Produto'));
                        $qtd = $p->quantidade ?? null;
                        $un = $p->unidade_medida ?? ($p->unidade ?? null);
                    @endphp
                    <li>{{ $nome }} @if ($qtd && $un)
                            ({{ $qtd }} {{ $un }})
                        @elseif($qtd)
                            ({{ $qtd }})
                        @elseif($un)
                            ({{ $un }})
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        <p>Sem outro assunto de momento, digne-se aceitar a nossa reiterada expressão de respeito e consideração.</p>
    </div>

    <div class="footer">
        <div>
            <strong>{{ mb_strtoupper($departamentoNome, 'UTF-8') }} – SECRETARIA DO GOVERNO PROVINCIAL DO NAMIBE</strong>,
            em Moçâmedes, aos {{ $dataFormatada }}.
        </div>
    </div>

    <div class="signature">

        <div class="role">Chefe do Departamento</div>

        <div class="muted"> <br> </div>
        <div class="role"> {{ mb_strtoupper($chefeNome, 'UTF-8') }}</div>
    </div>
</body>

</html>
