<?php

namespace App\Services;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agregação de indicadores do módulo de Relatórios & BI.
 *
 * Regras do eixo temporal (para que os números sejam reprodutíveis):
 *
 *  - Documentos de entrada são filtrados e distribuídos por `data_entrada`,
 *    a data oficial de receção no órgão — não por `created_at`, que é apenas
 *    o instante do registo no sistema e desloca todo o registo retroativo
 *    para o dia errado.
 *  - Documentos internos usam `created_at`, o instante de elaboração.
 *  - Na granularidade "dia" o eixo passa a horas; aí, e só aí, as entradas são
 *    distribuídas pela hora de `created_at` (a `data_entrada` não tem hora).
 *
 * Todas as agregações são feitas em SQL (`groupBy` + `selectRaw`) com
 * expressões resolvidas por driver, para funcionarem tanto em MySQL/MariaDB
 * como em SQLite sem carregar as listas em memória.
 */
class ReportService
{
    /** Granularidades aceites no filtro. */
    public const GRANULARIDADES = ['dia', 'mes', 'ano', 'custom'];

    /** Nº máximo de pontos no eixo temporal antes de agregar mais grosso. */
    private const MAX_BUCKETS = 96;

    /** Nº de linhas por página nas tabelas analíticas do painel. */
    private const POR_PAGINA = 15;

    /**
     * Ponto de entrada único: devolve filtros normalizados, KPIs, séries dos
     * gráficos, listas e catálogos.
     *
     * @param  array<string, mixed>  $filtros  Input do pedido (validado no controlador).
     * @param  bool  $paraExportacao  true devolve as listas completas (limitadas
     *                                por documentos.limite_exportacao) em vez de paginadas.
     * @return array<string, mixed>
     */
    public function getReportData(array $filtros, User $user, bool $paraExportacao = false): array
    {
        $f = $this->normalizarFiltros($filtros, $user);

        $entradas = $this->queryEntradas($f);
        $internos = $this->queryInternos($f);

        $sla = $this->metricasSla($entradas);

        return [
            'filters' => $f,
            'kpis' => $this->kpis($entradas, $internos, $sla, $f),
            'charts' => $this->graficos($entradas, $internos, $sla, $f),
            'lists' => $paraExportacao
                ? $this->listasCompletas($entradas, $internos)
                : $this->listasPaginadas($entradas, $internos),
            'catalogs' => $this->catalogos(),
        ];
    }

    // -----------------------------------------------------------------
    // Filtros e escopo
    // -----------------------------------------------------------------

    /**
     * Normaliza o input do pedido num conjunto de filtros fechado, já com o
     * escopo por perfil aplicado.
     *
     * Escopo: administradores e chefes de gabinete (ou quem tenha
     * `gabinete.view_all`) escolhem o departamento livremente; os restantes
     * ficam presos ao seu próprio departamento, independentemente do que
     * venha no pedido.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    protected function normalizarFiltros(array $filtros, User $user): array
    {
        $granularidade = in_array($filtros['granularity'] ?? null, self::GRANULARIDADES, true)
            ? $filtros['granularity']
            : 'mes';

        [$inicio, $fim] = $this->intervalo($granularidade, $filtros);

        $escopoLivre = $this->podeEscolherDepartamento($user);
        $departamentoId = $escopoLivre
            ? (filled($filtros['departamento_id'] ?? null) ? (int) $filtros['departamento_id'] : null)
            : $user->departamento_id;

        return [
            'granularity' => $granularidade,
            'date_from' => $inicio->toDateString(),
            'date_to' => $fim->toDateString(),
            'inicio' => $inicio,
            'fim' => $fim,
            'bucket' => $this->bucketDe($granularidade, $inicio, $fim),
            'departamento_id' => $departamentoId,
            'especie' => filled($filtros['especie'] ?? null) ? (string) $filtros['especie'] : null,
            'status' => filled($filtros['status'] ?? null) ? (string) $filtros['status'] : null,
            'year' => (int) ($filtros['year'] ?? $inicio->year),
            'month' => (int) ($filtros['month'] ?? $inicio->month),
            'escopo_livre' => $escopoLivre,
        ];
    }

    /**
     * Quem pode analisar qualquer departamento (ou o órgão inteiro).
     */
    public function podeEscolherDepartamento(User $user): bool
    {
        return $user->isAdmin()
            || $user->isChefeGabinete()
            || $user->isSuperChefeGabinete()
            || $user->can('gabinete.view_all');
    }

