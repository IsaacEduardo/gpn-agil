<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório de Viaturas</title>
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
        table th, table td {
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
        .status-operacional {
            padding: 3px 6px;
            border-radius: 3px;
            font-weight: bold;
        }
        .status-operacional-operacional {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .status-operacional-manutencao {
            background-color: #fcf8e3;
            color: #8a6d3b;
        }
        .status-operacional-inoperante {
            background-color: #f2dede;
            color: #a94442;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>RELATÓRIO DE VIATURAS</h1>
        <p>Data de geração: {{ date('d/m/Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Identificação</th>
                <th>Matrícula</th>
                <th>Modelo</th>
                <th>Marca</th>
                <th>Ano</th>
                <th>Tipo</th>
                <th>Status</th>
                <th>Afetação</th>
            </tr>
        </thead>
        <tbody>
            @forelse($viaturas as $viatura)
                <tr>
                    <td>{{ $viatura->id }}</td>
                    <td>{{ $viatura->identificacao }}</td>
                    <td>{{ $viatura->placa }}</td>
                    <td>{{ $viatura->modelo }}</td>
                    <td>{{ $viatura->marca }}</td>
                    <td>{{ $viatura->ano }}</td>
                    <td>{{ $viatura->tipo }}</td>
                    <td>
                        @if($viatura->status_operacional == 'Operacional')
                            <span class="status-operacional status-operacional-operacional">{{ $viatura->status_operacional }}</span>
                        @elseif($viatura->status_operacional == 'Em manutenção')
                            <span class="status-operacional status-operacional-manutencao">{{ $viatura->status_operacional }}</span>
                        @else
                            <span class="status-operacional status-operacional-inoperante">{{ $viatura->status_operacional }}</span>
                        @endif
                    </td>
                    <td>{{ $viatura->afetacao ?? 'Não definida' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center;">Nenhuma viatura encontrada</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <p>© {{ date('Y') }} GPN-AGIL - Gestão de Viaturas</p>
    </div>
</body>
</html>