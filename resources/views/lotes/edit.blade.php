@extends('layouts.app')

@section('title', 'Editar Lote de Terra')

@section('content')
<div class="container-fluid py-4">
    <div class="mb-4">
        <a href="{{ route('lotes.show', $lote) }}" class="btn btn-outline-secondary btn-sm mb-2">
            <i class="fas fa-arrow-left me-1"></i> Voltar ao Lote
        </a>
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-layer-group text-primary me-2"></i>Editar Lote de Terra</h1>
        <p class="text-muted">A alterar o registo <strong>{{ $lote->codigo_lote }}</strong> do inventário imobiliário provincial.</p>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('lotes.update', $lote) }}">
                @csrf
                @method('PUT')

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label font-weight-bold">Código do Lote <span class="text-danger">*</span></label>
                        <input type="text" name="codigo_lote" class="form-control @error('codigo_lote') is-invalid @enderror" value="{{ old('codigo_lote', $lote->codigo_lote) }}" required>
                        @error('codigo_lote')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Matrícula Cartorária</label>
                        <input type="text" name="matricula_cartoraria" class="form-control" value="{{ old('matricula_cartoraria', $lote->matricula_cartoraria) }}" placeholder="Ex: MAT-12345/NAM">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Inscrição Imobiliária</label>
                        <input type="text" name="inscricao_imobiliaria" class="form-control" value="{{ old('inscricao_imobiliaria', $lote->inscricao_imobiliaria) }}">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label font-weight-bold">Área Total (m²) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="area_m2" class="form-control @error('area_m2') is-invalid @enderror" value="{{ old('area_m2', $lote->area_m2) }}" required>
                        @error('area_m2')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Perímetro (m)</label>
                        <input type="number" step="0.01" name="perimetro_m" class="form-control" value="{{ old('perimetro_m', $lote->perimetro_m) }}">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label font-weight-bold">Zoneamento <span class="text-danger">*</span></label>
                        <select name="zoneamento" class="form-select @error('zoneamento') is-invalid @enderror" required>
                            <option value="HABITACIONAL" {{ old('zoneamento', $lote->zoneamento) === 'HABITACIONAL' ? 'selected' : '' }}>Habitacional</option>
                            <option value="COMERCIAL" {{ old('zoneamento', $lote->zoneamento) === 'COMERCIAL' ? 'selected' : '' }}>Comercial</option>
                            <option value="INDUSTRIAL" {{ old('zoneamento', $lote->zoneamento) === 'INDUSTRIAL' ? 'selected' : '' }}>Industrial</option>
                            <option value="AGRICOLA" {{ old('zoneamento', $lote->zoneamento) === 'AGRICOLA' ? 'selected' : '' }}>Agrícola</option>
                            <option value="EQUIPAMENTO_PUBLICO" {{ old('zoneamento', $lote->zoneamento) === 'EQUIPAMENTO_PUBLICO' ? 'selected' : '' }}>Equipamento Público</option>
                            <option value="MISTO" {{ old('zoneamento', $lote->zoneamento) === 'MISTO' ? 'selected' : '' }}>Misto</option>
                        </select>
                        @error('zoneamento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label font-weight-bold">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
                            <option value="DISPONIVEL" {{ old('status', $lote->status) === 'DISPONIVEL' ? 'selected' : '' }}>Disponível</option>
                            <option value="RESERVADO" {{ old('status', $lote->status) === 'RESERVADO' ? 'selected' : '' }}>Reservado</option>
                            <option value="ATRIBUIDO" {{ old('status', $lote->status) === 'ATRIBUIDO' ? 'selected' : '' }}>Atribuído</option>
                            <option value="EM_LICITACAO" {{ old('status', $lote->status) === 'EM_LICITACAO' ? 'selected' : '' }}>Em Licitação</option>
                            <option value="INDISPONIVEL" {{ old('status', $lote->status) === 'INDISPONIVEL' ? 'selected' : '' }}>Indisponível</option>
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="form-text text-muted">O estado também é alterado automaticamente pelo fluxo de solicitações.</small>
                    </div>
                </div>

                <hr class="my-4">

                <h5 class="card-title text-secondary mb-3"><i class="fas fa-map-marker-alt me-1"></i>Localização e Georreferenciamento</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label font-weight-bold">Município <span class="text-danger">*</span></label>
                        <input type="text" name="municipio" class="form-control" value="{{ old('municipio', $lote->municipio) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Comuna</label>
                        <input type="text" name="comuna" class="form-control" value="{{ old('comuna', $lote->comuna) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Bairro / Distrito</label>
                        <input type="text" name="bairro_distrito" class="form-control" value="{{ old('bairro_distrito', $lote->bairro_distrito) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Zona / Setor</label>
                        <input type="text" name="zona_setor" class="form-control" value="{{ old('zona_setor', $lote->zona_setor) }}">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Latitude do Centro WGS84</label>
                        <input type="text" name="latitude_centro" class="form-control" value="{{ old('latitude_centro', $lote->latitude_centro) }}" placeholder="Ex: -15.1961">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Longitude do Centro WGS84</label>
                        <input type="text" name="longitude_centro" class="form-control" value="{{ old('longitude_centro', $lote->longitude_centro) }}" placeholder="Ex: 12.1522">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Geometria GeoJSON (Polígono)</label>
                        <textarea name="geojson_geometria" class="form-control font-monospace" rows="4">{{ old('geojson_geometria', $lote->geojson_geometria) }}</textarea>
                        <small class="form-text text-muted">Objeto GeoJSON no formato WGS84 EPSG:4326 para renderização no mapa.</small>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Observações e Confrontações</label>
                    <textarea name="observacoes" class="form-control" rows="3">{{ old('observacoes', $lote->observacoes) }}</textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('lotes.show', $lote) }}" class="btn btn-light border">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Guardar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
