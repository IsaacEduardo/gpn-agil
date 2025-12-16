<!DOCTYPE html>
<html lang="pt-BR">
<head>
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
    <meta charset="UTF-8">
    <title>Termo de Entrega a Título Devolutivo</title>
    <style>
        .header { text-align: center; line-height: 1.4; margin-bottom: 8mm; }
        .insignia { display: block; margin: 0 auto 3mm; width: 22mm; height: auto; }
        .header .title { font-weight: bold; font-size: 12pt; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .section { margin-bottom: 14px; }
        .title { font-size: 16px; font-weight: bold; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { border: 1px solid #ccc; padding: 8px; }
        .footer { margin-top: 30px; font-size: 11px; color: #666; }
        .sign { margin-top: 30px; display: flex; justify-content: space-between; }
        .sign .box { width: 45%; text-align: center; }
        .muted { color: #666; }
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
        <div class="title">TERMO DE ENTREGA A TÍTULO DEVOLUTIVO</div>
        <div class="muted">Emitido em {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    <div class="section">
        <strong>Beneficiário:</strong> {{ $termo->beneficiario_nome }}
        @if(!empty($termo->beneficiario_documento))<br>
        <strong>Documento:</strong> {{ $termo->beneficiario_documento }}@endif
        @if(!empty($termo->beneficiario_setor))<br>
        <strong>Setor:</strong> {{ $termo->beneficiario_setor }}@endif
    </div>

    <div class="section">
        <table class="table">
            <thead>
                <tr>
                    <th>Item/Descrição</th>
                    <th>Quantidade</th>
                    <th>Unidade</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item['descricao'] }}</td>
                        <td>{{ $item['quantidade'] ?? '—' }}</td>
                        <td>{{ $item['unidade'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if(!empty($termo->observacoes))
    <div class="section">
        <strong>Observações:</strong><br>
        <span>{{ $termo->observacoes }}</span>
    </div>
    @endif

    <div class="section">
        O(s) item(ns) acima descrito(s) são entregues ao beneficiário a título de empréstimo, devendo ser devolvidos em perfeitas condições de uso quando solicitados ou ao término do prazo estabelecido pelas normas internas da instituição.
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
        GPN-AGIL • Sistema de Gestão de Logística e Patrimônio
    </div>

    @if (!empty($footerImg))
        <div class="pdf-footer">
            <img src="{{ $footerImg }}" alt="Rodapé institucional">
        </div>
    @endif
</body>
</html>