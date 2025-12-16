@extends('layouts.app')

@section('title', 'Protocolo de Entrada')

@php
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
    $rodapeUrl = null;
    foreach ($rodapeCandidates as $candidate) {
        if (file_exists(public_path('images/' . $candidate))) {
            $rodapeUrl = asset('images/' . $candidate);
            break;
        }
    }
@endphp

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3 no-print">
        <h3>Protocolo de Entrada</h3>
        <div>
            <a href="{{ route('documentos-entradas.show', $documento) }}" class="btn btn-secondary">Voltar</a>
            <a href="{{ route('documentos-entradas.protocolo.pdf', $documento) }}" target="_blank" class="btn btn-success" onclick="markAsPrinted()"><i class="fas fa-print me-1"></i> Imprimir</a>
            <span id="printStatus" class="ms-2 small text-muted"></span>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-5">
            
            <!-- Cabeçalho Institucional -->
            <div class="text-center mb-5">
                <img src="{{ asset('images/insignia.png') }}" onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/1/11/Emblem_of_Angola.svg'" alt="Insignia" style="width: 70px; height: auto; margin-bottom: 10px;">
                <div class="fw-bold text-uppercase" style="font-size: 0.9rem; letter-spacing: 1px;">República de Angola</div>
                <div class="fw-bold text-uppercase fs-4 my-1">Governo Provincial do Namibe</div>
            </div>

            <div class="text-center mb-5 border-bottom pb-4">
                <h4 class="mb-2 text-uppercase fw-bold">Protocolo de Entrada de Documento</h4>
                <div class="fs-3 fw-bold">Nº {{ $documento->numero_sequencial }}/{{ $documento->ano_referencia }}</div>
                <div class="small text-muted mt-2">Gerado em {{ optional($protocolo->gerado_em)->format('d/m/Y H:i') }}</div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="text-secondary small text-uppercase fw-bold">Data de Entrada</label>
                        <div class="fs-5">{{ optional($documento->data_entrada)->format('d/m/Y') }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="text-secondary small text-uppercase fw-bold">Espécie</label>
                        <div class="fs-5">{{ $documento->classificacao_especie ?? '—' }}</div>
                    </div>
                     <div class="mb-3">
                        <label class="text-secondary small text-uppercase fw-bold">Procedência</label>
                        <div class="fs-5">{{ $documento->procedencia ?? '—' }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="text-secondary small text-uppercase fw-bold">Registrado por (Usuário)</label>
                        <div class="fs-5 fw-bold">{{ optional($documento->usuario)->name ?? 'N/D' }}</div>
                    </div>
                </div>

                <div class="col-md-6">
                     <div class="mb-3">
                        <label class="text-secondary small text-uppercase fw-bold">Ref. Nº</label>
                        <div class="fs-5">{{ $documento->classificacao_ref_numero ?? '—' }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="text-secondary small text-uppercase fw-bold">Data do Documento</label>
                        <div class="fs-5">{{ optional($documento->data_documento)->format('d/m/Y') ?? '—' }}</div>
                    </div>
                     <div class="mb-3">
                        <label class="text-secondary small text-uppercase fw-bold">Departamento</label>
                        <div class="fs-5">{{ optional($documento->departamento)->nome ?? '—' }}</div>
                    </div>
                </div>
                
                <div class="col-12">
                    <label class="text-secondary small text-uppercase fw-bold">Assunto</label>
                    <div class="fs-5 border-start border-4 border-primary ps-3 bg-light py-2">{{ $documento->assunto }}</div>
                </div>

                @if(!empty($documento->observacoes))
                <div class="col-12">
                    <label class="text-secondary small text-uppercase fw-bold">Observações</label>
                    <div class="border rounded p-3 bg-light text-muted">{{ $documento->observacoes }}</div>
                </div>
                @endif
            </div>

            <div class="mt-5 pt-4 border-top">
                <div class="row align-items-center">
                    <div class="col-md-4 text-center text-md-start">
                        <div class="small text-muted mb-1">Código de Validação</div>
                        <div class="fw-bold fs-5">{{ $protocolo->codigo }}</div>
                    </div>
                     <div class="col-md-4 text-center my-3 my-md-0">
                         {!! QrCode::size(100)->generate($consultaUrl) !!}
                    </div>
                    <div class="col-md-4 text-center text-md-end">
                         <div class="small text-muted">Para consultar a autenticidade:</div>
                         <a href="{{ $consultaUrl }}" class="small text-decoration-none">{{ $consultaUrl }}</a>
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

<style>
@media print {
    @page { size: A5 landscape; margin: 10mm; }
    .no-print, .topbar, aside, footer, .d-lg-none { display: none !important; }
    body { background: #fff !important; margin: 0; padding: 0; overflow: visible !important; }
    .content-area { margin: 0 !important; padding: 0 !important; }
    .container { max-width: 100% !important; padding: 0 !important; margin: 0 !important; width: 100% !important; }
    .card { border: none !important; box-shadow: none !important; }
    .card-body { padding: 0 !important; }
    .fixed-bottom { position: fixed; bottom: 0; left: 0; right: 0; }
    ::-webkit-scrollbar { display: none; }
}
</style>

<script>
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
        if (res.ok) {
            const data = await res.json();
            statusEl.textContent = 'Impresso em: ' + (data.impresso_em ?? 'agora');
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