    /**
     * Converte granularidade + campos do formulário num intervalo fechado.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function intervalo(string $granularidade, array $filtros): array
    {
        $agora = CarbonImmutable::now();
        $ano = (int) ($filtros['year'] ?? $agora->year);
        $mes = max(1, min(12, (int) ($filtros['month'] ?? $agora->month)));

        if ($granularidade === 'dia') {
            $dia = filled($filtros['date_from'] ?? null)
                ? CarbonImmutable::parse($filtros['date_from'])
                : $agora;

            return [$dia->startOfDay(), $dia->endOfDay()];
        }

        if ($granularidade === 'ano') {
            $base = CarbonImmutable::create($ano, 1, 1);

            return [$base->startOfYear(), $base->endOfYear()];
        }

        if ($granularidade === 'custom') {
            $de = filled($filtros['date_from'] ?? null)
                ? CarbonImmutable::parse($filtros['date_from'])->startOfDay()
                : $agora->subDays(30)->startOfDay();
            $ate = filled($filtros['date_to'] ?? null)
                ? CarbonImmutable::parse($filtros['date_to'])->endOfDay()
                : $agora->endOfDay();

            // Intervalo invertido é corrigido em vez de devolver um período vazio.
            return $de->greaterThan($ate) ? [$ate->startOfDay(), $de->endOfDay()] : [$de, $ate];
        }

        $base = CarbonImmutable::create($ano, $mes, 1);

        return [$base->startOfMonth(), $base->endOfMonth()];
    }

    /**
     * Unidade do eixo temporal. Num intervalo personalizado longo o eixo sobe
     * de dia para mês (e de mês para ano) para não gerar centenas de colunas.
     */
    protected function bucketDe(string $granularidade, CarbonImmutable $inicio, CarbonImmutable $fim): string
    {
        if ($granularidade === 'dia') {
            return 'hora';
        }

        if ($granularidade === 'ano') {
            return 'mes';
        }

        if ($granularidade === 'mes') {
            return 'dia';
        }

        $dias = $inicio->diffInDays($fim) + 1;

        return match (true) {
            $dias <= self::MAX_BUCKETS => 'dia',
            $dias <= self::MAX_BUCKETS * 31 => 'mes',
            default => 'ano',
        };
    }

    // -----------------------------------------------------------------
    // Queries base
    // -----------------------------------------------------------------

    /**
     * Entradas do período, já filtradas. As colunas são sempre qualificadas:
     * estas queries são clonadas e cruzadas com joins nas agregações.
     *
     * @param  array<string, mixed>  $f
     */
    protected function queryEntradas(array $f): Builder
    {
        // Limites com hora: uma coluna DATE gravada como "Y-m-d 00:00:00" fica
        // fora de um intervalo cujo topo seja apenas "Y-m-d", e o documento do
        // último dia do período desaparecia do relatorio. Comparar com
        // datetimes mantem o indice utilizavel (ao contrario de DATE(coluna)).
        $q = DocumentoEntrada::query()
            ->whereBetween('documentos_entradas.data_entrada', [
                $f['inicio']->toDateTimeString(),
                $f['fim']->toDateTimeString(),
            ]);

        if ($f['departamento_id']) {
            $q->where('documentos_entradas.departamento_id', $f['departamento_id']);
        }

        if ($f['especie']) {
            $q->where('documentos_entradas.classificacao_especie', $f['especie']);
        }

        if ($f['status']) {
            $q->where('documentos_entradas.status', $f['status']);
        }

        return $q;
    }

    /**
     * Documentos internos do período, já filtrados.
     *
     * A espécie chega do formulário pelo nome (é a única chave que as entradas
     * têm, em `classificacao_especie`), por isso aqui é resolvida por relação.
     *
     * @param  array<string, mixed>  $f
     */
    protected function queryInternos(array $f): Builder
    {
        $q = DocumentoInterno::query()
            ->whereBetween('documento_internos.created_at', [$f['inicio'], $f['fim']]);

        if ($f['departamento_id']) {
            $q->where('documento_internos.departamento_id', $f['departamento_id']);
        }

        if ($f['especie']) {
            $nome = $f['especie'];
            $q->whereHas('especie', fn ($e) => $e->where('nome', $nome));
        }

        if ($f['status']) {
            $q->where('documento_internos.status', $f['status']);
        }

        return $q;
    }

