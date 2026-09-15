@extends('layouts.app')

@section('title', 'Relatórios e Analytics')

@php
    use App\Enums\DocumentoStatus;

    /** Rótulo legível de um estado, venha ele como string ou como enum. */
    $rotuloEstado = function ($estado) {
        $valor = $estado instanceof DocumentoStatus ? $estado->value : (string) $estado;

        return DocumentoStatus::tryFrom($valor)?->label() ?? str_replace('_', ' ', $valor);
    };

    $corEstado = function ($estado) {
        $valor = $estado instanceof DocumentoStatus ? $estado->value : (string) $estado;

        return DocumentoStatus::tryFrom($valor)?->color() ?? 'secondary';
    };

    // Query atual sem os parâmetros de paginação: é o que as exportações levam.
    $queryExportacao = collect(request()->query())
        ->except(['pagina_entradas', 'pagina_internos'])
        ->all();

    // Série alternativa do gráfico de produtividade: entradas e produção por
    // espécie documental, sobre o conjunto de espécies presente em qualquer das duas.
    $dadosEspecies = [
        'labels' => $charts['especies_entradas']->keys()
            ->merge($charts['especies_internos']->keys())
            ->unique()->values()->all(),
        'entradas' => $charts['especies_entradas']->all(),
        'internos' => $charts['especies_internos']->all(),
    ];
@endphp

