<?php

namespace App\Http\Controllers;

use App\Exports\DocumentoEntradasExport;
use App\Http\Requests\StoreDocumentoEntradaRequest;
use App\Models\Anexo;
use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoProtocolo;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\Pasta;
use App\Models\User;
use App\Services\DocumentoEntradaService;
use App\Services\DocumentoPermissionService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class DocumentoEntradaController extends Controller
{
    protected $documentoService;

    protected $permissionService;

    public function __construct(DocumentoEntradaService $documentoService, DocumentoPermissionService $permissionService)
    {
        $this->documentoService = $documentoService;
        $this->permissionService = $permissionService;
    }

    public function batchReceber(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'json'],
        ]);

        $ids = json_decode($validated['ids'], true);
        if (! is_array($ids) || empty($ids)) {
            return back()->with('error', 'Nenhum documento selecionado.');
        }

        $result = $this->documentoService->receiveBatch($ids, Auth::user());

        if ($result['success'] > 0) {
            $msg = $result['success'].' documento(s) recebido(s) com sucesso.';
            if ($result['failed'] > 0) {
                $msg .= ' ('.$result['failed'].' falharam ou sem permissão).';
            }

            return back()->with('success', $msg);
        }

        return back()->with('error', 'Não foi possível receber os documentos selecionados. Verifique as permissões.');
    }

    public function searchJson(Request $request)
    {
        $search = $request->input('q');
        if (empty($search) || strlen($search) < 2) {
            return response()->json([]);
        }

        // Ideally, we should apply some permission filtering here as well,
        // similar to getFilteredDocuments, to ensure users only see documents they are allowed to access.
        // For this phase 1, we will implement basic search but be mindful of sensitive data.

        $query = DocumentoEntrada::query();

        $query->where(function ($q) use ($search) {
            $q->where('assunto', 'like', "%{$search}%")
                ->orWhere('classificacao_ref_numero', 'like', "%{$search}%")
                ->orWhere('procedencia', 'like', "%{$search}%")
                ->orWhere('observacoes', 'like', "%{$search}%");

            // Search in attachments content (OCR) and filenames
            $q->orWhereHas('anexos', function ($subQ) use ($search) {
                $subQ->where('texto_extraido', 'like', "%{$search}%")
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

        // Search also in Documentos Internos
        $queryInternos = \App\Models\DocumentoInterno::query();
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
        $documentos = $this->documentoService->getFilteredDocuments($request, Auth::user());

        $departamentos = Cache::remember('departamentos_list_select', 300, function () {
            return Departamento::select(['id', 'nome'])->orderBy('nome')->get();
        });

        if (Auth::check()) {
            $actor = Auth::user();
            $userDeps = $this->permissionService->getUserDepartments($actor);
            $isChefe = $this->permissionService->isChefeDepartamento($actor);
            $isAdmin = $this->permissionService->isAdmin($actor);

            foreach ($documentos as $doc) {
                $enc = $doc->ultimoEncaminhamento;
                $hasPending = $enc && ! $enc->recebido_em;

                $canReceive = $hasPending && ($isAdmin || in_array((int) $enc->destino_departamento_id, $userDeps));
                $doc->setAttribute('can_receive', $canReceive);

                // Pode encaminhar: pertence ao departamento atual do documento (ou admin)
                // e não há encaminhamento pendente de recebimento.
                $canForward = ($isAdmin || in_array((int) $doc->departamento_id, $userDeps)) && ! $hasPending;
                $doc->setAttribute('can_forward', $canForward);

                $doc->setAttribute('is_chefe', $isChefe && in_array((int) $doc->departamento_id, $userDeps));
            }
        }

        return view('documentos_entradas.index', compact('documentos', 'departamentos'));
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

        $userDepartamentoId = Auth::user()->departamento_id;

        return view('documentos_entradas.create', compact('departamentos', 'especies', 'userDepartamentoId'));
    }

    public function store(StoreDocumentoEntradaRequest $request)
    {
        $validated = $request->validated();
        $this->documentoService->createDocument(
            $validated,
            $request->file('arquivo'),
            $request->file('anexos')
        );

        return redirect()->route('documentos-entradas.index')->with('success', 'Documento registrado com sucesso.');
    }

    public function show(DocumentoEntrada $documentos_entrada)
    {
        $doc = DocumentoEntrada::with([
            'departamento:id,nome,gabinete_id',
            'departamento.gabinete:id,nome,sigla,responsavel_id',
            'usuario:id,name',
            'vistoDepartamentoPor:id,name',
            'vistoGabinetePor:id,name',
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

        $modelosDespacho = \App\Models\ModeloDespacho::ativos()
            ->globalOrUser($actor->id)
            ->orderBy('titulo')
            ->get(['id', 'titulo', 'texto']);

        $relacionados = $doc->todos_relacionados;

        $canAssignTask = $this->permissionService->canManageTasks($actor, $doc);

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

        return view('documentos_entradas.show', compact('doc', 'departamentos', 'gabinetes', 'gabUsuarios', 'gabDepartamentos', 'depUsuarios', 'hasPendente', 'deps', 'pastas', 'modelosDespacho', 'relacionados', 'canAssignTask', 'canVisto', 'canVistoGabinete'));
    }

    public function previewAjax(DocumentoEntrada $documento)
    {
        if (! $this->permissionService->canViewDocument(Auth::user(), $documento)) {
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
        ])->findOrFail($documento->id);

        $actor = Auth::user();
        $userDeps = $this->permissionService->getUserDepartments($actor);
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

        return view('documentos_entradas.partials.preview', compact('doc', 'canReceive', 'canForward', 'pastas'));
    }

    public function relacionar(Request $request, DocumentoEntrada $documento)
    {
        $validated = $request->validate([
            'relacionado_id' => ['required', 'integer'],
            'relacionado_type' => ['nullable', 'string', 'in:entrada,interno'],
            'tipo' => ['nullable', 'string', 'max:50'],
        ]);

        $tipoDoc = $validated['relacionado_type'] ?? 'entrada';

        if ($tipoDoc === 'interno') {
            $docInterno = \App\Models\DocumentoInterno::find($validated['relacionado_id']);
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

        $alreadyRelated = \Illuminate\Support\Facades\DB::table('documento_relacoes')
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
        // Check if it's an internal document unlink
        if ($request->query('type') === 'interno') {
            $docInterno = \App\Models\DocumentoInterno::where('id', $relacionadoId)
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

    public function tarefasStore(Request $request, DocumentoEntrada $documento)
    {
        $validated = $request->validate([
            'tipo' => ['required', 'in:usuario,departamento'],
            'destino_id' => ['required', 'integer'],
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'prazo_at' => ['nullable', 'date'],
        ]);

        $actor = Auth::user();
        if (! $this->permissionService->canManageTasks($actor, $documento)) {
            return back()->with('danger', 'Você não tem permissão para designar tarefa neste documento.');
        }

        $tipo = $validated['tipo'];
        $destinoId = (int) $validated['destino_id'];
        $data = [
            'titulo' => $validated['titulo'],
            'descricao' => $validated['descricao'] ?? null,
            'prazo_at' => $validated['prazo_at'] ?? null,
        ];

        $docGabId = optional($documento->departamento)->gabinete_id;
        $isSuperChefe = $actor->isSuperChefeDoGabinete($docGabId);

        if ($isSuperChefe) {
            if ($tipo !== 'usuario') {
                return back()->withErrors(['tipo' => 'O Super Chefe apenas pode delegar tarefas a utilizadores específicos.']);
            }
            $user = User::find($destinoId);
            if (! $user) {
                return back()->withErrors(['destino_id' => 'Usuário não encontrado.']);
            }

            $gabinete = $documento->departamento ? $documento->departamento->gabinete : null;
            $isChefeGab = $gabinete && (int)$gabinete->responsavel_id === (int)$user->id;
            $isChefeDep = Departamento::where('gabinete_id', $docGabId)->where('responsavel_id', $user->id)->exists();

            if (! $isChefeGab && ! $isChefeDep) {
                return back()->withErrors(['destino_id' => 'O Super Chefe só pode delegar tarefas ao Chefe de Gabinete ou aos Chefes de Departamento do respetivo gabinete.']);
            }

            $data['assigned_to_user_id'] = $user->id;
        } elseif ($tipo === 'usuario') {
            $user = User::find($destinoId);
            if (! $user) {
                return back()->withErrors(['destino_id' => 'Usuário não encontrado.']);
            }

            $isGabResp = $this->permissionService->isGabineteResponsavel($actor, $docGabId);

            if ($isGabResp) {
                // Ensure user belongs to cabinet
                // Simplified check: user's department must belong to cabinet
                $uDep = $user->departamento;
                if (! $uDep || $uDep->gabinete_id !== $docGabId) {
                    return back()->withErrors(['destino_id' => 'Selecione usuário do seu gabinete.']);
                }
            } else {
                // Chief Dept
                $depId = (int) $documento->departamento_id;
                $belongs = ((int) $user->departamento_id === $depId) || $user->departamentos()->where('departamento_id', $depId)->exists();
                if (! $belongs) {
                    return back()->withErrors(['destino_id' => 'Selecione usuário do seu departamento.']);
                }
            }

            $data['assigned_to_user_id'] = $user->id;
        } else {
            $dep = Departamento::find($destinoId);
            if (! $dep) {
                return back()->withErrors(['destino_id' => 'Departamento não encontrado.']);
            }

            $isGabResp = $this->permissionService->isGabineteResponsavel($actor, $docGabId);

            if (! $isGabResp) {
                return back()->withErrors(['tipo' => 'Apenas responsável do gabinete pode designar ao departamento.']);
            }
            if ((int) $dep->gabinete_id !== (int) $docGabId) {
                return back()->withErrors(['destino_id' => 'Selecione departamento do seu gabinete.']);
            }

            $data['assigned_to_departamento_id'] = $dep->id;
        }

        $this->documentoService->createTask($documento, $data, $actor);

        return redirect()->route('documentos-entradas.show', $documento)->with('success', 'Tarefa designada com sucesso.');
    }

    public function tarefasConcluir(Request $request, DocumentoEntrada $documento, DocumentoTarefa $tarefa)
    {
        if ((int) $tarefa->documento_entrada_id !== (int) $documento->id) {
            abort(404);
        }
        if ($tarefa->status !== 'pendente') {
            return back()->with('info', 'Tarefa já atualizada.');
        }

        $actor = Auth::user();
        // Permission check is a bit complex for completion, let's keep it safe.
        // Or implement canCompleteTask in PermissionService.
        // For now, reuse logic or simplify.

        // Simplified Logic:
        $can = false;
        if ($tarefa->assigned_to_user_id && (int) $tarefa->assigned_to_user_id === (int) $actor->id) {
            $can = true;
        } elseif ($this->permissionService->canManageTasks($actor, $documento)) {
            $can = true;
        }

        // Specific case: task assigned to department, check if user is in that department
        if ($tarefa->assigned_to_departamento_id) {
            $userDeps = $this->permissionService->getUserDepartments($actor);
            if (in_array($tarefa->assigned_to_departamento_id, $userDeps)) {
                $can = true;
            }
        }

        if (! $can) {
            return back()->with('danger', 'Sem permissão para concluir esta tarefa.');
        }

        $responsavelId = null;
        if ($tarefa->assigned_to_departamento_id) {
            $respId = $request->input('responsavel_user_id');
            if ($respId) {
                // Validate responsible
                $user = User::find((int) $respId);
                $dep = Departamento::find($tarefa->assigned_to_departamento_id);
                $belongs = $user && (((int) $user->departamento_id === (int) $dep->id) || $user->departamentos()->where('departamento_id', $dep->id)->exists());
                if (! $belongs) {
                    return back()->withErrors(['responsavel_user_id' => 'Selecione um responsável pertencente ao departamento destino.']);
                }
                $responsavelId = (int) $respId;
            } else {
                $userDeps = $this->permissionService->getUserDepartments($actor);
                if (in_array($tarefa->assigned_to_departamento_id, $userDeps)) {
                    $responsavelId = $actor->id;
                } else {
                    return back()->withErrors(['responsavel_user_id' => 'Informe o responsável atual do departamento para concluir.']);
                }
            }
        }

        $this->documentoService->completeTask($tarefa, $actor, $responsavelId);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tarefa marcada como concluída.']);
        }

        return back()->with('success', 'Tarefa marcada como concluída.');
    }

    public function tarefasCancelar(Request $request, DocumentoEntrada $documento, DocumentoTarefa $tarefa)
    {
        if ((int) $tarefa->documento_entrada_id !== (int) $documento->id) {
            abort(404);
        }
        if ($tarefa->status !== 'pendente') {
            $msg = 'Tarefa já atualizada.';

            return $request->wantsJson() ? response()->json(['message' => $msg], 400) : back()->with('info', $msg);
        }

        $actor = Auth::user();
        $can = false;
        if ((int) $tarefa->assigned_by_id === (int) $actor->id) {
            $can = true;
        } elseif ($this->permissionService->canManageTasks($actor, $documento)) {
            $can = true;
        }

        if (! $can) {
            $msg = 'Sem permissão para cancelar esta tarefa.';

            return $request->wantsJson() ? response()->json(['message' => $msg], 403) : back()->with('danger', $msg);
        }

        $this->documentoService->cancelTask($tarefa, $actor);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Tarefa cancelada com sucesso.']);
        }

        return back()->with('success', 'Tarefa cancelada com sucesso.');
    }

    public function protocolo(DocumentoEntrada $documento)
    {
        // View logic mostly, keep as is but maybe move creation to service if missing
        $documento->load(['departamento', 'usuario', 'protocolo']);

        if (! $documento->protocolo) {
            // Should verify if this happens, usually created on store.
            // If it happens, create via service or just do it here.
            $codigo = sprintf('PRT-%d-%03d-%s', $documento->ano_referencia, $documento->numero_sequencial, strtoupper(Str::random(6)));
            $consultaUrl = route('documentos-entradas.protocolo', $documento);
            $protocolo = DocumentoProtocolo::create([
                'documento_entrada_id' => $documento->id,
                'codigo' => $codigo,
                'url_consulta' => $consultaUrl,
                'gerado_em' => now(),
            ]);
            $documento->setRelation('protocolo', $protocolo);
        }

        $consultaUrl = $documento->protocolo->url_consulta ?? route('documentos-entradas.protocolo', $documento);
        $protocolo = $documento->protocolo;

        return view('documentos_entradas.protocolo', compact('documento', 'protocolo', 'consultaUrl'));
    }

    public function protocoloPdf(DocumentoEntrada $documento)
    {
        // Same as above, keep view logic
        $documento->load(['departamento', 'usuario', 'protocolo']);

        if (! $documento->protocolo) {
            $codigo = sprintf('PRT-%d-%03d-%s', $documento->ano_referencia, $documento->numero_sequencial, strtoupper(Str::random(6)));
            $consultaUrl = route('documentos-entradas.protocolo', $documento);
            $protocolo = DocumentoProtocolo::create([
                'documento_entrada_id' => $documento->id,
                'codigo' => $codigo,
                'url_consulta' => $consultaUrl,
                'gerado_em' => now(),
            ]);
            $documento->setRelation('protocolo', $protocolo);
        }

        $consultaUrl = $documento->protocolo->url_consulta ?? route('documentos-entradas.protocolo', $documento);
        $protocolo = $documento->protocolo;

        // Create temporary QR Code SVG file for robust DomPDF rendering
        $tempQrCodePath = storage_path('app/temp_qr_'.$documento->id.'_'.Str::random(4).'.svg');
        try {
            $qrCodeSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(120)->generate($consultaUrl);
            file_put_contents($tempQrCodePath, (string) $qrCodeSvg);
        } catch (\Exception $e) {
            $tempQrCodePath = null;
        }

        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', base_path()); // Allow local file access for insignia and QR Code
        $dompdf = new Dompdf($options);

        $html = view('documentos_entradas.protocolo_pdf', compact('documento', 'protocolo', 'consultaUrl', 'tempQrCodePath'))->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A5', 'landscape');
        $dompdf->render();

        // Cleanup temporary QR Code file
        if ($tempQrCodePath && file_exists($tempQrCodePath)) {
            @unlink($tempQrCodePath);
        }

        $filename = sprintf('protocolo_%03d_%d.pdf', $documento->numero_sequencial, $documento->ano_referencia);

        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    public function exportPDF(Request $request)
    {
        $query = $this->documentoService->getFilteredDocumentsQuery($request, Auth::user());
        $query->with([
            'departamento:id,nome',
            'ultimoEncaminhamento',
            'ultimoEncaminhamento.origemDepartamento:id,nome',
            'ultimoEncaminhamento.destinoDepartamento:id,nome',
        ]);
        $documentos = $query->get();

        $filtersSummary = [];
        if ($request->filled('departamento_id')) {
            $depName = optional(Departamento::find($request->input('departamento_id')))->nome;
            if ($depName) {
                $filtersSummary['Departamento'] = $depName;
            }
        }

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $html = view('documentos_entradas.pdf', compact('documentos', 'filtersSummary'))->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->stream('relatorio-documentos-entradas-'.date('Y-m-d').'.pdf');
    }

    public function exportExcel(Request $request)
    {
        $query = $this->documentoService->getFilteredDocumentsQuery($request, Auth::user());
        $documentos = $query->get();

        return Excel::download(new DocumentoEntradasExport($documentos), 'relatorio-documentos-entradas-'.date('Y-m-d').'.xlsx');
    }

    public function marcarProtocoloImpresso(DocumentoEntrada $documento, Request $request)
    {
        $documento->load('protocolo');
        if (! $documento->protocolo) {
            return response()->json(['message' => 'Protocolo não encontrado'], 404);
        }

        $documento->protocolo->impresso_em = Carbon::now();
        $documento->protocolo->save();

        return response()->json([
            'message' => 'Protocolo marcado como impresso',
            'impresso_em' => $documento->protocolo->impresso_em,
        ]);
    }

    public function edit(DocumentoEntrada $documentos_entrada)
    {
        $departamentos = Cache::remember('departamentos_list', 600, fn () => Departamento::select('id', 'nome')->orderBy('nome')->get());
        $especies = Cache::remember('documento_especies_names', 600, fn () => DocumentoEspecie::where('ativo', true)->orderBy('ordem')->pluck('nome')->all());

        $tags = $documentos_entrada->tags->pluck('nome')->implode(', ');

        return view('documentos_entradas.edit', [
            'doc' => $documentos_entrada,
            'departamentos' => $departamentos,
            'especies' => $especies,
            'tags' => $tags,
        ]);
    }

    public function update(Request $request, DocumentoEntrada $documentos_entrada)
    {
        $validated = $request->validate([
            'classificacao_especie' => ['nullable', 'string', 'max:100'],
            'classificacao_ref_numero' => ['nullable', 'string', 'max:100'],
            'data_documento' => ['nullable', 'date'],
            'procedencia' => ['nullable', 'string', 'max:255'],
            'assunto' => ['required', 'string', 'max:500'],
            'observacoes' => ['nullable', 'string'],
            'saida_gabinete_data' => ['nullable', 'date'],
            'encaminhamento_orgao' => ['nullable', 'string', 'max:255'],
            'encaminhamento_oficio_numero' => ['nullable', 'string', 'max:100'],
            'departamento_id' => ['required', 'exists:departamentos,id'],
            'arquivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'anexos.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $this->documentoService->updateDocument(
            $documentos_entrada,
            $validated,
            $request->file('arquivo'),
            $request->file('anexos')
        );

        return redirect()->route('documentos-entradas.show', $documentos_entrada)->with('success', 'Documento atualizado com sucesso.');
    }

    public function destroyAnexo(DocumentoEntrada $documento, Anexo $anexo)
    {
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

    public function encaminhar(Request $request, DocumentoEntrada $documento)
    {
        $this->authorize('encaminhar', $documento);

        $validated = $request->validate([
            'destino_departamento_id' => ['required', 'exists:departamentos,id'],
            'observacao' => ['nullable', 'string'],
        ]);

        if ($documento->encaminhamentos()->whereNull('recebido_em')->exists()) {
            return $this->respondForwardError($request, 'destino_departamento_id', 'Há encaminhamento pendente; aguarde o recebimento antes de criar um novo.');
        }
        if ($documento->departamento_id === (int) $validated['destino_departamento_id']) {
            return $this->respondForwardError($request, 'destino_departamento_id', 'Selecione um departamento diferente do atual.');
        }

        try {
            $enc = $this->documentoService->forwardDocument(
                $documento,
                (int) $validated['destino_departamento_id'],
                $validated['observacao'] ?? null,
                Auth::user()
            );
        } catch (\RuntimeException $e) {
            // Corrida fechada pelo lock atómico do serviço.
            return $this->respondForwardError($request, 'destino_departamento_id', $e->getMessage());
        }

        if ($request->wantsJson()) {
            $destino = Departamento::find((int) $validated['destino_departamento_id']);

            return response()->json([
                'success' => true,
                'message' => 'Documento encaminhado com sucesso.',
                'documento_id' => $documento->id,
                'encaminhamento_id' => $enc->id,
                'destino' => $destino?->nome,
                'encaminhado_em' => optional($enc->encaminhado_em)->format('d/m'),
            ]);
        }

        return redirect()->route('documentos-entradas.show', $documento)->with('success', 'Documento encaminhado com sucesso.');
    }

    /**
     * Encaminhamento em lote para um único departamento de destino.
     * Autorização e validações são feitas por documento dentro do serviço.
     */
    public function batchEncaminhar(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'json'],
            'destino_departamento_id' => ['required', 'exists:departamentos,id'],
            'observacao' => ['nullable', 'string'],
        ]);

        $ids = json_decode($validated['ids'], true);
        if (! is_array($ids) || empty($ids)) {
            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => 'Nenhum documento selecionado.'], 422)
                : back()->with('error', 'Nenhum documento selecionado.');
        }

        $result = $this->documentoService->forwardBatch(
            array_map('intval', $ids),
            (int) $validated['destino_departamento_id'],
            $validated['observacao'] ?? null,
            Auth::user()
        );

        $message = $result['success'] > 0
            ? $result['success'].' documento(s) encaminhado(s) com sucesso.'.($result['failed'] > 0 ? ' ('.$result['failed'].' falharam, sem permissão, pendentes ou já no destino).' : '')
            : 'Nenhum documento pôde ser encaminhado. Verifique permissões, pendências ou o departamento de destino.';

        if ($request->wantsJson()) {
            return response()->json(['success' => $result['success'] > 0, 'message' => $message] + $result, $result['success'] > 0 ? 200 : 422);
        }

        return $result['success'] > 0 ? back()->with('success', $message) : back()->with('error', $message);
    }

    /**
     * Resposta de erro do encaminhamento: JSON 422 para pedidos AJAX, redirect com
     * erros de validação para pedidos web (mantém o comportamento original).
     */
    private function respondForwardError(Request $request, string $field, string $message)
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'errors' => [$field => [$message]]], 422);
        }

        return back()->withErrors([$field => $message]);
    }

    public function cancelarEncaminhamento(DocumentoEntrada $documento, DocumentoEncaminhamento $encaminhamento)
    {
        // 1. Validações básicas
        if ((int) $encaminhamento->documento_entrada_id !== (int) $documento->id) {
            abort(404);
        }

        // 2. Verifica se já foi recebido (não pode cancelar se o destino já recebeu)
        if ($encaminhamento->recebido_em) {
            return back()->with('error', 'Não é possível cancelar. O documento já foi recebido pelo destino.');
        }

        $actor = Auth::user();

        // 3. Verifica permissão: Apenas quem encaminhou ou quem tem permissão de gerência pode cancelar
        $isAuthor = (int) $encaminhamento->usuario_id === (int) $actor->id;
        // $isManager = $this->permissionService->isChefeDepartamento($actor);

        if (! $isAuthor) {
            abort(403, 'Você não tem permissão para cancelar este encaminhamento.');
        }

        // 4. Executa o cancelamento via Service
        $this->documentoService->cancelForwarding($encaminhamento, $actor);

        return back()->with('success', 'Encaminhamento cancelado com sucesso. O documento está disponível novamente.');
    }

    public function receberEncaminhamento(Request $request, DocumentoEntrada $documento, DocumentoEncaminhamento $encaminhamento)
    {
        if ((int) $encaminhamento->documento_entrada_id !== (int) $documento->id) {
            abort(404);
        }

        $actor = Auth::user();
        if (! $this->permissionService->canReceiveInDepartment($actor, (int) $encaminhamento->destino_departamento_id)) {
            abort(403);
        }

        // Idempotente: receiveDocument devolve false se já estava recebido (corrida).
        $recebeu = $encaminhamento->recebido_em
            ? false
            : $this->documentoService->receiveDocument($documento, $encaminhamento, $actor);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'already' => ! $recebeu,
                'message' => $recebeu ? 'Documento marcado como recebido.' : 'Encaminhamento já marcado como recebido.',
                'documento_id' => $documento->id,
            ]);
        }

        return $recebeu
            ? back()->with('success', 'Documento marcado como recebido.')
            : back()->with('info', 'Encaminhamento já marcado como recebido.');
    }

    public function saidaGabinete(Request $request, DocumentoEntrada $documento)
    {
        $this->authorize('saidaGabinete', $documento);

        $validated = $request->validate([
            'destino_gabinete_id' => ['required', 'exists:gabinetes,id'],
            'saida_gabinete_data' => ['required', 'date'],
            'encaminhamento_oficio_numero' => ['nullable', 'string', 'max:100'],
        ]);

        $actor = Auth::user();

        $docGabineteId = optional($documento->departamento)->gabinete_id;
        if ($documento->saida_gabinete_data) {
            return back()->withErrors(['destino_gabinete_id' => 'Documento já possui saída de gabinete registrada.']);
        }
        if ($documento->encaminhamentos()->whereNull('recebido_em')->exists()) {
            return back()->withErrors(['destino_gabinete_id' => 'Há encaminhamento interno pendente; receba antes de dar saída.']);
        }
        if ($docGabineteId && (int) $validated['destino_gabinete_id'] === (int) $docGabineteId) {
            return back()->withErrors(['destino_gabinete_id' => 'Selecione um gabinete diferente do atual.']);
        }

        $this->documentoService->sendToGabinete(
            $documento,
            (int) $validated['destino_gabinete_id'],
            $validated['saida_gabinete_data'],
            $validated['encaminhamento_oficio_numero'] ?? null,
            $request->input('observacao'),
            $actor
        );

        return redirect()->route('documentos-entradas.show', $documento)
            ->with('success', 'Saída do gabinete registrada com sucesso.');
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
            return Storage::disk($docsDisk)->response($documento->arquivo_caminho);
        }
        if (Storage::disk('public')->exists($documento->arquivo_caminho)) {
            return Storage::disk('public')->response($documento->arquivo_caminho);
        }
        abort(404);
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
            return Storage::disk($docsDisk)->response($anexo->caminho_arquivo);
        }

        if (Storage::disk('public')->exists($anexo->caminho_arquivo)) {
            return Storage::disk('public')->response($anexo->caminho_arquivo);
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
        ]);
    }

    public function destroy(DocumentoEntrada $documentos_entrada)
    {
        $actor = Auth::user();
        if (! $this->permissionService->isAdmin($actor) && $documentos_entrada->user_id !== $actor->id) {
            abort(403, 'Você não tem permissão para excluir este documento.');
        }

        $documentos_entrada->delete();

        return redirect()->route('documentos-entradas.index')
            ->with('success', 'Documento excluído com sucesso.');
    }
}
