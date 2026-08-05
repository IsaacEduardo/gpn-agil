@extends('layouts.app')

@section('title', 'Detalhes do Lote')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="container-fluid py-4">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <a href="{{ route('lotes.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
                <i class="fas fa-arrow-left me-1"></i> Voltar ao Inventário
            </a>
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-layer-group text-primary me-2"></i>Lote: {{ $lote->codigo_lote }}</h1>
            <p class="text-muted">Cadastrado em {{ $lote->created_at->format('d/m/Y H:i') }} por {{ $lote->createdBy->name ?? 'Sistema' }}</p>
        </div>
        <div>
            <a href="{{ route('lotes.edit', $lote) }}" class="btn btn-outline-warning">
                <i class="fas fa-edit me-1"></i> Editar Lote
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-md-5">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-light fw-bold"><i class="fas fa-info-circle me-1"></i> Ficha do Lote</div>
                <div class="card-body">
                    <p class="mb-2"><strong>Status Atual:</strong> <span class="badge {{ $lote->status_badge }} fs-6">{{ $lote->status }}</span></p>
                    <p class="mb-2"><strong>Zoneamento:</strong> <span class="badge bg-light text-dark border">{{ $lote->zoneamento }}</span></p>
                    <p class="mb-2"><strong>Área Total:</strong> <strong>{{ number_format($lote->area_m2, 2, ',', '.') }} m²</strong></p>
                    <p class="mb-2"><strong>Perímetro:</strong> {{ $lote->perimetro_m ? number_format($lote->perimetro_m, 2, ',', '.') . ' m' : 'Não informado' }}</p>
                    <hr>
                    <p class="mb-2"><strong>Matrícula Cartorária:</strong> {{ $lote->matricula_cartoraria ?? 'N/A' }}</p>
                    <p class="mb-2"><strong>Inscrição Imobiliária:</strong> {{ $lote->inscricao_imobiliaria ?? 'N/A' }}</p>
                    <p class="mb-2"><strong>Município / Comuna:</strong> {{ $lote->municipio }} {{ $lote->comuna ? ' / ' . $lote->comuna : '' }}</p>
                    <p class="mb-0"><strong>Bairro / Setor:</strong> {{ $lote->bairro_distrito ?? 'N/A' }} {{ $lote->zona_setor ? ' (' . $lote->zona_setor . ')' : '' }}</p>
                </div>
            </div>

            <!-- MAPA INDIVIDUAL DO LOTE -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light fw-bold"><i class="fas fa-map-marked-alt me-1"></i> Localização no Mapa</div>
                <div class="card-body p-0">
                    <div id="mapaLoteUnico" style="height: 250px; width: 100%;"></div>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-file-signature me-1"></i> Processos de Atribuição Vinculados</span>
                    <span class="badge bg-secondary">{{ $lote->solicitacoes->count() }} processos</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Protocolo</th>
                                <th>Requerente</th>
                                <th>Finalidade</th>
                                <th>Status</th>
                                <th class="text-end">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lote->solicitacoes as $sol)
                                <tr>
                                    <td><code>{{ $sol->numero_protocolo }}</code></td>
                                    <td>{{ $sol->requerente->nome_razao_social ?? 'N/A' }}</td>
                                    <td>{{ $sol->finalidade_uso }}</td>
                                    <td><span class="badge {{ $sol->status_badge }}">{{ $sol->status }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('solicitacoes.show', $sol) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> Ver Processo
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        Nenhum processo de atribuição registrado para este lote.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    var lat = {{ $lote->latitude_centro ?? -15.1961 }};
    var lng = {{ $lote->longitude_centro ?? 12.1522 }};

    var map = L.map('mapaLoteUnico').setView([lat, lng], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(map);

    @if($lote->geojson_geometria)
        try {
            var geojson = {!! $lote->geojson_geometria !!};
            var layer = L.geoJSON(geojson, {
                style: { color: '#198754', weight: 3, fillOpacity: 0.5 }
            }).addTo(map);
            map.fitBounds(layer.getBounds());
        } catch(e) {
            L.marker([lat, lng]).addTo(map).bindPopup("<b>Lote {{ $lote->codigo_lote }}</b>").openPopup();
        }
    @else
        L.marker([lat, lng]).addTo(map).bindPopup("<b>Lote {{ $lote->codigo_lote }}</b>").openPopup();
    @endif
});
</script>
@endsection
