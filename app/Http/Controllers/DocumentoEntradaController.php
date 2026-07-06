<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentoEntradaRequest;
use App\Models\Anexo;
use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\ModeloDespacho;
use App\Models\Pasta;
use App\Models\User;
use App\Services\Ai\DocumentoAssistantService;
use App\Services\DocumentoEntradaService;
use App\Services\DocumentoPermissionService;
use App\Support\SafeFileHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentoEntradaController extends Controller
{
    protected $documentoService;

    protected $permissionService;

    public function __construct(DocumentoEntradaService $documentoService, DocumentoPermissionService $permissionService)
    {
        $this->documentoService = $documentoService;
        $this->permissionService = $permissionService;
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

        $modelosDespacho = ModeloDespacho::ativos()
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
}
