@extends('layouts.app')

@section('title', 'Dashboard')

@section('styles')
    <style>
        .dashboard-container {
            max-width: 1600px;
        }

        /* Welcome Section — claro e arejado */
        .welcome-section {
            background: linear-gradient(135deg, #ffffff 0%, #FDECEE 100%);
            color: #0f172a;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 12px 32px -16px rgba(15, 23, 42, 0.12);
            position: relative;
            overflow: hidden;
            border: 1px solid #f1f5f9;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -10%;
            width: 360px;
            height: 360px;
            background: radial-gradient(circle at center, rgba(206, 17, 38, 0.08) 0%, transparent 65%);
            pointer-events: none;
        }

        .welcome-title {
            font-weight: 800;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            letter-spacing: -0.03em;
            color: #0f172a;
        }

        .welcome-subtitle {
            font-size: 1.05rem;
            font-weight: 400;
            color: #475569;
        }

        /* Micro-interação reutilizável */
        .hover-lift {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .hover-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px -10px rgba(206, 17, 38, 0.35);
        }

        /* KPI Cards */
        .kpi-card {
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 16px;
            background: white;
            padding: 1.5rem;
            height: 100%;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px rgba(148, 163, 184, 0.03);
            position: relative;
            overflow: hidden;
        }

        .kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(148, 163, 184, 0.12);
            border-color: rgba(206, 17, 38, 0.18);
        }

        .kpi-card::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: transparent;
            transition: background-color 0.3s ease;
        }

        .kpi-card-primary:hover::after { background-color: #CE1126; }
        .kpi-card-success:hover::after { background-color: #CE1126; }
        .kpi-card-warning:hover::after { background-color: #F0AD4E; }
        .kpi-card-info:hover::after { background-color: #06b6d4; }

        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .kpi-icon-box {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            transition: transform 0.3s ease;
        }

        .kpi-card:hover .kpi-icon-box {
            transform: scale(1.1);
        }

        .kpi-value {
            font-size: 2.25rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 0.5rem;
            color: #0f172a;
            letter-spacing: -0.03em;
        }

        .kpi-label {
            color: #64748b;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .kpi-footer {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #f1f5f9;
            font-size: 0.85rem;
            color: #64748b;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Content Cards */
        .content-card {
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(148, 163, 184, 0.03);
            height: 100%;
            background: white;
            overflow: hidden;
        }

        .content-card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .content-card-title {
            font-weight: 800;
            font-size: 1.15rem;
            margin: 0;
            color: #1e293b;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
        }

        /* Tables */
        .table-custom {
            margin-bottom: 0;
        }

        .table-custom th {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.725rem;
            letter-spacing: 0.06em;
            color: #64748b;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 1.5rem;
        }

        .table-custom td {
            padding: 1.1rem 1.5rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 0.9rem;
        }

        .table-custom tr:last-child td {
            border-bottom: none;
        }

        .table-custom tr {
            transition: background-color 0.2s ease;
        }

        .table-custom tr:hover td {
            background-color: #f8fafc;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 700;
            border-radius: 8px;
            letter-spacing: 0.01em;
            text-transform: uppercase;
        }

        .status-badge-warning {
            background-color: rgba(240, 173, 78, 0.1);
            color: #d97706;
            border: 1px solid rgba(240, 173, 78, 0.2);
        }

        .status-badge-info {
            background-color: rgba(6, 182, 212, 0.1);
            color: #0891b2;
            border: 1px solid rgba(6, 182, 212, 0.2);
        }

        .status-badge-success {
            background-color: rgba(25, 135, 84, 0.1);
            color: #059669;
            border: 1px solid rgba(25, 135, 84, 0.2);
        }

        .status-badge-secondary {
            background-color: rgba(100, 116, 139, 0.1);
            color: #475569;
            border: 1px solid rgba(100, 116, 139, 0.2);
        }

        /* Avatars */
        .avatar-initials {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            color: #475569;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            border: 1px solid #cbd5e1;
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }
    </style>
@endsection

@section('content')
    <div class="dashboard-container">

        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center position-relative z-1">
                <div class="col-lg-8">
                    <h1 class="welcome-title">Olá, {{ $user->name }}</h1>
                    <p class="welcome-subtitle mb-0">
                        Bem-vindo ao painel de controle. Aqui está o resumo das atividades
                        @if ($user->departamento)
                            do departamento <strong>{{ $user->departamento->nome }}</strong>.
                        @else
                            do sistema.
                        @endif
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <a href="{{ route('documentos-entradas.create') }}"
                        class="btn btn-primary fw-bold px-4 py-2 shadow-sm rounded-pill hover-lift">
                        <i class="fas fa-plus me-2"></i>Novo Documento
                    </a>
                </div>
            </div>
        </div>

        <!-- KPIs Row -->
        <div class="row g-4 mb-4">
            <!-- Documentos Entrada -->
            <div class="col-sm-6 col-xl-3">
                <div class="kpi-card kpi-card-primary">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-label">Docs. Entrada</div>
                            <div class="kpi-value">{{ $metrics['documentos']['total'] ?? 0 }}</div>
                        </div>
                        <div class="kpi-icon-box bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-inbox"></i>
                        </div>
                    </div>
                    <div class="kpi-footer">
                        <span class="text-warning fw-semibold">
                            <i class="fas fa-clock me-1"></i> {{ $metrics['documentos']['recebido'] ?? 0 }} pendentes
                        </span>
                        <span class="badge bg-light text-secondary rounded-pill px-2 py-1">Total</span>
                    </div>
                </div>
            </div>

            <!-- Documentos Internos -->
            <div class="col-sm-6 col-xl-3">
                <div class="kpi-card kpi-card-success">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-label">Docs. Internos</div>
                            <div class="kpi-value">{{ $metrics['documentos_internos']['total'] ?? 0 }}</div>
                        </div>
                        <div class="kpi-icon-box bg-success bg-opacity-10 text-success">
                            <i class="fas fa-file-contract"></i>
                        </div>
                    </div>
                    <div class="kpi-footer">
                        <span class="text-success fw-semibold">
                            <i class="fas fa-check-circle me-1"></i> {{ $metrics['documentos_internos']['assinado'] ?? 0 }} assinados
                        </span>
                        <span class="badge bg-light text-secondary rounded-pill px-2 py-1">Total</span>
                    </div>
                </div>
            </div>

            <!-- Requisições -->
            <div class="col-sm-6 col-xl-3">
                <div class="kpi-card kpi-card-warning">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-label">Requisições</div>
                            <div class="kpi-value">{{ $metrics['requisicoes']['total'] ?? 0 }}</div>
                        </div>
                        <div class="kpi-icon-box bg-warning bg-opacity-10 text-warning">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                    </div>
                    <div class="kpi-footer">
                        <span class="text-warning fw-semibold">
                            <i class="fas fa-hourglass-half me-1"></i> {{ $metrics['requisicoes']['pendente'] ?? 0 }} aguardando
                        </span>
                        <span class="badge bg-light text-secondary rounded-pill px-2 py-1">Total</span>
                    </div>
                </div>
            </div>

            <!-- Reservas -->
            <div class="col-sm-6 col-xl-3">
                <div class="kpi-card kpi-card-info">
                    <div class="kpi-header">
                        <div>
                            <div class="kpi-label">Reservas</div>
                            <div class="kpi-value">{{ $metrics['reservas']['total'] ?? 0 }}</div>
                        </div>
                        <div class="kpi-icon-box bg-info bg-opacity-10 text-info">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                    </div>
                    <div class="kpi-footer">
                        <span class="text-info fw-semibold">
                            <i class="fas fa-calendar-day me-1"></i>
                            {{ $recentReservas->where('data_inicio', '>=', now()->startOfDay())->count() }} hoje
                        </span>
                        <span class="badge bg-light text-secondary rounded-pill px-2 py-1">Total</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Split -->
        <div class="row g-4 mb-4">
            <!-- Left Column: Incoming Documents -->
            <div class="col-xl-6">
                <div class="content-card">
                    <div class="content-card-header">
                        <h5 class="content-card-title">
                            <i class="fas fa-file-import me-2 text-primary"></i>
                            @if (isset($deptStats) && count($deptStats) > 0)
                                Volume por Departamento
                            @else
                                Entradas Recentes
                            @endif
                        </h5>
                        <a href="{{ route('documentos-entradas.index') }}"
                            class="btn btn-sm btn-light text-primary fw-bold rounded-pill px-3">Ver Todos</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            @if (isset($deptStats) && count($deptStats) > 0)
                                <thead>
                                    <tr>
                                        <th>Departamento</th>
                                        <th class="text-center">Entradas</th>
                                        <th class="text-center">Internos</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($deptStats as $dept)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-initials me-3">{{ substr($dept->sigla, 0, 2) }}
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark">{{ $dept->sigla }}</div>
                                                        <div class="text-muted small">{{ Str::limit($dept->nome, 25) }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center fw-medium">{{ $dept->total_entrada }}</td>
                                            <td class="text-center fw-medium">{{ $dept->total_internos }}</td>
                                            <td class="text-end fw-bold text-primary">{{ $dept->total_entrada + $dept->total_internos }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            @else
                                <thead>
                                    <tr>
                                        <th>Assunto</th>
                                        <th>Status</th>
                                        <th class="text-end">Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentIncomingDocs as $doc)
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark mb-1">{{ Str::limit($doc->assunto, 40) }}
                                                </div>
                                                <div class="text-muted small">
                                                    <i class="fas fa-building me-1 opacity-50"></i> {{ $doc->procedencia }}
                                                </div>
                                            </td>
                                            <td>
                                                @php
                                                    $statusClass = match ($doc->status) {
                                                        'pendente', 'registrado' => 'warning',
                                                        'recebido', 'encaminhado' => 'info',
                                                        'respondido', 'finalizado' => 'success',
                                                        'arquivado' => 'secondary',
                                                        default => 'secondary',
                                                    };
                                                @endphp
                                                <span class="status-badge status-badge-{{ $statusClass }}">
                                                    <i class="fas fa-circle ms-0 me-1" style="font-size: 0.45rem; opacity: 0.8;"></i>
                                                    {{ ucfirst($doc->status) }}
                                                </span>
                                            </td>
                                            <td class="text-end text-muted small fw-medium">
                                                {{ $doc->created_at->format('d/m/Y') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center py-5 text-muted">
                                                <i class="fas fa-inbox fa-2x mb-3 opacity-25 d-block"></i>
                                                Nenhum documento recente.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            @endif
                        </table>
                    </div>
                    @if (isset($deptStats) && $deptStats instanceof \Illuminate\Contracts\Pagination\Paginator && $deptStats->hasPages())
                        <div class="d-flex justify-content-center py-3">
                            {{ $deptStats->links() }}
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right Column: Internal Documents -->
            <div class="col-xl-6">
                <div class="content-card">
                    <div class="content-card-header">
                        <h5 class="content-card-title">
                            <i class="fas fa-file-signature me-2 text-success"></i>Internos Recentes
                        </h5>
                        <a href="{{ route('documentos-internos.index') }}"
                            class="btn btn-sm btn-light text-primary fw-bold rounded-pill px-3">Ver Todos</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Título</th>
                                    <th>Estado</th>
                                    <th class="text-end">Criado em</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentInternalDocs as $doc)
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark mb-1">{{ Str::limit($doc->titulo, 40) }}</div>
                                            <div class="text-muted small">
                                                {{ $doc->modelo->nome ?? 'Documento' }}
                                            </div>
                                        </td>
                                        <td>
                                            @if ($doc->assinado_em)
                                                <span class="status-badge status-badge-success">
                                                    <i class="fas fa-check me-1"></i> Assinado
                                                </span>
                                            @else
                                                <span class="status-badge status-badge-warning">
                                                    <i class="fas fa-pen me-1"></i> Rascunho
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-end text-muted small fw-medium">
                                            {{ $doc->created_at->format('d/m/Y') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-5 text-muted">
                                            <i class="fas fa-file-alt fa-2x mb-3 opacity-25 d-block"></i>
                                            Nenhum documento interno recente.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4">
            <div class="col-md-6">
                <div class="content-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="content-card-title text-muted text-uppercase small">Status de Requisições</h5>
                    </div>
                    <div class="chart-container" style="height: 200px;">
                        <canvas id="chartRequisicoesStatus"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="content-card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="content-card-title text-muted text-uppercase small">Tipos de Requisições</h5>
                    </div>
                    <div class="chart-container" style="height: 200px;">
                        <canvas id="chartRequisicoesTipo"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Chart === 'undefined') return;

            // Chart Defaults
            Chart.defaults.font.family = "'Inter', sans-serif";
            Chart.defaults.color = '#64748b';

            // Status Chart
            const ctxStatus = document.getElementById('chartRequisicoesStatus');
            if (ctxStatus) {
                const ctx = ctxStatus.getContext('2d');
                
                // Yellow Gradient for Pendente
                const gradPending = ctx.createLinearGradient(0, 0, 0, 200);
                gradPending.addColorStop(0, 'rgba(240, 173, 78, 0.85)');
                gradPending.addColorStop(1, 'rgba(240, 173, 78, 0.3)');
                
                // Green Gradient for Aprovado
                const gradApprove = ctx.createLinearGradient(0, 0, 0, 200);
                gradApprove.addColorStop(0, 'rgba(25, 135, 84, 0.85)');
                gradApprove.addColorStop(1, 'rgba(25, 135, 84, 0.3)');
                
                // Red Gradient for Rejeitado
                const gradReject = ctx.createLinearGradient(0, 0, 0, 200);
                gradReject.addColorStop(0, 'rgba(217, 83, 79, 0.85)');
                gradReject.addColorStop(1, 'rgba(217, 83, 79, 0.3)');
                
                // Gray Gradient for Finalizado
                const gradFinal = ctx.createLinearGradient(0, 0, 0, 200);
                gradFinal.addColorStop(0, 'rgba(148, 163, 184, 0.85)');
                gradFinal.addColorStop(1, 'rgba(148, 163, 184, 0.3)');

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Pendente', 'Aprovado', 'Rejeitado', 'Finalizado'],
                        datasets: [{
                            label: 'Requisições',
                            data: [
                                {{ (int) ($metrics['requisicoes']['pendente'] ?? 0) }},
                                {{ (int) ($metrics['requisicoes']['aprovado'] ?? 0) }},
                                {{ (int) ($metrics['requisicoes']['rejeitado'] ?? 0) }},
                                {{ (int) ($metrics['requisicoes']['finalizado'] ?? 0) }}
                            ],
                            backgroundColor: [gradPending, gradApprove, gradReject, gradFinal],
                            borderColor: ['#F0AD4E', '#198754', '#D9534F', '#94a3b8'],
                            borderWidth: 1.5,
                            borderRadius: 8,
                            barPercentage: 0.5,
                            maxBarThickness: 35
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                backgroundColor: '#1e293b',
                                padding: 12,
                                cornerRadius: 8,
                                displayColors: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: '#f1f5f9',
                                    borderDash: [4, 4]
                                },
                                border: {
                                    display: false
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                border: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }

            // Type Chart
            const ctxType = document.getElementById('chartRequisicoesTipo');
            if (ctxType) {
                const ctx = ctxType.getContext('2d');
                
                // Gradients for doughnut slices (Vermelho, Preto, Cinza, Amarelo)
                const gradProd = ctx.createLinearGradient(0, 0, 0, 200);
                gradProd.addColorStop(0, '#CE1126');
                gradProd.addColorStop(1, '#8E0C17');

                const gradOfi = ctx.createLinearGradient(0, 0, 0, 200);
                gradOfi.addColorStop(0, '#1A1A1A');
                gradOfi.addColorStop(1, '#4B5563');

                const gradServ = ctx.createLinearGradient(0, 0, 0, 200);
                gradServ.addColorStop(0, '#6C757D');
                gradServ.addColorStop(1, '#9CA3AF');

                const gradPass = ctx.createLinearGradient(0, 0, 0, 200);
                gradPass.addColorStop(0, '#F0AD4E');
                gradPass.addColorStop(1, '#D97706');

                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Produtos', 'Oficina', 'Serviços', 'Passagem'],
                        datasets: [{
                            data: [
                                {{ (int) ($metrics['requisicoes']['produto'] ?? 0) }},
                                {{ (int) ($metrics['requisicoes']['oficina'] ?? 0) }},
                                {{ (int) ($metrics['requisicoes']['servico'] ?? 0) }},
                                {{ (int) ($metrics['requisicoes']['passagem'] ?? 0) }}
                            ],
                            backgroundColor: [gradProd, gradOfi, gradServ, gradPass],
                            borderWidth: 2,
                            borderColor: '#ffffff',
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '72%',
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    usePointStyle: true,
                                    boxWidth: 8,
                                    padding: 20,
                                    font: {
                                        size: 12,
                                        weight: '600'
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: '#1e293b',
                                padding: 12,
                                cornerRadius: 8
                            }
                        }
                    }
                });
            }
        });
    </script>
@endsection
