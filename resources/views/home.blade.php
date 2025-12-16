@extends('layouts.app')

@section('title', 'Dashboard')

@section('styles')
    <style>
        .dashboard-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .hero-title {
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .metric-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
        }

        .metric-icon {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .metric-value {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .metric-sub {
            font-size: 0.9rem;
            color: var(--bs-secondary-color);
        }

        .section-title {
            font-size: 1.05rem;
            font-weight: 600;
        }

        .quick-actions .btn {
            border-radius: 10px;
        }

        .status-badge {
            font-size: 0.75rem;
            border-radius: 999px;
            padding: 0.3rem 0.6rem;
        }

        .breadcrumb {
            --bs-breadcrumb-divider: '›';
        }

        .dashboard-hero {
            background: linear-gradient(90deg, rgba(var(--bs-primary-rgb), .08), rgba(var(--bs-primary-rgb), 0));
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(var(--bs-primary-rgb), .05);
        }

        .metric-card:hover {
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
            transform: translateY(-1px);
            transition: all .2s ease;
        }

        .metric-icon {
            background: var(--bs-body-bg);
            box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .06);
        }

        .list-group-item-action:hover {
            background-color: var(--bs-light-bg-subtle);
        }

        .quick-actions .btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }
    </style>
@endsection

@section('breadcrumbs')
    <div class="container py-2">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Início</a></li>
                <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
    <div class="container py-4">


        <!-- Métricas principais -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card metric-card p-3 ps-4 border-start border-3 border-indigo" style="border-left-color: #6610f2 !important;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="metric-value">{{ $metrics['documentos']['total'] ?? 0 }}</div>
                            <div class="metric-sub">Documentos</div>
                        </div>
                        <div class="metric-icon bg-indigo-subtle text-indigo" style="background-color: rgba(102, 16, 242, 0.1); color: #6610f2;"><i class="fas fa-file-contract"></i></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="status-badge bg-light text-dark border">Registrado: {{ $metrics['documentos']['registrado'] ?? 0 }}</span>
                        <span class="status-badge bg-info-subtle text-info">Encaminhado: {{ $metrics['documentos']['encaminhado'] ?? 0 }}</span>
                        <span class="status-badge bg-primary-subtle text-primary">Recebido: {{ $metrics['documentos']['recebido'] ?? 0 }}</span>
                        <span class="status-badge bg-success-subtle text-success">Respondido: {{ $metrics['documentos']['respondido'] ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <div class="card metric-card p-3 ps-4 border-start border-3 border-primary">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="metric-value">{{ $metrics['requisicoes']['total'] ?? 0 }}</div>
                            <div class="metric-sub">Requisições</div>
                        </div>
                        <div class="metric-icon bg-primary-subtle text-primary"><i class="fas fa-file-alt"></i></div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <span class="status-badge bg-warning-subtle text-warning">Pendente:
                            {{ $metrics['requisicoes']['pendente'] ?? 0 }}</span>
                        <span class="status-badge bg-success-subtle text-success">Aprovado:
                            {{ $metrics['requisicoes']['aprovado'] ?? 0 }}</span>
                        <span class="status-badge bg-danger-subtle text-danger">Rejeitado:
                            {{ $metrics['requisicoes']['rejeitado'] ?? 0 }}</span>
                        <span class="status-badge bg-secondary-subtle text-secondary">Finalizado:
                            {{ $metrics['requisicoes']['finalizado'] ?? 0 }}</span>
                    </div>
                    <div class="d-flex gap-2 mt-2">
                        <span class="badge bg-light text-dark">Produtos:
                            {{ $metrics['requisicoes']['produto'] ?? 0 }}</span>
                        <span class="badge bg-light text-dark">Oficina: {{ $metrics['requisicoes']['oficina'] ?? 0 }}</span>
                        <span class="badge bg-light text-dark">Serviço: {{ $metrics['requisicoes']['servico'] ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <div class="card metric-card p-3 ps-4 border-start border-3 border-info">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="metric-value">{{ $metrics['reservas']['total'] ?? 0 }}</div>
                            <div class="metric-sub">Reservas de Espaço</div>
                        </div>
                        <div class="metric-icon bg-info-subtle text-info"><i class="fas fa-calendar-alt"></i></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="status-badge bg-warning-subtle text-warning">Pendente:
                            {{ $metrics['reservas']['pendente'] ?? 0 }}</span>
                        <span class="status-badge bg-success-subtle text-success">Aprovada:
                            {{ $metrics['reservas']['aprovada'] ?? 0 }}</span>
                        <span class="status-badge bg-danger-subtle text-danger">Rejeitada:
                            {{ $metrics['reservas']['rejeitada'] ?? 0 }}</span>
                        <span class="status-badge bg-secondary-subtle text-secondary">Cancelada:
                            {{ $metrics['reservas']['cancelada'] ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <div class="card metric-card p-3 ps-4 border-start border-3 border-secondary">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="metric-value">{{ $metrics['empresas'] ?? 0 }}</div>
                            <div class="metric-sub">Empresas</div>
                        </div>
                        <div class="metric-icon bg-secondary-subtle text-secondary"><i class="fas fa-building"></i></div>
                    </div>
                    <div class="mt-2 text-muted">Departamentos: <strong>{{ $metrics['departamentos'] ?? 0 }}</strong></div>
                </div>
            </div>
            
            <!-- Card adicional para completar a linha se necessário ou mover para nova linha -->
            <div class="col-12 col-md-6 col-xl-3">
                <div class="card metric-card p-3 ps-4 border-start border-3 border-success">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="metric-value">{{ $metrics['viaturas']['total'] ?? 0 }}</div>
                            <div class="metric-sub">Viaturas</div>
                        </div>
                        <div class="metric-icon bg-success-subtle text-success"><i class="fas fa-car"></i></div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="status-badge bg-success-subtle text-success">Operacional:
                            {{ $metrics['viaturas']['operacional'] ?? 0 }}</span>
                        <span class="status-badge bg-warning-subtle text-warning">Manutenção:
                            {{ $metrics['viaturas']['manutencao'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <div class="section-title"><i class="fas fa-chart-bar me-2"></i>Distribuição de Status das
                            Requisições</div>
                    </div>
                    <div class="p-3">
                        <canvas id="chartRequisicoesStatus" height="140"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <div class="section-title"><i class="fas fa-chart-pie me-2"></i>Distribuição por Tipo de Requisição
                        </div>
                    </div>
                    <div class="p-3">
                        <canvas id="chartRequisicoesTipo" height="140"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        (function() {
            var el = document.getElementById('chartRequisicoesStatus');
            if (!el || typeof Chart === 'undefined') return;
            var pend = {{ (int) ($metrics['requisicoes']['pendente'] ?? 0) }};
            var aprov = {{ (int) ($metrics['requisicoes']['aprovado'] ?? 0) }};
            var reje = {{ (int) ($metrics['requisicoes']['rejeitado'] ?? 0) }};
            var fin = {{ (int) ($metrics['requisicoes']['finalizado'] ?? 0) }};
            var ctx = el.getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Pendente', 'Aprovado', 'Rejeitado', 'Finalizado'],
                    datasets: [{
                        label: 'Requisições',
                        data: [pend, aprov, reje, fin],
                        backgroundColor: [
                            'rgba(255,193,7,0.6)',
                            'rgba(25,135,84,0.6)',
                            'rgba(220,53,69,0.6)',
                            'rgba(108,117,125,0.6)'
                        ],
                        borderColor: [
                            'rgba(255,193,7,1)',
                            'rgba(25,135,84,1)',
                            'rgba(220,53,69,1)',
                            'rgba(108,117,125,1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        })();
        (function() {
            var el = document.getElementById('chartRequisicoesTipo');
            if (!el || typeof Chart === 'undefined') return;
            var prod = {{ (int) ($metrics['requisicoes']['produto'] ?? 0) }};
            var ofi = {{ (int) ($metrics['requisicoes']['oficina'] ?? 0) }};
            var serv = {{ (int) ($metrics['requisicoes']['servico'] ?? 0) }};
            var pass = {{ (int) ($metrics['requisicoes']['passagem'] ?? 0) }};
            var ctx = el.getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Produtos', 'Oficina', 'Serviços', 'Passagem'],
                    datasets: [{
                        data: [prod, ofi, serv, pass],
                        backgroundColor: [
                            'rgba(13,110,253,0.7)',
                            'rgba(25,135,84,0.7)',
                            'rgba(13,202,240,0.7)',
                            'rgba(255,193,7,0.7)'
                        ],
                        borderColor: [
                            'rgba(13,110,253,1)',
                            'rgba(25,135,84,1)',
                            'rgba(13,202,240,1)',
                            'rgba(255,193,7,1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        })();
    </script>
@endsection
