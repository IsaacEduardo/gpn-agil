@php
    use Carbon\Carbon;
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
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Protocolo de Entrada</title>
    <style>
        @page { margin: 0mm; }
        body { margin: 5mm 10mm 10mm 10mm; font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        
        .header-institucional { text-align: center; margin-bottom: 10px; margin-top: 5mm; }
        .header-institucional .insignia { width: 50px; height: auto; margin-bottom: 2px; }
        .header-institucional .title { font-weight: bold; font-size: 9pt; text-transform: uppercase; }
        .header-institucional .gov { font-weight: bold; font-size: 12pt; text-transform: uppercase; margin: 2px 0; }
        
        .box { border: 1px solid #ccc; padding: 10px 15px; border-radius: 5px; margin-top: 5px; background: #fff; }
        .protocol-header { text-align: center; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .protocol-header h3 { margin: 0 0 2px; text-transform: uppercase; color: #333; font-size: 10pt; }
        .protocol-header h2 { margin: 0; color: #000; font-size: 14pt; }
        
        .grid { display: table; width: 100%; border-collapse: collapse; margin-top: 5px; }
        .row { display: table-row; }
        .col { display: table-cell; width: 50%; padding: 4px 5px; vertical-align: top; border-bottom: 1px solid #f5f5f5; }
        .label { font-weight: bold; color: #555; font-size: 9px; text-transform: uppercase; display: block; margin-bottom: 1px; }
        .value { font-size: 11px; color: #000; }
        .value-bold { font-weight: bold; }
        
        .section { margin-top: 10px; padding: 5px; background-color: #f9f9f9; border-radius: 4px; border: 1px solid #eee; }
        .muted { color: #666; font-style: italic; }
        
        .footer-info { margin-top: 15px; font-size: 9px; color: #777; border-top: 1px solid #eee; padding-top: 5px; text-align: center; }
        
        .pdf-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: auto;
            z-index: -1000;
        }
        .pdf-footer img {
            width: 100%;
        }
    </style>
</head>
<body>

    @if($rodapeBase64)
    <div class="pdf-footer">
        <img src="{{ $rodapeBase64 }}">
    </div>
    @endif

    <div class="header-institucional">
        <img src="{{ $insigniaSrc }}" alt="Insignia" class="insignia">
        <div class="title">República de Angola</div>
        <div class="gov">Governo Provincial do Namibe</div>
    </div>

    <div class="box">
        <div class="protocol-header">
            <h3>Protocolo de Entrada de Documento</h3>
            <h2>Nº {{ sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia) }}</h2>
        </div>

        <div class="grid">
            <div class="row">
                <div class="col">
                    <span class="label">Data de Entrada</span>
                    <span class="value">{{ optional(\Carbon\Carbon::parse($documento->data_entrada))->format('d/m/Y') }}</span>
                </div>
                <div class="col">
                    <span class="label">Espécie</span>
                    <span class="value">{{ $documento->classificacao_especie ?? 'N/A' }}</span>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <span class="label">Referência</span>
                    <span class="value">{{ $documento->classificacao_ref_numero ?? 'N/A' }}</span>
                </div>
                <div class="col">
                    <span class="label">Data do Documento</span>
                    <span class="value">{{ $documento->data_documento ? \Carbon\Carbon::parse($documento->data_documento)->format('d/m/Y') : 'N/A' }}</span>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <span class="label">Procedência</span>
                    <span class="value">{{ $documento->procedencia }}</span>
                </div>
                <div class="col">
                    <span class="label">Assunto</span>
                    <span class="value">{{ $documento->assunto }}</span>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <span class="label">Registrado por (Usuário)</span>
                    <span class="value value-bold">{{ optional($documento->usuario)->name ?? 'N/D' }}</span>
                </div>
                <div class="col">
                    <span class="label">Departamento</span>
                     <span class="value">{{ optional($documento->departamento)->nome ?? 'N/D' }}</span>
                </div>
            </div>
        </div>

        @if(!empty($documento->observacoes))
        <div class="section">
            <span class="label">Observações</span>
            <span class="muted">{{ $documento->observacoes }}</span>
        </div>
        @endif
        
        <div class="footer-info">
            <strong>Código de Validação:</strong> {{ $protocolo->codigo ?? '—' }} &nbsp;|&nbsp;
            <strong>Consulte a autenticidade em:</strong> {{ $consultaUrl }}
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 10px;">
         <span style="font-size: 9px; color: #999;">Emitido em {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}</span>
    </div>

</body>
</html>
