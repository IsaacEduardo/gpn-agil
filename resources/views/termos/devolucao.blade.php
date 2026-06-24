<!DOCTYPE html>
<html lang="pt-BR">

<head>
    @php
        $insigniaSrc = $dadosInstituicao->logo_absolute_path;
        $footerImg = '';
        $rodapePath = $dadosInstituicao->rodape_absolute_path;
        if (!$rodapePath) {
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
                    $rodapePath = $candidate;
                    break;
                }
            }
        }
        if ($rodapePath && file_exists($rodapePath)) {
            $mime = mime_content_type($rodapePath);
            if (in_array($mime, ['image/png', 'image/jpeg', 'image/jpg'])) {
                $footerImg = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($rodapePath));
            }
        }
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termo de Devolução - {{ $requisicao->codigo_sequencial }}</title>
    <style>
        .header { text-align: center; line-height: 1.4; margin-bottom: 8mm; }
        .insignia { display: block; margin: 0 auto 3mm; width: 22mm; height: auto; }
        .header .title { font-weight: bold; font-size: 12pt; }
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
            margin-bottom: 30px;
            border-bottom: 2px solid #dc3545;
            padding-bottom: 15px;
        }

        .header h1 {
            font-size: 20px;
            margin: 0;
            color: #dc3545;
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
            color: #dc3545;
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
            color: #dc3545;
        }

        .items-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .condition-section {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .condition-title {
            font-weight: bold;
            color: #856404;
            margin-bottom: 10px;
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
            color: #dc3545;
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
            background-color: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }

        .warning-box {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .warning-title {
            font-weight: bold;
            color: #721c24;
            margin-bottom: 10px;
        }
        .pdf-footer { position: fixed; left: 0; right: 0; bottom: 0; text-align: center; }
        .pdf-footer img { width: 100%; height: auto; }
    </style>
</head>

<body>
    <div class="header">
        <img class="insignia" src="{{ $insigniaSrc }}" alt="Insígnia da República de Angola">
        <div class="title">{{ $dadosInstituicao->cabecalho_linha1 }}</div>
        <div class="title">{{ $dadosInstituicao->cabecalho_linha2 }}</div>
        @if($dadosInstituicao->cabecalho_linha3)
            <div class="title">{{ $dadosInstituicao->cabecalho_linha3 }}</div>
        @else
            <div class="title">SECRETARIA GERAL</div>
        @endif
        <h1>TERMO DE DEVOLUÇÃO</h1>
        <h2>Ondaka - Sistema de Gestão de Logística e Patrimônio</h2>
    </div>

    <div class="warning-box">
        <div class="warning-title">⚠️ IMPORTANTE - TERMO DE DEVOLUÇÃO</div>
        <p>Este documento comprova a devolução dos itens/serviços relacionados à requisição abaixo especificada.
            Todos os itens devem ser devolvidos nas mesmas condições em que foram entregues, salvo desgaste natural do
            uso.</p>
    </div>

    <div class="info-section">
        <h3>Informações da Requisição Original</h3>
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
                <div class="info-label">Solicitante Original:</div>
                <div class="info-value">{{ $requisicao->usuario?->name ?? '—' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Empresa Destinatária:</div>
                <div class="info-value">{{ $requisicao->empresa_destinataria }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status:</div>
                <div class="info-value"><span class="status-badge">DEVOLUÇÃO</span></div>
            </div>
            <div class="info-row">
                <div class="info-label">Data da Devolução:</div>
                <div class="info-value">{{ now()->format('d/m/Y H:i') }}</div>
            </div>
        </div>
    </div>

    @if ($requisicao->tipo == 'produto' && $requisicao->produtos)
        <div class="info-section">
            <h3>Produtos a Devolver</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Quantidade Original</th>
                        <th>Quantidade Devolvida</th>
                        <th>Estado de Conservação</th>
                        <th>Observações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requisicao->produtos as $produto)
                        <tr>
                            <td>{{ $produto->descricao }}</td>
                            <td>{{ $produto->quantidade }}</td>
                            <td>_________</td>
                            <td>_________</td>
                            <td>_________</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif($requisicao->tipo == 'oficina' && $requisicao->oficina)
        <div class="info-section">
            <h3>Devolução de Viatura - Serviços de Oficina</h3>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Viatura:</div>
                    <div class="info-value">{{ $requisicao->oficina->viatura->prefixo ?? 'N/A' }} -
                        {{ $requisicao->oficina->viatura->placa ?? 'N/A' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Quilometragem na Entrega:</div>
                    <div class="info-value">{{ number_format($requisicao->oficina->quilometragem_atual) }} km</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Quilometragem na Devolução:</div>
                    <div class="info-value">_________________ km</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Serviços Executados:</div>
                    <div class="info-value">{{ $requisicao->oficina->servicos_solicitados }}</div>
                </div>
            </div>
        </div>

        <div class="condition-section">
            <div class="condition-title">Estado da Viatura na Devolução</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Combustível:</div>
                    <div class="info-value">☐ Cheio ☐ 3/4 ☐ 1/2 ☐ 1/4 ☐ Vazio</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Limpeza:</div>
                    <div class="info-value">☐ Limpa ☐ Suja ☐ Muito Suja</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Avarias:</div>
                    <div class="info-value">☐ Sem Avarias ☐ Com Avarias (especificar abaixo)</div>
                </div>
            </div>
            <p><strong>Especificar Avarias:</strong> _________________________________________________</p>
        </div>
    @elseif($requisicao->tipo == 'servico' && $requisicao->servicos)
        <div class="info-section">
            <h3>Devolução - Serviços Gerais</h3>
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
                    <div class="info-label">Data de Execução:</div>
                    <div class="info-value">
                        {{ $requisicao->servicos?->data_prevista_execucao ? \Carbon\Carbon::parse($requisicao->servicos->data_prevista_execucao)->format('d/m/Y') : '—' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="condition-section">
            <div class="condition-title">Estado dos Equipamentos/Materiais na Devolução</div>
            <p><strong>Equipamentos Devolvidos:</strong> _________________________________________________</p>
            <p><strong>Estado de Conservação:</strong> _________________________________________________</p>
            <p><strong>Observações:</strong> _________________________________________________</p>
        </div>
    @endif

    <div class="info-section">
        <h3>Motivo da Devolução</h3>
        <div class="condition-section">
            <p>☐ Conclusão do serviço/projeto</p>
            <p>☐ Cancelamento da requisição</p>
            <p>☐ Substituição por outro item/serviço</p>
            <p>☐ Defeito/Problema técnico</p>
            <p>☐ Outros: _________________________________________________</p>
        </div>
    </div>

    <div class="signature-section">
        <h3>Assinaturas e Confirmações de Devolução</h3>

        <div class="signature-box">
            <div class="signature-title">DEVOLVIDO POR:</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Nome:</div>
                    <div class="info-value">{{ $requisicao->usuario->name }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Cargo:</div>
                    <div class="info-value">_________________________________</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Data:</div>
                    <div class="info-value">{{ now()->format('d/m/Y') }}</div>
                </div>
            </div>
            <div class="signature-line">Assinatura</div>
        </div>

        <div class="signature-box">
            <div class="signature-title">RECEBIDO POR (RESPONSÁVEL PELO PATRIMÔNIO):</div>
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

        <div class="signature-box">
            <div class="signature-title">APROVAÇÃO DA DEVOLUÇÃO:</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Chefe do Departamento:</div>
                    <div class="info-value">_________________________________</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Data:</div>
                    <div class="info-value">_________________________________</div>
                </div>
            </div>
            <div class="signature-line">Assinatura e Carimbo</div>
        </div>
    </div>

    <div class="footer">
        <p>© {{ date('Y') }} Ondaka - Sistema de Gestão de Logística e Patrimônio</p>
        <p>Termo de Devolução gerado automaticamente em {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
    @if (!empty($footerImg))
        <div class="pdf-footer">
            <img src="{{ $footerImg }}" alt="Rodapé institucional">
        </div>
    @endif
</body>

</html>
