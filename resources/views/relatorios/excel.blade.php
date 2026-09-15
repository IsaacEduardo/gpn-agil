@php
    use App\Enums\DocumentoStatus;

    $rotuloEstado = fn ($estado) => DocumentoStatus::tryFrom(
        $estado instanceof DocumentoStatus ? $estado->value : (string) $estado
    )?->label() ?? str_replace('_', ' ', (string) $estado);

    $periodo = \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y')
        .' a '.\Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y');
@endphp
{{--
    Estrutura de linhas fixa — RelatorioGeralExport::styles() formata pelo
    número da linha (título = 1, indicadores = 4, cabeçalho da tabela = 9).
    Alterar a ordem das secções obriga a atualizar essa classe.
--}}
<table>
    <tbody>
        {{-- 1 --}}
        <tr>
            <td colspan="8">RELATÓRIO DE GESTÃO DOCUMENTAL — GPN-AGIL</td>
        </tr>
        {{-- 2 --}}
        <tr>
            <td colspan="8">
                Período: {{ $periodo }} · Emitido por {{ $emitido_por }} em {{ $emitido_em }} · Emissão {{ $identificador_emissao }}
            </td>
        </tr>
        {{-- 3 --}}
        <tr><td colspan="8"></td></tr>
        {{-- 4 --}}
        <tr>
            <td colspan="8">RESUMO DE INDICADORES</td>
        </tr>
        {{-- 5 --}}
        <tr>
            <td colspan="2">Total de entradas</td>
            <td>{{ $kpis['total_entradas'] }}</td>
            <td colspan="2">Documentos produzidos</td>
            <td>{{ $kpis['total_internos'] }}</td>
            <td>Assinados</td>
            <td>{{ $kpis['total_assinados'] }}</td>
        </tr>
        {{-- 6 --}}
        <tr>
            <td colspan="2">Tempo médio de resposta (dias)</td>
            <td>{{ $kpis['avg_resposta_entradas_dias'] }}</td>
            <td colspan="2">Tempo médio de homologação (dias)</td>
            <td>{{ $kpis['avg_assinatura_internos_dias'] }}</td>
            <td>Arquivados</td>
            <td>{{ $kpis['total_arquivados'] }}</td>
        </tr>
        {{-- 7 --}}
        <tr>
            <td colspan="2">Cumprimento de prazos (%)</td>
            <td>{{ $kpis['sla_compliance_percent'] }}</td>
            <td colspan="2">No prazo / fora do prazo</td>
            <td>{{ $kpis['sla_no_prazo'] }} / {{ $kpis['sla_criticos'] }}</td>
            <td>Pendentes</td>
            <td>{{ $kpis['sla_pendentes'] }}</td>
        </tr>
        {{-- 8 --}}
        <tr><td colspan="8"></td></tr>
        {{-- 9: cabeçalho da tabela detalhada --}}
        <tr>
            <td>TIPO</td>
            <td>CÓDIGO / REFERÊNCIA</td>
            <td>DATA</td>
            <td>ESPÉCIE</td>
            <td>ASSUNTO / TÍTULO</td>
            <td>DEPARTAMENTO</td>
            <td>PROCEDÊNCIA / AUTOR</td>
            <td>ESTADO</td>
        </tr>

        @foreach ($lists['entradas'] as $doc)
            <tr>
                <td>Entrada</td>
                <td>{{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}</td>
                <td>{{ optional($doc->data_entrada)->format('d/m/Y') }}</td>
                <td>{{ $doc->classificacao_especie }}</td>
                <td>{{ $doc->assunto }}</td>
                <td>{{ optional($doc->departamento)->nome }}</td>
                <td>{{ optional($doc->procedenciaCatalogo)->nome ?: $doc->procedencia }}</td>
                <td>{{ $rotuloEstado($doc->status) }}</td>
            </tr>
        @endforeach

        @foreach ($lists['internos'] as $doc)
            <tr>
                <td>Produzido</td>
                <td>{{ $doc->numero_referencia }}</td>
                <td>{{ optional($doc->created_at)->format('d/m/Y') }}</td>
                <td>{{ optional($doc->especie)->nome }}</td>
                <td>{{ $doc->titulo }}</td>
                <td>{{ optional($doc->departamento)->nome }}</td>
                <td>{{ optional($doc->autor)->name }}</td>
                <td>{{ $rotuloEstado($doc->status) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
