@extends('layouts.app')

@section('title', 'Detalhes da Viatura')

@section('content')
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-car me-2"></i>Detalhes da Viatura</h5>
            <div>
                <a href="{{ route('viaturas.edit', $viatura->id) }}" class="btn btn-sm btn-light me-2">
                    <i class="fas fa-edit me-1"></i>Editar
                </a>
                <a href="{{ route('viaturas.index') }}" class="btn btn-sm btn-light">
                    <i class="fas fa-arrow-left me-1"></i>Voltar
                </a>
            </div>
        </div>
        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Informações Básicas</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <tbody>
                                    <tr>
                                        <th style="width: 30%">Identificação:</th>
                                        <td>{{ $viatura->identificacao }}</td>
                                    </tr>
                                    <tr>
                                        <th>Matrícula:</th>
                                        <td>{{ $viatura->placa }}</td>
                                    </tr>
                                    <tr>
                                        <th>Modelo:</th>
                                        <td>{{ $viatura->modelo }}</td>
                                    </tr>
                                    <tr>
                                        <th>Marca:</th>
                                        <td>{{ $viatura->marca }}</td>
                                    </tr>
                                    <tr>
                                        <th>Ano:</th>
                                        <td>{{ $viatura->ano }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Informações Adicionais</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <tbody>
                                    <tr>
                                        <th style="width: 30%">Status Operacional:</th>
                                        <td>
                                            @if ($viatura->status_operacional == 'Operacional')
                                                <span class="badge bg-success">{{ $viatura->status_operacional }}</span>
                                            @elseif($viatura->status_operacional == 'Em manutenção')
                                                <span class="badge bg-warning">{{ $viatura->status_operacional }}</span>
                                            @else
                                                <span class="badge bg-danger">{{ $viatura->status_operacional }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Afetação:</th>
                                        <td>{{ $viatura->afetacao ?? 'Não definida' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Data de Cadastro:</th>
                                        <td>{{ $viatura->created_at->format('d/m/Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Última Atualização:</th>
                                        <td>{{ $viatura->updated_at->format('d/m/Y H:i') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Observações</h6>
                        </div>
                        <div class="card-body">
                            <p>{{ $viatura->observacoes ?? 'Nenhuma observação registrada.' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Seção de Fotos e Documentos -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Fotos e Documentos</h6>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-tabs" id="mediaTab" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="fotos-tab" data-bs-toggle="tab"
                                        data-bs-target="#fotos" type="button" role="tab" aria-controls="fotos"
                                        aria-selected="true">
                                        <i class="fas fa-images me-1"></i> Fotos
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="documentos-tab" data-bs-toggle="tab"
                                        data-bs-target="#documentos" type="button" role="tab"
                                        aria-controls="documentos" aria-selected="false">
                                        <i class="fas fa-file-alt me-1"></i> Documentos
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content p-3" id="mediaTabContent">
                                <!-- Aba de Fotos -->
                                <div class="tab-pane fade show active" id="fotos" role="tabpanel"
                                    aria-labelledby="fotos-tab">
                                    @if ($viatura->fotos && $viatura->fotos->where('tipo', 'foto')->count() > 0)
                                        <div class="row">
                                            @foreach ($viatura->fotos->where('tipo', 'foto') as $foto)
                                                <div class="col-md-3 mb-3">
                                                    <div class="card">
                                                        <a href="{{ asset('storage/' . $foto->caminho_arquivo) }}"
                                                            target="_blank" data-lightbox="viatura-fotos"
                                                            data-title="Foto da Viatura {{ $viatura->identificacao }}">
                                                            <img src="{{ asset('storage/' . $foto->caminho_arquivo) }}"
                                                                class="card-img-top" alt="Foto da Viatura">
                                                        </a>
                                                        <div class="card-footer p-2 text-center">
                                                            <small
                                                                class="text-muted">{{ \Carbon\Carbon::parse($foto->created_at)->format('d/m/Y') }}</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i> Nenhuma foto cadastrada para esta
                                            viatura.
                                        </div>
                                    @endif
                                </div>

                                <!-- Aba de Documentos -->
                                <div class="tab-pane fade" id="documentos" role="tabpanel" aria-labelledby="documentos-tab">
                                    @if ($viatura->fotos && $viatura->fotos->where('tipo', 'documento')->count() > 0)
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Documento</th>
                                                        <th>Data de Upload</th>
                                                        <th>Ações</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($viatura->fotos->where('tipo', 'documento') as $documento)
                                                        <tr>
                                                            <td>
                                                                <i class="fas fa-file-pdf me-2"></i>
                                                                Documento {{ $loop->iteration }}
                                                            </td>
                                                            <td>{{ \Carbon\Carbon::parse($documento->created_at)->format('d/m/Y H:i') }}
                                                            </td>
                                                            <td>
                                                                <a href="{{ asset('storage/' . $documento->caminho_arquivo) }}"
                                                                    class="btn btn-sm btn-primary" target="_blank">
                                                                    <i class="fas fa-eye me-1"></i> Visualizar
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i> Nenhum documento cadastrado para esta
                                            viatura.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>


    </div>
    </div>
    </div>
    </div>
    </div>
@endsection

@push('lightbox_styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css">
@endpush

@push('lightbox_scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox.min.js"></script>
@endpush