    // -----------------------------------------------------------------
    // KPIs
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $sla
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    protected function kpis(Builder $entradas, Builder $internos, array $sla, array $f): array
    {
        $totalEntradas = (clone $entradas)->count();
        $totalInternos = (clone $internos)->count();

        $porEstadoInterno = $this->contagemPorColuna($internos, 'documento_internos.status');

        return [
            'total_entradas' => $totalEntradas,
            'total_internos' => $totalInternos,
            'total_produzido' => $totalInternos,
            'total_geral' => $totalEntradas + $totalInternos,
            'total_assinados' => (clone $internos)->whereNotNull('documento_internos.assinado_em')->count(),
            'total_rascunhos' => (int) ($porEstadoInterno[DocumentoStatus::RASCUNHO->value] ?? 0),
            'total_em_analise' => (int) ($porEstadoInterno[DocumentoStatus::EM_ANALISE->value] ?? 0),
            'total_aprovados' => (int) ($porEstadoInterno[DocumentoStatus::APROVADO->value] ?? 0),
            'total_arquivados' => (clone $entradas)->where('documentos_entradas.arquivado', true)->count()
                + (clone $internos)->where('documento_internos.arquivado', true)->count(),
            'avg_resposta_entradas_dias' => $this->mediaTempoResposta($entradas),
            'avg_assinatura_internos_dias' => $this->mediaTempoAssinatura($internos),
            'sla_compliance_percent' => $sla['compliance_percent'],
            'sla_no_prazo' => $sla['no_prazo'],
            'sla_criticos' => $sla['atrasados'],
            'sla_pendentes' => $sla['pendentes'],
            'variacao_entradas_percent' => $this->variacaoPeriodoAnterior($totalEntradas, $f),
        ];
    }

    /**
     * Tempo médio, em dias, entre a entrada do documento e a sua saída do
     * gabinete (despacho, encaminhamento ou arquivo). Só conta documentos
     * efetivamente resolvidos — incluir os pendentes distorceria a média.
     */
    protected function mediaTempoResposta(Builder $query): float
    {
        $valor = (clone $query)
            ->whereRaw($this->sqlFechoEntrada().' IS NOT NULL')
            ->selectRaw('AVG('.$this->sqlDiasEntrada().') as media')
            ->value('media');

        return round(max(0.0, (float) $valor), 1);
    }

    /**
     * Tempo médio, em dias, entre a criação e a assinatura de um documento interno.
     */
    protected function mediaTempoAssinatura(Builder $query): float
    {
        $expressao = $this->sqlDiasEntre('documento_internos.created_at', 'documento_internos.assinado_em');

        $valor = (clone $query)
            ->whereNotNull('documento_internos.assinado_em')
            ->selectRaw('AVG('.$expressao.') as media')
            ->value('media');

        return round(max(0.0, (float) $valor), 1);
    }

    /**
     * Variação percentual de entradas face ao período imediatamente anterior
     * com a mesma duração. Devolve null quando não há base de comparação.
     *
     * @param  array<string, mixed>  $f
     */
    protected function variacaoPeriodoAnterior(int $totalAtual, array $f): ?float
    {
        $dias = $f['inicio']->diffInDays($f['fim']) + 1;

        $anterior = $f;
        $anterior['fim'] = $f['inicio']->subDay()->endOfDay();
        $anterior['inicio'] = $f['inicio']->subDays($dias);

        $totalAnterior = $this->queryEntradas($anterior)->count();

        if ($totalAnterior === 0) {
            return null;
        }

        return round((($totalAtual - $totalAnterior) / $totalAnterior) * 100, 1);
    }

    // -----------------------------------------------------------------
    // SLA
    // -----------------------------------------------------------------

