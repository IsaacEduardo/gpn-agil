<?php

namespace App\Services;

use App\Models\Departamento;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Desempenho dos técnicos na execução das tarefas dos documentos externos.
 *
 * Regras (2026-10-02):
 *  - Contam as tarefas atribuídas a pessoas e criadas no período. Retiradas (um
 *    colega assumiu a tarefa em concorrência) e canceladas não são trabalho do
 *    técnico e ficam fora de todos os indicadores.
 *  - Sem pontuação única nem ranking: amostras pequenas fariam de uma ordenação
 *    ruído com aparência de juízo. Cada indicador leva o seu n.
 *  - O prazo é uma data: concluir no próprio dia do prazo está dentro dele.
 *  - Tempo de resolução: da atribuição (ou de quando foi assumida, em
 *    concorrência) até à conclusão. concluida_em só existe desde 2026-09-13; as
 *    tarefas concluídas sem esta data não entram nas medianas.
 *  - Qualidade fica fora: não há aprovação nem devolução do parecer.
 *
 * Âmbito, decidido no servidor: admin (ou gabinete.view_all) vê tudo; chefe de
 * gabinete e super-chefe veem os departamentos do gabinete; o chefe designado
 * vê os departamentos que chefia; quem tem relatorios.desempenho sem chefia vê
 * o próprio departamento; os restantes veem apenas os seus números.
 */
class DesempenhoEquipaService
{
    public function __construct(
        protected DocumentoPermissionService $permissoes,
        protected ReportService $relatorios,
    ) {}

    /**
     * Quem o utilizador pode ver: 'todos', 'equipa' (os departamentos indicados)
     * ou 'proprio' (só ele).
     *
     * @return array{tipo: string, departamentos: int[]}
     */
    public function escopo(User $user): array
    {
        if ($user->isAdmin() || $this->permissoes->isAdmin($user) || $user->can('gabinete.view_all')) {
            return ['tipo' => 'todos', 'departamentos' => []];
        }

        $gabinetes = Gabinete::where('responsavel_id', $user->id)
            ->orWhere('super_chefe_id', $user->id)
            ->pluck('id');

        $deps = Departamento::whereIn('gabinete_id', $gabinetes)->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->merge($this->permissoes->departamentosChefiados($user));

        if ($deps->isEmpty() && $user->can('relatorios.desempenho')) {
            $deps = collect($this->permissoes->getUserDepartments($user))->map(fn ($id) => (int) $id);
        }

        $deps = $deps->unique()->values()->all();

        return $deps === []
            ? ['tipo' => 'proprio', 'departamentos' => []]
            : ['tipo' => 'equipa', 'departamentos' => $deps];
    }

