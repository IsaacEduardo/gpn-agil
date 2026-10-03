@extends('layouts.app')

@section('title', 'Desempenho da equipa')

@php
    use App\Services\DesempenhoEquipaService;

    $duracao = fn (?float $horas) => DesempenhoEquipaService::duracaoLegivel($horas);
    $pct = fn (?float $valor) => $valor === null ? '—' : number_format($valor, 0, ',', ' ').'%';
    $user = Auth::user();
    $veVisaoGeral = $user->isAdmin() || $user->isChefeGabinete() || $user->isSuperChefeGabinete() || $user->isChefeDepartamento() || $user->can('relatorios.view');
    $proprio = $escopo['tipo'] === 'proprio';
@endphp

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-0 fw-bold">
                <i class="fas fa-user-check me-2 text-primary"></i>{{ $proprio ? 'O meu desempenho' : 'Desempenho da equipa' }}
            </h1>
            <p class="text-muted small mb-0">
                Execução das tarefas dos documentos externos atribuídas
                de {{ \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') }}
                a {{ \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') }}.
            </p>
        </div>
    </div>

    @if ($veVisaoGeral)
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link" href="{{ route('relatorios.index') }}"><i class="fas fa-chart-line me-1"></i>Visão geral</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" aria-current="page" href="{{ route('relatorios.desempenho') }}"><i class="fas fa-user-check me-1"></i>Desempenho da equipa</a>
            </li>
        </ul>
    @endif

    {{-- Filtros: o mesmo período do painel (ReportService::periodo). --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body bg-light rounded-3 p-3">
            <form method="GET" action="{{ route('relatorios.desempenho') }}" class="row g-2 align-items-end" id="formDesempenho">
                <div class="col-lg-2 col-md-4">
                    <label for="desGranularidade" class="form-label small fw-bold text-secondary mb-1">Período</label>
                    <select name="granularity" id="desGranularidade" class="form-select form-select-sm">
                        <option value="mes" @selected($filters['granularity'] === 'mes')>Mês</option>
                        <option value="ano" @selected($filters['granularity'] === 'ano')>Ano</option>
                        <option value="custom" @selected($filters['granularity'] === 'custom')>Intervalo personalizado</option>
                    </select>
                </div>
                <div class="col-lg-1 col-md-3" data-campo="mes ano">
                    <label for="desAno" class="form-label small fw-bold text-secondary mb-1">Ano</label>
                    <select name="year" id="desAno" class="form-select form-select-sm">
                        @foreach (range(now()->year, now()->year - 5) as $ano)
                            <option value="{{ $ano }}" @selected((int) $filters['year'] === $ano)>{{ $ano }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3" data-campo="mes">
                    <label for="desMes" class="form-label small fw-bold text-secondary mb-1">Mês</label>
                    <select name="month" id="desMes" class="form-select form-select-sm">
                        @foreach (range(1, 12) as $mes)
                            <option value="{{ $mes }}" @selected((int) $filters['month'] === $mes)>
                                {{ ucfirst(\Carbon\Carbon::create(null, $mes, 1)->locale('pt')->translatedFormat('F')) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3" data-campo="custom">
                    <label for="desDe" class="form-label small fw-bold text-secondary mb-1">De</label>
                    <input type="date" name="date_from" id="desDe" class="form-control form-control-sm" value="{{ $filters['date_from'] }}">
                </div>
                <div class="col-lg-2 col-md-3" data-campo="custom">
                    <label for="desAte" class="form-label small fw-bold text-secondary mb-1">Até</label>
                    <input type="date" name="date_to" id="desAte" class="form-control form-control-sm" value="{{ $filters['date_to'] }}">
                </div>
                @if ($departamentos->count() > 1)
                    <div class="col-lg-2 col-md-4">
                        <label for="desDepartamento" class="form-label small fw-bold text-secondary mb-1">Departamento</label>
                        <select name="departamento_id" id="desDepartamento" class="form-select form-select-sm">
                            <option value="">— Todos os que vejo —</option>
                            @foreach ($departamentos as $dep)
                                <option value="{{ $dep->id }}" @selected((int) $filters['departamento_id'] === (int) $dep->id)>{{ $dep->sigla ?: $dep->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-lg-2 col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-filter me-1"></i> Filtrar</button>
                    <a href="{{ route('relatorios.desempenho') }}" class="btn btn-outline-secondary btn-sm" title="Limpar filtros"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>
    </div>

    {{-- Resumo --}}
    <div class="row g-3 mb-4">
        @foreach ([
            ['Tarefas recebidas', $totais['recebidas'], 'fa-inbox', 'primary', null],
            ['Concluídas', $totais['concluidas'], 'fa-check-double', 'success', $pct($totais['taxa_conclusao']).' do recebido'],
            ['Cumprimento do prazo', $pct($totais['cumprimento_prazo']), 'fa-gauge-high', 'info', $totais['no_prazo'].' de '.$totais['avaliadas_prazo'].' com prazo'],
            ['Pendentes fora do prazo', $totais['pendentes_atrasadas'], 'fa-triangle-exclamation', $totais['pendentes_atrasadas'] > 0 ? 'danger' : 'secondary', $totais['pendentes'].' pendentes no total'],
            ['Mediana de resolução', $duracao($totais['mediana_resolucao_horas']), 'fa-stopwatch', 'secondary', 'n = '.$totais['n_resolucao']],
        ] as [$rotulo, $valor, $icone, $cor, $nota])
            <div class="col-xl col-md-4 col-sm-6">
                <div class="card border-0 shadow-sm border-start border-4 border-{{ $cor }} h-100">
                    <div class="card-body py-3">
                        <div class="small text-muted fw-semibold"><i class="fas {{ $icone }} me-1 text-{{ $cor }}"></i>{{ $rotulo }}</div>
                        <div class="h4 fw-bold mb-0">{{ $valor }}</div>
                        @if ($nota)<div class="small text-muted">{{ $nota }}</div>@endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Por técnico: ordenado por nome, sem ranking (ver DesempenhoEquipaService). --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3 border-0">
            <h2 class="h6 mb-0 fw-bold text-secondary"><i class="fas fa-users me-2 text-primary"></i>{{ $proprio ? 'Os meus números' : 'Por técnico' }}</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small" id="tabelaDesempenho">
                <thead class="table-light">
                    <tr>
                        <th>Técnico</th>
                        <th class="text-end">Recebidas</th>
                        <th class="text-end">Concluídas</th>
                        <th class="text-end">Pendentes</th>
                        <th class="text-end" title="Pendentes com o prazo já ultrapassado">Fora do prazo</th>
                        <th class="text-end" title="Concluídas até ao dia do prazo, entre as concluídas que tinham prazo">Cumprimento do prazo</th>
                        <th class="text-end" title="Da atribuição (ou de quando foi assumida) até à conclusão">Mediana de resolução</th>
                        <th class="text-end" title="Tarefas em concorrência que o técnico assumiu">Assumidas</th>
                        <th class="text-end" title="Da oferta em concorrência até assumir">Mediana até assumir</th>
                        <th class="text-end" title="Assumidas e paradas além do prazo, ou concluídas depois dele">Assumidas em atraso</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($linhas as $l)
                        <tr data-user-id="{{ $l['user_id'] }}">
                            <td>
                                <div class="fw-semibold text-dark">{{ $l['nome'] }}</div>
                                @if ($l['departamento'])<div class="text-muted" style="font-size: .75rem;">{{ $l['departamento'] }}</div>@endif
                            </td>
                            <td class="text-end fw-semibold">{{ $l['recebidas'] }}</td>
                            <td class="text-end">
                                {{ $l['concluidas'] }}
                                @if ($l['taxa_conclusao'] !== null)<span class="text-muted">· {{ $pct($l['taxa_conclusao']) }}</span>@endif
                            </td>
                            <td class="text-end">{{ $l['pendentes'] }}</td>
                            <td class="text-end {{ $l['pendentes_atrasadas'] > 0 ? 'text-danger fw-bold' : 'text-muted' }}">{{ $l['pendentes_atrasadas'] }}</td>
                            <td class="text-end">
                                {{ $pct($l['cumprimento_prazo']) }}
                                <div class="text-muted" style="font-size: .72rem;">{{ $l['no_prazo'] }} de {{ $l['avaliadas_prazo'] }}</div>
                            </td>
                            <td class="text-end">
                                {{ $duracao($l['mediana_resolucao_horas']) }}
                                <div class="text-muted" style="font-size: .72rem;">n = {{ $l['n_resolucao'] }}</div>
                            </td>
                            <td class="text-end">{{ $l['assumidas'] }}</td>
                            <td class="text-end">
                                {{ $duracao($l['mediana_ate_assumir_horas']) }}
                                <div class="text-muted" style="font-size: .72rem;">n = {{ $l['n_ate_assumir'] }}</div>
                            </td>
                            <td class="text-end">
                                @if ($l['assumidas_em_atraso'] > 0)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">{{ $l['assumidas_em_atraso'] }}</span>
                                @else
                                    <span class="text-muted">0</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">Sem técnicos neste âmbito.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($linhas->count() > 1)
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td>Total</td>
                            <td class="text-end">{{ $totais['recebidas'] }}</td>
                            <td class="text-end">{{ $totais['concluidas'] }} <span class="text-muted">· {{ $pct($totais['taxa_conclusao']) }}</span></td>
                            <td class="text-end">{{ $totais['pendentes'] }}</td>
                            <td class="text-end">{{ $totais['pendentes_atrasadas'] }}</td>
                            <td class="text-end">{{ $pct($totais['cumprimento_prazo']) }}</td>
                            <td class="text-end">{{ $duracao($totais['mediana_resolucao_horas']) }}</td>
                            <td class="text-end">{{ $totais['assumidas'] }}</td>
                            <td class="text-end">{{ $duracao($totais['mediana_ate_assumir_horas']) }}</td>
                            <td class="text-end">{{ $totais['assumidas_em_atraso'] }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Como ler: os limites dos números estão à vista, não escondidos. --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body small text-muted">
            <div class="fw-semibold text-secondary mb-2"><i class="fas fa-circle-info me-1"></i>Como ler estes números</div>
            <ul class="mb-0 ps-3">
                <li>Contam as tarefas atribuídas no período. Tarefas canceladas e as que um colega assumiu primeiro (em concorrência) não contam.</li>
                <li>O cumprimento do prazo só considera as tarefas concluídas que tinham prazo; concluir no próprio dia do prazo está dentro dele.</li>
                <li>A mediana de resolução só usa tarefas com data de conclusão (registada desde 13/09/2026).
                    @if ($totais['sem_data_conclusao'] > 0)
                        Neste período, <strong>{{ $totais['sem_data_conclusao'] }}</strong> tarefa(s) concluída(s) não a têm e ficam de fora.
                    @endif
                </li>
                <li>Os números medem rapidez e prazo, não a qualidade do parecer. Com poucas tarefas, uma diferença entre técnicos pode ser só acaso: veja sempre o n.</li>
            </ul>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        const seletor = document.getElementById('desGranularidade');
        if (!seletor) return;
        const actualizar = function () {
            document.querySelectorAll('#formDesempenho [data-campo]').forEach(function (el) {
                const visivel = el.getAttribute('data-campo').split(' ').includes(seletor.value);
                el.classList.toggle('d-none', !visivel);
                el.querySelectorAll('input, select').forEach(function (campo) { campo.disabled = !visivel; });
            });
        };
        seletor.addEventListener('change', actualizar);
        actualizar();
    })();
</script>
@endsection