    /**
     * Cumprimento de prazos.
     *
     * O prazo aplicável vem da espécie documental
     * (documento_especies.prazo_tratamento_dias) e, na sua ausência, de
     * documentos.prazo_tratamento_dias — a mesma regra de
     * DocumentoEntrada::prazo_tratamento_dias.
     *
     * Diferença deliberada face ao acessor `sla_status` do modelo: ali um
     * documento arquivado é sempre "normal", porque o acessor mede risco na
     * fila viva; aqui interessa o histórico, logo um documento resolvido 60
     * dias depois da entrada conta como atrasado.
     *
     * A contagem sai de uma única query agregada por (espécie, resolvido,
     * dias), o que devolve poucas dezenas de linhas mesmo com milhares de
     * documentos.
     *
     * @return array<string, mixed>
     */
    protected function metricasSla(Builder $query): array
    {
        $linhas = (clone $query)
            ->selectRaw(implode(', ', [
                'documentos_entradas.classificacao_especie as especie',
                'CASE WHEN '.$this->sqlFechoEntrada().' IS NULL THEN 0 ELSE 1 END as resolvido',
                $this->sqlDiasEntrada().' as dias',
                'COUNT(*) as total',
            ]))
            ->groupBy('especie', 'resolvido', 'dias')
            ->get();

        $prazos = DocumentoEspecie::prazosPorNome();
        $prazoGlobal = (int) config('documentos.prazo_tratamento_dias', 5);
        $fracaoAviso = (float) config('documentos.fracao_aviso_prazo', 0.4);

        $noPrazo = $atrasados = $emRisco = $pendentes = 0;

        foreach ($linhas as $linha) {
            $total = (int) $linha->total;
            $dias = (int) floor((float) $linha->dias);
            $resolvido = (bool) $linha->resolvido;
            $prazo = $prazos[$linha->especie] ?? $prazoGlobal;

            if (! $resolvido) {
                $pendentes += $total;
            }

            if ($dias > $prazo) {
                $atrasados += $total;

                continue;
            }

            $noPrazo += $total;

            $aviso = max(1, (int) ceil($prazo * $fracaoAviso));
            if (! $resolvido && $dias >= $aviso) {
                $emRisco += $total;
            }
        }

        $avaliados = $noPrazo + $atrasados;

        return [
            'no_prazo' => $noPrazo,
            'atrasados' => $atrasados,
            'em_risco' => $emRisco,
            'pendentes' => $pendentes,
            'avaliados' => $avaliados,
            'compliance_percent' => $avaliados > 0 ? round(($noPrazo / $avaliados) * 100, 1) : 100.0,
        ];
    }

    // -----------------------------------------------------------------
    // Séries dos gráficos
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $sla
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    protected function graficos(Builder $entradas, Builder $internos, array $sla, array $f): array
    {
        return [
            'tendencia_temporal' => $this->tendenciaTemporal($entradas, $internos, $f),
            'status_entradas' => $this->contagemPorColuna($entradas, 'documentos_entradas.status'),
            'status_internos' => $this->contagemPorColuna($internos, 'documento_internos.status'),
            'especies_entradas' => $this->contagemPorColuna($entradas, 'documentos_entradas.classificacao_especie', 'Não classificado'),
            'especies_internos' => $this->contagemPorRelacao($internos, 'documento_especies', 'documento_internos.documento_especie_id', 'Não especificado'),
            'procedencias' => $this->contagemPorRelacao($entradas, 'procedencias', 'documentos_entradas.procedencia_id', 'Outros', 'documentos_entradas.procedencia'),
            'produtividade_departamentos' => $this->produtividadeDepartamentos($entradas, $internos),
            'sla' => $sla,
        ];
    }

    /**
     * Série temporal entradas vs. documentos produzidos, alinhada a uma grelha
     * fixa de períodos para que os buckets vazios apareçam como zero.
     *
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    protected function tendenciaTemporal(Builder $entradas, Builder $internos, array $f): array
    {
        $grelha = $this->grelhaTemporal($f);

        // Na vista horária a data_entrada (sem hora) não serve; usa-se o
        // instante do registo, que é o que dá sentido a um gráfico por hora.
        $colunaEntradas = $f['bucket'] === 'hora'
            ? 'documentos_entradas.created_at'
            : 'documentos_entradas.data_entrada';

        $serieEntradas = $this->contagemPorBucket($entradas, $colunaEntradas, $f['bucket']);
        $serieInternos = $this->contagemPorBucket($internos, 'documento_internos.created_at', $f['bucket']);

        $chaves = array_keys($grelha);

        return [
            'labels' => array_values($grelha),
            'entradas' => array_map(fn ($k) => (int) ($serieEntradas[$k] ?? 0), $chaves),
            'internos' => array_map(fn ($k) => (int) ($serieInternos[$k] ?? 0), $chaves),
        ];
    }

    /**
     * Grelha de períodos do intervalo: chave técnica => rótulo legível.
     *
     * @param  array<string, mixed>  $f
     * @return array<string, string>
     */
    protected function grelhaTemporal(array $f): array
    {
        $grelha = [];

        if ($f['bucket'] === 'hora') {
            foreach (range(0, 23) as $h) {
                $grelha[sprintf('%02d', $h)] = sprintf('%02dh', $h);
            }

            return $grelha;
        }

        $cursor = $f['inicio'];

        while ($cursor->lessThanOrEqualTo($f['fim']) && count($grelha) < 400) {
            if ($f['bucket'] === 'mes') {
                // Locale fixado: os rótulos do eixo não dependem de APP_LOCALE.
                $grelha[$cursor->format('Y-m')] = $cursor->locale('pt')->translatedFormat('M/y');
                $cursor = $cursor->addMonth();
            } elseif ($f['bucket'] === 'ano') {
                $grelha[$cursor->format('Y')] = $cursor->format('Y');
                $cursor = $cursor->addYear();
            } else {
                $grelha[$cursor->format('Y-m-d')] = $cursor->format('d/m');
                $cursor = $cursor->addDay();
            }
        }

        return $grelha;
    }

