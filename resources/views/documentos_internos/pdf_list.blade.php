<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório de Documentos Internos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 16pt;
        }
        .header p {
            margin: 5px 0 0;
            font-size: 9pt;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 9pt;
        }
        td {
            font-size: 9pt;
        }
        tr:nth-child(even) {
            background-color: #fafafa;
        }
        .badge {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8pt;
            font-weight: bold;
        }
        .badge-rascunho { background-color: #e2e8f0; color: #475569; }
        .badge-finalizado { background-color: #dcfce7; color: #166534; }
        
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 8pt;
            text-align: center;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Relatório de Documentos Internos</h1>
        <p>Ondaka - Sistema Integrado de Gestão</p>
        <p>Gerado em: {{ now()->format('d/m/Y H:i') }} | Por: {{ Auth::user()->name }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Referência</th>
                <th>Título</th>
                <th>Espécie</th>
                <th>Autor</th>
                <th>Status</th>
                <th>Data Criação</th>
                <th>Data Assinatura</th>
            </tr>
        </thead>
        <tbody>
            @foreach($documentos as $doc)
                <tr>
                    <td>{{ $doc->numero_referencia }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($doc->titulo, 50) }}</td>
                    <td>{{ $doc->especie->nome ?? '-' }}</td>
                    <td>{{ $doc->autor->name ?? '-' }}</td>
                    <td>
                        <span class="badge badge-{{ $doc->status->value }}">
                            {{ $doc->status->label() }}
                        </span>
                    </td>
                    <td>{{ $doc->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $doc->assinado_em ? $doc->assinado_em->format('d/m/Y H:i') : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Documento gerado eletronicamente. Confidencial.</p>
    </div>
</body>
</html>