    /**
     * Departamento pedido no filtro, se estiver dentro do âmbito.
     *
     * @return bool false quando o pedido sai do âmbito (o controlador recusa).
     */
    public function departamentoPermitido(array $escopo, ?int $departamentoId): bool
    {
        if ($departamentoId === null) {
            return true;
        }

        return match ($escopo['tipo']) {
            'todos' => true,
            'equipa' => in_array($departamentoId, $escopo['departamentos'], true),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $filtros  já validados
     * @return array<string, mixed>
     */
    public function dados(array $filtros, User $user): array
    {
        $periodo = $this->relatorios->periodo($filtros);
        $escopo = $this->escopo($user);
        $departamentoId = filled($filtros['departamento_id'] ?? null) ? (int) $filtros['departamento_id'] : null;

        $base = $this->tarefasDoPeriodo($periodo['inicio'], $periodo['fim']);
        $pessoas = $this->pessoas($user, $escopo, $departamentoId, $base);
        $ids = $pessoas->pluck('id')->all();

        $contagens = $this->contagens(clone $base, $ids);
        $duracoes = $this->duracoes(clone $base, $ids);

        $linhas = $pessoas->map(fn (User $p) => $this->linha($p, $contagens->get($p->id), $duracoes->get($p->id, collect())))->values();

        return [
            'filters' => [
                'granularity' => $periodo['granularity'],
                'date_from' => $periodo['inicio']->toDateString(),
                'date_to' => $periodo['fim']->toDateString(),
                'year' => (int) ($filtros['year'] ?? $periodo['inicio']->year),
                'month' => (int) ($filtros['month'] ?? $periodo['inicio']->month),
                'departamento_id' => $departamentoId,
            ],
            'escopo' => $escopo,
            'linhas' => $linhas,
            'totais' => $this->totais($linhas, $duracoes->flatten(1)),
            'departamentos' => $this->departamentosDoFiltro($escopo),
        ];
    }

    /** Tarefas atribuídas a pessoas no período, sem retiradas nem canceladas. */
    protected function tarefasDoPeriodo(CarbonImmutable $inicio, CarbonImmutable $fim)
    {
        return DocumentoTarefa::query()
            ->whereNotNull('assigned_to_user_id')
            ->whereNotIn('status', ['cancelada', DocumentoTarefa::STATUS_RETIRADA])
            ->whereBetween('created_at', [$inicio->toDateTimeString(), $fim->toDateTimeString()]);
    }

    /** @return Collection<int, User> */
    protected function pessoas(User $user, array $escopo, ?int $departamentoId, $base): Collection
    {
        if ($escopo['tipo'] === 'proprio') {
            return collect([$user]);
        }

        $deps = $departamentoId !== null ? [$departamentoId] : $escopo['departamentos'];
        $query = User::query()->select(['id', 'name', 'departamento_id'])->orderBy('name');

        if ($deps !== []) {
            $query->where(fn ($q) => $q->whereIn('departamento_id', $deps)
                ->orWhereHas('departamentos', fn ($d) => $d->whereIn('departamentos.id', $deps)));
        } else {
            // Órgão inteiro: só quem teve tarefas no período, para a lista não
            // se encher de contas que nunca executam tarefas.
            $query->whereIn('id', (clone $base)->select('assigned_to_user_id'));
        }

        return $query->with('departamento:id,nome,sigla')->get();
    }

    /** Uma só consulta agregada para todos os técnicos. */
    protected function contagens($base, array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $hoje = now()->toDateString();
        $soma = fn (string $condicao) => "SUM(CASE WHEN {$condicao} THEN 1 ELSE 0 END)";
        $noPrazo = 'concluida_em IS NOT NULL AND DATE(concluida_em) <= DATE(prazo_at)';
        $atrasoPendente = "status = 'pendente' AND prazo_at IS NOT NULL AND DATE(prazo_at) < '{$hoje}'";
        $atrasoConcluida = "status = 'concluida' AND prazo_at IS NOT NULL AND concluida_em IS NOT NULL AND DATE(concluida_em) > DATE(prazo_at)";

        return $base->whereIn('assigned_to_user_id', $ids)
            ->groupBy('assigned_to_user_id')
            ->selectRaw(implode(', ', [
                'assigned_to_user_id as uid',
                'COUNT(*) as recebidas',
                $soma("status = 'concluida'").' as concluidas',
                $soma("status = 'pendente'").' as pendentes',
                $soma($atrasoPendente).' as pendentes_atrasadas',
                $soma("status = 'concluida' AND prazo_at IS NOT NULL AND concluida_em IS NOT NULL").' as avaliadas_prazo',
                $soma("status = 'concluida' AND prazo_at IS NOT NULL AND {$noPrazo}").' as no_prazo',
                $soma("status = 'concluida' AND concluida_em IS NULL").' as sem_data_conclusao',
                $soma('assumida_em IS NOT NULL').' as assumidas',
                $soma("assumida_em IS NOT NULL AND (({$atrasoPendente}) OR ({$atrasoConcluida}))").' as assumidas_em_atraso',
            ]))
            ->get()
            ->keyBy('uid');
    }

    /**
     * Durações em horas, por técnico. Calculadas em PHP (medianas não são
     * portáveis entre MySQL e SQLite); é uma só consulta, só das colunas precisas.
     *
     * @return Collection<int, Collection<int, array{resolucao: ?float, ate_assumir: ?float}>>
     */
    protected function duracoes($base, array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return $base->whereIn('assigned_to_user_id', $ids)
            ->where(fn ($q) => $q->whereNotNull('concluida_em')->orWhereNotNull('assumida_em'))
            ->get(['assigned_to_user_id', 'status', 'created_at', 'assumida_em', 'concluida_em'])
            ->groupBy('assigned_to_user_id')
            ->map(fn (Collection $tarefas) => $tarefas->map(fn (DocumentoTarefa $t) => [
                'resolucao' => $t->status === 'concluida' && $t->concluida_em
                    ? $this->horas($t->assumida_em ?? $t->created_at, $t->concluida_em)
                    : null,
                'ate_assumir' => $t->assumida_em ? $this->horas($t->created_at, $t->assumida_em) : null,
            ]));
    }

    protected function linha(User $pessoa, $c, Collection $duracoes): array
    {
        $n = fn (string $campo) => (int) ($c->{$campo} ?? 0);

        return [
            'user_id' => $pessoa->id,
            'nome' => $pessoa->name,
            'departamento' => optional($pessoa->departamento)->sigla ?: optional($pessoa->departamento)->nome,
            'recebidas' => $n('recebidas'),
            'concluidas' => $n('concluidas'),
            'pendentes' => $n('pendentes'),
            'pendentes_atrasadas' => $n('pendentes_atrasadas'),
            'taxa_conclusao' => $this->percentagem($n('concluidas'), $n('recebidas')),
            'avaliadas_prazo' => $n('avaliadas_prazo'),
            'no_prazo' => $n('no_prazo'),
            'cumprimento_prazo' => $this->percentagem($n('no_prazo'), $n('avaliadas_prazo')),
            'sem_data_conclusao' => $n('sem_data_conclusao'),
            'assumidas' => $n('assumidas'),
            'assumidas_em_atraso' => $n('assumidas_em_atraso'),
            ...$this->medianas($duracoes),
        ];
    }

    protected function totais(Collection $linhas, Collection $duracoes): array
    {
        $soma = fn (string $campo) => (int) $linhas->sum($campo);

        return [
            'recebidas' => $soma('recebidas'),
            'concluidas' => $soma('concluidas'),
            'pendentes' => $soma('pendentes'),
            'pendentes_atrasadas' => $soma('pendentes_atrasadas'),
            'taxa_conclusao' => $this->percentagem($soma('concluidas'), $soma('recebidas')),
            'avaliadas_prazo' => $soma('avaliadas_prazo'),
            'no_prazo' => $soma('no_prazo'),
            'cumprimento_prazo' => $this->percentagem($soma('no_prazo'), $soma('avaliadas_prazo')),
            'sem_data_conclusao' => $soma('sem_data_conclusao'),
            'assumidas' => $soma('assumidas'),
            'assumidas_em_atraso' => $soma('assumidas_em_atraso'),
            ...$this->medianas($duracoes),
        ];
    }

    /** @return array{mediana_resolucao_horas: ?float, n_resolucao: int, mediana_ate_assumir_horas: ?float, n_ate_assumir: int} */
    protected function medianas(Collection $duracoes): array
    {
        $resolucao = $duracoes->pluck('resolucao')->filter(fn ($v) => $v !== null)->values();
        $assumir = $duracoes->pluck('ate_assumir')->filter(fn ($v) => $v !== null)->values();

        return [
            'mediana_resolucao_horas' => $this->mediana($resolucao),
            'n_resolucao' => $resolucao->count(),
            'mediana_ate_assumir_horas' => $this->mediana($assumir),
            'n_ate_assumir' => $assumir->count(),
        ];
    }

    protected function mediana(Collection $valores): ?float
    {
        if ($valores->isEmpty()) {
            return null;
        }

        return round((float) $valores->median(), 1);
    }

    protected function percentagem(int $parte, int $total): ?float
    {
        return $total > 0 ? round(100 * $parte / $total, 1) : null;
    }

    protected function horas($de, $ate): float
    {
        return max(0, Carbon::parse($de)->diffInMinutes(Carbon::parse($ate), false)) / 60;
    }

    /** Departamentos que o filtro pode oferecer, dentro do âmbito. */
    protected function departamentosDoFiltro(array $escopo): Collection
    {
        return match ($escopo['tipo']) {
            'todos' => Departamento::orderBy('nome')->get(['id', 'nome', 'sigla']),
            'equipa' => Departamento::whereIn('id', $escopo['departamentos'])->orderBy('nome')->get(['id', 'nome', 'sigla']),
            default => collect(),
        };
    }

    /** "3,5 h" abaixo de dois dias, "4,2 dias" acima. */
    public static function duracaoLegivel(?float $horas): string
    {
        if ($horas === null) {
            return '—';
        }

        return $horas < 48
            ? number_format($horas, 1, ',', ' ').' h'
            : number_format($horas / 24, 1, ',', ' ').' dias';
    }
}