    /**
     * Contagem agregada por período, em SQL.
     *
     * @return array<string, int>
     */
    protected function contagemPorBucket(Builder $query, string $coluna, string $bucket): array
    {
        $expressao = $this->sqlFormatoData($coluna, $bucket);

        return (clone $query)
            ->selectRaw($expressao.' as bucket, COUNT(*) as total')
            ->groupBy(DB::raw($expressao))
            ->pluck('total', 'bucket')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Contagem agregada por uma coluna do próprio documento.
     *
     * @return Collection<string, int>
     */
    protected function contagemPorColuna(Builder $query, string $coluna, string $rotuloVazio = 'Sem valor'): Collection
    {
        return (clone $query)
            ->selectRaw($coluna.' as chave, COUNT(*) as total')
            ->groupBy(DB::raw($coluna))
            ->orderByDesc('total')
            ->get()
            ->mapWithKeys(fn ($l) => [(string) ($l->chave ?: $rotuloVazio) => (int) $l->total]);
    }

    /**
     * Contagem agregada pelo nome de uma tabela de catálogo (espécies,
     * procedências), com recurso opcional a uma coluna de texto livre quando o
     * documento não aponta para o catálogo.
     *
     * @return Collection<string, int>
     */
    protected function contagemPorRelacao(
        Builder $query,
        string $tabela,
        string $chaveEstrangeira,
        string $rotuloVazio = 'Outros',
        ?string $colunaTextoLivre = null
    ): Collection {
        $nome = $colunaTextoLivre
            ? "COALESCE({$tabela}.nome, {$colunaTextoLivre})"
            : "{$tabela}.nome";

        return (clone $query)
            ->leftJoin($tabela, $tabela.'.id', '=', $chaveEstrangeira)
            ->selectRaw($nome.' as chave, COUNT(*) as total')
            ->groupBy(DB::raw($nome))
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->mapWithKeys(fn ($l) => [(string) ($l->chave ?: $rotuloVazio) => (int) $l->total]);
    }

    /**
     * Produtividade comparada por departamento: duas queries agregadas, não
     * uma por departamento.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function produtividadeDepartamentos(Builder $entradas, Builder $internos): array
    {
        $porEntradas = (clone $entradas)
            ->selectRaw('documentos_entradas.departamento_id as dep, COUNT(*) as total')
            ->groupBy('dep')
            ->pluck('total', 'dep');

        $porInternos = (clone $internos)
            ->selectRaw('documento_internos.departamento_id as dep, COUNT(*) as total')
            ->groupBy('dep')
            ->pluck('total', 'dep');

        $ids = $porEntradas->keys()->merge($porInternos->keys())->filter()->unique()->all();

        $labels = $valoresEntradas = $valoresInternos = [];

        foreach (Departamento::whereIn('id', $ids)->orderBy('nome')->get() as $dep) {
            $labels[] = $dep->sigla ?: $dep->nome;
            $valoresEntradas[] = (int) ($porEntradas[$dep->id] ?? 0);
            $valoresInternos[] = (int) ($porInternos[$dep->id] ?? 0);
        }

        return [
            'labels' => $labels,
            'entradas' => $valoresEntradas,
            'internos' => $valoresInternos,
        ];
    }

    // -----------------------------------------------------------------
    // Listas
    // -----------------------------------------------------------------

    /**
     * Listas paginadas para o painel. Cada tabela tem o seu parâmetro de
     * página, para poderem ser navegadas de forma independente.
     *
     * @return array<string, mixed>
     */
    protected function listasPaginadas(Builder $entradas, Builder $internos): array
    {
        return [
            'entradas' => (clone $entradas)
                ->with(['departamento:id,nome,sigla', 'procedenciaCatalogo:id,nome'])
                ->orderByDesc('documentos_entradas.data_entrada')
                ->orderByDesc('documentos_entradas.id')
                ->paginate(self::POR_PAGINA, ['*'], 'pagina_entradas')
                ->withQueryString(),
            'internos' => (clone $internos)
                ->with(['departamento:id,nome,sigla', 'especie:id,nome', 'autor:id,name'])
                ->orderByDesc('documento_internos.created_at')
                ->paginate(self::POR_PAGINA, ['*'], 'pagina_internos')
                ->withQueryString(),
        ];
    }

    /**
     * Listas completas para exportação, sujeitas ao mesmo teto das restantes
     * exportações do sistema (documentos.limite_exportacao).
     *
     * Colhe-se um registo a mais do que o limite: se ele aparecer, o pedido
     * excede o teto e o controlador recusa a exportação em vez de entregar um
     * relatório truncado sem o leitor dar por isso — é a mesma regra das
     * exportações de documentos de entrada.
     *
     * @return array<string, mixed>
     */
    protected function listasCompletas(Builder $entradas, Builder $internos): array
    {
        $limite = (int) config('documentos.limite_exportacao', 5000);

        $listaEntradas = (clone $entradas)
            ->with(['departamento:id,nome,sigla', 'procedenciaCatalogo:id,nome'])
            ->orderByDesc('documentos_entradas.data_entrada')
            ->orderByDesc('documentos_entradas.id')
            ->limit($limite + 1)
            ->get();

        $listaInternos = (clone $internos)
            ->with(['departamento:id,nome,sigla', 'especie:id,nome', 'autor:id,name'])
            ->orderByDesc('documento_internos.created_at')
            ->limit($limite + 1)
            ->get();

        return [
            'entradas' => $listaEntradas->take($limite),
            'internos' => $listaInternos->take($limite),
            'limite' => $limite,
            'excede_limite' => $listaEntradas->count() > $limite || $listaInternos->count() > $limite,
        ];
    }

    /**
     * Catálogos para os selects do formulário de filtros.
     *
     * @return array<string, mixed>
     */
    protected function catalogos(): array
    {
        return [
            'departamentos' => Departamento::orderBy('nome')->get(['id', 'nome', 'sigla']),
            'especies' => DocumentoEspecie::orderBy('nome')->get(['id', 'nome']),
            'estados' => collect(DocumentoStatus::cases())
                ->mapWithKeys(fn (DocumentoStatus $c) => [$c->value => $c->label()]),
        ];
    }

    // -----------------------------------------------------------------
    // Expressões SQL agnósticas do driver
    // -----------------------------------------------------------------

    /**
     * Formata uma coluna de data na chave do bucket pedido.
     *
     * Os tokens %Y/%m/%d/%H são comuns a DATE_FORMAT (MySQL/MariaDB) e a
     * strftime (SQLite); só o nome da função difere.
     */
    protected function sqlFormatoData(string $coluna, string $bucket): string
    {
        $formato = match ($bucket) {
            'hora' => '%H',
            'mes' => '%Y-%m',
            'ano' => '%Y',
            default => '%Y-%m-%d',
        };

        return $this->driver() === 'sqlite'
            ? "strftime('{$formato}', {$coluna})"
            : "DATE_FORMAT({$coluna}, '{$formato}')";
    }

    /**
     * Instante em que uma entrada saiu do gabinete: arquivo, despacho ou saída
     * registada — o que existir primeiro.
     */
    protected function sqlFechoEntrada(): string
    {
        return 'COALESCE(documentos_entradas.arquivado_em, documentos_entradas.data_despacho, documentos_entradas.saida_gabinete_data)';
    }

    /**
     * Dias decorridos de uma entrada: até ao fecho, ou até agora se ainda
     * estiver pendente.
     */
    protected function sqlDiasEntrada(): string
    {
        $fecho = $this->sqlFechoEntrada();

        return $this->driver() === 'sqlite'
            ? "(julianday(COALESCE({$fecho}, 'now')) - julianday(documentos_entradas.data_entrada))"
            : "DATEDIFF(COALESCE({$fecho}, NOW()), documentos_entradas.data_entrada)";
    }

    /**
     * Diferença em dias entre duas colunas de data/hora.
     */
    protected function sqlDiasEntre(string $de, string $ate): string
    {
        return $this->driver() === 'sqlite'
            ? "(julianday({$ate}) - julianday({$de}))"
            : "DATEDIFF({$ate}, {$de})";
    }

    protected function driver(): string
    {
        return DB::connection()->getDriverName();
    }
}
