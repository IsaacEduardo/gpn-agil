@extends('layouts.app')

@section('title', 'Gestão de Gabinete')

@section('styles')
<style>
    .card-kpi {
        border-radius: 16px;
        border: none;
        transition: all 0.3s ease;
        background: #ffffff;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
    }
    .card-kpi:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.1), 0 8px 8px -5px rgba(0, 0, 0, 0.04);
    }
    .icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
    }
    .tab-nav-link {
        font-weight: 600;
        color: var(--text-muted);
        border: none !important;
        padding: 0.8rem 1.2rem;
        border-radius: 10px;
        transition: all 0.2s;
    }
    .tab-nav-link:hover {
        color: var(--primary-accent);
        background-color: rgba(59, 130, 246, 0.05);
    }
    .tab-nav-link.active {
        color: white !important;
        background: linear-gradient(135deg, var(--primary-accent), var(--primary-accent-hover)) !important;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }
    /* Timeline styles */
    .timeline-container {
        position: relative;
        padding-left: 20px;
    }
    .timeline-container::before {
        content: '';
        position: absolute;
        left: 5px;
        top: 8px;
        bottom: 8px;
        width: 2px;
        background: #e2e8f0;
    }
    .timeline-item {
        position: relative;
        margin-bottom: 20px;
    }
    .timeline-item:last-child {
        margin-bottom: 0;
    }
    .timeline-marker {
        position: absolute;
        left: -20px;
        top: 4px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #cbd5e1;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px rgba(203, 213, 225, 0.3);
    }
    .timeline-marker.success {
        background: #198754;
        box-shadow: 0 0 0 2px rgba(25, 135, 84, 0.3);
    }
    .timeline-marker.primary {
        background: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.3);
    }
    .timeline-marker.warning {
        background: #F0AD4E;
        box-shadow: 0 0 0 2px rgba(240, 173, 78, 0.3);
    }
</style>
@endsection

