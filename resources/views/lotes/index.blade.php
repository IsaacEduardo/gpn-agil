@extends('layouts.app')

@section('title', 'Inventário de Lotes de Terra')

@section('content')
<!-- Leaflet.js CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-map-marked-alt text-primary me-2"></i>Inventário de Lotes de Terra</h1>
            <p class="text-muted">Gestão territorial, áreas, zoneamento e mapa de geolocalização no Província do Namibe.</p>
        </div>
        <a href="{{ route('lotes.create') }}" class="btn btn-primary shadow-sm">
            <i class="fas fa-plus-circle me-1"></i> Cadastrar Novo Lote
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- MAPA INTERATIVO LEAFLET.JS -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <span class="fw-bold"><i class="fas fa-globe-africa text-success me-2"></i>Mapa Territorial Interativo (Geolocalização Leaflet.js)</span>
            <div>
                <span class="badge bg-success me-1">Disponível</span>
                <span class="badge bg-info me-1">Reservado</span>
                <span class="badge bg-danger me-1">Atribuído</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div id="mapaLotes" style="height: 380px; width: 100%; border-radius: 0 0 0.375rem 0.375rem;"></div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('lotes.index') }}" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Buscar Código Lote, Matrícula ou Bairro..." value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">-- Todos os Status --</option>
                        <option value="DISPONIVEL" {{ request('status') === 'DISPONIVEL' ? 'selected' : '' }}>Disponível</option>
                        <option value="RESERVADO" {{ request('status') === 'RESERVADO' ? 'selected' : '' }}>Reservado</option>
                        <option value="ATRIBUIDO" {{ request('status') === 'ATRIBUIDO' ? 'selected' : '' }}>Atribuído</option>
                        <option value="EM_LICITACAO" {{ request('status') === 'EM_LICITACAO' ? 'selected' : '' }}>Em Licitação</option>
                        <option value="INDISPONIVEL" {{ request('status') === 'INDISPONIVEL' ? 'selected' : '' }}>Indisponível</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="zoneamento" class="form-select">
                        <option value="">-- Todos os Zoneamentos --</option>
                        <option value="HABITACIONAL" {{ request('zoneamento') === 'HABITACIONAL' ? 'selected' : '' }}>Habitacional</option>
                        <option value="COMERCIAL" {{ request('zoneamento') === 'COMERCIAL' ? 'selected' : '' }}>Comercial</option>
                        <option value="INDUSTRIAL" {{ request('zoneamento') === 'INDUSTRIAL' ? 'selected' : '' }}>Industrial</option>
                        <option value="AGRICOLA" {{ request('zoneamento') === 'AGRICOLA' ? 'selected' : '' }}>Agrícola</option>
                        <option value="EQUIPAMENTO_PUBLICO" {{ request('zoneamento') === 'EQUIPAMENTO_PUBLICO' ? 'selected' : '' }}>Equipamento Público</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-outline-primary"><i class="fas fa-filter me-1"></i> Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- TABELA -->
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Código Lote</th>
                        <th>Município / Bairro</th>
                        <th>Área (m²)</th>
                        <th>Zoneamento</th>
                        <th>Status</th>
                        <th>Geolocalização</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lotes as $lote)
                        <tr>
                            <td>
                                <strong><a href="{{ route('lotes.show', $lote) }}" class="text-decoration-none text-dark">{{ $lote->codigo_lote }}</a></strong>
                                @if($lote->matricula_cartoraria)
                                    <br><small class="text-muted"><i class="fas fa-bookmark me-1"></i>Mat: {{ $lote->matricula_cartoraria }}</small>
                                @endif
                            </td>
                            <td>{{ $lote->municipio }} {{ $lote->bairro_distrito ? ' / ' . $lote->bairro_distrito : '' }}</td>
                            <td><strong>{{ number_format($lote->area_m2, 2, ',', '.') }}</strong> m²</td>
                            <td><span class="badge bg-light text-dark border">{{ $lote->zoneamento }}</span></td>
                            <td><span class="badge {{ $lote->status_badge }}">{{ $lote->status }}</span></td>
                            <td>
                                @if($lote->latitude_centro && $lote->longitude_centro)
                                    <small class="text-success"><i class="fas fa-map-marker-alt me-1"></i>{{ $lote->latitude_centro }}, {{ $lote->longitude_centro }}</small>
                                @else
                                    <small class="text-muted">Sem coordenadas</small>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('lotes.show', $lote) }}" class="btn btn-sm btn-outline-info me-1" title="Ver Detalhes">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('lotes.edit', $lote) }}" class="btn btn-sm btn-outline-warning" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fas fa-map-signs fa-2x mb-2"></i><br>
                                Nenhum lote registrado no inventário territorial.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($lotes->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $lotes->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // Inicializar mapa centrado no Namibe, Angola (-15.1961, 12.1522)
    var map = L.map('mapaLotes').setView([-15.1961, 12.1522], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap - Governo Provincial do Namibe'
    }).addTo(map);

    // Carregar polígonos GeoJSON via API
    fetch("{{ route('lotes.geojson') }}")
        .then(response => response.json())
        .then(data => {
            if (data.features && data.features.length > 0) {
                var geojsonLayer = L.geoJSON(data, {
                    style: function(feature) {
                        var color = '#198754'; // DISPONIVEL (verde)
                        if (feature.properties.status === 'RESERVADO') color = '#0dcaf0'; // azul
                        if (feature.properties.status === 'ATRIBUIDO') color = '#dc3545'; // vermelho
                        if (feature.properties.status === 'EM_LICITACAO') color = '#ffc107'; // amarelo
                        return { color: color, weight: 3, opacity: 0.8, fillOpacity: 0.4 };
                    },
                    onEachFeature: function(feature, layer) {
                        var p = feature.properties;
                        layer.bindPopup("<b>Lote: " + p.codigo_lote + "</b><br>Status: " + p.status + "<br>Área: " + p.area_m2 + "<br>Zoneamento: " + p.zoneamento);
                    }
                }).addTo(map);

                map.fitBounds(geojsonLayer.getBounds());
            }
        })
        .catch(err => console.log('Sem dados GeoJSON para exibir no mapa no momento.', err));
});
</script>
@endsection
