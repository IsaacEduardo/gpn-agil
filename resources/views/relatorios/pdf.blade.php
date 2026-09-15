@php
    use App\Enums\DocumentoStatus;

    // O DomPDF não vai buscar ficheiros ao disco público: o logótipo segue embutido.
    $logoBase64 = null;
    $caminhoLogo = optional($instituicao)->logo_absolute_path;
    if ($caminhoLogo && is_file($caminhoLogo)) {
        $logoBase64 = 'data:image/'.pathinfo($caminhoLogo, PATHINFO_EXTENSION)
            .';base64,'.base64_encode(file_get_contents($caminhoLogo));
    }

    $rotuloEstado = fn ($estado) => DocumentoStatus::tryFrom(
        $estado instanceof DocumentoStatus ? $estado->value : (string) $estado
    )?->label() ?? str_replace('_', ' ', (string) $estado);

    $rotuloGranularidade = [
        'dia' => 'Diária',
        'mes' => 'Mensal',
        'ano' => 'Anual',
        'custom' => 'Intervalo personalizado',
    ][$filters['granularity']] ?? $filters['granularity'];

    $departamentoFiltrado = $filters['departamento_id']
        ? optional($catalogs['departamentos']->firstWhere('id', (int) $filters['departamento_id']))->nome
        : 'Todos os departamentos';