@section('content')
<div class="container-fluid py-4">
    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">
                <i class="fas fa-building-user text-primary me-2"></i>Gestão do Gabinete: {{ $gabinete->nome }}
            </h1>
            <p class="text-muted mb-0">Portal executivo para aprovação rápida, assinatura e análise do fluxo de trâmite.</p>
        </div>
        @if(!auth()->user()->isSuperChefeGabinete())
        <div>
            <a href="{{ route('documentos-internos.create') }}" class="btn btn-primary rounded-pill px-4 shadow-sm">
                <i class="fas fa-plus me-2"></i>Novo Documento
            </a>
        </div>
        @endif
    </div>

    {{-- KPIs --}}
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card-kpi p-4 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Pendentes de Aprovação</span>
                        <h2 class="h2 fw-bold text-gray-800 mb-0 mt-1">{{ $stats['em_analise'] }}</h2>
                    </div>
                    <div class="icon-wrapper bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-file-contract"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-kpi p-4 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Prontos para Assinar</span>
                        <h2 class="h2 fw-bold text-gray-800 mb-0 mt-1">{{ $docsParaAssinar->count() }}</h2>
                    </div>
                    <div class="icon-wrapper bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-pen-nib"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card-kpi p-4 shadow-sm">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Assinados Hoje</span>
                        <h2 class="h2 fw-bold text-gray-800 mb-0 mt-1">{{ $stats['assinados_hoje'] }}</h2>
                    </div>
                    <div class="icon-wrapper bg-success bg-opacity-10 text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Split --}}
    <div class="row g-4">
        {{-- Left: Lists & Operations --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                {{-- Modern Tab Navigation --}}
                <ul class="nav nav-pills gap-2 mb-4" id="cabinetTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link tab-nav-link active" id="sign-tab" data-bs-toggle="tab" data-bs-target="#sign" type="button" role="tab">
                            <i class="fas fa-pen-nib me-2"></i>Para Assinar
                            @if($docsParaAssinar->count() > 0)
                                <span class="badge bg-danger ms-2">{{ $docsParaAssinar->count() }}</span>
                            @endif
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link tab-nav-link" id="approve-tab" data-bs-toggle="tab" data-bs-target="#approve" type="button" role="tab">
                            <i class="fas fa-file-contract me-2"></i>Para Aprovar
                            @if($docsParaAprovar->count() > 0)
                                <span class="badge bg-warning text-dark ms-2">{{ $docsParaAprovar->count() }}</span>
                            @endif
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link tab-nav-link" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                            <i class="fas fa-list me-2"></i>Histórico Completo
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="cabinetTabsContent">
                    {{-- Tab: Para Assinar --}}
                    <div class="tab-pane fade show active" id="sign" role="tabpanel">
                        @if($docsParaAssinar->isEmpty())
                            <div class="text-center py-5 text-muted">
                                <img src="{{ asset('images/empty.svg') }}" class="mb-3 d-none" style="height: 120px;">
                                <i class="fas fa-check-double fa-3x mb-3 text-success opacity-50"></i>
                                <h5 class="fw-bold">Nenhum documento pendente</h5>
                                <p class="small">Tudo em dia! Todos os documentos do gabinete estão assinados.</p>
                            </div>
                        @else
                            <form id="batchSignForm" action="{{ route('gabinete.batch-sign') }}" method="POST" x-data="{ submitting: false }" @submit="submitting = true">
                                @csrf
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr class="table-light">
                                                @if(!auth()->user()->isSuperChefeGabinete())
                                                    <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="checkAllSign"></th>
                                                @endif
                                                <th>Referência</th>
                                                <th>Título</th>
                                                <th>Departamento</th>
                                                <th>Autor</th>
                                                <th>Data</th>
                                                <th class="text-end">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($docsParaAssinar as $doc)
                                                <tr>
                                                    @if(!auth()->user()->isSuperChefeGabinete())
                                                        <td><input type="checkbox" name="documento_ids[]" value="{{ $doc->id }}" class="form-check-input check-sign"></td>
                                                    @endif
                                                    <td class="fw-bold text-primary">{{ $doc->numero_referencia }}</td>
                                                    <td>
                                                        <a href="{{ route('documentos-internos.show', $doc) }}" class="text-decoration-none text-dark fw-semibold">
                                                            {{ Str::limit($doc->titulo, 50) }}
                                                        </a>
                                                    </td>
                                                    <td><span class="badge bg-light text-dark border">{{ $doc->departamento->sigla }}</span></td>
                                                    <td class="small">{{ $doc->autor->name }}</td>
                                                    <td class="small text-muted">{{ $doc->updated_at->format('d/m H:i') }}</td>
                                                    <td class="text-end">
                                                        <div class="d-flex justify-content-end gap-1">
                                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#edmsPreviewModal" data-preview-url="{{ route('documentos-internos.pdf', $doc) }}" data-preview-title="{{ $doc->titulo }}">
                                                                <i class="fas fa-eye me-1"></i> Ver
                                                            </button>
                                                            <a href="{{ route('documentos-internos.show', $doc) }}" class="btn btn-sm btn-light border rounded-pill">
                                                                <i class="fas fa-arrow-right"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                
                                @if(!auth()->user()->isSuperChefeGabinete())
                                {{-- Sticky Action Bar --}}
                                <div id="signActionBar" class="card position-fixed bottom-0 start-50 translate-middle-x mb-4 shadow-lg border-0 d-none" style="z-index: 1050; width: auto; min-width: 300px;">
                                    <div class="card-body d-flex align-items-center gap-3 py-2 px-4 bg-dark text-white rounded-pill">
                                        <span id="signCount" class="fw-bold">0 selecionados</span>
                                        <div class="vr bg-white opacity-25"></div>
                                        <button type="button" class="btn btn-success btn-sm rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#batchSignModal">
                                            <i class="fas fa-pen-nib me-2"></i>Assinar em Lote
                                        </button>
                                    </div>
                                </div>

                                {{-- Modal Assinatura --}}
                                <div class="modal fade" id="batchSignModal" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content border-0 shadow-lg rounded-4">
                                            <div class="modal-header bg-success text-white p-4">
                                                <h5 class="modal-title fw-bold"><i class="fas fa-file-signature me-2"></i>Assinatura Digital em Lote</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <p class="text-muted">Você está prestes a aplicar a sua assinatura digitalizada e carimbo nos documentos selecionados.</p>
                                                <div class="alert alert-info small border-0 bg-light-primary text-primary">
                                                    <i class="fas fa-info-circle me-1"></i> Esta ação é legalmente válida e atesta a conformidade das peças processuais no gabinete.
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold text-gray-700">Introduza a sua senha de confirmação</label>
                                                    <input type="password" name="password" class="form-control rounded-3" required placeholder="Senha de login">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold text-gray-700">Senha do certificado digital</label>
                                                    <input type="password" name="certificate_password" class="form-control rounded-3" autocomplete="off" placeholder="Preencha apenas se o seu certificado tiver senha">
                                                    <small class="text-muted">Não é armazenada — solicitada apenas no momento de assinar.</small>
                                                </div>
                                            </div>
                                            <div class="modal-footer border-0 p-4">
                                                <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal" :disabled="submitting">Cancelar</button>
                                                <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold d-flex align-items-center gap-2" :disabled="submitting">
                                                    <template x-if="submitting">
                                                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                                    </template>
                                                    <span x-text="submitting ? 'Assinando...' : 'Confirmar Assinatura'">Confirmar Assinatura</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </form>
                        @endif
                    </div>

                    {{-- Tab: Para Aprovar --}}
                    <div class="tab-pane fade" id="approve" role="tabpanel">
                        @if($docsParaAprovar->isEmpty())
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-clipboard-check fa-3x mb-3 text-warning opacity-50"></i>
                                <h5 class="fw-bold">Nenhum documento pendente</h5>
                                <p class="small">Sem requisições de revisão pendentes.</p>
                            </div>
                        @else
                            <form id="batchApproveForm" action="{{ route('gabinete.batch-approve') }}" method="POST">
                                @csrf
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr class="table-light">
                                                @if(!auth()->user()->isSuperChefeGabinete())
                                                    <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="checkAllApprove"></th>
                                                @endif
                                                <th>Referência</th>
                                                <th>Título</th>
                                                <th>Departamento</th>
                                                <th>Autor</th>
                                                <th>Data Criação</th>
                                                <th class="text-end">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($docsParaAprovar as $doc)
                                                <tr>
                                                    @if(!auth()->user()->isSuperChefeGabinete())
                                                        <td><input type="checkbox" name="documento_ids[]" value="{{ $doc->id }}" class="form-check-input check-approve"></td>
                                                    @endif
                                                    <td class="fw-bold text-warning">{{ $doc->numero_referencia }}</td>
                                                    <td class="fw-semibold">{{ Str::limit($doc->titulo, 50) }}</td>
                                                    <td><span class="badge bg-light text-dark border">{{ $doc->departamento->sigla }}</span></td>
                                                    <td class="small">{{ $doc->autor->name }}</td>
                                                    <td class="small text-muted">{{ $doc->created_at->format('d/m H:i') }}</td>
                                                    <td class="text-end">
                                                        <div class="d-flex justify-content-end gap-1">
                                                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#edmsPreviewModal" data-preview-url="{{ route('documentos-internos.pdf', $doc) }}" data-preview-title="{{ $doc->titulo }}">
                                                                <i class="fas fa-eye me-1"></i> Ver
                                                            </button>
                                                            <a href="{{ route('documentos-internos.show', $doc) }}" class="btn btn-sm btn-light border rounded-pill">
                                                                <i class="fas fa-arrow-right"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                @if(!auth()->user()->isSuperChefeGabinete())
                                {{-- Sticky Action Bar --}}
                                <div id="approveActionBar" class="card position-fixed bottom-0 start-50 translate-middle-x mb-4 shadow-lg border-0 d-none" style="z-index: 1050; width: auto; min-width: 300px;">
                                    <div class="card-body d-flex align-items-center gap-3 py-2 px-4 bg-dark text-white rounded-pill">
                                        <span id="approveCount" class="fw-bold">0 selecionados</span>
                                        <div class="vr bg-white opacity-25"></div>
                                        <button type="submit" class="btn btn-warning text-dark btn-sm rounded-pill px-3 fw-bold" onclick="return confirm('Confirma a aprovação dos documentos selecionados?')">
                                            <i class="fas fa-check-double me-2"></i>Aprovar em Lote
                                        </button>
                                    </div>
                                </div>
                                @endif
                            </form>
                        @endif
                    </div>

                    {{-- Tab: Histórico Completo --}}
                    <div class="tab-pane fade" id="all" role="tabpanel">
                        <form action="{{ route('gabinete.dashboard') }}" method="GET" class="mb-4">
                            <input type="hidden" name="tab" value="all">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <select name="departamento_id" class="form-select rounded-3" onchange="this.form.submit()">
                                        <option value="">Todos os Departamentos</option>
                                        @foreach ($departamentos as $dept)
                                            <option value="{{ $dept->id }}" {{ request('departamento_id') == $dept->id ? 'selected' : '' }}>
                                                {{ $dept->nome }} ({{ $dept->sigla }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select name="status" class="form-select rounded-3" onchange="this.form.submit()">
                                        <option value="">Todos os Status</option>
                                        @foreach (\App\Enums\DocumentoStatus::cases() as $status)
                                            <option value="{{ $status->value }}" {{ request('status') == $status->value ? 'selected' : '' }}>
                                                {{ $status->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" name="search" class="form-control rounded-3" placeholder="Pesquisar título ou ref..." value="{{ request('search') }}">
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary w-100 rounded-pill">
                                        <i class="fas fa-filter me-2"></i>Filtrar
                                    </button>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr class="table-light">
                                        <th>Referência</th>
                                        <th>Título</th>
                                        <th>Departamento</th>
                                        <th>Status</th>
                                        <th>Data</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($documentos as $doc)
                                        <tr>
                                            <td class="fw-bold text-nowrap">{{ $doc->numero_referencia ?? 'S/N' }}</td>
                                            <td class="fw-semibold">{{ Str::limit($doc->titulo, 40) }}</td>
                                            <td><span class="badge bg-light text-dark border">{{ $doc->departamento->sigla }}</span></td>
                                            <td><span class="badge bg-{{ $doc->status->color() }} bg-opacity-10 text-{{ $doc->status->color() }} border border-{{ $doc->status->color() }} border-opacity-25">{{ $doc->status->label() }}</span></td>
                                            <td class="small text-muted">{{ $doc->created_at->format('d/m/Y') }}</td>
                                            <td class="text-end">
                                                <div class="d-flex justify-content-end gap-1">
                                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#edmsPreviewModal" data-preview-url="{{ route('documentos-internos.pdf', $doc) }}" data-preview-title="{{ $doc->titulo }}">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <a href="{{ route('documentos-internos.show', $doc) }}" class="btn btn-sm btn-light border rounded-pill">
                                                        <i class="fas fa-arrow-right"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center py-4 text-muted">Nenhum documento encontrado.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-center mt-3">
                            {{ $documentos->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Analytics & Activities --}}
        <div class="col-lg-4">
            {{-- Analytics Card --}}
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h5 class="fw-bold text-gray-800 mb-3"><i class="fas fa-chart-pie me-2 text-primary"></i>Distribuição Documental</h5>
                <div style="height: 180px; position: relative;">
                    <canvas id="statusDoughnutChart"></canvas>
                </div>
                <hr class="my-4 text-muted opacity-25">
                <h5 class="fw-bold text-gray-800 mb-3"><i class="fas fa-chart-bar me-2 text-primary"></i>Carga de Pendências</h5>
                <div style="height: 160px; position: relative;">
                    <canvas id="loadBarChart"></canvas>
                </div>
            </div>

            {{-- Recent Activity Log --}}
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <h5 class="fw-bold text-gray-800 mb-3"><i class="fas fa-clock-rotate-left me-2 text-primary"></i>Atividade Recente</h5>
                <div class="timeline-container">
                    @forelse($recentLogs as $log)
                        @php
                            $markerClass = 'primary';
                            if ($log->action === 'create') $markerClass = 'success';
                            if ($log->action === 'delete') $markerClass = 'danger';
                            if ($log->action === 'approve') $markerClass = 'warning';
                        @endphp
                        <div class="timeline-item">
                            <span class="timeline-marker {{ $markerClass }}"></span>
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <span class="fw-bold small text-gray-800">{{ $log->user ? $log->user->name : 'Sistema' }}</span>
                                <span class="text-muted small" style="font-size: 0.75rem;">{{ $log->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="small text-muted mb-0">
                                Realizou a ação <strong>{{ $log->action }}</strong> em 
                                <span class="text-primary">{{ class_basename($log->auditable_type) }}</span>
                            </p>
                        </div>
                    @empty
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-info-circle mb-1"></i>
                            <span class="small d-block">Nenhuma atividade recente no gabinete.</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Premium Preview Modal --}}
<div class="modal fade" id="edmsPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" style="max-height: 90vh;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 bg-dark text-white p-4">
                <h5 class="modal-title fw-bold" id="previewModalTitle"><i class="fas fa-eye me-2"></i>Visualizador de Documento</h5>
                <div class="d-flex align-items-center gap-2">
                    <a href="" id="previewDownloadBtn" class="btn btn-outline-light btn-sm rounded-pill px-3 me-2" download>
                        <i class="fas fa-download me-1"></i> Baixar Original
                    </a>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
            </div>
            <div class="modal-body p-0 bg-secondary position-relative" style="height: 70vh;">
                <!-- Elegant Spinner Loader -->
                <div id="previewLoader" class="position-absolute top-50 start-50 translate-middle d-flex flex-column align-items-center text-white">
                    <div class="spinner-border text-light mb-2" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">A carregar...</span>
                    </div>
                    <span class="fw-semibold">A carregar visualização...</span>
                </div>
                <!-- Preview Frame -->
                <iframe id="previewIframe" src="" class="w-100 h-100 d-none" style="border: none;"></iframe>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://unpkg.com/alpinejs" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Iframe Document Preview
        const previewModal = document.getElementById('edmsPreviewModal');
        if (previewModal) {
            previewModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const url = button.getAttribute('data-preview-url');
                const title = button.getAttribute('data-preview-title');
                
                const iframe = document.getElementById('previewIframe');
                const loader = document.getElementById('previewLoader');
                const downloadBtn = document.getElementById('previewDownloadBtn');
                const modalTitle = document.getElementById('previewModalTitle');
                
                modalTitle.innerHTML = `<i class="fas fa-eye me-2"></i> ${title}`;
                downloadBtn.href = url + '?download=1';
                
                loader.classList.remove('d-none');
                iframe.classList.add('d-none');
                
                iframe.src = url;
                iframe.onload = function() {
                    loader.classList.add('d-none');
                    iframe.classList.remove('d-none');
                };
            });
            
            previewModal.addEventListener('hide.bs.modal', function() {
                document.getElementById('previewIframe').src = '';
            });
        }

        // Helper for checkboxes
        function setupBatchActions(tableId, checkClass, checkAllId, actionBarId, countId) {
            const checkboxes = document.querySelectorAll(`.${checkClass}`);
            const checkAll = document.getElementById(checkAllId);
            const actionBar = document.getElementById(actionBarId);
            const countLabel = document.getElementById(countId);

            if(!checkAll) return;

            function updateState() {
                const checked = document.querySelectorAll(`.${checkClass}:checked`);
                const count = checked.length;
                
                if (count > 0) {
                    actionBar.classList.remove('d-none');
                    countLabel.textContent = `${count} selecionado(s)`;
                } else {
                    actionBar.classList.add('d-none');
                }
            }

            checkAll.addEventListener('change', function() {
                checkboxes.forEach(cb => cb.checked = this.checked);
                updateState();
            });

            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateState);
            });
        }

        setupBatchActions('sign', 'check-sign', 'checkAllSign', 'signActionBar', 'signCount');
        setupBatchActions('approve', 'check-approve', 'checkAllApprove', 'approveActionBar', 'approveCount');

        // Restore tab if URL contains hash or query
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('tab') === 'all') {
            const allTabEl = document.querySelector('#all-tab');
            if (allTabEl) {
                bootstrap.Tab.getInstance(allTabEl)?.show() || new bootstrap.Tab(allTabEl).show();
            }
        }

        // --- Charts Implementation ---
        // Status Doughnut Chart
        const statusCtx = document.getElementById('statusDoughnutChart').getContext('2d');
        const statusKeys = {!! json_encode($statusDistrib->keys()) !!};
        const statusValues = {!! json_encode($statusDistrib->values()) !!};
        
        // Translate status keys for user labels
        const statusLabels = statusKeys.map(k => {
            switch(k) {
                case 'rascunho': return 'Rascunho';
                case 'em_analise': return 'Em Análise';
                case 'aprovado': return 'Aprovado';
                case 'assinado': return 'Assinado';
                case 'arquivado': return 'Arquivado';
                default: return k.charAt(0).toUpperCase() + k.slice(1);
            }
        });

        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: ['#cbd5e1', '#F0AD4E', '#3b82f6', '#198754', '#6366f1'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            font: { size: 10 }
                        }
                    }
                },
                cutout: '65%'
            }
        });

        // Load Horizontal Bar Chart
        const loadCtx = document.getElementById('loadBarChart').getContext('2d');
        const deptLabels = {!! json_encode($departamentos->pluck('sigla')) !!};
        const deptPending = {!! json_encode($departamentos->pluck('docs_pendentes')) !!};

        new Chart(loadCtx, {
            type: 'bar',
            data: {
                labels: deptLabels,
                datasets: [{
                    label: 'Pendências',
                    data: deptPending,
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { display: false },
                        ticks: { stepSize: 1, font: { size: 9 } }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { size: 9 } }
                    }
                }
            }
        });
    });
</script>
@endsection
