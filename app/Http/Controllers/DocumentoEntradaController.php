<?php

namespace App\Http\Controllers;

use App\Application\DocumentManagement\Commands\CriarDocumentoEntradaCommand;
use App\Application\DocumentManagement\DTOs\CriarDocumentoEntradaDTO;
use App\Application\DocumentManagement\Handlers\CriarDocumentoEntradaHandler;
use App\Enums\DocumentoStatus;
use App\Http\Requests\StoreDocumentoEntradaRequest;
use App\Jobs\ProcessarOcrAnexo;
use App\Models\Anexo;
use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\ModeloDespacho;
use App\Models\Pasta;
use App\Models\Procedencia;
use App\Models\User;
use App\Services\Ai\DocumentoAssistantService;
use App\Services\DocumentoEntradaService;
use App\Services\DocumentoPermissionService;
use App\Support\SafeFileHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentoEntradaController extends Controller
{
    /**
     * Nº de registos de auditoria mostrados de uma vez. A listagem vem do mais
     * recente para o mais antigo, pelo que truncar sem indicar o total esconderia
     * o início da história do documento.
     */
    private const LIMITE_AUDITORIA = 50;

    protected $documentoService;

    protected $permissionService;

    protected CriarDocumentoEntradaHandler $criarHandler;

    public function __construct(
        DocumentoEntradaService $documentoService,
        DocumentoPermissionService $permissionService,
        CriarDocumentoEntradaHandler $criarHandler
    ) {
        $this->documentoService = $documentoService;
        $this->permissionService = $permissionService;
        $this->criarHandler = $criarHandler;
    }

    public function searchJson(Request $request)
    {
        $search = $request->input('q');
        if (empty($search) || strlen($search) < 2) {
            return response()->json([]);
        }

        $actor = Auth::user();

        // A pesquisa respeita o mesmo scoping de visibilidade das listagens
        $query = DocumentoEntrada::query();
        $this->documentoService->applyVisibilityScope($query, $actor);

        $query->where(function ($q) use ($search) {
            $q->where('assunto', 'like', "%{$search}%")
                ->orWhere('classificacao_ref_numero', 'like', "%{$search}%")
                ->orWhere('procedencia', 'like', "%{$search}%")
                ->orWhere('observacoes', 'like', "%{$search}%");

            // Search in attachments content (OCR) and filenames
            $q->orWhereHas('anexos', function ($subQ) use ($search) {
                $subQ->where(fn ($t) => $t->pesquisarTextoExtraido($search))
                    ->orWhere('nome_original', 'like', "%{$search}%");
            });

            // Support searching by numeric ID or sequence
            if (is_numeric($search)) {
                $q->orWhere('id', $search)
                    ->orWhere('numero_sequencial', $search);
            }
        });

        $results = $query->latest('id')
            ->take(10)
            ->get()
            ->map(function ($doc) {
                $ref = $doc->classificacao_ref_numero ? " | Ref: {$doc->classificacao_ref_numero}" : '';

                return [
                    'id' => $doc->id,
                    'type' => 'entrada',
                    'text' => sprintf(
                        '#%d/%d - %s%s',
                        $doc->numero_sequencial,
                        $doc->ano_referencia,
                        Str::limit($doc->assunto, 60),
                        $ref
                    ),
                    'assunto' => $doc->assunto,
                ];
            });

        // Search also in Documentos Internos (scoped à visibilidade do utilizador)
        $queryInternos = DocumentoInterno::query()->accessibleBy($actor);
        $queryInternos->where(function ($q) use ($search) {
            $q->where('titulo', 'like', "%{$search}%")
                ->orWhere('numero_referencia', 'like', "%{$search}%")
                ->orWhere('conteudo_final', 'like', "%{$search}%")
                ->orWhere('destinatario_nome', 'like', "%{$search}%")
                ->orWhere('destinatario_orgao', 'like', "%{$search}%");
        });

        $internos = $queryInternos->latest('id')
            ->take(10)
            ->get()
            ->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'type' => 'interno',
                    'text' => sprintf(
                        '[INTERNO] %s - %s',
                        $doc->numero_referencia ?? 'S/Ref',
                        Str::limit($doc->titulo, 60)
                    ),
                    'assunto' => $doc->titulo,
                ];
            });

        // Use values() to ensure we have a base collection and reset keys for JSON array
        return response()->json($results->toBase()->merge($internos)->values());
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', DocumentoEntrada::class);

        $actor = Auth::user();
        $documentos = $this->documentoService->getFilteredDocuments($request, $actor);

        $departamentos = Cache::remember('departamentos_list_select', 300, function () {
            return Departamento::select(['id', 'nome'])->orderBy('nome')->get();
        });

        // Obter Perfil e Abas de Esteira de Trabalho do Utilizador
        $userProfile = $actor ? $this->permissionService->getUserWorkflowProfile($actor) : 'gabinete';
        $workflowTabs = $this->documentoService->getRoleWorkflowTabs($actor, $request);
        $activeTab = $request->input('tab') ?: $this->documentoService->getDefaultTabForProfile($userProfile);

        if ($actor) {
            $userDeps = $this->permissionService->getUserDepartments($actor);
            $isChefe = $this->permissionService->isChefeDepartamento($actor);
            $isAdmin = $this->permissionService->isAdmin($actor);

            // Departamentos elegíveis para despacho, agrupados por gabinete: evita
            // uma query por linha no modal de despacho da listagem.
            $depsPorGabinete = Cache::remember('departamentos_por_gabinete', 300, function () {
                return Departamento::select(['id', 'nome', 'sigla', 'gabinete_id'])
                    ->orderBy('nome')->get()->groupBy('gabinete_id');
            });

            $docIds = $documentos->pluck('id')->filter()->all();
            $minhasTarefas = count($docIds) ? DocumentoTarefa::with(['assignedBy:id,name'])
                ->whereIn('documento_entrada_id', $docIds)
                ->where('assigned_to_user_id', $actor->id)
                ->whereIn('status', ['pendente', 'em_andamento'])
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('documento_entrada_id') : collect();

            foreach ($documentos as $doc) {
                $minhaTarefa = isset($minhasTarefas[$doc->id]) ? $minhasTarefas[$doc->id]->first() : null;
                $doc->setAttribute('minha_tarefa', $minhaTarefa);

                $enc = $doc->ultimoEncaminhamento;
                $hasPending = $enc && ! $enc->recebido_em;

                $canReceive = $hasPending && ($isAdmin || in_array((int) $enc->destino_departamento_id, $userDeps));
                $doc->setAttribute('can_receive', $canReceive);

                $canForward = ($isAdmin || in_array((int) $doc->departamento_id, $userDeps)) && ! $hasPending;
                $doc->setAttribute('can_forward', $canForward);

                $doc->setAttribute('is_chefe', $isChefe && in_array((int) $doc->departamento_id, $userDeps));
                $canDespachar = $this->permissionService->canDespachar($actor, $doc);
                $doc->setAttribute('can_despachar', $canDespachar);
                $doc->setAttribute(
                    'departamentos_despacho',
                    $canDespachar
                        ? ($depsPorGabinete->get(optional($doc->departamento)->gabinete_id) ?? collect())
                        : collect()
                );
                $doc->setAttribute('can_encaminhar_tratado', $this->permissionService->canEncaminharTratado($actor, $doc));
            }
        }

        return view('documentos_entradas.index', compact(
            'documentos',
            'departamentos',
            'activeTab',
            'userProfile',
            'workflowTabs'
        ));
    }

    public function create()
    {
        $this->authorize('create', DocumentoEntrada::class);

        $departamentos = Cache::remember('departamentos_list', 600, function () {
            return Departamento::select('id', 'nome')->orderBy('nome')->get();
        });

        $especies = Cache::remember('documento_especies_names', 600, function () {
            return DocumentoEspecie::where('ativo', true)->orderBy('ordem')->pluck('nome')->all();
        });

        $procedencias = Cache::remember('procedencias_list', 300, function () {
            return Procedencia::where('ativo', true)->orderBy('nome')->get(['id', 'nome']);
        });

        $userDepartamentoId = Auth::user()->departamento_id;

        return view('documentos_entradas.create', compact('departamentos', 'especies', 'procedencias', 'userDepartamentoId'));
    }

    public function store(StoreDocumentoEntradaRequest $request)
    {
        $validated = $request->validated();

        // Com dois operadores ao balcão, o mesmo ofício acaba registado duas
        // vezes. Avisa-se, não se bloqueia: uma segunda via legítima tem de
        // continuar a poder ser registada, mediante confirmação explícita.
        if (! $request->boolean('confirmar_duplicado')) {
            $existente = $this->documentoService->procurarPossivelDuplicado($validated);

            if ($existente) {
                return back()
                    ->withInput()
                    ->withErrors(['duplicado' => sprintf(
                        'Já existe o documento #%03d/%d com a mesma procedência, referência e data (%s). Confirme que pretende registar mesmo assim.',
                        $existente->numero_sequencial,
                        $existente->ano_referencia,
                        Str::limit($existente->assunto, 60),
                    )])
                    ->with('duplicado_id', $existente->id);
            }
        }

        // ── Camada de Domínio (DDD) — Validação de Invariantes ──────────────────
        // Constrói o DTO, instancia a DocumentoEntradaEntity e valida as
        // invariantes de domínio (assunto obrigatório, protocolo não vazio).
        // Não persiste aqui: DocumentoEntrada requer numeração sequencial
        // transaccional gerida pelo Service — usar o repositório causaria
        // um INSERT sem numero_sequencial (NOT NULL).
        $this->criarHandler->validateOnly(
            new CriarDocumentoEntradaCommand(
                CriarDocumentoEntradaDTO::fromArray($validated)
            )
        );

        // ── Infra-estrutura — Persistência Completa ──────────────────────────────
        // O Service gere: numeração sequencial, upload de ficheiros, tags,
        // jobs de OCR e a notificação à chefia do departamento de destino.
        //
        // Não é criado nenhum DocumentoEncaminhamento no registo: um
        // encaminhamento por receber bloqueia o despacho, a saída de gabinete e
        // o botão de encaminhar da listagem — o documento ficaria congelado à
        // nascença. O destino é avisado por notificação.
        $this->documentoService->createDocument(
            $validated,
            $request->file('arquivo'),
            $request->file('anexos')
        );

        return redirect()->route('documentos-entradas.index')->with('success', 'Documento registrado com sucesso.');
    }

    public function show(DocumentoEntrada $documentos_entrada)
    {
        $actor = Auth::user();
        if (! $this->permissionService->canViewDocument($actor, $documentos_entrada)) {
            $deps = $this->permissionService->getUserDepartments($actor);
            $hasHistory = false;
            if (count($deps)) {
                $hasHistory = DocumentoEncaminhamento::where('documento_entrada_id', $documentos_entrada->id)
                    ->where(function ($q) use ($deps) {
                        $q->whereIn('origem_departamento_id', $deps)
                            ->orWhereIn('destino_departamento_id', $deps);
                    })->exists();
            }
            if (! $hasHistory) {
                abort(403, 'Acesso negado a este documento.');
            }
        }

        $doc = DocumentoEntrada::with([
            'departamento:id,nome,gabinete_id',
            'departamento.gabinete:id,nome,sigla,responsavel_id',
            'usuario:id,name',
            'vistoDepartamentoPor:id,name',
            'vistoGabinetePor:id,name',
            'despachadoPor:id,name',
            'departamentosDestino:id,nome',
            'pasta:id,nome',
            'arquivadoPor:id,name',
            'anexos',
            'encaminhamentos' => fn ($q) => $q->orderBy('encaminhado_em', 'asc'),
            'encaminhamentos.origemDepartamento:id,nome',
            'encaminhamentos.destinoDepartamento:id,nome',
            'encaminhamentos.usuario:id,name',
            'encaminhamentos.recebidoPor:id,name',
            'encaminhamentosExternos' => fn ($q) => $q->orderBy('enviado_em', 'asc'),
            'encaminhamentosExternos.origemGabinete:id,nome,sigla',
            'encaminhamentosExternos.destinoGabinete:id,nome,sigla',
            'encaminhamentosExternos.usuario:id,name',
            'tarefas' => fn ($q) => $q->orderBy('created_at', 'desc'),
            'tarefas.assignedBy:id,name',
            'tarefas.assignedToUser:id,name',
            'tarefas.assignedToDepartamento:id,nome',
            'tarefas.assignedToDepartamento.usuarios:id,name,departamento_id',
            'tarefas.responsavelAtual:id,name',
            'vinculosOrigem.vinculadoPor:id,name',
        ])->findOrFail($documentos_entrada->id);

        $departamentos = Cache::remember('departamentos_list', 600, fn () => Departamento::select(['id', 'nome'])->orderBy('nome')->get());
        $gabinetes = Cache::remember('gabinetes_list', 600, fn () => Gabinete::select(['id', 'nome', 'sigla'])->orderBy('nome')->get());

        $actor = Auth::user();
        $gabUsuarios = collect();
        $gabDepartamentos = collect();
        $depUsuarios = collect();

        $gab = optional($doc->departamento)->gabinete;
        if ($gab) {
            if ($this->permissionService->isGabineteResponsavel($actor, $gab->id)) {
                $gabUsuarios = User::whereHas('departamento', function ($q) use ($gab) {
                    $q->where('gabinete_id', $gab->id);
                })->orderBy('name')->get(['id', 'name']);

                $gabDepartamentos = Departamento::where('gabinete_id', $gab->id)->orderBy('nome')->get(['id', 'nome']);
            } elseif ($actor->isSuperChefeDoGabinete($gab)) {
                $destIds = collect();
                if ($gab->responsavel_id) {
                    $destIds->push($gab->responsavel_id);
                }
                $depChiefs = Departamento::where('gabinete_id', $gab->id)->whereNotNull('responsavel_id')->pluck('responsavel_id');
                $destIds = $destIds->merge($depChiefs)->unique()->filter()->values();

                if ($destIds->isNotEmpty()) {
                    $gabUsuarios = User::whereIn('id', $destIds)->orderBy('name')->get(['id', 'name']);
                } else {
                    $gabUsuarios = collect();
                }
            }
        }

        if ($doc->departamento_id) {
            $depId = (int) $doc->departamento_id;
            $depUsuarios = User::where('departamento_id', $depId)
                ->orWhereHas('departamentos', fn ($q) => $q->where('departamentos.id', $depId))
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $hasPendente = $doc->encaminhamentos()->whereNull('recebido_em')->exists();
        $deps = $this->permissionService->getUserDepartments($actor);

        $pastas = Pasta::with('departamento.gabinete')
            ->where('departamento_id', $actor->departamento_id)
            ->orWhere('created_by', $actor->id)
            ->orderBy('nome')
            ->get();

        $modelosDespacho = ModeloDespacho::ativos()
            ->globalOrUser($actor->id)
            ->orderBy('titulo')
            ->get(['id', 'titulo', 'texto']);

        $relacionados = $doc->todos_relacionados;

        $canAssignTask = $this->permissionService->canManageTasks($actor, $doc);

        // A view não decide autorizações: recebe-as já calculadas, com a mesma
        // regra que os endpoints aplicam.
        foreach ($doc->tarefas as $tarefa) {
            $pendente = $tarefa->status === 'pendente';
            $tarefa->setAttribute('can_concluir', $pendente && $this->permissionService->canConcluirTarefa($actor, $doc, $tarefa));
            $tarefa->setAttribute('can_cancelar', $pendente && $this->permissionService->canCancelarTarefa($actor, $doc, $tarefa));
        }

        $canVisto = false;
        $canVistoGabinete = false;

        // Visto Departamento
        if ($this->permissionService->isChefeDepartamento($actor)) {
            $userDeps = $this->permissionService->getUserDepartments($actor);
            if (in_array((int) $doc->departamento_id, $userDeps)) {
                $canVisto = true;
            }
        }

        // Visto Gabinete
        $docGabId = optional($doc->departamento)->gabinete_id;
        if ($docGabId && $this->permissionService->isGabineteResponsavel($actor, $docGabId)) {
            $canVistoGabinete = true;
        }

        $canDespachar = $this->permissionService->canDespachar($actor, $doc);
        $canEncaminharTratado = $actor->can('encaminhar', $doc) && $doc->status === 'tratado';
        // A auditoria expõe IPs e o antes/depois de cada alteração: fica na
        // chefia, não em quem apenas consegue ver o documento.
        $canVerAuditoria = $actor->can('verAuditoria', $doc);

        $auditsTotal = $canVerAuditoria ? $doc->audits()->count() : 0;
        $audits = $canVerAuditoria
            ? $doc->audits()->with('user')->take(self::LIMITE_AUDITORIA)->get()
            : collect();

        $timelineEvents = $this->buildTimelineEvents($doc);

        return view('documentos_entradas.show', compact(
            'doc', 'departamentos', 'gabinetes', 'gabUsuarios', 'gabDepartamentos', 'depUsuarios',
            'hasPendente', 'deps', 'pastas', 'modelosDespacho', 'relacionados', 'canAssignTask',
            'canVisto', 'canVistoGabinete', 'canDespachar', 'canEncaminharTratado', 'audits',
            'auditsTotal', 'canVerAuditoria', 'timelineEvents'
        ));
    }

    /**
     * Constrói a coleção unificada de eventos cronológicos do ciclo de vida do documento.
     */
    protected function buildTimelineEvents(DocumentoEntrada $doc): Collection
    {
        $events = collect();

        // 1. Registo Inicial
        $dataRegisto = $doc->created_at ?? $doc->data_entrada;
        if ($dataRegisto) {
            $events->push([
                'tipo' => 'registo',
                'data' => $dataRegisto,
                'titulo' => 'Registo e Entrada no Sistema',
                'descricao' => "Documento registado com a espécie '{$doc->classificacao_especie}' e Ref. nº ".($doc->classificacao_ref_numero ?: 'S/N').($doc->procedencia ? " com procedência de {$doc->procedencia}." : '.'),
                'autor' => optional($doc->usuario)->name ?? 'Sistema',
                'setor' => optional($doc->departamento)->nome ?? 'Gabinete',
                'icone' => 'fas fa-file-import',
                'badge_class' => 'bg-primary',
                'badge_text' => 'Registo',
            ]);
        }

        // 2. Visto do Chefe de Departamento
        if ($doc->visto_departamento_data) {
            $isAprovado = $doc->visto_departamento_status === 'aprovado';
            $events->push([
                'tipo' => 'visto_departamento',
                'data' => $doc->visto_departamento_data,
                'titulo' => 'Visto da Chefia Departamental: '.($isAprovado ? 'Aprovado' : 'Rejeitado'),
                'descricao' => $doc->visto_departamento_observacao ?: ($isAprovado ? 'Documento validado pelo chefe do departamento.' : 'Documento rejeitado na verificação departamental.'),
                'autor' => optional($doc->vistoDepartamentoPor)->name ?? 'Chefe do Departamento',
                'setor' => optional($doc->departamento)->nome,
                'icone' => $isAprovado ? 'fas fa-user-check' : 'fas fa-user-times',
                'badge_class' => $isAprovado ? 'bg-success' : 'bg-danger',
                'badge_text' => $isAprovado ? 'Visto Aprovado' : 'Visto Rejeitado',
            ]);
        }

        // 3. Visto do Gabinete
        if ($doc->visto_gabinete_data) {
            $isAprovado = $doc->visto_gabinete_status === 'aprovado';
            $events->push([
                'tipo' => 'visto_gabinete',
                'data' => $doc->visto_gabinete_data,
                'titulo' => 'Visto do Gabinete: '.($isAprovado ? 'Aprovado' : 'Rejeitado'),
                'descricao' => $doc->visto_gabinete_observacao ?: ($isAprovado ? 'Aprovação institucional concedida pelo Gabinete.' : 'Documento rejeitado pelo Gabinete.'),
                'autor' => optional($doc->vistoGabinetePor)->name ?? 'Responsável do Gabinete',
                'setor' => optional(optional($doc->departamento)->gabinete)->nome ?? 'Gabinete',
                'icone' => $isAprovado ? 'fas fa-stamp' : 'fas fa-times-circle',
                'badge_class' => $isAprovado ? 'bg-success' : 'bg-danger',
                'badge_text' => $isAprovado ? 'Gabinete Aprovou' : 'Gabinete Rejeitou',
            ]);
        }

        // 4. Despacho / Parecer do Gabinete
        if ($doc->texto_despacho || $doc->data_despacho) {
            $destinos = $doc->departamentosDestino->pluck('nome')->implode(', ');
            $events->push([
                'tipo' => 'despacho',
                'data' => $doc->data_despacho ?? $doc->updated_at,
                'titulo' => 'Despacho Emitido pelo Gabinete',
                'descricao' => ($destinos ? "Destinatários: {$destinos}\n\n" : '').$doc->texto_despacho,
                'autor' => optional($doc->despachadoPor)->name ?? 'Chefe de Gabinete',
                'setor' => 'Gabinete',
                'icone' => 'fas fa-file-signature',
                'badge_class' => 'bg-warning text-dark',
                'badge_text' => 'Despacho',
            ]);
        }

        // 5. Encaminhamentos Internos
        foreach ($doc->encaminhamentos as $enc) {
            $origem = optional($enc->origemDepartamento)->nome ?? '—';
            $destino = optional($enc->destinoDepartamento)->nome ?? '—';

            // Evento de Envio
            $events->push([
                'tipo' => 'encaminhamento_envio',
                'data' => $enc->encaminhado_em,
                'titulo' => "Encaminhamento Interno: {$origem} ➔ {$destino}",
                'descricao' => $enc->observacao ?: 'Documento tramitado internamente entre setores.',
                'autor' => optional($enc->usuario)->name ?? 'Utilizador',
                'setor' => $origem,
                'icone' => 'fas fa-share',
                'badge_class' => 'bg-info',
                'badge_text' => 'Tramitado',
            ]);

            // Evento de Recebimento (se recebido)
            if ($enc->recebido_em) {
                $events->push([
                    'tipo' => 'encaminhamento_recebido',
                    'data' => $enc->recebido_em,
                    'titulo' => "Recebimento Confirmado: {$destino}",
                    'descricao' => 'Recebido no departamento de destino por '.(optional($enc->recebidoPor)->name ?? 'Utilizador').'.',
                    'autor' => optional($enc->recebidoPor)->name ?? 'Utilizador',
                    'setor' => $destino,
                    'icone' => 'fas fa-inbox',
                    'badge_class' => 'bg-success',
                    'badge_text' => 'Recebido no Setor',
                ]);
            }
        }

        // 6. Encaminhamentos Externos (Saída de Gabinete)
        foreach ($doc->encaminhamentosExternos as $ext) {
            $origemGab = optional($ext->origemGabinete)->nome ?? 'Gabinete';
            $destinoGab = optional($ext->destinoGabinete)->nome ?? 'Destino Externo';
            $events->push([
                'tipo' => 'encaminhamento_externo',
                'data' => $ext->enviado_em,
                'titulo' => "Saída Externa: {$origemGab} ➔ {$destinoGab}",
                'descricao' => 'Encaminhamento institucional externo'.($ext->oficio_numero ? " através do Ofício nº {$ext->oficio_numero}." : '.').($ext->observacao ? " Obs: {$ext->observacao}" : ''),
                'autor' => optional($ext->usuario)->name ?? 'Gabinete',
                'setor' => $origemGab,
                'icone' => 'fas fa-globe',
                'badge_class' => 'bg-primary',
                'badge_text' => 'Saída Externa',
            ]);
        }

        // 7. Tarefas Atribuídas e Concluídas
        foreach ($doc->tarefas as $t) {
            $destinoNome = $t->assignedToUser ? optional($t->assignedToUser)->name : (optional($t->assignedToDepartamento)->nome ?? 'Setor');
            $events->push([
                'tipo' => 'tarefa_criada',
                'data' => $t->created_at,
                'titulo' => "Tarefa Atribuída: {$t->titulo}",
                'descricao' => ($t->descricao ? "{$t->descricao}\n" : '')."Atribuído a: {$destinoNome}".($t->prazo_at ? ' • Prazo: '.$t->prazo_at->format('d/m/Y') : ''),
                'autor' => optional($t->assignedBy)->name ?? 'Chefia',
                'setor' => optional($t->assignedToDepartamento)->nome,
                'icone' => 'fas fa-tasks',
                'badge_class' => 'bg-secondary',
                'badge_text' => 'Tarefa Criada',
            ]);

            if ($t->status === 'concluida' || $t->status === 'concluido') {
                $executor = optional($t->responsavelAtual)->name ?? (optional($t->assignedToUser)->name ?? 'Técnico');
                $events->push([
                    'tipo' => 'tarefa_concluida',
                    // concluida_em e a data do facto; updated_at desloca-se com
                    // qualquer alteracao posterior a tarefa.
                    'data' => $t->concluida_em ?? $t->updated_at,
                    'titulo' => "Tarefa Concluída: {$t->titulo}",
                    'descricao' => "Demanda executada e finalizada por {$executor}.",
                    'autor' => $executor,
                    'setor' => optional($t->assignedToDepartamento)->nome,
                    'icone' => 'fas fa-check-double',
                    'badge_class' => 'bg-success',
                    'badge_text' => 'Tarefa Concluída',
                ]);
            }
        }

        // 8. Vínculos e Dossiê (ex: respostas elaboradas)
        if ($doc->relationLoaded('vinculosOrigem')) {
            foreach ($doc->vinculosOrigem as $v) {
                $events->push([
                    'tipo' => 'vinculo',
                    'data' => $v->created_at,
                    'titulo' => 'Dossiê: Vínculo Bilateral ('.($v->tipo_relacao ?: 'RELACIONADO').')',
                    'descricao' => 'Vinculado ao documento '.($v->destino_tipo === 'INTERNO' ? 'Interno' : 'Externo')." #{$v->destino_id} com relação de {$v->tipo_relacao}.",
                    'autor' => optional($v->vinculadoPor)->name ?? 'Utilizador',
                    'setor' => null,
                    'icone' => 'fas fa-link',
                    'badge_class' => 'bg-dark',
                    'badge_text' => 'Vínculo / Resposta',
                ]);
            }
        }

        // 9. Arquivamento
        if ($doc->arquivado && $doc->arquivado_em) {
            $events->push([
                'tipo' => 'arquivamento',
                'data' => $doc->arquivado_em,
                'titulo' => 'Processo Arquivado',
                'descricao' => 'Documento devidamente finalizado e arquivado no acervo digital'.($doc->pasta ? " na pasta: {$doc->pasta->nome}." : '.'),
                'autor' => optional($doc->arquivadoPor)->name ?? 'Arquivo',
                'setor' => optional($doc->pasta)->nome,
                'icone' => 'fas fa-archive',
                'badge_class' => 'bg-secondary',
                'badge_text' => 'Arquivado',
            ]);
        }

        return $events->sortByDesc('data')->values();
    }

    public function previewAjax(DocumentoEntrada $documento)
    {
        $actor = Auth::user();
        if (! $this->permissionService->canViewDocument($actor, $documento)) {
            return response()->json(['error' => 'Sem permissão para visualizar este documento.'], 403);
        }

        $doc = DocumentoEntrada::with([
            'departamento:id,nome',
            'usuario:id,name',
            'anexos',
            'encaminhamentos' => fn ($q) => $q->orderBy('encaminhado_em', 'desc'),
            'encaminhamentos.origemDepartamento:id,nome',
            'encaminhamentos.destinoDepartamento:id,nome',
            'encaminhamentos.usuario:id,name',
            'encaminhamentos.recebidoPor:id,name',
            'departamentosDestino:id,nome',
        ])->findOrFail($documento->id);

        $userDeps = $this->permissionService->getUserDepartments($actor);
        $userProfile = $this->permissionService->getUserWorkflowProfile($actor);

        $enc = $doc->ultimoEncaminhamento;
        $canReceive = false;
        if ($enc && ! $enc->recebido_em) {
            $canReceive = $this->permissionService->isAdmin($actor) || in_array((int) $enc->destino_departamento_id, $userDeps);
        }

        $canForward = $this->permissionService->isAdmin($actor) ||
            ($this->permissionService->isChefeDepartamento($actor) && in_array((int) $doc->departamento_id, $userDeps)) ||
            (optional($doc->departamento)->gabinete_id && $this->permissionService->isGabineteResponsavel($actor, $doc->departamento->gabinete_id));

        $pastas = Pasta::with('departamento.gabinete')
            ->where('departamento_id', $actor->departamento_id)
            ->orWhere('created_by', $actor->id)
            ->orderBy('nome')
            ->get();

        // Só departamentos do gabinete do documento: a mesma regra que o servidor
        // aplica no despacho (ver DocumentoEntradaService).
        $departamentos = $this->documentoService->departamentosDestinoPermitidos($doc);

        // Técnicos/utilizadores do departamento para atribuição
        $depUsuarios = User::whereHas('departamentos', function ($q) use ($userDeps) {
            $q->whereIn('departamentos.id', $userDeps);
        })->orWhere('departamento_id', $actor->departamento_id)
            ->distinct()
            ->orderBy('name')
            ->get(['id', 'name']);

        $minhaTarefa = DocumentoTarefa::where('documento_entrada_id', $doc->id)
            ->where('assigned_to_user_id', $actor->id)
            ->whereIn('status', ['pendente', 'em_andamento'])
            ->first();

        $tarefas = DocumentoTarefa::with(['assignedToUser:id,name', 'assignedBy:id,name'])
            ->where('documento_entrada_id', $doc->id)
            ->orderByDesc('created_at')
            ->get();

        // O painel de acao rapida e onde o gabinete despacha com mais frequencia:
        // os modelos de texto tem de estar aqui, nao so no modal do detalhe.
        $modelosDespacho = ModeloDespacho::ativos()
            ->globalOrUser($actor->id)
            ->orderBy('titulo')
            ->get(['id', 'titulo', 'texto']);

        return view('documentos_entradas.partials.preview', compact(
            'doc',
            'modelosDespacho',
            'userProfile',
            'canReceive',
            'canForward',
            'pastas',
            'departamentos',
            'depUsuarios',
            'minhaTarefa',
            'tarefas'
        ));
    }

    public function quickAction(Request $request, DocumentoEntrada $documento)
    {
        $actor = Auth::user();
        if (! $this->permissionService->canViewDocument($actor, $documento)) {
            return response()->json(['error' => 'Sem permissão para operar neste documento.'], 403);
        }

        $profile = $this->permissionService->getUserWorkflowProfile($actor);

        if ($profile === 'gabinete') {
            // Ver o documento nunca basta para o despachar: canViewDocument é largo
            // (histórico, tarefas, departamentos destino). A regra é a mesma do
            // endpoint despachar() — tem de ser o gabinete DESTE documento.
            if (! $this->permissionService->canDespachar($actor, $documento)) {
                return response()->json([
                    'error' => 'Apenas o Chefe de Gabinete ou o Responsável pelo Gabinete deste documento podem despachá-lo.',
                ], 403);
            }

            $permitidos = $this->documentoService->departamentosDestinoPermitidos($documento)->pluck('id')->all();

            $validated = $request->validate([
                'destino_departamento_ids' => ['required', 'array', 'min:1'],
                'destino_departamento_ids.*' => ['integer', Rule::in($permitidos)],
                'texto_despacho' => ['required', 'string'],
                'prazo_at' => ['nullable', 'date'],
            ], [
                'destino_departamento_ids.*.in' => 'Só é possível despachar para departamentos do gabinete deste documento.',
            ]);

            // Despachar do Gabinete e encaminhar para os destinos selecionados
            $documento->saida_gabinete_data = now();
            $documento->despachado_por_id = $actor->id;
            $documento->data_despacho = now();
            $documento->texto_despacho = $validated['texto_despacho'];
            $documento->visto_gabinete_status = 'aprovado';
            $documento->visto_gabinete_por = $actor->id;
            $documento->visto_gabinete_data = now();
            $documento->status = DocumentoStatus::TRATADO->value;
            $documento->save();

            // Sincronizar departamentos de destino
            $documento->departamentosDestino()->sync($validated['destino_departamento_ids']);

            // Criar registo de encaminhamento primário
            foreach ($validated['destino_departamento_ids'] as $destId) {
                DocumentoEncaminhamento::create([
                    'documento_entrada_id' => $documento->id,
                    'origem_departamento_id' => $documento->departamento_id,
                    'destino_departamento_id' => $destId,
                    'usuario_id' => $actor->id,
                    'encaminhado_em' => now(),
                    'despacho_instrucao' => $validated['texto_despacho'],
                ]);
            }

            $message = 'Despacho executivo emitido e documento encaminhado com sucesso!';
        } elseif ($profile === 'chefe_departamento') {
            // Mesma exigência do DocumentoEntradaTarefaController::store.
            if (! $this->permissionService->canManageTasks($actor, $documento)) {
                return response()->json([
                    'error' => 'Você não tem permissão para delegar tarefas neste documento.',
                ], 403);
            }

            $validated = $request->validate([
                'assigned_to_user_id' => ['required', 'integer', 'exists:users,id'],
                'descricao' => ['required', 'string'],
                'prazo_at' => ['required', 'date'],
            ]);

            // O destinatário tem de pertencer ao departamento/gabinete — regra
            // partilhada com o TarefaController via DocumentoEntradaService.
            $destino = User::find($validated['assigned_to_user_id']);
            $erroDestino = $destino
                ? $this->documentoService->validarDestinatarioTarefa($documento, $destino, $actor)
                : 'Usuário não encontrado.';

            if ($erroDestino !== null) {
                return response()->json([
                    'message' => $erroDestino,
                    'errors' => ['assigned_to_user_id' => [$erroDestino]],
                ], 422);
            }

            // Criar Tarefa/Despacho atribuída ao técnico
            $tarefa = DocumentoTarefa::create([
                'documento_entrada_id' => $documento->id,
                'assigned_by_id' => $actor->id,
                'assigned_to_user_id' => $validated['assigned_to_user_id'],
                'titulo' => 'Despacho Executivo / Demanda Técnica',
                'descricao' => $validated['descricao'],
                'prazo_at' => $validated['prazo_at'],
                'status' => 'pendente',
            ]);

            // Aprovação automática do documento pelo Chefe de Departamento ao delegar
            $documento->visto_departamento_status = 'aprovado';
            $documento->visto_departamento_por = $actor->id;
            $documento->visto_departamento_data = now();
            $documento->visto_departamento_observacao = 'Aprovado automaticamente com a emissão do despacho/tarefa.';
            if ($documento->status === DocumentoStatus::ENCAMINHADO->value) {
                $documento->status = DocumentoStatus::RECEBIDO->value;
            }
            $documento->save();

            $message = 'Despacho/Tarefa delegada com sucesso e documento aprovado!';
        } elseif ($profile === 'tecnico') {
            $validated = $request->validate([
                'tarefa_id' => ['required', 'integer', 'exists:documento_tarefas,id'],
                'observacao' => ['required', 'string'],
            ]);

            $tarefa = DocumentoTarefa::where('id', $validated['tarefa_id'])
                ->where('assigned_to_user_id', $actor->id)
                ->firstOrFail();

            $tarefa->status = 'concluida';
            $tarefa->resposta = $validated['observacao'];
            $tarefa->concluida_em = now();
            $tarefa->save();

            $message = 'Parecer/Resposta técnica registrada e demanda concluída!';
        } else {
            return response()->json(['error' => 'Ação não permitida para o perfil atual.'], 403);
        }

        $workflowTabs = $this->documentoService->getRoleWorkflowTabs($actor, $request);

        return response()->json([
            'success' => true,
            'message' => $message,
            'tabs' => $workflowTabs,
        ]);
    }

    public function relacionar(Request $request, DocumentoEntrada $documento)
    {
        $this->authorize('relacionar', $documento);

        $validated = $request->validate([
            'relacionado_id' => ['required', 'integer'],
            'relacionado_type' => ['nullable', 'string', 'in:entrada,interno'],
            'tipo' => ['nullable', 'string', 'max:50'],
        ]);

        $tipoDoc = $validated['relacionado_type'] ?? 'entrada';

        if ($tipoDoc === 'interno') {
            $docInterno = DocumentoInterno::find($validated['relacionado_id']);
            if (! $docInterno) {
                return back()->withErrors(['relacionado_id' => 'Documento interno não encontrado.']);
            }

            // Link interno to entrada (belongsTo)
            $docInterno->documento_entrada_id = $documento->id;
            $docInterno->save();

            return back()->with('success', 'Documento interno vinculado com sucesso.');
        }

        // Default behavior (Entrada <-> Entrada)
        if ($documento->id == $validated['relacionado_id']) {
            return back()->withErrors(['relacionado_id' => 'Não é possível vincular o documento a si mesmo.']);
        }

        // Verify existence
        if (! DocumentoEntrada::where('id', $validated['relacionado_id'])->exists()) {
            return back()->withErrors(['relacionado_id' => 'Documento de entrada não encontrado.']);
        }

        $alreadyRelated = DB::table('documento_relacoes')
            ->where(function ($q) use ($documento, $validated) {
                $q->where('documento_id', $documento->id)
                    ->where('relacionado_id', $validated['relacionado_id']);
            })
            ->orWhere(function ($q) use ($documento, $validated) {
                $q->where('documento_id', $validated['relacionado_id'])
                    ->where('relacionado_id', $documento->id);
            })
            ->exists();

        if ($alreadyRelated) {
            return back()->with('info', 'Documentos já estão relacionados.');
        }

        $documento->documentosRelacionados()->attach($validated['relacionado_id'], [
            'tipo' => $validated['tipo'] ?? 'relacionado',
        ]);

        return back()->with('success', 'Documento relacionado com sucesso.');
    }

    public function desrelacionar(Request $request, DocumentoEntrada $documento, $relacionadoId)
    {
        $this->authorize('relacionar', $documento);

        // Check if it's an internal document unlink
        if ($request->query('type') === 'interno') {
            $docInterno = DocumentoInterno::where('id', $relacionadoId)
                ->where('documento_entrada_id', $documento->id)
                ->first();

            if ($docInterno) {
                $docInterno->documento_entrada_id = null;
                $docInterno->save();

                return back()->with('success', 'Vínculo com documento interno removido.');
            }

            return back()->with('error', 'Vínculo não encontrado.');
        }

        // Default behavior (Entrada <-> Entrada)
        $documento->documentosRelacionados()->detach($relacionadoId);
        $documento->documentosRelacionadosInverso()->detach($relacionadoId);

        return back()->with('success', 'Vínculo removido com sucesso.');
    }

    public function edit(DocumentoEntrada $documentos_entrada)
    {
        $this->authorize('update', $documentos_entrada);

        $departamentos = Cache::remember('departamentos_list', 600, fn () => Departamento::select('id', 'nome')->orderBy('nome')->get());
        $especies = Cache::remember('documento_especies_names', 600, fn () => DocumentoEspecie::where('ativo', true)->orderBy('ordem')->pluck('nome')->all());
        $procedencias = Cache::remember('procedencias_list', 300, fn () => Procedencia::where('ativo', true)->orderBy('nome')->get(['id', 'nome']));

        $tags = $documentos_entrada->tags->pluck('nome')->implode(', ');

        return view('documentos_entradas.edit', [
            'doc' => $documentos_entrada,
            'departamentos' => $departamentos,
            'especies' => $especies,
            'procedencias' => $procedencias,
            'tags' => $tags,
            'podeAlterarDepartamento' => $this->podeAlterarDepartamento(Auth::user(), $documentos_entrada),
        ]);
    }

    public function update(Request $request, DocumentoEntrada $documentos_entrada)
    {
        $this->authorize('update', $documentos_entrada);

        $validated = $request->validate([
            'classificacao_especie' => ['nullable', 'string', 'max:100'],
            'classificacao_ref_numero' => ['nullable', 'string', 'max:100'],
            'data_documento' => ['nullable', 'date'],
            'procedencia' => ['nullable', 'string', 'max:255'],
            'procedencia_id' => ['nullable', 'exists:procedencias,id'],
            'assunto' => ['required', 'string', 'max:500'],
            'observacoes' => ['nullable', 'string'],
            'saida_gabinete_data' => ['nullable', 'date'],
            'encaminhamento_orgao' => ['nullable', 'string', 'max:255'],
            'encaminhamento_oficio_numero' => ['nullable', 'string', 'max:100'],
            'departamento_id' => ['nullable', 'exists:departamentos,id'],
            'arquivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.StoreDocumentoEntradaRequest::LIMITE_FICHEIRO_KB],
            'anexos.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.StoreDocumentoEntradaRequest::LIMITE_FICHEIRO_KB],
        ]);

        // Mudar de setor faz-se por encaminhamento, que deixa rasto. Gravar
        // departamento_id pelo formulário movia o documento em silêncio, e o
        // servidor aceitava-o de qualquer utilizador — a restrição só existia
        // na view. A regra passa a ser a mesma nos dois lados.
        if (! $this->podeAlterarDepartamento(Auth::user(), $documentos_entrada)) {
            unset($validated['departamento_id']);
        }

        $this->documentoService->updateDocument(
            $documentos_entrada,
            $validated,
            $request->file('arquivo'),
            $request->file('anexos')
        );

        return redirect()->route('documentos-entradas.show', $documentos_entrada)->with('success', 'Documento atualizado com sucesso.');
    }

    /**
     * O departamento do documento só é corrigível por um administrador e
     * enquanto o documento não tiver saído do sítio. A partir daí, a mudança de
     * setor faz-se por encaminhamento.
     */
    private function podeAlterarDepartamento(?User $actor, DocumentoEntrada $documento): bool
    {
        if (! $actor || ! $this->permissionService->isAdmin($actor)) {
            return false;
        }

        return ! $documento->encaminhamentos()->exists();
    }

    public function destroyAnexo(DocumentoEntrada $documento, Anexo $anexo)
    {
        $this->authorize('update', $documento);

        if ($anexo->anexavel_type !== DocumentoEntrada::class || (int) $anexo->anexavel_id !== (int) $documento->id) {
            abort(403);
        }

        if ($anexo->caminho_arquivo) {
            $docsDisk = config('filesystems.docs_disk');
            if (Storage::disk($docsDisk)->exists($anexo->caminho_arquivo)) {
                Storage::disk($docsDisk)->delete($anexo->caminho_arquivo);
            }
            // Cleanup legacy public file if exists
            if (Storage::disk('public')->exists($anexo->caminho_arquivo)) {
                Storage::disk('public')->delete($anexo->caminho_arquivo);
            }
        }
        $anexo->delete();

        return back()->with('success', 'Anexo removido com sucesso.');
    }

    // Visto Aprovar/Rejeitar - keeping as is but ensuring status strings match Enums implicitly
    public function vistoAprovar(DocumentoEntrada $documento)
    {
        $this->authorize('vistoAprovar', $documento);
        $this->documentoService->registerVisto($documento, 'departamento', 'aprovado', Auth::user());

        return back()->with('success', 'Visto do chefe registrado como aprovado.');
    }

    public function vistoRejeitar(DocumentoEntrada $documento)
    {
        $this->authorize('vistoRejeitar', $documento);
        $this->documentoService->registerVisto($documento, 'departamento', 'rejeitado', Auth::user());

        return back()->with('success', 'Visto do chefe registrado como rejeitado.');
    }

    public function vistoGabineteAprovar(DocumentoEntrada $documento)
    {
        $this->authorize('vistoGabineteAprovar', $documento);
        $this->documentoService->registerVisto($documento, 'gabinete', 'aprovado', Auth::user());

        return back()->with('success', 'Visto do gabinete registrado como aprovado.');
    }

    public function vistoGabineteRejeitar(DocumentoEntrada $documento)
    {
        $this->authorize('vistoGabineteRejeitar', $documento);
        $this->documentoService->registerVisto($documento, 'gabinete', 'rejeitado', Auth::user());

        return back()->with('success', 'Visto do gabinete registrado como rejeitado.');
    }

    public function downloadArquivo(DocumentoEntrada $documento)
    {
        $actor = Auth::user();
        if (! $this->permissionService->canViewDocument($actor, $documento)) {
            // Fallback to history check if simple check fails
            $deps = $this->permissionService->getUserDepartments($actor);
            $hasHistory = false;
            if (count($deps)) {
                $hasHistory = DocumentoEncaminhamento::where('documento_entrada_id', $documento->id)
                    ->where(function ($q) use ($deps) {
                        $q->whereIn('origem_departamento_id', $deps)
                            ->orWhereIn('destino_departamento_id', $deps);
                    })->exists();
            }
            if (! $hasHistory) {
                abort(403);
            }
        }

        if (! $documento->arquivo_caminho) {
            abort(404);
        }

        $docsDisk = config('filesystems.docs_disk');
        if (Storage::disk($docsDisk)->exists($documento->arquivo_caminho)) {
            return $this->safeFileResponse($docsDisk, $documento->arquivo_caminho);
        }
        if (Storage::disk('public')->exists($documento->arquivo_caminho)) {
            return $this->safeFileResponse('public', $documento->arquivo_caminho);
        }
        abort(404);
    }

    /**
     * Serve um ficheiro com Content-Type validado contra o conteúdo real
     * (whitelist inline + nosniff) para impedir XSS via ficheiros disfarçados.
     */
    private function safeFileResponse(string $disk, string $path)
    {
        $mime = Storage::disk($disk)->mimeType($path) ?: null;

        return Storage::disk($disk)->response(
            $path,
            null,
            SafeFileHeaders::for($mime, basename($path))
        );
    }

    public function downloadAnexo(DocumentoEntrada $documento, Anexo $anexo)
    {
        $actor = Auth::user();
        if (! $this->permissionService->canViewDocument($actor, $documento)) {
            // Fallback to history check
            $deps = $this->permissionService->getUserDepartments($actor);
            $hasHistory = false;
            if (count($deps)) {
                $hasHistory = DocumentoEncaminhamento::where('documento_entrada_id', $documento->id)
                    ->where(function ($q) use ($deps) {
                        $q->whereIn('origem_departamento_id', $deps)
                            ->orWhereIn('destino_departamento_id', $deps);
                    })->exists();
            }
            if (! $hasHistory) {
                abort(403);
            }
        }

        if ((int) $anexo->anexavel_id !== (int) $documento->id || $anexo->anexavel_type !== DocumentoEntrada::class) {
            abort(404);
        }

        if (! $anexo->caminho_arquivo) {
            abort(404);
        }

        $docsDisk = config('filesystems.docs_disk');
        if (Storage::disk($docsDisk)->exists($anexo->caminho_arquivo)) {
            return $this->safeFileResponse($docsDisk, $anexo->caminho_arquivo);
        }

        if (Storage::disk('public')->exists($anexo->caminho_arquivo)) {
            return $this->safeFileResponse('public', $anexo->caminho_arquivo);
        }
        abort(404);
    }

    public function getOcrText(DocumentoEntrada $documento, Anexo $anexo)
    {
        $actor = Auth::user();
        if (! $this->permissionService->canViewDocument($actor, $documento)) {
            $deps = $this->permissionService->getUserDepartments($actor);
            $hasHistory = false;
            if (count($deps)) {
                $hasHistory = DocumentoEncaminhamento::where('documento_entrada_id', $documento->id)
                    ->where(function ($q) use ($deps) {
                        $q->whereIn('origem_departamento_id', $deps)
                            ->orWhereIn('destino_departamento_id', $deps);
                    })->exists();
            }
            if (! $hasHistory) {
                return response()->json(['error' => 'Não autorizado.'], 403);
            }
        }

        if ((int) $anexo->anexavel_id !== (int) $documento->id || $anexo->anexavel_type !== DocumentoEntrada::class) {
            return response()->json(['error' => 'Anexo inválido.'], 404);
        }

        return response()->json([
            'nome_original' => $anexo->nome_original,
            'texto_extraido' => $anexo->texto_extraido ?? '',
            'ocr_status' => $anexo->ocr_status ?? 'PENDENTE',
            'ocr_status_badge' => $anexo->ocr_status_badge,
            'ocr_metodo' => $anexo->ocr_metodo,
            'ocr_palavras_count' => (int) ($anexo->ocr_palavras_count ?? 0),
            'ocr_processado_em' => $anexo->ocr_processado_em ? $anexo->ocr_processado_em->format('d/m/Y H:i:s') : null,
            'ocr_erro' => $anexo->ocr_erro,
        ]);
    }

    public function reprocessOcr(DocumentoEntrada $documento, Anexo $anexo)
    {
        $actor = Auth::user();
        if (! $this->permissionService->canViewDocument($actor, $documento)) {
            return response()->json(['error' => 'Não autorizado.'], 403);
        }

        if ((int) $anexo->anexavel_id !== (int) $documento->id || $anexo->anexavel_type !== DocumentoEntrada::class) {
            return response()->json(['error' => 'Anexo inválido.'], 404);
        }

        $anexo->update([
            'ocr_status' => 'PENDENTE',
            'ocr_erro' => null,
        ]);

        ProcessarOcrAnexo::dispatch($anexo->id);

        return response()->json([
            'success' => true,
            'message' => 'Job de OCR reenviado para a fila de processamento.',
            'anexo_id' => $anexo->id,
            'ocr_status' => 'PENDENTE',
        ]);
    }

    public function destroy(DocumentoEntrada $documentos_entrada)
    {
        $this->authorize('delete', $documentos_entrada);

        $documentos_entrada->delete();

        return redirect()->route('documentos-entradas.index')
            ->with('success', 'Documento excluído com sucesso.');
    }

    public function generateCabinetNote(DocumentoEntrada $documento)
    {
        $actor = Auth::user();
        if (! $this->permissionService->canViewDocument($actor, $documento)) {
            return response()->json(['error' => 'Não autorizado.'], 403);
        }

        $assistant = app(DocumentoAssistantService::class);
        if (! $assistant->isAvailable()) {
            return response()->json(['error' => 'Assistente de IA não está ativo ou configurado.'], 503);
        }

        try {
            $note = $assistant->generateCabinetNote($documento);

            return response()->json(['nota' => $note]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Falha ao gerar nota: '.$e->getMessage()], 500);
        }
    }

    public function suggestActions(DocumentoEntrada $documento)
    {
        $actor = Auth::user();
        if (! $this->permissionService->canViewDocument($actor, $documento)) {
            return response()->json(['error' => 'Não autorizado.'], 403);
        }

        $assistant = app(DocumentoAssistantService::class);
        if (! $assistant->isAvailable()) {
            return response()->json(['error' => 'Assistente de IA não está ativo ou configurado.'], 503);
        }

        $gab = optional($documento->departamento)->gabinete;
        if ($gab) {
            $departments = Departamento::where('gabinete_id', $gab->id)->orderBy('nome')->get(['id', 'nome']);
            $users = User::with('departamento')->whereHas('departamento', function ($q) use ($gab) {
                $q->where('gabinete_id', $gab->id);
            })->orderBy('name')->get(['id', 'name', 'departamento_id']);
        } else {
            $departments = Departamento::orderBy('nome')->get(['id', 'nome']);
            $users = User::with('departamento')->orderBy('name')->get(['id', 'name', 'departamento_id']);
        }

        try {
            $suggestions = $assistant->suggestActions($documento, $departments, $users);

            return response()->json($suggestions);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Falha ao sugerir ações: '.$e->getMessage()], 500);
        }
    }

    public function despachar(Request $request, DocumentoEntrada $documento)
    {
        $actor = Auth::user();
        if (! $this->permissionService->canDespachar($actor, $documento)) {
            abort(403, 'Apenas o Chefe de Gabinete ou o Responsável pelo Gabinete podem despachar este documento.');
        }

        $permitidos = $this->documentoService->departamentosDestinoPermitidos($documento)->pluck('id')->all();

        $validated = $request->validate([
            'texto_despacho' => ['required', 'string'],
            'departamentos_ids' => ['required', 'array', 'min:1'],
            'departamentos_ids.*' => ['integer', Rule::in($permitidos)],
        ], [
            'texto_despacho.required' => 'O texto do despacho é obrigatório.',
            'departamentos_ids.required' => 'Selecione pelo menos um departamento de destino.',
            'departamentos_ids.min' => 'Selecione pelo menos um departamento de destino.',
            'departamentos_ids.*.in' => 'Só é possível despachar para departamentos do gabinete deste documento.',
        ]);

        $this->documentoService->despacharDocumento(
            $documento,
            $validated['texto_despacho'],
            $validated['departamentos_ids'],
            $actor
        );

        return back()->with('success', 'Documento despachado com sucesso para os departamentos selecionados.');
    }

    public function encaminhar(Request $request, DocumentoEntrada $documento)
    {
        $actor = Auth::user();
        if (! $this->permissionService->canEncaminharTratado($actor, $documento)) {
            abort(403, 'Você não tem permissão para encaminhar este documento.');
        }

        try {
            $this->documentoService->encaminharDocumentoTratado($documento, $actor);

            return back()->with('success', 'Documento encaminhado com sucesso para os departamentos destinatários.');
        } catch (\Throwable $e) {
            return back()->with('danger', $e->getMessage());
        }
    }
}