@endphp
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório EDMS — GPN-AGIL</title>
    <style>
        @page { margin: 22mm 12mm 18mm 12mm; }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9.5pt;
            color: #1f2937;
            line-height: 1.35;
            margin: 0;
        }

        h1, h2, h3 { margin: 0; }

        .titulo-relatorio {
            text-align: center;
            font-size: 13pt;
            text-transform: uppercase;
            color: #0f172a;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 4mm;
            margin: 4mm 0 4mm 0;
        }

        .titulo-relatorio small {
            display: block;
            font-size: 8.5pt;
            font-weight: normal;
            text-transform: none;
            color: #64748b;
            margin-top: 1mm;
        }

        .seccao {
            font-size: 10pt;
            color: #0f172a;
            border-left: 3px solid #0284c7;
            padding-left: 2mm;
            margin: 5mm 0 2mm 0;
        }

        table { width: 100%; border-collapse: collapse; }

        .filtros td {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 1.5mm 2mm;
            font-size: 8.5pt;
        }

        .filtros .rotulo {
            color: #64748b;
            text-transform: uppercase;
            font-size: 7pt;
            display: block;
        }

        .kpi td {
            width: 20%;
            border: 1px solid #cbd5e1;
            background: #f1f5f9;
            padding: 2.5mm 1mm;
            text-align: center;
        }

        .kpi .valor { font-size: 14pt; font-weight: bold; color: #0284c7; }
        .kpi .rotulo { font-size: 7pt; color: #475569; text-transform: uppercase; }

        .grafico { text-align: center; margin-bottom: 4mm; }
        .grafico img { max-width: 100%; max-height: 62mm; }
        .grafico .legenda { font-size: 8pt; color: #64748b; margin-top: 1mm; }

        .dados th {
            background: #0f172a;
            color: #ffffff;
            font-size: 7.5pt;
            text-transform: uppercase;
            padding: 1.5mm 2mm;
            text-align: left;
        }

        .dados td {
            border-bottom: 1px solid #e2e8f0;
            padding: 1.5mm 2mm;
            font-size: 8pt;
        }

        .dados tr:nth-child(even) td { background: #f8fafc; }
        .dados .numerico { text-align: right; }
        .vazio { text-align: center; color: #94a3b8; padding: 4mm; }

        .rodape {
            position: fixed;
            bottom: -12mm;
            left: 0;
            right: 0;
            height: 10mm;
            border-top: 1px solid #e2e8f0;
            padding-top: 1.5mm;
            font-size: 7pt;
            color: #94a3b8;
        }

        .rodape .esquerda { float: left; }
        .rodape .direita { float: right; }
        .rodape .pagina:after { content: "Página " counter(page) " de " counter(pages); }

        .nota { font-size: 7.5pt; color: #64748b; margin-top: 2mm; }
    </style>
</head>
<body>

    {{-- Rodapé fixo, repetido em todas as páginas --}}
    <div class="rodape">
        <span class="esquerda">
            GPN-AGIL · Emissão {{ $identificador_emissao }} · {{ $emitido_por }} · {{ $emitido_em }}
        </span>
        <span class="direita pagina"></span>
    </div>

    {{-- Cabeçalho institucional: logo → República → instituição → gabinete --}}
    @include('partials.document-header', [
        'logoSrc' => $logoBase64,
        'gabineteNome' => $linha_gabinete,
        'showQrCode' => false,
        'larguraLogo' => '18mm',
    ])

    <h1 class="titulo-relatorio">
        Relatório de Gestão Documental
        <small>
            Produção, tramitação e cumprimento de prazos ·
            {{ \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') }}
            a {{ \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') }}
        </small>
    </h1>

    <h2 class="seccao">1. Filtros aplicados</h2>
    <table class="filtros">
        <tr>
            <td width="25%"><span class="rotulo">Granularidade</span>{{ $rotuloGranularidade }}</td>
            <td width="25%"><span class="rotulo">Período</span>{{ \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') }}</td>
            <td width="25%"><span class="rotulo">Departamento</span>{{ $departamentoFiltrado ?: 'Todos os departamentos' }}</td>
            <td width="25%"><span class="rotulo">Espécie</span>{{ $filters['especie'] ?: 'Todas' }}</td>
        </tr>
        <tr>
            <td><span class="rotulo">Estado</span>{{ $filters['status'] ? $rotuloEstado($filters['status']) : 'Todos' }}</td>
            <td><span class="rotulo">Emitido por</span>{{ $emitido_por }}</td>
            <td><span class="rotulo">Emitido em</span>{{ $emitido_em }}</td>
            <td><span class="rotulo">Identificador</span>{{ $identificador_emissao }}</td>
        </tr>
    </table>

    <h2 class="seccao">2. Indicadores do período</h2>
    <table class="kpi">
        <tr>
            <td>
                <div class="valor">{{ $kpis['total_entradas'] }}</div>
                <div class="rotulo">Entradas</div>
            </td>
            <td>
                <div class="valor">{{ $kpis['total_internos'] }}</div>
                <div class="rotulo">Produzidos</div>
            </td>
            <td>
                <div class="valor">{{ $kpis['total_assinados'] }}</div>
                <div class="rotulo">Assinados</div>
            </td>
            <td>
                <div class="valor">{{ number_format($kpis['avg_resposta_entradas_dias'], 1, ',', '.') }}</div>
                <div class="rotulo">Dias — resposta média</div>
            </td>
            <td>
                <div class="valor">{{ number_format($kpis['sla_compliance_percent'], 1, ',', '.') }}%</div>
                <div class="rotulo">Prazos cumpridos</div>
            </td>
        </tr>
    </table>

    <p class="nota">
        Cumprimento de prazos: {{ $charts['sla']['no_prazo'] }} documentos dentro do prazo e
        {{ $charts['sla']['atrasados'] }} fora do prazo, num total de {{ $charts['sla']['avaliados'] }} avaliados
        ({{ $charts['sla']['pendentes'] }} ainda por resolver).
        Tempo médio de homologação interna: {{ number_format($kpis['avg_assinatura_internos_dias'], 1, ',', '.') }} dias.
    </p>

    @if (! empty($graficos_base64))
        <h2 class="seccao">3. Representação gráfica</h2>
        @foreach ($graficos_base64 as $legenda => $imagem)
            <div class="grafico">
                <img src="{{ $imagem }}" alt="{{ $legenda }}">
                <div class="legenda">{{ $legenda }}</div>
            </div>
        @endforeach
    @endif

    <h2 class="seccao">{{ empty($graficos_base64) ? '3' : '4' }}. Documentos de entrada</h2>
    <table class="dados">
        <thead>
            <tr>
                <th width="9%">Nº</th>
                <th width="11%">Entrada</th>
                <th width="13%">Espécie</th>
                <th>Assunto</th>
                <th width="16%">Procedência</th>
                <th width="10%">Depart.</th>
                <th width="7%" class="numerico">Dias</th>
                <th width="12%">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lists['entradas'] as $doc)
                <tr>
                    <td>{{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}</td>
                    <td>{{ optional($doc->data_entrada)->format('d/m/Y') }}</td>
                    <td>{{ $doc->classificacao_especie ?: '—' }}</td>
                    <td>{{ Str::limit($doc->assunto, 70) }}</td>
                    <td>{{ Str::limit(optional($doc->procedenciaCatalogo)->nome ?: $doc->procedencia ?: '—', 28) }}</td>
                    <td>{{ optional($doc->departamento)->sigla ?: optional($doc->departamento)->nome }}</td>
                    <td class="numerico">{{ $doc->dias_decorridos }}</td>
                    <td>{{ $rotuloEstado($doc->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="vazio">Nenhum documento de entrada no período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2 class="seccao">{{ empty($graficos_base64) ? '4' : '5' }}. Documentos produzidos</h2>
    <table class="dados">
        <thead>
            <tr>
                <th width="13%">Referência</th>
                <th width="11%">Criação</th>
                <th>Título</th>
                <th width="14%">Espécie</th>
                <th width="16%">Autor</th>
                <th width="12%">Estado</th>
                <th width="11%">Assinatura</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lists['internos'] as $doc)
                <tr>
                    <td>{{ $doc->numero_referencia ?: '—' }}</td>
                    <td>{{ optional($doc->created_at)->format('d/m/Y') }}</td>
                    <td>{{ Str::limit($doc->titulo, 60) }}</td>
                    <td>{{ optional($doc->especie)->nome ?: '—' }}</td>
                    <td>{{ Str::limit(optional($doc->autor)->name ?: '—', 26) }}</td>
                    <td>{{ $rotuloEstado($doc->status) }}</td>
                    <td>{{ optional($doc->assinado_em)->format('d/m/Y') ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="vazio">Nenhum documento produzido no período.</td></tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
