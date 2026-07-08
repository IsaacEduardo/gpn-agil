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
    <title>Termo de Entrega de Viatura</title>
    <style>
        .header { text-align: center; line-height: 1.4; margin-bottom: 8mm; }
        .insignia { display: block; margin: 0 auto 3mm; width: 22mm; height: auto; }
        .header .title { font-weight: bold; font-size: 12pt; }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .section {
            margin-bottom: 14px;
        }

        .title {
            font-size: 16px;
            font-weight: bold;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            border: 1px solid #ccc;
            padding: 8px;
        }

        .footer {
            margin-top: 30px;
            font-size: 11px;
            color: #666;
        }

        .sign {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }

        .sign .box {
            width: 45%;
            text-align: center;
        }

        .muted {
            color: #666;
        }
        .pdf-footer { position: fixed; left: 0; right: 0; bottom: 0; text-align: center; }
        .pdf-footer img { width: 100%; height: auto; }
    </style>
</head>

<body>
    <div class="header">
        @include('partials.document-header', [
            'logoSrc' => $insigniaSrc,
            'gabineteNome' => \App\Support\CabecalhoDocumento::linhaGabinete(
                (isset($requisicao) && $requisicao->gabinete) ? $requisicao->gabinete : optional(auth()->user())->gabinete()
            ),
        ])
        <div class="title">TERMO DE ENTREGA DE VIATURA</div>
        <div class="muted">Emitido em {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    <div class="section">
        <strong>Beneficiário:</strong> {{ $termo->beneficiario_nome }}
        @if (!empty($termo->beneficiario_documento))
            <br>
            <strong>Documento:</strong> {{ $termo->beneficiario_documento }}
        @endif
        @if (!empty($termo->beneficiario_setor))
            <br>
            <strong>Setor:</strong> {{ $termo->beneficiario_setor }}
        @endif
    </div>

    <div class="section">
        <table class="table">
            <thead>
                <tr>
                    <th>Identificação</th>
                    <th>Placa</th>
                    <th>Modelo</th>
                    <th>Marca</th>
                    <th>Ano</th>
                    <th>Tipo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($viaturas as $v)
                    <tr>
                        <td>{{ $v->identificacao ?? '—' }}</td>
                        <td>{{ $v->placa ?? '—' }}</td>
                        <td>{{ $v->modelo ?? '—' }}</td>
                        <td>{{ $v->marca ?? '—' }}</td>
                        <td>{{ $v->ano ?? '—' }}</td>
                        <td>{{ $v->tipo ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;">Nenhuma viatura selecionada</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (!empty($termo->observacoes))
        <div class="section">
            <strong>Observações:</strong><br>
            <span>{{ $termo->observacoes }}</span>
        </div>
    @endif

    <div class="section">
        O beneficiário declara receber a viatura acima identificada, comprometendo-se com sua utilização adequada, zelo
        e devolução quando solicitada, observando as políticas internas de uso e manutenção.
    </div>

    <div class="sign">
        <div class="box">
            ________________________________<br>
            Entregador
        </div>
        <div class="box">
            ________________________________<br>
            Beneficiário: {{ $termo->beneficiario_nome }}
        </div>
    </div>

    <div class="footer">
        Ondaka • Sistema de Gestão de Logística e Patrimônio
    </div>

    @if (!empty($footerImg))
        <div class="pdf-footer">
            <img src="{{ $footerImg }}" alt="Rodapé institucional">
        </div>
    @endif
</body>

</html>