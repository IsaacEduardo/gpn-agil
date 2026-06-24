@extends('layouts.app')

@section('title', 'Gestão Departamental')

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
                <i class="fas fa-sitemap text-primary me-2"></i>Gestão Departamental: {{ $departamento->nome }}
            </h1>
            <p class="text-muted mb-0">Visão unificada das requisições e documentos internos sob competência deste departamento.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('documentos-internos.create') }}" class="btn btn-outline-primary rounded-pill px-3">
                <i class="fas fa-plus me-1"></i>Doc. Interno
            </a>
            <a href="{{ route('requisicoes.create') }}" class="btn btn-outline-success rounded-pill px-3">
                <i class="fas fa-cart-plus me-1"></i>Requisição
            </a>
        </div>
    </div>

    {{-- KPIs Row --}}
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card-kpi p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Docs Pendentes</span>
                        <h2 class="h2 fw-bold text-gray-800 mb-0 mt-1">{{ $statsDocs['para_aprovacao'] }}</h2>
                    </div>
                    <div class="icon-wrapper bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-file-signature"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card-kpi p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Reqs Pendentes</span>
                        <h2 class="h2 fw-bold text-gray-800 mb-0 mt-1">{{ $statsReqs['pendentes'] }}</h2>
                    </div>
                    <div class="icon-wrapper bg-warning bg-opacity-10 text-warning">
                        <i class="fas fa-box-open"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card-kpi p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Docs Aprovados</span>
                        <h2 class="h2 fw-bold text-gray-800 mb-0 mt-1">{{ $statsDocs['aprovados'] }}</h2>
                    </div>
                    <div class="icon-wrapper bg-success bg-opacity-10 text-success">
                        <i class="fas fa-check-double"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card-kpi p-4 shadow-sm h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Tempo Médio (Docs)</span>
                        <h2 class="h2 fw-bold text-gray-800 mb-0 mt-1">{{ number_format($avgTimeDocs, 1) }} d</h2>
                    </div>
                    <div class="icon-wrapper bg-info bg-opacity-10 text-info">
                        <i class="fas fa-stopwatch"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Workspace Split --}}
    <div class="row g-4">
        {{-- Left: Lists of actions --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold text-gray-800 mb-0">Ações Pendentes</h5>
                    {{-- Navigation card-header pills --}}
                    <ul class="nav nav-pills gap-2" id="deptTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link tab-nav-link active" id="docs-tab" data-bs-toggle="tab" data-bs-target="#docs" type="button" role="tab">
                                Docs. Aprovação <span class="badge bg-white text-primary ms-1 shadow-sm">{{ $statsDocs['para_aprovacao'] }}</span>
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link tab-nav-link" id="reqs-tab" data-bs-toggle="tab" data-bs-target="#reqs" type="button" role="tab">
                                Reqs. Aprovação <span class="badge bg-white text-primary ms-1 shadow-sm">{{ $statsReqs['pendentes'] }}</span>
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link tab-nav-link" id="sign-tab" data-bs-toggle="tab" data-bs-target="#sign" type="button" role="tab">
                                Para Assinar <span class="badge bg-danger text-white ms-1">{{ $docsParaAssinar->count() + $reqsParaAssinar->count() }}</span>
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content" id="deptTabsContent">
                    {{-- Tab: Documentos para aprovação --}}
                    <div class="tab-pane fade show active" id="docs" role="tabpanel">
                        @if ($docsParaAprovar->isEmpty())
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-check-circle fa-3x text-success opacity-50 mb-3"></i>
                                <h5 class="fw-bold">Nenhum documento pendente</h5>
                                <p class="small">Todos os documentos do departamento foram processados.</p>
                            </div>
                        @else
                            <form action="{{ route('departamento.batch.approve.docs') }}" method="POST">
                                @csrf
                                <div class="table-responsive mb-3">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr class="table-light">
                                                <th style="width: 40px"><input type="checkbox" class="form-check-input" id="checkAllDocs"></th>
                                                <th>Referência</th>
                                                <th>Título</th>
                                                <th>Autor</th>
                                                <th>Data</th>
                                                <th class="text-end">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($docsParaAprovar as $doc)
                                                <tr>
                                                    <td><input type="checkbox" name="documento_ids[]" value="{{ $doc->id }}" class="form-check-input check-doc"></td>
                                                    <td class="fw-bold text-primary">{{ $doc->numero_referencia }}</td>
                                                    <td class="fw-semibold">{{ Str::limit($doc->titulo, 50) }}</td>
                                                    <td>{{ $doc->autor->name }}</td>
                                                    <td class="small text-muted">{{ $doc->created_at->format('d/m/Y H:i') }}</td>
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
                                <button type="submit" class="btn btn-primary rounded-pill px-4 shadow-sm" id="btnApproveDocs" disabled>
                                    <i class="fas fa-check-double me-2"></i>Aprovar Selecionados
                                </button>
                            </form>
                        @endif
                    </div>

                    {{-- Tab: Requisições para aprovação --}}
                    <div class="tab-pane fade" id="reqs" role="tabpanel">
                        @if ($reqsPendentes->isEmpty())
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-box-open fa-3x text-warning opacity-50 mb-3"></i>
                                <h5 class="fw-bold">Nenhuma requisição pendente</h5>
                                <p class="small">Não existem requisições sob revisão no momento.</p>
                            </div>
                        @else
                            <form action="{{ route('departamento.batch.approve.reqs') }}" method="POST">
                                @csrf
                                <div class="table-responsive mb-3">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr class="table-light">
                                                <th style="width: 40px"><input type="checkbox" class="form-check-input" id="checkAllReqs"></th>
                                                <th>Código ID</th>
                                                <th>Solicitante</th>
                                                <th>Tipo</th>
                                                <th>Empresa/Destino</th>
                                                <th>Data</th>
                                                <th class="text-end">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($reqsPendentes as $req)
                                                <tr>
                                                    <td><input type="checkbox" name="requisicao_ids[]" value="{{ $req->id }}" class="form-check-input check-req"></td>
                                                    <td class="fw-bold text-success">{{ $req->codigo_sequencial ?? '#' . $req->id }}</td>
                                                    <td class="fw-semibold">{{ $req->usuario->name }}</td>
                                                    <td><span class="badge bg-light text-dark border">{{ $req->tipo->label() ?? $req->tipo }}</span></td>
                                                    <td>{{ $req->empresa_destinataria ?? 'N/A' }}</td>
                                                    <td class="small text-muted">{{ $req->created_at->format('d/m/Y H:i') }}</td>
                                                    <td class="text-end">
                                                        <a href="{{ route('requisicoes.show', $req) }}" class="btn btn-sm btn-outline-primary rounded-pill">
                                                            <i class="fas fa-eye me-1"></i> Visualizar
                                                        </a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" class="btn btn-success rounded-pill px-4 shadow-sm" id="btnApproveReqs" disabled>
                                    <i class="fas fa-check-double me-2"></i>Aprovar Selecionados
                                </button>
                            </form>
                        @endif
                    </div>

                    {{-- Tab: Para Assinar --}}
                    <div class="tab-pane fade" id="sign" role="tabpanel">
                        <div class="row g-4">
                            {{-- Documentos para Assinar --}}
                            <div class="col-md-6">
                                <div class="card border-0 bg-light bg-opacity-50 p-3 h-100">
                                    <h6 class="fw-bold text-danger mb-3"><i class="fas fa-file-invoice me-2"></i>Documentos para Assinar ({{ $docsParaAssinar->count() }})</h6>
                                    @if ($docsParaAssinar->isEmpty())
                                        <p class="text-muted small">Nenhum documento aguardando assinatura.</p>
                                    @else
                                        <form action="{{ route('departamento.batch.sign.docs') }}" method="POST">
                                            @csrf
                                            <div class="table-responsive mb-3" style="max-height: 250px; overflow-y: auto;">
                                                <table class="table table-sm table-hover align-middle small">
                                                    <thead>
                                                        <tr>
                                                            <th width="30"><input type="checkbox" id="checkAllSignDocs" class="form-check-input"></th>
                                                            <th>Ref</th>
                                                            <th>Título</th>
                                                            <th class="text-end">Ações</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($docsParaAssinar as $doc)
                                                            <tr>
                                                                <td><input type="checkbox" name="documento_ids[]" value="{{ $doc->id }}" class="form-check-input check-sign-doc"></td>
                                                                <td class="fw-bold">{{ $doc->numero_referencia }}</td>
                                                                <td>{{ Str::limit($doc->titulo, 25) }}</td>
                                                                <td class="text-end">
                                                                    <button type="button" class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#edmsPreviewModal" data-preview-url="{{ route('documentos-internos.pdf', $doc) }}" data-preview-title="{{ $doc->titulo }}">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="d-grid">
                                                <button type="button" class="btn btn-danger btn-sm rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#modalSignDocs" id="btnSignDocs" disabled>
                                                    <i class="fas fa-pen-nib me-1"></i>Assinar Selecionados
                                                </button>
                                            </div>

                                            {{-- Modal Sign Docs --}}
                                            <div class="modal fade" id="modalSignDocs" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                                        <div class="modal-header bg-danger text-white p-4">
                                                            <h5 class="modal-title fw-bold">Assinar Documentos</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            <p class="text-muted">Confirme sua senha para validar legalmente e assinar os documentos selecionados.</p>
                                                            <input type="password" name="password" class="form-control rounded-3 mb-2" placeholder="Sua senha" required>
                                                            <input type="password" name="certificate_password" class="form-control rounded-3" autocomplete="off" placeholder="Senha do certificado digital (se aplicável)">
                                                            <small class="text-muted">A senha do certificado não é armazenada.</small>
                                                        </div>
                                                        <div class="modal-footer border-0 p-4">
                                                            <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">Confirmar Assinatura</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            {{-- Requisições para Assinar --}}
                            <div class="col-md-6">
                                <div class="card border-0 bg-light bg-opacity-50 p-3 h-100">
                                    <h6 class="fw-bold text-danger mb-3"><i class="fas fa-cart-shopping me-2"></i>Requisições para Assinar ({{ $reqsParaAssinar->count() }})</h6>
                                    @if ($reqsParaAssinar->isEmpty())
                                        <p class="text-muted small">Nenhuma requisição aguardando assinatura.</p>
                                    @else
                                        <form action="{{ route('departamento.batch.sign.reqs') }}" method="POST">
                                            @csrf
                                            <div class="table-responsive mb-3" style="max-height: 250px; overflow-y: auto;">
                                                <table class="table table-sm table-hover align-middle small">
                                                    <thead>
                                                        <tr>
                                                            <th width="30"><input type="checkbox" id="checkAllSignReqs" class="form-check-input"></th>
                                                            <th>ID</th>
                                                            <th>Solicitante</th>
                                                            <th class="text-end">Ações</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($reqsParaAssinar as $req)
                                                            <tr>
                                                                <td><input type="checkbox" name="requisicao_ids[]" value="{{ $req->id }}" class="form-check-input check-sign-req"></td>
                                                                <td class="fw-bold">#{{ $req->id }}</td>
                                                                <td>{{ Str::limit($req->usuario->name, 20) }}</td>
                                                                <td class="text-end">
                                                                    <a href="{{ route('requisicoes.show', $req) }}" target="_blank">
                                                                        <i class="fas fa-external-link-alt"></i>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="d-grid">
                                                <button type="button" class="btn btn-danger btn-sm rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#modalSignReqs" id="btnSignReqs" disabled>
                                                    <i class="fas fa-pen-nib me-1"></i>Assinar Selecionados
                                                </button>
                                            </div>

                                            {{-- Modal Sign Reqs --}}
                                            <div class="modal fade" id="modalSignReqs" tabindex="-1">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content border-0 shadow-lg rounded-4">
                                                        <div class="modal-header bg-danger text-white p-4">
                                                            <h5 class="modal-title fw-bold">Assinar Requisições</h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body p-4">
                                                            <p class="text-muted">Confirme sua senha para validar legalmente e assinar as requisições selecionadas.</p>
                                                            <input type="password" name="password" class="form-control rounded-3 mb-2" placeholder="Sua senha" required>
                                                            <input type="password" name="certificate_password" class="form-control rounded-3" autocomplete="off" placeholder="Senha do certificado digital (se aplicável)">
                                                            <small class="text-muted">A senha do certificado não é armazenada.</small>
                                                        </div>
                                                        <div class="modal-footer border-0 p-4">
                                                            <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">Confirmar Assinatura</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: Analytics / SLAs & Timeline --}}
        <div class="col-lg-4">
            {{-- SLA Compliance Progresso --}}
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h5 class="fw-bold text-gray-800 mb-2"><i class="fas fa-clock text-primary me-2"></i>Cumprimento de SLA</h5>
                <p class="text-muted small">Meta de processamento de documentos regulamentar do departamento.</p>
                <div class="d-flex align-items-center gap-3 my-3">
                    <div class="progress flex-grow-1" style="height: 12px; border-radius: 99px;">
                        <div class="progress-bar bg-{{ $slaCompliance >= 80 ? 'success' : ($slaCompliance >= 50 ? 'warning' : 'danger') }}" role="progressbar" style="width: {{ $slaCompliance }}%" aria-valuenow="{{ $slaCompliance }}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <span class="fw-bold text-gray-800">{{ $slaCompliance }}%</span>
                </div>
                <div class="alert alert-light border-0 py-2 mb-0 small text-muted">
                    <i class="fas fa-info-circle me-1 text-primary"></i> Documentos com trâmite abaixo do limite de 2 dias.
                </div>
            </div>

            {{-- Requisitions Doughnut Chart --}}
            <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                <h5 class="fw-bold text-gray-800 mb-3"><i class="fas fa-chart-pie me-2 text-primary"></i>Status das Requisições</h5>
                <div style="height: 200px; position: relative;">
                    <canvas id="reqChart"></canvas>
                </div>
            </div>

            {{-- Recent Activity Timeline --}}
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <h5 class="fw-bold text-gray-800 mb-3"><i class="fas fa-clock-rotate-left me-2 text-primary"></i>Atividade do Departamento</h5>
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
                                Fez <strong>{{ $log->action }}</strong> em 
                                <span class="text-primary">{{ class_basename($log->auditable_type) }}</span>
                            </p>
                        </div>
                    @empty
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-info-circle mb-1"></i>
                            <span class="small d-block">Nenhuma atividade recente no departamento.</span>
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

        // Checkbox Logic
        function setupChecks(checkAllId, checkClass, btnId) {
            const checkAll = document.getElementById(checkAllId);
            const checks = document.querySelectorAll('.' + checkClass);
            const btn = document.getElementById(btnId);

            if (!checkAll) return;

            function updateBtn() {
                const anyChecked = document.querySelectorAll('.' + checkClass + ':checked').length > 0;
                btn.disabled = !anyChecked;
            }

            checkAll.addEventListener('change', function() {
                checks.forEach(c => c.checked = this.checked);
                updateBtn();
            });

            checks.forEach(c => c.addEventListener('change', updateBtn));
        }

        setupChecks('checkAllDocs', 'check-doc', 'btnApproveDocs');
        setupChecks('checkAllReqs', 'check-req', 'btnApproveReqs');
        setupChecks('checkAllSignDocs', 'check-sign-doc', 'btnSignDocs');
        setupChecks('checkAllSignReqs', 'check-sign-req', 'btnSignReqs');

        // Doughnut Chart (Requisições)
        const ctx = document.getElementById('reqChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Pendentes', 'Aprovadas', 'Rejeitadas'],
                datasets: [{
                    data: [
                        {{ $statsReqs['pendentes'] }},
                        {{ $statsReqs['aprovadas'] }},
                        {{ $statsReqs['rejeitadas'] }}
                    ],
                    backgroundColor: ['#F0AD4E', '#198754', '#D9534F'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: { size: 10 }
                        }
                    }
                },
                cutout: '70%',
            },
        });
    });
</script>
@endsection
