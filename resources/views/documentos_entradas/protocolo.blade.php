@extends('layouts.app')

@section('title', 'Protocolo de Entrada')

@php
    $rodapeUrl = $dadosInstituicao->rodape_url;
    if (!$rodapeUrl) {
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
        foreach ($rodapeCandidates as $candidate) {
            if (file_exists(public_path('images/' . $candidate))) {
                $rodapeUrl = asset('images/' . $candidate);
                break;
            }
        }
    }
    
    $statusImpresso = !empty($protocolo->impresso_em);
@endphp

@section('content')
<!-- Google Fonts for Modern Typography -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">

<div class="container py-4" id="protocol-view">
    <!-- Action Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print pb-3 border-bottom">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('documentos-entradas.index') }}" class="text-decoration-none text-muted">Documentos</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('documentos-entradas.show', $documento) }}" class="text-decoration-none text-muted">Detalhes</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Protocolo</li>
                </ol>
            </nav>
            <h4 class="mb-0 fw-bold text-dark font-outfit">Visualização do Protocolo</h4>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('documentos-entradas.show', $documento) }}" class="btn btn-outline-secondary d-flex align-items-center gap-2 px-3">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
            <a href="{{ route('documentos-entradas.protocolo.etiqueta', [$documento, 'auto_print' => 1]) }}" target="_blank" class="btn btn-success d-flex align-items-center gap-2 px-4 shadow-sm fw-bold" onclick="markAsPrinted()">
                <i class="fas fa-barcode"></i> Imprimir Etiqueta Adesiva
            </a>
            <a href="{{ route('documentos-entradas.protocolo.pdf', $documento) }}" target="_blank" class="btn btn-outline-primary d-flex align-items-center gap-2 px-3 shadow-sm" onclick="markAsPrinted()">
                <i class="fas fa-file-pdf"></i> Recibo A4
            </a>
        </div>
    </div>

    <!-- Main Receipt Card -->
    <div class="receipt-card mx-auto shadow-lg bg-white position-relative overflow-hidden mb-5">
        
        <!-- Status Indicator Ribbons -->
        <div class="status-badge-container no-print">
            <span id="printBadge" class="badge {{ $statusImpresso ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' }} px-3 py-2 rounded-pill font-inter fw-medium">
                <i class="fas {{ $statusImpresso ? 'fa-check-circle' : 'fa-clock' }} me-1"></i>
                <span id="printStatusText">{{ $statusImpresso ? 'Impresso' : 'Aguardando Impressão' }}</span>
            </span>
            <span id="printStatus" class="ms-2 small text-muted font-inter">
                @if($statusImpresso)
                    (Impresso em: {{ $protocolo->impresso_em->format('d/m/Y H:i') }})
                @endif
            </span>
        </div>

        <div class="card-body p-5">
            <!-- Institutional Header -->
            <div class="text-center mb-4 pb-4 border-bottom-dashed">
                <img src="{{ $dadosInstituicao->logo_url }}" alt="Insígnia Oficial" class="institutional-logo mb-3">
                <div class="institutional-title">{{ $dadosInstituicao->cabecalho_linha1 }}</div>
                <div class="institutional-gov text-uppercase">{{ $dadosInstituicao->cabecalho_linha2 }}</div>
                @php($gabDestino = \App\Support\CabecalhoDocumento::linhaGabinete(optional($documento->departamento)->gabinete))
                @if($gabDestino)
                    <div class="institutional-sub py-1">{{ $gabDestino }}</div>
                @endif
            </div>

            <!-- Receipt Subheader -->
            <div class="text-center mb-5">
                <span class="badge bg-light text-secondary text-uppercase tracking-wider px-3 py-2 mb-2 font-inter fw-semibold" style="font-size: 0.75rem;">Comprovativo de Entrada</span>
                <h3 class="font-outfit fw-bold text-dark mb-1">PROTOCOLO DE REGISTO</h3>
                <div class="protocol-number font-outfit">Nº {{ sprintf('%03d/%d', $documento->numero_sequencial, $documento->ano_referencia) }}</div>
                <div class="small text-muted font-inter mt-1">Gerado em {{ optional($protocolo->gerado_em)->format('d/m/Y \à\s H:i') }}</div>
            </div>

            <!-- Data Grid Layout -->
            <div class="info-grid mb-5">
                <div class="row g-0">
                    <div class="col-md-6 border-end-md">
                        <div class="info-item">
                            <span class="info-label">Data de Entrada</span>
                            <span class="info-value">{{ optional($documento->data_entrada)->format('d/m/Y') }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Espécie de Documento</span>
                            <span class="info-value font-medium">{{ $documento->classificacao_especie ?? 'Não especificado' }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Origem / Procedência</span>
                            <span class="info-value">{{ $documento->procedencia ?? '—' }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Registado Por</span>
                            <span class="info-value text-primary font-medium">{{ optional($documento->usuario)->name ?? 'N/D' }}</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item">
                            <span class="info-label">Nº de Referência do Ofício</span>
                            <span class="info-value font-monospace">{{ $documento->classificacao_ref_numero ?? 'S/Nº' }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Data do Documento</span>
                            <span class="info-value">{{ optional($documento->data_documento)->format('d/m/Y') ?? '—' }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Departamento Destinatário</span>
                            <span class="info-value font-medium">{{ optional($documento->departamento)->nome ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Subject Block -->
                <div class="row g-0 border-top">
                    <div class="col-12 p-4">
                        <span class="info-label mb-2 d-block">Assunto</span>
                        <div class="subject-box font-inter text-dark">{{ $documento->assunto }}</div>
                    </div>
                </div>

                <!-- Observations Block -->
                @if(!empty($documento->observacoes))
                <div class="row g-0 border-top">
                    <div class="col-12 p-4 bg-light-subtle">
                        <span class="info-label mb-2 d-block">Observações</span>
                        <div class="text-muted small font-inter" style="line-height: 1.6;">{{ $documento->observacoes }}</div>
                    </div>
                </div>
                @endif
            </div>

            <!-- Verification Footer Card -->
            <div class="validation-footer p-4 border rounded-3 bg-light">
                <div class="row align-items-center g-4">
                    <div class="col-lg-5 text-center text-lg-start">
                        <span class="info-label mb-1 d-block text-uppercase">Código de Validação</span>
                        <div class="d-flex align-items-center justify-content-center justify-content-lg-start gap-2">
                            <span class="fw-bold font-monospace text-dark fs-5">{{ $protocolo->codigo }}</span>
                            <button class="btn btn-sm btn-outline-secondary py-1 px-2 no-print" onclick="copiarCodigo('{{ $protocolo->codigo }}', this)" title="Copiar código">
                                <i class="far fa-copy"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-lg-2 text-center">
                        <div class="qr-code-frame d-inline-block p-2 bg-white rounded shadow-sm">
                            {!! QrCode::size(85)->generate($consultaUrl) !!}
                        </div>
                    </div>
                    <div class="col-lg-5 text-center text-lg-end border-start-lg">
                        <span class="info-label mb-1 d-block text-uppercase">Consulta de Autenticidade</span>
                        <a href="{{ $consultaUrl }}" class="small text-break font-monospace text-primary text-decoration-none no-print">{{ $consultaUrl }}</a>
                        <span class="d-none d-print-block font-monospace text-dark small">{{ $consultaUrl }}</span>
                    </div>
                </div>
            </div>
            
            @if($rodapeUrl)
            <div class="mt-5 text-center d-none d-print-block fixed-bottom">
                 <img src="{{ $rodapeUrl }}" style="width: 100%; max-height: 80px; object-fit: contain;">
            </div>
            @endif
        </div>
    </div>
</div>

<!-- Premium Style Tokens -->
<style>
    #protocol-view {
        font-family: 'Inter', sans-serif;
    }
    .font-inter {
        font-family: 'Inter', sans-serif;
    }
    .font-outfit {
        font-family: 'Outfit', sans-serif;
    }
    .font-medium {
        font-weight: 500;
    }
    .tracking-wider {
        letter-spacing: 0.08em;
    }
    .receipt-card {
        max-width: 820px;
        border-radius: 16px;
        border: 1px solid rgba(0, 0, 0, 0.08);
    }
    .status-badge-container {
        position: absolute;
        top: 24px;
        right: 28px;
        display: flex;
        align-items: center;
    }
    .institutional-logo {
        width: 65px;
        height: auto;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.06));
    }
    .border-bottom-dashed {
        border-bottom: 2px dashed rgba(0, 0, 0, 0.08);
    }
    .institutional-title {
        font-weight: 600;
        font-size: 0.75rem;
        letter-spacing: 2px;
        color: #6c757d;
    }
    .institutional-gov {
        font-weight: 700;
        font-size: 1.15rem;
        color: #212529;
        letter-spacing: 0.5px;
        font-family: 'Outfit', sans-serif;
    }
    .institutional-sub {
        font-weight: 500;
        font-size: 0.8rem;
        color: #495057;
        letter-spacing: 0.5px;
    }
    .protocol-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0d6efd;
        letter-spacing: -0.5px;
    }
    .info-grid {
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 12px;
        overflow: hidden;
    }
    .info-item {
        padding: 18px 24px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }
    .col-md-6 .info-item:last-child {
        border-bottom: none;
    }
    .info-label {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #868e96;
        letter-spacing: 0.8px;
        margin-bottom: 4px;
        display: block;
    }
    .info-value {
        font-size: 0.95rem;
        color: #212529;
        font-weight: 400;
    }
    .subject-box {
        font-size: 1rem;
        font-weight: 500;
        line-height: 1.55;
        color: #212529;
        padding-left: 14px;
        border-left: 3px solid #0d6efd;
    }
    .qr-code-frame {
        border: 1px solid rgba(0, 0, 0, 0.06);
    }
    .validation-footer {
        border: 1px solid rgba(0, 0, 0, 0.08) !important;
    }

    @media (min-width: 768px) {
        .border-end-md {
            border-end: 1px solid rgba(0, 0, 0, 0.08);
            border-right: 1px solid rgba(0, 0, 0, 0.08);
        }
        .border-start-lg {
            border-left: 1px solid rgba(0, 0, 0, 0.08);
        }
    }

    /* Print Optimizations */
    @media print {
        @page { 
            size: A5 landscape; 
            margin: 0; 
        }
        body { 
            background: #fff !important; 
            margin: 0; 
            padding: 8mm; 
            overflow: visible !important; 
            font-size: 10px;
        }
        .no-print, .topbar, aside, footer, header, nav, .navbar, .breadcrumb { 
            display: none !important; 
        }
        .container { 
            max-width: 100% !important; 
            padding: 0 !important; 
            margin: 0 !important; 
            width: 100% !important; 
        }
        .receipt-card { 
            border: none !important; 
            box-shadow: none !important; 
            max-width: 100% !important;
            margin: 0 !important;
        }
        .card-body { 
            padding: 0 !important; 
        }
        .info-grid {
            border: 1px solid #000 !important;
        }
        .info-item {
            padding: 10px 15px !important;
            border-bottom: 1px solid #000 !important;
        }
        .border-end-md {
            border-right: 1px solid #000 !important;
        }
        .fixed-bottom { 
            position: fixed; 
            bottom: 0; 
            left: 0; 
            right: 0; 
        }
        ::-webkit-scrollbar { 
            display: none; 
        }
    }
</style>

<!-- Copy and Async Scripts -->
<script>
function copiarCodigo(texto, btn) {
    navigator.clipboard.writeText(texto).then(() => {
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>';
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-success');
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.classList.remove('btn-success');
            btn.classList.add('btn-outline-secondary');
        }, 1500);
    });
}

function markAsPrinted() {
    const url = @json(route('documentos-entradas.protocolo.impresso', $documento));
    fetch(url, {
        method: 'PATCH',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    }).then(async (res) => {
        const statusEl = document.getElementById('printStatus');
        const badgeEl = document.getElementById('printBadge');
        const badgeTextEl = document.getElementById('printStatusText');
        
        if (res.ok) {
            const data = await res.json();
            
            // Update Text
            const dateStr = data.impresso_em ? new Date(data.impresso_em).toLocaleString('pt-PT', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'}) : 'agora';
            statusEl.textContent = '(Impresso em: ' + dateStr + ')';
            
            // Update Badge colors
            badgeEl.classList.remove('bg-warning-subtle', 'text-warning-emphasis', 'border-warning-subtle');
            badgeEl.classList.add('bg-success-subtle', 'text-success', 'border-success-subtle');
            
            // Update Badge Icon & Label
            badgeEl.querySelector('i').className = 'fas fa-check-circle me-1';
            badgeTextEl.textContent = 'Impresso';
        } else {
            statusEl.textContent = 'Falha ao registrar impressão';
        }
    }).catch(() => {
        const statusEl = document.getElementById('printStatus');
        statusEl.textContent = 'Erro ao registrar impressão';
    });
}

window.addEventListener('afterprint', markAsPrinted);
</script>
@endsection
