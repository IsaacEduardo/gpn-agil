<?php

namespace App\Services;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoInterno;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Tempo de vida do cache em segundos (45 segundos para alta performance em VPS)
     */
    protected const CACHE_TTL = 45;

    /**
     * Retorna os dados agregados do dashboard conforme o perfil do usuário
     */
    public function obterDadosDashboard(User $user, bool $forceFresh = false): array
    {
        $perfil = $this->determinarPerfil($user);
        $cacheKey = "dashboard:v2:user:{$user->id}:{$perfil}";

        if ($forceFresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $perfil) {
            return match ($perfil) {
                'CHEFE_GABINETE_ADMIN' => $this->getChefeGabineteAdminData($user),
                'CHEFE_DEPARTAMENTO' => $this->getChefeDepartamentoData($user),
                default => $this->getTecnicoData($user),
            };
        });
    }

    /**
     * Valor e destino de um cartão que leva à listagem de entradas, a partir dos
     * mesmos parâmetros: o número é contado pela própria consulta da lista, pelo
     * que coincide sempre com o que o utilizador encontra ao clicar. Antes os
     * cartões tinham contagens próprias e links com ?status=, que a listagem
     * ignorava — "Tratados" levava aos que carecem de despacho.
     *
     * @return array{valor: int, link: string}
     */
    private function cartaoEntradas(User $user, array $parametros): array
    {
        return [
            'valor' => app(DocumentoEntradaService::class)->contarListagem($parametros, $user),
            'link' => route('documentos-entradas.index', $parametros),
        ];
    }

    /**
     * Como cartaoEntradas(), para a listagem de documentos internos.
     *
     * @return array{valor: int, link: string}
     */
    private function cartaoInternos(User $user, array $parametros): array
    {
        return [
            'valor' => app(DocumentoInternoService::class)->contarListagem($parametros, $user),
            'link' => route('documentos-internos.index', $parametros),
        ];
    }

    /**
     * Determina o perfil operacional do usuário
     */
    public function determinarPerfil(User $user): string
    {
        if ($user->isAdmin() || $user->isChefeGabinete() || $user->isSuperChefeGabinete()) {
            return 'CHEFE_GABINETE_ADMIN';
        }

        if ($user->isChefeDepartamento()) {
            return 'CHEFE_DEPARTAMENTO';
        }

        return 'TECNICO';
    }

    /**
     * 1. Perfil: Técnico do Departamento (Foco em Execução)
     */
    public function getTecnicoData(User $user): array
    {
        $dept = $user->departamentoPrincipal();
        $startOfMonth = now()->startOfMonth();

        // 1. Prazos das tarefas pendentes (alerta do cartão de tarefas)
        $tarefasAgg = DocumentoTarefa::where('assigned_to_user_id', $user->id)
            ->selectRaw("
                COUNT(CASE WHEN status = 'pendente' AND prazo_at IS NOT NULL AND prazo_at < ? THEN 1 END) as total_atrasadas,
                COUNT(CASE WHEN status = 'pendente' AND prazo_at IS NOT NULL AND prazo_at >= ? AND prazo_at <= ? THEN 1 END) as total_urgentes
            ", [now(), now(), now()->addDays(2)])
            ->first();

        $totalAtrasadas = (int) ($tarefasAgg->total_atrasadas ?? 0);
        $totalUrgentes = (int) ($tarefasAgg->total_urgentes ?? 0);

        // Cartões: o número é o da lista para onde levam (ver cartaoEntradas).
        // Os internos levam autor e estado explícitos para manterem o sentido
        // "meus" mesmo que o perfil da listagem não seja o de técnico.
        // "Concluídos no Mês" somava tarefas e internos homologados — duas listas
        // diferentes, sem destino que mostrasse esse número; passa a contar os
        // documentos em que o técnico concluiu uma tarefa este mês.
        $cartaoTarefas = $this->cartaoEntradas($user, ['tab' => 'atribuidos_mim']);
        $cartaoRascunhos = $this->cartaoInternos($user, ['tab' => 'meus_rascunhos', 'status' => 'rascunho', 'autor_id' => $user->id]);
        $cartaoSubmetidos = $this->cartaoInternos($user, ['tab' => 'em_revisao', 'status' => 'em_analise', 'autor_id' => $user->id]);
        $cartaoConcluidos = $this->cartaoEntradas($user, ['tab' => 'concluidos', 'concluidas_desde' => $startOfMonth->toDateString()]);

        // 2. Minhas Demandas Imediatas (Tarefas Pendentes Atribuídas a Mim)
        $demandasImediatas = DocumentoTarefa::with(['documentoEntrada.departamento', 'assignedBy'])
            ->where('assigned_to_user_id', $user->id)
            ->where('status', 'pendente')
            ->orderByRaw('CASE WHEN prazo_at IS NULL THEN 1 ELSE 0 END, prazo_at ASC, created_at DESC')
            ->limit(6)
            ->get()
            ->map(function (DocumentoTarefa $t) {
                $isAtrasada = $t->prazo_at && $t->prazo_at->isPast();
                $isUrgente = $t->prazo_at && !$isAtrasada && $t->prazo_at->diffInDays(now()) <= 2;

                return [
                    'id' => $t->id,
                    'titulo' => $t->titulo,
                    'descricao' => $t->descricao,
                    'documento_entrada_id' => $t->documento_entrada_id,
                    'documento_numero' => $t->documentoEntrada ? ($t->documentoEntrada->numero_sequencial . '/' . $t->documentoEntrada->ano_referencia) : '—',
                    'documento_assunto' => $t->documentoEntrada?->assunto ?? '—',
                    'solicitante' => $t->assignedBy?->name ?? 'Chefia',
                    'prazo_formatado' => $t->prazo_at ? $t->prazo_at->format('d/m/Y') : 'Sem prazo',
                    'is_atrasada' => $isAtrasada,
                    'is_urgente' => $isUrgente,
                    'status_info' => $this->formatarStatusBadge('tarefa', $t->status),
                    'url_executar' => $t->documentoEntrada ? route('documentos-entradas.show', $t->documentoEntrada->id) : '#',
                ];
            });

        // 3. Meus Documentos Internos Recentes
        $meusInternos = DocumentoInterno::with(['especie', 'departamento'])
            ->where('criado_por', $user->id)
            ->orderBy('updated_at', 'desc')
            ->limit(6)
            ->get()
            ->map(function (DocumentoInterno $doc) {
                $statusVal = is_string($doc->status) ? $doc->status : ($doc->status?->value ?? 'rascunho');

                return [
                    'id' => $doc->id,
                    'titulo' => $doc->titulo,
                    'numero_referencia' => $doc->numero_referencia ?: '#' . $doc->id,
                    'especie' => $doc->especie?->nome ?? 'Documento',
                    'status' => $statusVal,
                    'status_info' => $this->formatarStatusBadge('interno', $statusVal),
                    'atualizado_em' => $doc->updated_at ? $doc->updated_at->format('d/m/Y H:i') : '—',
                    'url_show' => route('documentos-internos.show', $doc->id),
                    'url_edit' => route('documentos-internos.edit', $doc->id),
                    'pode_editar' => $statusVal === 'rascunho',
                ];
            });

        return [
            'perfil' => 'TECNICO',
            'perfil_label' => 'Técnico Operacional',
            'banner' => [
                'saudacao' => "Bem-vindo(a), {$user->name}",
                'titulo' => 'Minha Área de Trabalho',
                'subtitulo' => $dept ? ($dept->sigla ? "{$dept->sigla} — {$dept->nome}" : $dept->nome) : 'Departamento Geral',
                'icone' => 'fas fa-user-cog',
                'badge' => 'Técnico Operacional',
            ],
            'kpis' => [
                [
                    'id' => 'tarefas_pendentes',
                    'label' => 'Minhas Tarefas Pendentes',
                    'valor' => $cartaoTarefas['valor'],
                    'alerta' => $totalAtrasadas > 0 ? "{$totalAtrasadas} em atraso" : ($totalUrgentes > 0 ? "{$totalUrgentes} p/ vencer" : null),
                    'icone' => 'fas fa-tasks',
                    'cor' => 'warning',
                    'link' => $cartaoTarefas['link'],
                ],
                [
                    'id' => 'rascunhos_internos',
                    'label' => 'Meus Rascunhos Internos',
                    'valor' => $cartaoRascunhos['valor'],
                    'alerta' => 'Em elaboração',
                    'icone' => 'fas fa-pencil-ruler',
                    'cor' => 'secondary',
                    'link' => $cartaoRascunhos['link'],
                ],
                [
                    'id' => 'documentos_submetidos',
                    'label' => 'Documentos Submetidos',
                    'valor' => $cartaoSubmetidos['valor'],
                    'alerta' => 'Aguardando revisão',
                    'icone' => 'fas fa-paper-plane',
                    'cor' => 'info',
                    'link' => $cartaoSubmetidos['link'],
                ],
                [
                    'id' => 'concluidos_mes',
                    'label' => 'Concluídos no Mês',
                    'valor' => $cartaoConcluidos['valor'],
                    'alerta' => 'Tarefas concluídas por mim',
                    'icone' => 'fas fa-check-circle',
                    'cor' => 'success',
                    'link' => $cartaoConcluidos['link'],
                ],
            ],
            'listas' => [
                'esquerda' => [
                    'titulo' => 'Minhas Demandas Imediatas',
                    'subtitulo' => 'Tarefas ativas atribuídas ao seu usuário com prioridade de prazo',
                    'icone' => 'fas fa-clipboard-check text-warning',
                    'tipo' => 'demandas_tecnico',
                    'itens' => $demandasImediatas,
                    'vazio_mensagem' => 'Nenhuma tarefa pendente no momento. Bom trabalho!',
                    'url_ver_todos' => $cartaoTarefas['link'],
                ],
                'direita' => [
                    'titulo' => 'Meus Documentos Internos Recentes',
                    'subtitulo' => 'Minutas, pareceres e informações em elaboração ou submetidas',
                    'icone' => 'fas fa-file-alt text-primary',
                    'tipo' => 'internos_tecnico',
                    'itens' => $meusInternos,
                    'vazio_mensagem' => 'Nenhum documento interno criado recentemente.',
                    // Os seus documentos, em qualquer estado (a lista ao lado é a dos recentes).
                    'url_ver_todos' => route('documentos-internos.index', ['tab' => 'todos', 'autor_id' => $user->id]),
                ],
            ],
        ];
    }

    /**
     * 2. Perfil: Chefe de Departamento (Foco em Gestão Setorial)
     */
    public function getChefeDepartamentoData(User $user): array
    {
        $dept = $user->departamentoPrincipal();
        $deptId = $dept?->id;

        // 1. Cartões: o número é o da lista para onde levam (ver cartaoEntradas).
        // Antes o painel tinha critério próprio — só departamento_id, que muda
        // apenas no recebimento, e um estado 'em_andamento' que não existe —,
        // pelo que os documentos encaminhados ao sector nunca eram contados.
        //
        // "Total do Acervo" somava entradas e internos, que vivem em listas
        // diferentes: nenhum clique mostrava esse número. Passa a ser o acervo
        // de entradas do sector, arquivadas incluídas (como já contava).
        $cartaoNovas = $this->cartaoEntradas($user, ['tab' => 'novos_departamento']);
        $cartaoDelegados = $this->cartaoEntradas($user, ['tab' => 'delegados']);
        $cartaoMinutas = $this->cartaoInternos($user, ['tab' => 'revisao', 'status' => 'em_analise']);
        $cartaoAcervo = $this->cartaoEntradas($user, ['tab' => 'todos_departamento', 'incluir_arquivados' => 1]);

        $minutasRevisao = $cartaoMinutas['valor'];

        $entradas = app(DocumentoEntradaService::class);
        $entradasVisiveis = function () use ($entradas, $user) {
            $query = DocumentoEntrada::query();
            $entradas->applyVisibilityScope($query, $user);

            return $query;
        };

        // 2. Entradas Recentes no Departamento
        $entradasRecentes = $entradasVisiveis()->with(['usuario'])
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get()
            ->map(function (DocumentoEntrada $doc) {
                return [
                    'id' => $doc->id,
                    'numero' => "{$doc->numero_sequencial}/{$doc->ano_referencia}",
                    'assunto' => $doc->assunto,
                    'procedencia' => $doc->procedencia ?: ($doc->usuario?->name ?? 'Externa'),
                    'especie' => $doc->classificacao_especie ?? 'Ofício',
                    'data_entrada' => optional($doc->data_entrada)->format('d/m/Y') ?? $doc->created_at->format('d/m/Y'),
                    'status' => $doc->status,
                    'status_info' => $this->formatarStatusBadge('entrada', $doc->status),
                    'url_show' => route('documentos-entradas.show', $doc->id),
                ];
            });

        // 3. Minutas Submetidas para Revisão da Chefia
        $minutasEquipe = DocumentoInterno::with(['autor', 'especie'])
            ->when($deptId, fn ($q) => $q->where('departamento_id', $deptId))
            ->where('status', DocumentoStatus::EM_ANALISE->value)
            ->orderBy('created_at', 'asc')
            ->limit(6)
            ->get()
            ->map(function (DocumentoInterno $doc) {
                return [
                    'id' => $doc->id,
                    'titulo' => $doc->titulo,
                    'numero_referencia' => $doc->numero_referencia ?: '#' . $doc->id,
                    'autor' => $doc->autor?->name ?? 'Técnico',
                    'especie' => $doc->especie?->nome ?? 'Parecer',
                    'status' => 'em_analise',
                    'status_info' => $this->formatarStatusBadge('interno', 'em_analise'),
                    'data_submissao' => $doc->created_at ? $doc->created_at->format('d/m/Y H:i') : '—',
                    'url_show' => route('documentos-internos.show', $doc->id),
                ];
            });

        return [
            'perfil' => 'CHEFE_DEPARTAMENTO',
            'perfil_label' => 'Chefia de Departamento',
            'banner' => [
                'saudacao' => "Olá, {$user->name}",
                'titulo' => 'Painel de Gestão Setorial',
                'subtitulo' => $dept ? ($dept->sigla ? "{$dept->sigla} — {$dept->nome}" : $dept->nome) : 'Departamento Setorial',
                'icone' => 'fas fa-sitemap',
                'badge' => 'Chefia de Departamento',
            ],
            'kpis' => [
                [
                    'id' => 'novas_entradas',
                    'label' => 'Novas Entradas no Setor',
                    'valor' => $cartaoNovas['valor'],
                    'alerta' => 'Aguardando tramitação',
                    'icone' => 'fas fa-inbox',
                    'cor' => 'primary',
                    'link' => $cartaoNovas['link'],
                ],
                [
                    'id' => 'tarefas_delegadas',
                    'label' => 'Delegados / Em Andamento',
                    'valor' => $cartaoDelegados['valor'],
                    'alerta' => 'Em execução pela equipe',
                    'icone' => 'fas fa-user-clock',
                    'cor' => 'warning',
                    'link' => $cartaoDelegados['link'],
                ],
                [
                    'id' => 'minutas_revisao',
                    'label' => 'Minutas p/ Minha Revisão',
                    'valor' => $minutasRevisao,
                    'alerta' => $minutasRevisao > 0 ? 'Ação requerida' : 'Em dia',
                    'icone' => 'fas fa-clipboard-check',
                    'cor' => $minutasRevisao > 0 ? 'danger' : 'success',
                    'link' => $cartaoMinutas['link'],
                ],
                [
                    'id' => 'total_acervo',
                    'label' => 'Acervo de Entradas do Setor',
                    'valor' => $cartaoAcervo['valor'],
                    'alerta' => 'Inclui arquivados',
                    'icone' => 'fas fa-archive',
                    'cor' => 'info',
                    'link' => $cartaoAcervo['link'],
                ],
            ],
            'listas' => [
                'esquerda' => [
                    'titulo' => 'Entradas Recentes no Departamento',
                    'subtitulo' => 'Documentos externos recebidos e tramitados para o setor',
                    'icone' => 'fas fa-file-import text-primary',
                    'tipo' => 'entradas_chefe',
                    'itens' => $entradasRecentes,
                    'vazio_mensagem' => 'Nenhuma entrada recente registrada para este departamento.',
                    'url_ver_todos' => route('documentos-entradas.index', ['tab' => 'todos_departamento']),
                ],
                'direita' => [
                    'titulo' => 'Minutas Submetidas p/ Revisão',
                    'subtitulo' => 'Pareceres e relatórios elaborados pela equipe que aguardam seu visto',
                    'icone' => 'fas fa-signature text-danger',
                    'tipo' => 'minutas_chefe',
                    'itens' => $minutasEquipe,
                    'vazio_mensagem' => 'Nenhuma minuta pendente de revisão no momento.',
                    'url_ver_todos' => $cartaoMinutas['link'],
                ],
            ],
        ];
    }

    /**
     * 3. Perfil: Administrador / Chefe de Gabinete (Foco Estratégico e Governança)
     */
    public function getChefeGabineteAdminData(User $user): array
    {
        $gabinete = $user->gabineteGerenciado ?? $user->gabineteSuperGerenciado ?? $user->gabinete();
        $anoAtual = (int) date('Y');

        // Este painel é partilhado pelo administrador e pelo chefe de gabinete,
        // mas as consultas corriam sempre sem filtro: um chefe via os contadores
        // de toda a instituição e a lista de despachos pendentes trazia-lhe
        // documentos de outros gabinetes — número, assunto e procedência — com
        // botão DESPACHAR que o backend depois recusava. Divulgava o que o
        // utilizador não pode ver e oferecia ações que não pode praticar.
        //
        // O administrador mantém a visão institucional; qualquer outro perfil
        // fica limitado ao gabinete que chefia. Sem gabinete resolvido, não há
        // âmbito legítimo: o filtro fecha em vez de abrir.
        $gabineteId = $user->isAdmin() ? null : ($gabinete?->id ?? -1);

        // DocumentoEntrada e DocumentoInterno ligam-se ao gabinete pelo
        // departamento; null deixa a consulta intacta (administrador).
        $limitarAoGabinete = function ($query) use ($gabineteId) {
            if ($gabineteId !== null) {
                // Internos: também os emitidos pelo próprio gabinete (sem departamento).
                $query->getModel() instanceof DocumentoInterno
                    ? $query->doGabinete($gabineteId)
                    : $query->whereHas('departamento', fn ($q) => $q->where('gabinete_id', $gabineteId));
            }

            return $query;
        };

        // 1. Cartões de governança: o número é o da lista para onde levam (ver
        // cartaoEntradas), com a visibilidade da própria listagem — que para o
        // chefe de gabinete já é a do seu gabinete.
        //
        // Os links usavam ?status=, que a listagem de entradas ignorava: os três
        // cartões de entradas abriam a mesma lista ("A Carecer de Tratamento").
        //
        // carecer_despacho: registados e pendentes de tratamento, como o
        // separador. TRATADO significa que o departamento o tratou (o despacho
        // já entrega o documento). O total do ano conta os arquivados, por isso
        // a lista também os inclui.
        $cartaoCarecer = $this->cartaoEntradas($user, ['tab' => 'carecer_tratamento']);
        $cartaoTratados = $this->cartaoEntradas($user, ['tab' => 'tratados']);
        $cartaoEmAnalise = $this->cartaoInternos($user, ['tab' => 'homologacao', 'status' => 'em_analise']);
        $cartaoTotalAno = $this->cartaoEntradas($user, ['tab' => 'todos', 'ano' => $anoAtual, 'incluir_arquivados' => 1]);

        $carecerDespacho = $cartaoCarecer['valor'];

        // 2. Entradas Prioritárias / Despachos Pendentes
        $entradasPrioritarias = $limitarAoGabinete(DocumentoEntrada::with(['departamento']))
            ->whereIn('status', ['pendente_tratamento', 'registrado'])
            ->where('arquivado', false)
            ->orderBy('created_at', 'asc')
            ->limit(6)
            ->get()
            ->map(function (DocumentoEntrada $doc) {
                return [
                    'id' => $doc->id,
                    'numero' => "{$doc->numero_sequencial}/{$doc->ano_referencia}",
                    'assunto' => $doc->assunto,
                    'procedencia' => $doc->procedencia ?? 'Entidade Externa',
                    'departamento' => $doc->departamento?->nome ?? 'Gabinete',
                    'data_entrada' => optional($doc->data_entrada)->format('d/m/Y') ?? $doc->created_at->format('d/m/Y'),
                    'status' => $doc->status,
                    'status_info' => $this->formatarStatusBadge('entrada', $doc->status),
                    'url_show' => route('documentos-entradas.show', $doc->id),
                ];
            });

        // 3. Atos Administrativos & Ordens Emitidas Recentes
        $atosEmitidos = $limitarAoGabinete(DocumentoInterno::with(['autor', 'departamento', 'especie']))
            ->whereIn('status', [DocumentoStatus::ASSINADO->value, DocumentoStatus::APROVADO->value])
            ->orderByRaw('COALESCE(assinado_em, updated_at) DESC')
            ->limit(6)
            ->get()
            ->map(function (DocumentoInterno $doc) {
                $statusVal = is_string($doc->status) ? $doc->status : ($doc->status?->value ?? 'assinado');

                return [
                    'id' => $doc->id,
                    'titulo' => $doc->titulo,
                    'numero_referencia' => $doc->numero_referencia ?: '#' . $doc->id,
                    'especie' => $doc->especie?->nome ?? 'Ato Administrativo',
                    'departamento' => $doc->departamento?->sigla ?? ($doc->departamento?->nome ?? 'Gabinete'),
                    'autor' => $doc->autor?->name ?? 'Gabinete',
                    'status' => $statusVal,
                    'status_info' => $this->formatarStatusBadge('interno', $statusVal),
                    'data_emissao' => $doc->assinado_em ? $doc->assinado_em->format('d/m/Y H:i') : $doc->updated_at->format('d/m/Y'),
                    'url_show' => route('documentos-internos.show', $doc->id),
                    'url_pdf' => route('documentos-internos.pdf', $doc->id),
                ];
            });

        return [
            'perfil' => 'CHEFE_GABINETE_ADMIN',
            'perfil_label' => $user->isAdmin() ? 'Administração Geral' : 'Chefe de Gabinete',
            'banner' => [
                'saudacao' => $user->isAdmin() ? "Painel de Governança Geral, {$user->name}" : "Excelência, {$user->name}",
                'titulo' => 'Painel Executivo & Governança Provincial',
                'subtitulo' => $gabinete ? $gabinete->nome : 'Governo Provincial',
                'icone' => 'fas fa-landmark',
                'badge' => 'Visão Estratégica & Decisão',
            ],
            'kpis' => [
                [
                    'id' => 'carecer_despacho',
                    'label' => 'A Carecer de Despacho',
                    'valor' => $carecerDespacho,
                    'alerta' => $carecerDespacho > 0 ? 'Decisão prioritária' : 'Zerado',
                    'icone' => 'fas fa-stamp',
                    'cor' => 'danger',
                    'link' => $cartaoCarecer['link'],
                ],
                [
                    'id' => 'tratados_departamentos',
                    'label' => 'Tratados pelos Departamentos',
                    'valor' => $cartaoTratados['valor'],
                    'alerta' => 'Concluídos na origem',
                    'icone' => 'fas fa-clipboard-check',
                    'cor' => 'success',
                    'link' => $cartaoTratados['link'],
                ],
                [
                    'id' => 'documentos_em_analise',
                    'label' => 'Documentos em Análise Setorial',
                    'valor' => $cartaoEmAnalise['valor'],
                    'alerta' => 'Em tramitação nos departamentos',
                    'icone' => 'fas fa-hourglass-half',
                    'cor' => 'info',
                    'link' => $cartaoEmAnalise['link'],
                ],
                [
                    'id' => 'total_registrado_ano',
                    'label' => "Total Geral Registrado ({$anoAtual})",
                    'valor' => $cartaoTotalAno['valor'],
                    'alerta' => 'Entradas oficiais no ano',
                    'icone' => 'fas fa-chart-line',
                    'cor' => 'success',
                    'link' => $cartaoTotalAno['link'],
                ],
            ],
            'listas' => [
                'esquerda' => [
                    'titulo' => 'Entradas Prioritárias / Despachos Pendentes',
                    'subtitulo' => 'Processos que aguardam despacho executivo do Gabinete',
                    'icone' => 'fas fa-exclamation-circle text-danger',
                    'tipo' => 'despachos_executivos',
                    'itens' => $entradasPrioritarias,
                    'vazio_mensagem' => 'Não há despachos prioritários pendentes.',
                    'url_ver_todos' => $cartaoCarecer['link'],
                ],
                'direita' => [
                    'titulo' => 'Atos Administrativos & Ordens Emitidas',
                    'subtitulo' => 'Documentos internos homologados e assinados recentemente',
                    'icone' => 'fas fa-file-contract text-success',
                    'tipo' => 'atos_emitidos',
                    'itens' => $atosEmitidos,
                    'vazio_mensagem' => 'Nenhum ato administrativo emitido recentemente.',
                    // Aprovados e assinados, como a lista ao lado (antes ?status=assinado
                    // sobre o separador "Para Homologação" dava sempre uma lista vazia).
                    'url_ver_todos' => route('documentos-internos.index', ['tab' => 'assinados']),
                ],
            ],
        ];
    }

    /**
     * Formata rótulos e classes de badges de status em português corporativo elegante
     */
    public function formatarStatusBadge(string $modulo, ?string $status): array
    {
        $statusKey = strtolower((string) $status);

        $map = [
            // Rascunhos
            'rascunho' => [
                'label' => 'Rascunho',
                'badge' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                'icone' => 'fas fa-pen-nib',
            ],
            // Pendente / A Carecer / Em Análise
            'pendente_tratamento' => [
                'label' => 'A Carecer de Tratamento',
                'badge' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                'icone' => 'fas fa-clock',
            ],
            'pendente' => [
                'label' => 'Pendente',
                'badge' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                'icone' => 'fas fa-hourglass-half',
            ],
            'em_analise' => [
                'label' => 'Em Análise',
                'badge' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                'icone' => 'fas fa-search',
            ],
            'em_andamento' => [
                'label' => 'Em Andamento',
                'badge' => 'bg-primary-subtle text-primary-emphasis border border-primary-subtle',
                'icone' => 'fas fa-spinner fa-spin',
            ],
            'registrado' => [
                'label' => 'Registrado',
                'badge' => 'bg-primary-subtle text-primary border border-primary-subtle',
                'icone' => 'fas fa-file-alt',
            ],
            'tratado' => [
                'label' => 'Tratado',
                'badge' => 'bg-info-subtle text-info border border-info-subtle',
                'icone' => 'fas fa-check-double',
            ],
            'encaminhado' => [
                'label' => 'Encaminhado',
                'badge' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
                'icone' => 'fas fa-paper-plane',
            ],
            'recebido' => [
                'label' => 'Recebido',
                'badge' => 'bg-teal-subtle text-teal-emphasis border border-teal-subtle',
                'icone' => 'fas fa-inbox',
            ],
            // Concluídos / Aprovados
            'aprovado' => [
                'label' => 'Aprovado',
                'badge' => 'bg-success-subtle text-success-emphasis border border-success-subtle',
                'icone' => 'fas fa-check',
            ],
            'assinado' => [
                'label' => 'Assinado',
                'badge' => 'bg-success-subtle text-success-emphasis border border-success-subtle',
                'icone' => 'fas fa-signature',
            ],
            'concluida' => [
                'label' => 'Concluída',
                'badge' => 'bg-success-subtle text-success-emphasis border border-success-subtle',
                'icone' => 'fas fa-check-circle',
            ],
            'respondido' => [
                'label' => 'Respondido',
                'badge' => 'bg-success-subtle text-success border border-success-subtle',
                'icone' => 'fas fa-reply',
            ],
            'arquivado' => [
                'label' => 'Arquivado',
                'badge' => 'bg-dark-subtle text-dark border border-dark-subtle',
                'icone' => 'fas fa-archive',
            ],
            // Rejeitados / Cancelados
            'rejeitado' => [
                'label' => 'Rejeitado',
                'badge' => 'bg-danger-subtle text-danger border border-danger-subtle',
                'icone' => 'fas fa-times-circle',
            ],
            'cancelado' => [
                'label' => 'Cancelado',
                'badge' => 'bg-danger-subtle text-danger border border-danger-subtle',
                'icone' => 'fas fa-ban',
            ],
            'cancelada' => [
                'label' => 'Cancelada',
                'badge' => 'bg-danger-subtle text-danger border border-danger-subtle',
                'icone' => 'fas fa-ban',
            ],
        ];

        return $map[$statusKey] ?? [
            'label' => ucfirst(str_replace('_', ' ', $statusKey ?: 'Indefinido')),
            'badge' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            'icone' => 'fas fa-circle-notch',
        ];
    }
}
