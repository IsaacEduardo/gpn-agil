@extends('layouts.app')

@section('title', 'Central de Comando Operacional — Dashboard')

@section('styles')
<style>
    .dashboard-container {
        max-width: 1650px;
    }

    /* Welcome Section Banner */
    .dashboard-banner {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 50%, #eff6ff 100%);
        border-radius: 20px;
        padding: 2rem 2.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05), 0 10px 25px -10px rgba(15, 23, 42, 0.08);
        border: 1px solid rgba(226, 232, 240, 0.9);
        position: relative;
        overflow: hidden;
    }

    .dashboard-banner::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -10%;
        width: 380px;
        height: 380px;
        background: radial-gradient(circle at center, rgba(14, 165, 233, 0.08) 0%, transparent 70%);
        pointer-events: none;
    }

    .dashboard-banner-title {
        font-weight: 800;
        font-size: 1.85rem;
        letter-spacing: -0.03em;
        color: #0f172a;
        line-height: 1.2;
    }

    .dashboard-banner-sub {
        font-size: 1rem;
        color: #64748b;
        font-weight: 500;
    }

    /* KPI Cards */
    .kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1.5rem;
        height: 100%;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 2px 4px rgba(15, 23, 42, 0.03);
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        text-decoration: none;
        color: inherit;
        overflow: hidden;
    }

    .kpi-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px -8px rgba(15, 23, 42, 0.12);
        border-color: #cbd5e1;
        color: inherit;
    }

    .kpi-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3.5px;
        background: #94a3b8;
        transition: height 0.2s ease;
    }

    .kpi-card-primary::after { background: linear-gradient(90deg, #2563eb, #3b82f6); }
    .kpi-card-success::after { background: linear-gradient(90deg, #059669, #10b981); }
    .kpi-card-warning::after { background: linear-gradient(90deg, #d97706, #f59e0b); }
    .kpi-card-danger::after  { background: linear-gradient(90deg, #dc2626, #ef4444); }
    .kpi-card-info::after    { background: linear-gradient(90deg, #0284c7, #0ea5e9); }
    .kpi-card-secondary::after { background: linear-gradient(90deg, #475569, #64748b); }

    .kpi-value {
        font-size: 2.35rem;
        font-weight: 800;
        line-height: 1;
        color: #0f172a;
        letter-spacing: -0.04em;
        margin-top: 0.5rem;
        margin-bottom: 0.25rem;
    }

    .kpi-label {
        font-size: 0.88rem;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .kpi-icon-box {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
    }

    /* Operational Cards (Lists) */
    .op-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        box-shadow: 0 2px 4px rgba(15, 23, 42, 0.03);
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .op-card-header {
        padding: 1.35rem 1.5rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .op-card-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 0.15rem;
    }

    .op-card-subtitle {
        font-size: 0.82rem;
        color: #64748b;
    }

    .op-card-body {
        padding: 1rem 1.25rem;
        flex: 1;
    }

    /* List item items */
    .op-item {
        padding: 0.9rem 1rem;
        border-radius: 12px;
        margin-bottom: 0.6rem;
        background: #f8fafc;
        border: 1px solid #f1f5f9;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .op-item:last-child {
        margin-bottom: 0;
    }

    .op-item:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
        transform: translateX(2px);
    }

    .op-item-title {
        font-weight: 600;
        font-size: 0.93rem;
        color: #1e293b;
        margin-bottom: 0.2rem;
        line-height: 1.3;
    }

    .op-item-meta {
        font-size: 0.78rem;
        color: #64748b;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    /* Micro-badges */
    .badge-soft {
        font-weight: 600;
        font-size: 0.72rem;
        padding: 0.3em 0.7em;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }
</style>
@endsection

@section('content')
<div class="container-fluid dashboard-container py-4 px-md-4">
    
    <!-- 1. Header / Welcome Banner -->
    <div class="dashboard-banner d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 d-flex align-items-center justify-content-center shadow-xs" style="width: 60px; height: 60px; font-size: 1.75rem;">
                <i class="{{ $dashboard['banner']['icone'] ?? 'fas fa-shield-alt' }}"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="dashboard-banner-title mb-0">{{ $dashboard['banner']['titulo'] }}</h1>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 small fw-semibold">
                        {{ $dashboard['banner']['badge'] }}
                    </span>
                </div>
                <div class="dashboard-banner-sub">
                    <span class="fw-semibold text-dark">{{ $dashboard['banner']['saudacao'] }}</span> • {{ $dashboard['banner']['subtitulo'] }}
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-secondary btn-sm bg-white shadow-xs fw-medium px-3" id="btnRefreshDashboard" onclick="refreshDashboard(true)" title="Recarregar dados em tempo real">
                <i class="fas fa-sync-alt me-1" id="iconRefresh"></i> Atualizar
            </button>
            <a href="{{ route('documentos-internos.create') }}" class="btn btn-primary btn-sm shadow-xs fw-medium px-3">
                <i class="fas fa-plus me-1"></i> Elaborar Documento
            </a>
        </div>
    </div>

    <!-- 2. Matriz de 4 KPIs Operacionais do Perfil -->
    <div class="row g-3 g-xl-4 mb-4" id="kpisContainer">
        @foreach ($dashboard['kpis'] as $kpi)
            <div class="col-12 col-sm-6 col-xl-3">
                <a href="{{ $kpi['link'] }}" class="kpi-card kpi-card-{{ $kpi['cor'] ?? 'primary' }}">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="kpi-label">{{ $kpi['label'] }}</span>
                        <div class="kpi-icon-box bg-{{ $kpi['cor'] ?? 'primary' }}-subtle text-{{ $kpi['cor'] ?? 'primary' }}">
                            <i class="{{ $kpi['icone'] }}"></i>
                        </div>
                    </div>
                    <div>
                        <div class="kpi-value" id="kpi-val-{{ $kpi['id'] }}">{{ number_format($kpi['valor'], 0, ',', '.') }}</div>
                        <div class="d-flex align-items-center justify-content-between text-muted small mt-1">
                            @if (!empty($kpi['alerta']))
                                <span class="badge bg-{{ $kpi['cor'] ?? 'primary' }}-subtle text-{{ $kpi['cor'] ?? 'primary' }}-emphasis fw-medium px-2 py-0.5 rounded">
                                    {{ $kpi['alerta'] }}
                                </span>
                            @else
                                <span>Ver detalhes</span>
                            @endif
                            <i class="fas fa-arrow-right text-muted opacity-50 small"></i>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <!-- 3. Painéis Operacionais em 2 Colunas -->
    <div class="row g-4">
        
        <!-- Coluna Esquerda -->
        <div class="col-lg-6">
            <div class="op-card">
                <div class="op-card-header">
                    <div>
                        <h2 class="op-card-title mb-0 d-flex align-items-center gap-2">
                            <i class="{{ $dashboard['listas']['esquerda']['icone'] }}"></i>
                            {{ $dashboard['listas']['esquerda']['titulo'] }}
                        </h2>
                        <div class="op-card-subtitle">{{ $dashboard['listas']['esquerda']['subtitulo'] }}</div>
                    </div>
                    @if (!empty($dashboard['listas']['esquerda']['url_ver_todos']))
                        <a href="{{ $dashboard['listas']['esquerda']['url_ver_todos'] }}" class="btn btn-sm btn-link text-decoration-none fw-semibold p-0">
                            Ver Todos <i class="fas fa-chevron-right ms-1 small"></i>
                        </a>
                    @endif
                </div>

                <div class="op-card-body">
                    @php($itensEsq = $dashboard['listas']['esquerda']['itens'] ?? collect())
                    @if (count($itensEsq) > 0)
                        <div class="d-flex flex-column">
                            @foreach ($itensEsq as $item)
                                @if ($dashboard['listas']['esquerda']['tipo'] === 'demandas_tecnico')
                                    <!-- Demanda de Tarefa Técnico -->
                                    <div class="op-item">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="p-2 rounded bg-warning bg-opacity-10 text-warning mt-1">
                                                <i class="fas fa-tasks"></i>
                                            </div>
                                            <div>
                                                <div class="op-item-title">
                                                    <a href="{{ $item['url_executar'] }}" class="text-decoration-none text-dark stretched-link">
                                                        {{ $item['titulo'] }}
                                                    </a>
                                                </div>
                                                <div class="op-item-meta">
                                                    <span><i class="fas fa-file-alt me-1 text-muted"></i> Doc: <strong>{{ $item['documento_numero'] }}</strong></span>
                                                    <span><i class="fas fa-user-edit me-1 text-muted"></i> {{ $item['solicitante'] }}</span>
                                                    <span class="{{ $item['is_atrasada'] ? 'text-danger fw-bold' : ($item['is_urgente'] ? 'text-warning fw-bold' : '') }}">
                                                        <i class="fas fa-calendar-alt me-1"></i> Prazo: {{ $item['prazo_formatado'] }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex-shrink-0 z-2 position-relative">
                                            <a href="{{ $item['url_executar'] }}" class="btn btn-sm btn-outline-primary shadow-none px-2.5 py-1">
                                                <i class="fas fa-play me-1"></i> Executar
                                            </a>
                                        </div>
                                    </div>

                                @elseif ($dashboard['listas']['esquerda']['tipo'] === 'entradas_chefe')
                                    <!-- Entrada Setorial Chefe -->
                                    <div class="op-item">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="p-2 rounded bg-primary bg-opacity-10 text-primary mt-1">
                                                <i class="fas fa-file-import"></i>
                                            </div>
                                            <div>
                                                <div class="op-item-title">
                                                    <a href="{{ $item['url_show'] }}" class="text-decoration-none text-dark stretched-link">
                                                        {{ $item['assunto'] }}
                                                    </a>
                                                </div>
                                                <div class="op-item-meta">
                                                    <span><i class="fas fa-hashtag me-1 text-muted"></i> {{ $item['numero'] }}</span>
                                                    <span><i class="fas fa-building me-1 text-muted"></i> {{ $item['procedencia'] }}</span>
                                                    <span><i class="fas fa-calendar-alt me-1 text-muted"></i> {{ $item['data_entrada'] }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex-shrink-0 z-2 position-relative text-end">
                                            <span class="badge-soft {{ $item['status_info']['badge'] }} mb-1">
                                                <i class="{{ $item['status_info']['icone'] }}"></i> {{ $item['status_info']['label'] }}
                                            </span>
                                            <div>
                                                <a href="{{ $item['url_show'] }}" class="btn btn-xs btn-outline-secondary py-0.5 px-2">
                                                    Ver
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                @elseif ($dashboard['listas']['esquerda']['tipo'] === 'despachos_executivos')
                                    <!-- Despacho Executivo Gabinete/Admin -->
                                    <div class="op-item">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="p-2 rounded bg-danger bg-opacity-10 text-danger mt-1">
                                                <i class="fas fa-stamp"></i>
                                            </div>
                                            <div>
                                                <div class="op-item-title">
                                                    <a href="{{ $item['url_show'] }}" class="text-decoration-none text-dark stretched-link">
                                                        {{ $item['assunto'] }}
                                                    </a>
                                                </div>
                                                <div class="op-item-meta">
                                                    <span><i class="fas fa-hashtag me-1 text-muted"></i> {{ $item['numero'] }}</span>
                                                    <span><i class="fas fa-landmark me-1 text-muted"></i> {{ $item['procedencia'] }}</span>
                                                    <span><i class="fas fa-calendar-alt me-1 text-muted"></i> {{ $item['data_entrada'] }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex-shrink-0 z-2 position-relative text-end">
                                            <span class="badge-soft {{ $item['status_info']['badge'] }} mb-1 d-block">
                                                <i class="{{ $item['status_info']['icone'] }}"></i> {{ $item['status_info']['label'] }}
                                            </span>
                                            <a href="{{ $item['url_show'] }}" class="btn btn-sm btn-primary py-0.5 px-2.5">
                                                <i class="fas fa-file-signature me-1"></i> Despachar
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-5 text-muted">
                            <div class="bg-light rounded-circle p-3 d-inline-flex mb-2">
                                <i class="fas fa-check-circle fa-2x text-muted opacity-50"></i>
                            </div>
                            <p class="mb-0 small fw-medium">{{ $dashboard['listas']['esquerda']['vazio_mensagem'] }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Coluna Direita -->
        <div class="col-lg-6">
            <div class="op-card">
                <div class="op-card-header">
                    <div>
                        <h2 class="op-card-title mb-0 d-flex align-items-center gap-2">
                            <i class="{{ $dashboard['listas']['direita']['icone'] }}"></i>
                            {{ $dashboard['listas']['direita']['titulo'] }}
                        </h2>
                        <div class="op-card-subtitle">{{ $dashboard['listas']['direita']['subtitulo'] }}</div>
                    </div>
                    @if (!empty($dashboard['listas']['direita']['url_ver_todos']))
                        <a href="{{ $dashboard['listas']['direita']['url_ver_todos'] }}" class="btn btn-sm btn-link text-decoration-none fw-semibold p-0">
                            Ver Todos <i class="fas fa-chevron-right ms-1 small"></i>
                        </a>
                    @endif
                </div>

                <div class="op-card-body">
                    @php($itensDir = $dashboard['listas']['direita']['itens'] ?? collect())
                    @if (count($itensDir) > 0)
                        <div class="d-flex flex-column">
                            @foreach ($itensDir as $item)
                                @if ($dashboard['listas']['direita']['tipo'] === 'internos_tecnico')
                                    <!-- Meus Internos Técnico -->
                                    <div class="op-item">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="p-2 rounded bg-info bg-opacity-10 text-info mt-1">
                                                <i class="fas fa-file-signature"></i>
                                            </div>
                                            <div>
                                                <div class="op-item-title">
                                                    <a href="{{ $item['url_show'] }}" class="text-decoration-none text-dark stretched-link">
                                                        {{ $item['titulo'] }}
                                                    </a>
                                                </div>
                                                <div class="op-item-meta">
                                                    <span><i class="fas fa-tag me-1 text-muted"></i> {{ $item['especie'] }}</span>
                                                    <span><i class="fas fa-hashtag me-1 text-muted"></i> Ref: {{ $item['numero_referencia'] }}</span>
                                                    <span><i class="fas fa-clock me-1 text-muted"></i> {{ $item['atualizado_em'] }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex-shrink-0 z-2 position-relative text-end">
                                            <span class="badge-soft {{ $item['status_info']['badge'] }} mb-1 d-block">
                                                <i class="{{ $item['status_info']['icone'] }}"></i> {{ $item['status_info']['label'] }}
                                            </span>
                                            @if ($item['pode_editar'])
                                                <a href="{{ $item['url_edit'] }}" class="btn btn-xs btn-outline-primary py-0.5 px-2">
                                                    <i class="fas fa-edit me-1"></i> Editar
                                                </a>
                                            @else
                                                <a href="{{ $item['url_show'] }}" class="btn btn-xs btn-outline-secondary py-0.5 px-2">
                                                    Visualizar
                                                </a>
                                            @endif
                                        </div>
                                    </div>

                                @elseif ($dashboard['listas']['direita']['tipo'] === 'minutas_chefe')
                                    <!-- Minutas Submetidas Chefia -->
                                    <div class="op-item">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="p-2 rounded bg-danger bg-opacity-10 text-danger mt-1">
                                                <i class="fas fa-search-plus"></i>
                                            </div>
                                            <div>
                                                <div class="op-item-title">
                                                    <a href="{{ $item['url_show'] }}" class="text-decoration-none text-dark stretched-link">
                                                        {{ $item['titulo'] }}
                                                    </a>
                                                </div>
                                                <div class="op-item-meta">
                                                    <span><i class="fas fa-user me-1 text-muted"></i> Autor: <strong>{{ $item['autor'] }}</strong></span>
                                                    <span><i class="fas fa-tag me-1 text-muted"></i> {{ $item['especie'] }}</span>
                                                    <span><i class="fas fa-clock me-1 text-muted"></i> {{ $item['data_submissao'] }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex-shrink-0 z-2 position-relative text-end">
                                            <span class="badge-soft {{ $item['status_info']['badge'] }} mb-1 d-block">
                                                <i class="{{ $item['status_info']['icone'] }}"></i> {{ $item['status_info']['label'] }}
                                            </span>
                                            <a href="{{ $item['url_show'] }}" class="btn btn-sm btn-primary py-0.5 px-2.5">
                                                <i class="fas fa-check-circle me-1"></i> Revisar
                                            </a>
                                        </div>
                                    </div>

                                @elseif ($dashboard['listas']['direita']['tipo'] === 'atos_emitidos')
                                    <!-- Atos Emitidos Gabinete / Geral -->
                                    <div class="op-item">
                                        <div class="d-flex align-items-start gap-3">
                                            <div class="p-2 rounded bg-success bg-opacity-10 text-success mt-1">
                                                <i class="fas fa-certificate"></i>
                                            </div>
                                            <div>
                                                <div class="op-item-title">
                                                    <a href="{{ $item['url_show'] }}" class="text-decoration-none text-dark stretched-link">
                                                        {{ $item['titulo'] }}
                                                    </a>
                                                </div>
                                                <div class="op-item-meta">
                                                    <span><i class="fas fa-hashtag me-1 text-muted"></i> {{ $item['numero_referencia'] }}</span>
                                                    <span><i class="fas fa-building me-1 text-muted"></i> {{ $item['departamento'] }}</span>
                                                    <span><i class="fas fa-calendar-check me-1 text-muted"></i> {{ $item['data_emissao'] }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex-shrink-0 z-2 position-relative text-end">
                                            <span class="badge-soft {{ $item['status_info']['badge'] }} mb-1 d-block">
                                                <i class="{{ $item['status_info']['icone'] }}"></i> {{ $item['status_info']['label'] }}
                                            </span>
                                            <div class="d-flex gap-1 justify-content-end">
                                                <a href="{{ $item['url_show'] }}" class="btn btn-xs btn-outline-secondary py-0.5 px-2">
                                                    Ver
                                                </a>
                                                @if (!empty($item['url_pdf']))
                                                    <a href="{{ $item['url_pdf'] }}" target="_blank" class="btn btn-xs btn-outline-danger py-0.5 px-1.5" title="Baixar PDF">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-5 text-muted">
                            <div class="bg-light rounded-circle p-3 d-inline-flex mb-2">
                                <i class="fas fa-folder-open fa-2x text-muted opacity-50"></i>
                            </div>
                            <p class="mb-0 small fw-medium">{{ $dashboard['listas']['direita']['vazio_mensagem'] }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    /**
     * Atualização suave de KPIs via AJAX
     */
    function refreshDashboard(forceFresh = false) {
        const icon = document.getElementById('iconRefresh');
        if (icon) icon.classList.add('fa-spin');

        const url = '{{ route("api.dashboard.estatisticas") }}' + (forceFresh ? '?fresh=1' : '');

        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(response => {
            if (response.success && response.data && response.data.kpis) {
                response.data.kpis.forEach(kpi => {
                    const el = document.getElementById('kpi-val-' + kpi.id);
                    if (el) {
                        el.textContent = new Intl.NumberFormat('pt-PT').format(kpi.valor);
                    }
                });
                
                // Se o usuário clicou no botão atualizar, recarrega a página para atualizar também as listas
                if (forceFresh) {
                    window.location.reload();
                }
            }
        })
        .catch(err => console.error('Erro ao atualizar dashboard:', err))
        .finally(() => {
            if (icon) icon.classList.remove('fa-spin');
        });
    }

    // Auto-refresh suave a cada 60s se a aba estiver em foco
    let autoRefreshInterval = setInterval(() => {
        if (!document.hidden) {
            refreshDashboard(false);
        }
    }, 60000);
</script>
@endsection
