<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termo de Entrega Padrão - {{ $requisicao->codigo_sequencial }}</title>
    @php
        $insigniaLocal = public_path('images/insignia.png');
        $insigniaSrc = file_exists($insigniaLocal) ? $insigniaLocal : 'https://upload.wikimedia.org/wikipedia/commons/1/11/Emblem_of_Angola.svg';
        $footerImg = '';
        $rodapeCandidates = [
            public_path('images/rodape_estacionario.png'),
            public_path('images/rodape_estacionario.jpg'),
            public_path('images/Estacionariodoc.jpg'),
            public_path('images/Estacionariodoc.png'),
            public_path('images/estacionario.png'),
            public_path('images/estacionario.jpg'),
        ];
        foreach ($rodapeCandidates as $candidate) {
            if (file_exists($candidate)) {
                $mime = mime_content_type($candidate);
                if (in_array($mime, ['image/png', 'image/jpeg', 'image/jpg'])) {
                    $footerImg = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($candidate));
                    break;
                }
            }
        }
    @endphp
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            line-height: 1.4;
            margin-bottom: 8mm;
        }

        .insignia { display: block; margin: 0 auto 3mm; width: 22mm; height: auto; }
        .header .title { font-weight: bold; font-size: 12pt; }

        .header h1 {
            font-size: 20px;
            margin: 0;
            color: #0d6efd;
            font-weight: bold;
        }

        .header h2 {
            font-size: 16px;
            margin: 5px 0 0;
            color: #666;
        }

        .info-section {
            margin-bottom: 20px;
        }

        .info-section h3 {
            font-size: 14px;
            color: #0d6efd;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }

        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            font-weight: bold;
            width: 30%;
            padding: 5px 10px 5px 0;
            vertical-align: top;
        }

        .info-value {
            display: table-cell;
            padding: 5px 0;
            vertical-align: top;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .items-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #0d6efd;
        }

        .items-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .signature-section {
            margin-top: 40px;
            page-break-inside: avoid;
        }

        .signature-box {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 20px;
            min-height: 80px;
        }

        .signature-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #0d6efd;
        }

        .signature-line {
            border-bottom: 1px solid #333;
            margin-top: 40px;
            text-align: center;
            padding-top: 5px;
        }

        .footer {
            text-align: center;
            font-size: 10px;
            color: #666;
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .status-badge {
            background-color: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }
        .pdf-footer { position: fixed; left: 0; right: 0; bottom: 0; text-align: center; }
        .pdf-footer img { width: 100%; height: auto; }
    </style>
</head>

<body>
    <div class="header">
        <img class="insignia" src="{{ $insigniaSrc }}" alt="Insígnia da República de Angola">
        <div class="title">REPÚBLICA DE ANGOLA</div>
        <div class="title">SECRETARIA GERAL</div>
        <div class="title">DLP</div>
        <h1>TERMO DE ENTREGA PADRÃO</h1>
        <h2>GPN-AGIL - Sistema de Gestão de Logística e Patrimônio</h2>
    </div>

    <div class="info-section">
        <h3>Informações da Requisição</h3>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Código da Requisição:</div>
                <div class="info-value">{{ $requisicao->codigo_sequencial }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Data da Requisição:</div>
                <div class="info-value">{{ \Carbon\Carbon::parse($requisicao->created_at)->format('d/m/Y H:i') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Solicitante:</div>
                <div class="info-value">{{ $requisicao->usuario?->name ?? '—' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Empresa Destinatária:</div>
                <div class="info-value">{{ $requisicao->empresa_destinataria }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status:</div>
                <div class="info-value"><span class="status-badge">{{ strtoupper($requisicao->status) }}</span></div>
            </div>
        </div>
    </div>

    @if ($requisicao->tipo == 'produto' && $requisicao->produtos)
        <div class="info-section">
            <h3>Produtos Solicitados</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Quantidade</th>
                        <th>Unidade</th>
                        <th>Valor Unitário</th>
                        <th>Valor Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requisicao->produtos as $produto)
                        <tr>
                            <td>{{ $produto->descricao }}</td>
                            <td>{{ $produto->quantidade }}</td>
                            <td>{{ $produto->unidade }}</td>
                            <td>{{ number_format($produto->valor_unitario / 100, 2, ',', '.') }} Kz</td>
                            <td>{{ number_format(($produto->quantidade * $produto->valor_unitario) / 100, 2, ',', '.') }}
                                Kz</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif($requisicao->tipo == 'oficina' && $requisicao->oficina)
        <div class="info-section">
            <h3>Serviços de Oficina</h3>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Viatura:</div>
                    <div class="info-value">{{ $requisicao->oficina->viatura->prefixo ?? 'N/A' }} -
                        {{ $requisicao->oficina->viatura->placa ?? 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Quilometragem:</div>
                    <div class="info-value">{{ number_format($requisicao->oficina->quilometragem_atual) }} km</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Problema Relatado:</div>
                    <div class="info-value">{{ $requisicao->oficina->descricao_problema }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Serviços Solicitados:</div>
                    <div class="info-value">{{ $requisicao->oficina->servicos_solicitados }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Urgência:</div>
                    <div class="info-value">{{ ucfirst($requisicao->oficina->urgencia) }}</div>
                </div>
            </div>
        </div>
    @elseif($requisicao->tipo == 'servico' && $requisicao->servicos)
        <div class="info-section">
            <h3>Serviços Gerais</h3>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Tipo de Serviço:</div>
                    <div class="info-value">{{ $requisicao->servicos?->tipo_servico }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Descrição:</div>
                    <div class="info-value">{{ $requisicao->servicos?->descricao_servico }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Local de Execução:</div>
                    <div class="info-value">{{ $requisicao->servicos?->local_execucao }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Data Prevista:</div>
                    <div class="info-value">
                        {{ $requisicao->servicos?->data_prevista_execucao ? \Carbon\Carbon::parse($requisicao->servicos->data_prevista_execucao)->format('d/m/Y') : '—' }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($requisicao->observacoes)
        <div class="info-section">
            <h3>Observações</h3>
            <p>{{ $requisicao->observacoes }}</p>
        </div>
    @endif

    <div class="signature-section">
        <h3>Assinaturas e Confirmações</h3>

        <div class="signature-box">
            <div class="signature-title">ENTREGUE POR:</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Nome:</div>
                    <div class="info-value">{{ $requisicao->aprovador->name ?? 'Aprovador do Sistema' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Data:</div>
                    <div class="info-value">{{ now()->format('d/m/Y') }}</div>
                </div>
            </div>
            <div class="signature-line">Assinatura</div>
        </div>

        <div class="signature-box">
            <div class="signature-title">RECEBIDO POR:</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Nome:</div>
                    <div class="info-value">_________________________________</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Cargo:</div>
                    <div class="info-value">_________________________________</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Data:</div>
                    <div class="info-value">_________________________________</div>
                </div>
            </div>
            <div class="signature-line">Assinatura</div>
        </div>
    </div>

    <div class="footer">
        <p>© {{ date('Y') }} GPN-AGIL - Sistema de Gestão de Logística e Patrimônio</p>
        <p>Documento gerado automaticamente em {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
    @if (!empty($footerImg))
        <div class="pdf-footer">
            <img src="{{ $footerImg }}" alt="Rodapé institucional">
        </div>
    @endif
</body>

</html>
