<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Relatório de Documentos de Entrada</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }

        .header h1 {
            font-size: 18px;
            margin: 0;
            color: #2c3e50;
        }

        .header p {
            margin: 5px 0 0;
            color: #7f8c8d;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table th,
        table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .footer {
            text-align: center;
            font-size: 10px;
            color: #7f8c8d;
            margin-top: 20px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .badge {
            padding: 3px 6px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 11px;
        }

        .bg-success {
            background-color: #dff0d8;
            color: #3c763d;
        }

        .bg-danger {
            background-color: #f2dede;
            color: #a94442;
        }

        .bg-secondary {
            background-color: #ecf0f1;
            color: #7f8c8d;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1>RELATÓRIO DE DOCUMENTOS DE ENTRADA</h1>
        <p>Data de geração: {{ date('d/m/Y H:i') }}</p>
        @isset($filtersSummary)
            @php($pairs = collect($filtersSummary ?? []))
            @if ($pairs->count())
                <p style="margin-top:6px">Filtros:
                    {{ $pairs->map(function ($v, $k) {return $k . ': ' . $v;})->join(' • ') }}</p>
            @endif
        @endisset
    </div>

    <table>
        <thead>
            <tr>
                <th>Nº</th>
                <th>Ano</th>
                <th>Entrada</th>
                <th>Espécie</th>
                <th>Ref. Nº</th>
                <th>Procedência</th>
                <th>Assunto</th>
                <th>Departamento</th>
                <th>Status</th>
                <th>Visto Dep.</th>
                <th>Visto Gab.</th>
                <th>Saída Gab.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($documentos as $doc)
                <tr>
                    <td>{{ sprintf('%03d', (int) $doc->numero_sequencial) }}</td>
                    <td>{{ $doc->ano_referencia }}</td>
                    <td>{{ optional($doc->data_entrada)->format('d/m/Y') }}</td>
                    <td>{{ $doc->classificacao_especie }}</td>
                    <td>{{ $doc->classificacao_ref_numero }}</td>
                    <td>{{ $doc->procedencia }}</td>
                    <td>{{ $doc->assunto }}</td>
                    <td>{{ optional($doc->departamento)->nome }}</td>
                    <td>{{ $doc->status }}</td>
                    <td>
                        @if ($doc->visto_departamento_status === 'aprovado')
                            <span class="badge bg-success">Aprovado</span>
                        @elseif($doc->visto_departamento_status === 'rejeitado')
                            <span class="badge bg-danger">Rejeitado</span>
                        @else
                            <span class="badge bg-secondary">Pendente</span>
                        @endif
                    </td>
                    <td>
                        @if ($doc->visto_gabinete_status === 'aprovado')
                            <span class="badge bg-success">Aprovado</span>
                        @elseif($doc->visto_gabinete_status === 'rejeitado')
                            <span class="badge bg-danger">Rejeitado</span>
                        @else
                            <span class="badge bg-secondary">Pendente</span>
                        @endif
                    </td>
                    <td>{{ optional($doc->saida_gabinete_data)->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" style="text-align: center;">Nenhum documento encontrado</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>© {{ date('Y') }} GPN-AGIL - Gestão de Documentos</p>
    </div>
</body>

</html>