@section('content')
<div class="container-fluid py-4">

    {{-- Cabeçalho e exportações --}}
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0 fw-bold">
                <i class="fas fa-chart-line me-2 text-primary"></i>Módulo de Relatórios e Business Intelligence
            </h1>
            <p class="text-muted small mb-0">
                Entradas, produção documental, tempo médio de resposta e cumprimento de prazos
                — {{ \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') }}
                a {{ \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') }}.
            </p>
        </div>

        <div class="dropdown">
            <button class="btn btn-dark dropdown-toggle shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-download me-1"></i> Exportar
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li>
                    {{-- O PDF vai por POST porque leva as imagens dos gráficos (o DomPDF não corre JavaScript). --}}
                    <form action="{{ route('relatorios.export.pdf') }}" method="POST" target="_blank" id="formExportPdf">
                        @csrf
                        @foreach ($queryExportacao as $chave => $valor)
                            <input type="hidden" name="{{ $chave }}" value="{{ $valor }}">
                        @endforeach
                        <input type="hidden" name="chart_temporal_img" id="chart_temporal_img">
                        <input type="hidden" name="chart_status_img" id="chart_status_img">
                        <input type="hidden" name="chart_departamentos_img" id="chart_departamentos_img">
                        <button type="submit" class="dropdown-item">
                            <i class="fas fa-file-pdf text-danger me-2"></i> Relatório PDF (impresso)
                        </button>
                    </form>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item" href="{{ route('relatorios.export.excel', $queryExportacao) }}">
                        <i class="fas fa-file-excel text-success me-2"></i> Planilha Excel (XLSX)
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('relatorios.export.csv', $queryExportacao) }}">
                        <i class="fas fa-file-csv text-info me-2"></i> Dados em CSV
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="{{ route('relatorios.export.xml', $queryExportacao) }}">
                        <i class="fas fa-file-code text-warning me-2"></i> Metadados XML
                    </a>
                </li>
            </ul>
        </div>
    </div>

    {{-- Barra de filtros --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body bg-light rounded-3 p-3">
            <form method="GET" action="{{ route('relatorios.index') }}" class="row g-2 align-items-end">
                <div class="col-lg-2 col-md-4">
                    <label for="granularitySelect" class="form-label small fw-bold text-secondary mb-1">Granularidade</label>
                    <select name="granularity" id="granularitySelect" class="form-select form-select-sm">
                        <option value="dia" @selected($filters['granularity'] === 'dia')>Por dia</option>
                        <option value="mes" @selected($filters['granularity'] === 'mes')>Por mês</option>
                        <option value="ano" @selected($filters['granularity'] === 'ano')>Por ano</option>
                        <option value="custom" @selected($filters['granularity'] === 'custom')>Intervalo personalizado</option>
                    </select>
                </div>

                <div class="col-lg-1 col-md-3 campo-granularidade campo-mes campo-ano">
                    <label for="filtroAno" class="form-label small fw-bold text-secondary mb-1">Ano</label>
                    <select name="year" id="filtroAno" class="form-select form-select-sm">
                        @foreach (range(now()->year, now()->year - 5) as $ano)
                            <option value="{{ $ano }}" @selected((int) $filters['year'] === $ano)>{{ $ano }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-2 col-md-3 campo-granularidade campo-mes">
                    <label for="filtroMes" class="form-label small fw-bold text-secondary mb-1">Mês</label>
                    <select name="month" id="filtroMes" class="form-select form-select-sm">
                        @foreach (range(1, 12) as $mes)
                            <option value="{{ $mes }}" @selected((int) $filters['month'] === $mes)>
                                {{ ucfirst(\Carbon\Carbon::create(null, $mes, 1)->locale('pt')->translatedFormat('F')) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-2 col-md-3 campo-granularidade campo-dia campo-custom">
                    <label for="filtroDataInicial" class="form-label small fw-bold text-secondary mb-1">
                        <span class="rotulo-data-inicial">Data inicial</span>
                    </label>
                    <input type="date" name="date_from" id="filtroDataInicial" class="form-control form-control-sm"
                           value="{{ $filters['date_from'] }}">
                </div>

                <div class="col-lg-2 col-md-3 campo-granularidade campo-custom">
                    <label for="filtroDataFinal" class="form-label small fw-bold text-secondary mb-1">Data final</label>
                    <input type="date" name="date_to" id="filtroDataFinal" class="form-control form-control-sm"
                           value="{{ $filters['date_to'] }}">
                </div>

                <div class="col-lg-2 col-md-4">
                    <label for="filtroDepartamento" class="form-label small fw-bold text-secondary mb-1">Departamento</label>
                    <select name="departamento_id" id="filtroDepartamento" class="form-select form-select-sm"
                            @disabled(! $filters['escopo_livre'])>
                        <option value="">— Todos —</option>
                        @foreach ($catalogs['departamentos'] as $departamento)
                            <option value="{{ $departamento->id }}"
                                @selected((int) $filters['departamento_id'] === (int) $departamento->id)>
                                {{ $departamento->sigla ?: $departamento->nome }}
                            </option>
                        @endforeach
                    </select>
                    @unless ($filters['escopo_livre'])
                        <div class="form-text small">Limitado ao seu departamento.</div>
                    @endunless
                </div>

                <div class="col-lg-2 col-md-4">
                    <label for="filtroEspecie" class="form-label small fw-bold text-secondary mb-1">Espécie documental</label>
                    <select name="especie" id="filtroEspecie" class="form-select form-select-sm">
                        <option value="">— Todas —</option>
                        @foreach ($catalogs['especies'] as $especie)
                            <option value="{{ $especie->nome }}" @selected($filters['especie'] === $especie->nome)>
                                {{ $especie->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-2 col-md-4">
                    <label for="filtroStatus" class="form-label small fw-bold text-secondary mb-1">Estado</label>
                    <select name="status" id="filtroStatus" class="form-select form-select-sm">
                        <option value="">— Todos —</option>
                        @foreach ($catalogs['estados'] as $valor => $rotulo)
                            <option value="{{ $valor }}" @selected($filters['status'] === $valor)>{{ $rotulo }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-2 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="fas fa-filter me-1"></i> Filtrar
                    </button>
                    <a href="{{ route('relatorios.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpar filtros">
                        <i class="fas fa-times"></i> Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>

    {{-- Indicadores --}}
    <div class="row g-3 mb-4">
        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm border-start border-4 border-primary h-100">
                <div class="card-body py-3">
                    <div class="text-uppercase small text-muted fw-bold">Entradas</div>
                    <div class="h3 mb-0 fw-bold text-primary">{{ $kpis['total_entradas'] }}</div>
                    @if (! is_null($kpis['variacao_entradas_percent']))
                        @php $variacao = $kpis['variacao_entradas_percent']; @endphp
                        <span class="small fw-semibold {{ $variacao >= 0 ? 'text-success' : 'text-danger' }}">
                            <i class="fas fa-arrow-{{ $variacao >= 0 ? 'up' : 'down' }}"></i>
                            {{ number_format(abs($variacao), 1, ',', '.') }}%
                        </span>
                        <span class="small text-muted">vs. período anterior</span>
                    @else
                        <span class="small text-muted">Sem período anterior comparável</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm border-start border-4 border-info h-100">
                <div class="card-body py-3">
                    <div class="text-uppercase small text-muted fw-bold">Produzidos</div>
                    <div class="h3 mb-0 fw-bold text-info">{{ $kpis['total_internos'] }}</div>
                    <span class="small text-muted">
                        {{ $kpis['total_rascunhos'] }} rascunhos · {{ $kpis['total_em_analise'] }} em análise
                    </span>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6">
            <div class="card border-0 shadow-sm border-start border-4 border-success h-100">
                <div class="card-body py-3">
                    <div class="text-uppercase small text-muted fw-bold">Assinados</div>
                    <div class="h3 mb-0 fw-bold text-success">{{ $kpis['total_assinados'] }}</div>
                    <span class="small text-muted">
                        Homologação média: {{ number_format($kpis['avg_assinatura_internos_dias'], 1, ',', '.') }} dias
                    </span>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-6 col-sm-6">
            <div class="card border-0 shadow-sm border-start border-4 border-warning h-100">
                <div class="card-body py-3">
                    <div class="text-uppercase small text-muted fw-bold">Tempo médio de resposta</div>
                    <div class="h3 mb-0 fw-bold text-warning">
                        {{ number_format($kpis['avg_resposta_entradas_dias'], 1, ',', '.') }}
                        <span class="fs-6 text-muted">dias</span>
                    </div>
                    <span class="small text-muted">Da entrada ao despacho, encaminhamento ou arquivo</span>
                </div>
            </div>
        </div>

        <div class="col-xl col-md-6 col-sm-6">
            @php $sla = $charts['sla']; @endphp
            <div class="card border-0 shadow-sm border-start border-4 {{ $sla['compliance_percent'] >= 80 ? 'border-success' : 'border-danger' }} h-100">
                <div class="card-body py-3">
                    <div class="text-uppercase small text-muted fw-bold">Cumprimento de prazos</div>
                    <div class="h3 mb-0 fw-bold {{ $sla['compliance_percent'] >= 80 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($sla['compliance_percent'], 1, ',', '.') }}%
                    </div>
                    <span class="small text-muted">
                        {{ $sla['no_prazo'] }} no prazo · {{ $sla['atrasados'] }} fora do prazo
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Gráficos --}}
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h2 class="h6 mb-0 fw-bold text-secondary">
                        <i class="fas fa-chart-area text-primary me-2"></i>Evolução temporal: entradas vs. produção
                    </h2>
                </div>
                <div class="card-body">
                    <canvas id="chartTemporal" height="120"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h2 class="h6 mb-0 fw-bold text-secondary">
                        <i class="fas fa-gauge-high text-danger me-2"></i>Desempenho de prazos (SLA)
                    </h2>
                </div>
                <div class="card-body">
                    <div class="position-relative" style="height: 160px;">
                        <canvas id="chartSla"></canvas>
                        <div class="position-absolute top-50 start-50 translate-middle text-center" style="margin-top: 18px;">
                            <div class="h4 mb-0 fw-bold">{{ number_format($sla['compliance_percent'], 0) }}%</div>
                            <div class="small text-muted">no prazo</div>
                        </div>
                    </div>
                    <dl class="row small mb-0 mt-3">
                        <dt class="col-8 fw-normal text-muted">Documentos avaliados</dt>
                        <dd class="col-4 text-end fw-bold mb-1">{{ $sla['avaliados'] }}</dd>
                        <dt class="col-8 fw-normal text-muted">Pendentes em risco</dt>
                        <dd class="col-4 text-end fw-bold text-warning mb-1">{{ $sla['em_risco'] }}</dd>
                        <dt class="col-8 fw-normal text-muted">Fora do prazo</dt>
                        <dd class="col-4 text-end fw-bold text-danger mb-0">{{ $sla['atrasados'] }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h2 class="h6 mb-0 fw-bold text-secondary">
                        <i class="fas fa-chart-column text-info me-2"></i>Produtividade
                    </h2>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Eixo da produtividade">
                        <button type="button" class="btn btn-outline-secondary active" data-eixo="departamentos">
                            Por departamento
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-eixo="especies">
                            Por espécie
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="chartDepartamentos" height="170"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h2 class="h6 mb-0 fw-bold text-secondary">
                        <i class="fas fa-chart-pie text-success me-2"></i>Por estado
                    </h2>
                </div>
                <div class="card-body">
                    <canvas id="chartStatus" height="200"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0">
                    <h2 class="h6 mb-0 fw-bold text-secondary">
                        <i class="fas fa-sitemap text-warning me-2"></i>Por procedência
                    </h2>
                </div>
                <div class="card-body">
                    <canvas id="chartProcedencias" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabelas analíticas --}}
    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0 fw-bold text-secondary">
                        <i class="fas fa-inbox text-primary me-2"></i>Documentos de entrada
                    </h2>
                    <span class="badge bg-primary-subtle text-primary">{{ $lists['entradas']->total() }} no período</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Nº</th>
                                <th scope="col">Entrada</th>
                                <th scope="col">Assunto</th>
                                <th scope="col">Departamento</th>
                                <th scope="col" class="text-end">Tramitação</th>
                                <th scope="col">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lists['entradas'] as $doc)
                                <tr>
                                    <td class="text-nowrap">
                                        <a href="{{ route('documentos-entradas.show', $doc->id) }}" class="fw-bold text-decoration-none">
                                            {{ $doc->numero_sequencial }}/{{ $doc->ano_referencia }}
                                        </a>
                                    </td>
                                    <td class="text-nowrap">{{ optional($doc->data_entrada)->format('d/m/Y') }}</td>
                                    <td class="text-truncate" style="max-width: 240px;" title="{{ $doc->assunto }}">
                                        {{ $doc->assunto }}
                                        @if ($doc->classificacao_especie)
                                            <span class="d-block text-muted">{{ $doc->classificacao_especie }}</span>
                                        @endif
                                    </td>
                                    <td>{{ optional($doc->departamento)->sigla ?: optional($doc->departamento)->nome }}</td>
                                    <td class="text-end text-nowrap">
                                        <span class="{{ $doc->sla_status === 'critical' ? 'text-danger fw-bold' : ($doc->sla_status === 'warning' ? 'text-warning fw-semibold' : '') }}">
                                            {{ $doc->dias_decorridos }} d
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $corEstado($doc->status) }}-subtle text-{{ $corEstado($doc->status) }} border border-{{ $corEstado($doc->status) }}-subtle">
                                            {{ $rotuloEstado($doc->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center py-4 text-muted">Nenhum documento de entrada no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($lists['entradas']->hasPages())
                    <div class="card-footer bg-white border-0 pt-3">
                        {{ $lists['entradas']->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0 fw-bold text-secondary">
                        <i class="fas fa-file-signature text-success me-2"></i>Documentos produzidos
                    </h2>
                    <span class="badge bg-success-subtle text-success">{{ $lists['internos']->total() }} no período</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Referência</th>
                                <th scope="col">Título</th>
                                <th scope="col">Autor</th>
                                <th scope="col">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lists['internos'] as $doc)
                                <tr>
                                    <td class="text-nowrap">
                                        <a href="{{ route('documentos-internos.show', $doc->id) }}" class="fw-bold text-decoration-none">
                                            {{ $doc->numero_referencia ?: '—' }}
                                        </a>
                                        <span class="d-block text-muted">{{ optional($doc->created_at)->format('d/m/Y') }}</span>
                                    </td>
                                    <td class="text-truncate" style="max-width: 200px;" title="{{ $doc->titulo }}">
                                        {{ $doc->titulo }}
                                        @if (optional($doc->especie)->nome)
                                            <span class="d-block text-muted">{{ $doc->especie->nome }}</span>
                                        @endif
                                    </td>
                                    <td>{{ optional($doc->autor)->name }}</td>
                                    <td>
                                        <span class="badge bg-{{ $corEstado($doc->status) }}-subtle text-{{ $corEstado($doc->status) }} border border-{{ $corEstado($doc->status) }}-subtle">
                                            {{ $rotuloEstado($doc->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center py-4 text-muted">Nenhum documento produzido no período.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($lists['internos']->hasPages())
                    <div class="card-footer bg-white border-0 pt-3">
                        {{ $lists['internos']->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    'use strict';

    const PALETA = ['#0284c7', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#64748b'];

    const dados = {
        temporal: @json($charts['tendencia_temporal']),
        departamentos: @json($charts['produtividade_departamentos']),
        especies: @json($dadosEspecies),
        status: @json($charts['status_entradas']),
        procedencias: @json($charts['procedencias']),
        sla: @json($charts['sla']),
    };

    /** Mostra apenas os campos de data relevantes para a granularidade escolhida. */
    function alternarCamposGranularidade() {
        const escolha = document.getElementById('granularitySelect').value;

        document.querySelectorAll('.campo-granularidade').forEach(function (campo) {
            campo.classList.add('d-none');
        });
        document.querySelectorAll('.campo-' + escolha).forEach(function (campo) {
            campo.classList.remove('d-none');
        });

        const rotulo = document.querySelector('.rotulo-data-inicial');
        if (rotulo) {
            rotulo.textContent = escolha === 'dia' ? 'Dia' : 'Data inicial';
        }
    }

    const opcoesBase = { responsive: true, maintainAspectRatio: false };

    function criarGraficos() {
        const graficos = {};

        graficos.temporal = new Chart(document.getElementById('chartTemporal'), {
            type: 'line',
            data: {
                labels: dados.temporal.labels,
                datasets: [
                    {
                        label: 'Entradas',
                        data: dados.temporal.entradas,
                        borderColor: PALETA[0],
                        backgroundColor: 'rgba(2, 132, 199, 0.12)',
                        fill: true,
                        tension: 0.3,
                    },
                    {
                        label: 'Produzidos',
                        data: dados.temporal.internos,
                        borderColor: PALETA[1],
                        backgroundColor: 'rgba(16, 185, 129, 0.12)',
                        fill: true,
                        tension: 0.3,
                    },
                ],
            },
            options: Object.assign({}, opcoesBase, {
                // Fundo branco para a imagem exportada não sair transparente no PDF.
                animation: false,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            }),
        });

        graficos.departamentos = new Chart(document.getElementById('chartDepartamentos'), {
            type: 'bar',
            data: {
                labels: dados.departamentos.labels,
                datasets: [
                    { label: 'Entradas', data: dados.departamentos.entradas, backgroundColor: PALETA[0] },
                    { label: 'Produzidos', data: dados.departamentos.internos, backgroundColor: PALETA[1] },
                ],
            },
            options: Object.assign({}, opcoesBase, {
                animation: false,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            }),
        });

        graficos.status = new Chart(document.getElementById('chartStatus'), {
            type: 'doughnut',
            data: {
                labels: Object.keys(dados.status),
                datasets: [{ data: Object.values(dados.status), backgroundColor: PALETA }],
            },
            options: Object.assign({}, opcoesBase, {
                animation: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } },
            }),
        });

        graficos.procedencias = new Chart(document.getElementById('chartProcedencias'), {
            type: 'pie',
            data: {
                labels: Object.keys(dados.procedencias),
                datasets: [{ data: Object.values(dados.procedencias), backgroundColor: PALETA }],
            },
            options: Object.assign({}, opcoesBase, {
                animation: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } } },
            }),
        });

        // Medidor de SLA: meia rosca com o valor ao centro (ver marcação acima).
        graficos.sla = new Chart(document.getElementById('chartSla'), {
            type: 'doughnut',
            data: {
                labels: ['No prazo', 'Fora do prazo'],
                datasets: [{
                    data: [dados.sla.no_prazo, dados.sla.atrasados],
                    backgroundColor: [PALETA[1], PALETA[3]],
                    borderWidth: 0,
                }],
            },
            options: Object.assign({}, opcoesBase, {
                animation: false,
                circumference: 180,
                rotation: 270,
                cutout: '72%',
                plugins: { legend: { display: false } },
            }),
        });

        return graficos;
    }

    /**
     * Troca o eixo do gráfico de produtividade entre departamento e espécie
     * documental, reaproveitando as séries já calculadas no servidor.
     */
    function ligarAlternadorDeEixo(grafico) {
        const botoes = document.querySelectorAll('[data-eixo]');

        botoes.forEach(function (botao) {
            botao.addEventListener('click', function () {
                botoes.forEach((b) => b.classList.remove('active'));
                botao.classList.add('active');

                if (botao.dataset.eixo === 'departamentos') {
                    grafico.data.labels = dados.departamentos.labels;
                    grafico.data.datasets[0].data = dados.departamentos.entradas;
                    grafico.data.datasets[1].data = dados.departamentos.internos;
                } else {
                    const rotulos = dados.especies.labels;
                    grafico.data.labels = rotulos;
                    grafico.data.datasets[0].data = rotulos.map((r) => dados.especies.entradas[r] || 0);
                    grafico.data.datasets[1].data = rotulos.map((r) => dados.especies.internos[r] || 0);
                }

                grafico.update();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        alternarCamposGranularidade();
        document.getElementById('granularitySelect').addEventListener('change', alternarCamposGranularidade);

        const graficos = criarGraficos();
        ligarAlternadorDeEixo(graficos.departamentos);

        // O PDF é gerado no servidor; os gráficos seguem como PNG produzidos aqui.
        document.getElementById('formExportPdf').addEventListener('submit', function () {
            document.getElementById('chart_temporal_img').value = graficos.temporal.toBase64Image('image/png');
            document.getElementById('chart_status_img').value = graficos.status.toBase64Image('image/png');
            document.getElementById('chart_departamentos_img').value = graficos.departamentos.toBase64Image('image/png');
        });
    });
})();
</script>
@endsection
