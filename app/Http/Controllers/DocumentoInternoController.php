<?php

namespace App\Http\Controllers;

use App\Enums\DocumentoStatus;
use App\Exports\DocumentosInternosExport;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\ModeloDocumento;
use App\Models\User;
use App\Services\DocumentoInternoService;
use App\Services\DocumentoWorkflowService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class DocumentoInternoController extends Controller
{
    protected $service;

    protected $signatureService;

    protected $workflowService;

    public function __construct(DocumentoInternoService $service, \App\Services\SignatureService $signatureService, DocumentoWorkflowService $workflowService)
    {
        $this->service = $service;
        $this->signatureService = $signatureService;
        $this->workflowService = $workflowService;
    }

    public function index(Request $request)
    {
        // Use the hierarchical scope instead of hardcoded department check
        $query = DocumentoInterno::accessibleBy(Auth::user())
            ->with(['especie', 'autor', 'departamento'])
            ->withExists(['favoritadoPor as is_favorited' => function ($q) {
                $q->where('user_id', Auth::id());
            }]);

        // Filtro por Texto (Título ou Referência)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                    ->orWhere('numero_referencia', 'like', "%{$search}%");
            });
        }

        // Filtro por Favoritos
        if ($request->boolean('favoritos')) {
            $query->whereHas('favoritadoPor', function ($q) {
                $q->where('user_id', Auth::id());
            });
        }

        // Filtro por Espécie
        if ($request->filled('especie_id')) {
            $query->where('documento_especie_id', $request->especie_id);
        }

        // Filtro por Status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtro por Data
        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }
        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }

        // Filtro por Autor
        if ($request->filled('autor_id')) {
            $query->where('criado_por', $request->autor_id);
        }

        // Ordenação Dinâmica
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('order', 'desc');

        // Whitelist de colunas para ordenação
        $allowedSorts = ['numero_referencia', 'titulo', 'created_at', 'updated_at', 'status'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByDesc('created_at');
        }

        $documentos = $query->paginate(15)->withQueryString();

        $especies = DocumentoEspecie::where('ativo', true)->orderBy('nome')->get();

        // Autores (apenas do departamento do usuário para consistência)
        $autores = User::where('departamento_id', Auth::user()->departamento_id)->orderBy('name')->get();

        return view('documentos_internos.index', compact('documentos', 'especies', 'autores'));
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new DocumentosInternosExport($request), 'documentos_internos_'.now()->format('Ymd_His').'.xlsx');
    }

    public function exportPdf(Request $request)
    {
        $query = DocumentoInterno::accessibleBy(Auth::user())
            ->with(['especie', 'autor', 'departamento']);

        // Apply same filters as index
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                    ->orWhere('numero_referencia', 'like', "%{$search}%");
            });
        }

        if ($request->filled('especie_id')) {
            $query->where('documento_especie_id', $request->especie_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }

        $documentos = $query->orderByDesc('created_at')->get();

        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $html = view('documentos_internos.pdf_list', compact('documentos'))->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape'); // Landscape for list view
        $dompdf->render();

        return $dompdf->stream('documentos_internos_'.now()->format('Ymd_His').'.pdf', ['Attachment' => true]);
    }

    public function create(Request $request)
    {
        $documentoEntrada = null;
        if ($request->has('documento_entrada_id')) {
            $documentoEntrada = DocumentoEntrada::find($request->documento_entrada_id);
        }

        $especies = DocumentoEspecie::where('ativo', true)->orderBy('nome')->get();
        // $modelos = ModeloDocumento::where('ativo', true)->get()->groupBy('documento_especie_id');
        $modelos = $this->service->getTemplatesForUser(Auth::user())->groupBy('documento_especie_id');

        return view('documentos_internos.create', compact('documentoEntrada', 'especies', 'modelos'));
    }

    public function preview(Request $request)
    {
        $request->validate([
            'modelo_id' => 'required|exists:modelo_documentos,id',
            'documento_entrada_id' => 'nullable|exists:documentos_entradas,id',
        ]);

        $modelo = ModeloDocumento::find($request->modelo_id);
        $docEntrada = $request->documento_entrada_id ? DocumentoEntrada::find($request->documento_entrada_id) : null;

        $content = $this->service->processarTemplate($modelo->conteudo, $docEntrada, Auth::user(), $request->except(['modelo_id', 'documento_entrada_id', '_token']));

        return response()->json(['content' => $content]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'documento_especie_id' => 'required|exists:documento_especies,id',
            'modelo_documento_id' => 'nullable|exists:modelo_documentos,id',
            'documento_entrada_id' => 'nullable|exists:documentos_entradas,id',
            'conteudo_final' => 'required|string',
            'destinatario_nome' => 'nullable|string|max:255',
            'destinatario_cargo' => 'nullable|string|max:255',
            'destinatario_orgao' => 'nullable|string|max:255',
            'destinatario_local' => 'nullable|string|max:255',
        ]);

        $doc = new DocumentoInterno($validated);
        $doc->criado_por = Auth::id();
        $doc->departamento_id = Auth::user()->departamento_id;
        $doc->status = DocumentoStatus::RASCUNHO; // Default

        // Generate Reference Number
        // We load relationships needed for generation
        $doc->load(['especie', 'departamento']);
        // Need to save first? No, we need ID for count usually, but here we calculate before save.
        // But we need relations. So let's set them.
        $doc->setRelation('especie', DocumentoEspecie::find($validated['documento_especie_id']));
        $doc->setRelation('departamento', Auth::user()->departamento);

        $doc->numero_referencia = $this->service->gerarNumeroReferencia($doc);

        $doc->save();

        return redirect()->route('documentos-internos.index')
            ->with('success', 'Documento interno criado com sucesso: '.$doc->numero_referencia);
    }

    public function show(Request $request, DocumentoInterno $documentoInterno)
    {
        // Check permissions via Policy
        $this->authorize('view', $documentoInterno);

        // Load relationships needed for view
        $documentoInterno->load(['especie', 'departamento.gabinete', 'autor', 'documentoEntrada']);

        // AJAX Preview (returns only the paper content)
        if ($request->ajax() || $request->query('preview')) {
            $html = view('documentos_internos.partials.paper', compact('documentoInterno'))->render();

            return response()->json(['html' => $html]);
        }

        // Check permissions
        return view('documentos_internos.show', compact('documentoInterno'));
    }

    public function edit(DocumentoInterno $documentoInterno)
    {
        // Check permissions
        if ($documentoInterno->status !== DocumentoStatus::RASCUNHO) {
            return back()->with('error', 'Apenas rascunhos podem ser editados.');
        }

        $especies = DocumentoEspecie::where('ativo', true)->orderBy('nome')->get();

        return view('documentos_internos.edit', compact('documentoInterno', 'especies'));
    }

    public function update(Request $request, DocumentoInterno $documentoInterno)
    {
        // Check permissions
        if ($documentoInterno->bloqueado_edicao) {
            return back()->with('error', 'Documento assinado não pode ser editado.');
        }

        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'conteudo_final' => 'required|string',
            'change_type' => 'nullable|in:patch,minor,major',
            'change_log' => 'nullable|string',
        ]);

        $this->service->updateWithVersioning(
            $documentoInterno,
            $validated,
            Auth::user(),
            $request->input('change_type', 'patch'),
            $request->input('change_log')
        );

        return redirect()->route('documentos-internos.index')
            ->with('success', 'Documento atualizado com sucesso.');
    }

    public function submit(DocumentoInterno $documentoInterno)
    {
        try {
            $this->workflowService->submitForReview($documentoInterno, Auth::user());

            return back()->with('success', 'Documento enviado para análise.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function approve(DocumentoInterno $documentoInterno)
    {
        $this->authorize('approve', $documentoInterno);
        try {
            $this->workflowService->approve($documentoInterno, Auth::user());

            return back()->with('success', 'Documento aprovado.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, DocumentoInterno $documentoInterno)
    {
        $this->authorize('approve', $documentoInterno);
        $request->validate(['motivo' => 'required|string']);
        try {
            $this->workflowService->reject($documentoInterno, Auth::user(), $request->motivo);

            return back()->with('success', 'Documento devolvido.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function restore(DocumentoInterno $documentoInterno, int $version)
    {
        if ($documentoInterno->bloqueado_edicao) {
            return back()->with('error', 'Documento assinado não pode ser restaurado.');
        }

        $this->service->restoreVersion($documentoInterno, $version, Auth::user());

        return back()->with('success', 'Versão restaurada com sucesso.');
    }

    public function sign(Request $request, DocumentoInterno $documentoInterno)
    {
        $request->validate([
            'password' => 'required|string',
            'certificate_password' => 'nullable|string',
        ]);

        try {
            $this->signatureService->sign(
                $documentoInterno,
                Auth::user(),
                $request->password,
                false,
                $request->input('certificate_password')
            );

            // Status and Audit are handled by Service/Model
            return back()->with('success', 'Documento assinado digitalmente com sucesso.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function toggleFavorite(DocumentoInterno $documentoInterno)
    {
        $user = Auth::user();
        $isFavorited = $documentoInterno->favoritadoPor()->where('user_id', $user->id)->exists();

        if ($isFavorited) {
            $documentoInterno->favoritadoPor()->detach($user->id);
            $status = false;
        } else {
            $documentoInterno->favoritadoPor()->attach($user->id);
            $status = true;
        }

        return response()->json(['is_favorited' => $status]);
    }

    public function downloadPdf(DocumentoInterno $documentoInterno, Request $request)
    {
        $documentoInterno->logAudit('download');
        $documentoInterno->load(['especie', 'departamento.gabinete', 'autor']);

        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Times-Roman');

        $dompdf = new Dompdf($options);

        // Use a specific view for PDF to ensure clean output without navigation/sidebars
        $html = view('documentos_internos.pdf', compact('documentoInterno'))->render();

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'Documento_'.str_replace('/', '-', $documentoInterno->numero_referencia).'.pdf';

        $isAttachment = $request->query('download') === '1';

        return $dompdf->stream($filename, ['Attachment' => $isAttachment]);
    }

    /**
     * Validação pública de autenticidade de documentos internos.
     */
    public function verificarPublico(string $hash)
    {
        $documento = DocumentoInterno::with(['especie', 'departamento.gabinete', 'assinadoPor'])
            ->where('assinatura_hash', $hash)
            ->first();

        // Se não encontrar pelo hash, tentar encontrar pela referência
        if (! $documento) {
            // Caso tenham passado o número da referência formatada ou slug
            $ref = str_replace('-', '/', $hash);
            $documento = DocumentoInterno::with(['especie', 'departamento.gabinete', 'assinadoPor'])
                ->where('numero_referencia', $ref)
                ->first();
        }

        $valido = false;
        $erroMsg = '';

        if ($documento && $documento->assinado_em) {
            // Verificar a integridade criptográfica
            $content = $documento->conteudo_final;
            $contentHash = hash('sha256', $content);
            $signatureString = "DOC:{$documento->id}|TS:{$documento->assinado_em}|USER:{$documento->assinado_por_user_id}|CONTENT_HASH:{$contentHash}";

            $certificate = \App\Models\UserCertificate::where('user_id', $documento->assinado_por_user_id)->latest()->first();

            if ($certificate && $certificate->isValid() && $certificate->public_key) {
                // Verificar assinatura com a chave pública do certificado
                $binarySignature = base64_decode($documento->assinatura_hash);
                $pubKey = openssl_pkey_get_public($certificate->public_key);
                if ($pubKey) {
                    $ok = openssl_verify($signatureString, $binarySignature, $pubKey, OPENSSL_ALGO_SHA256);
                    if ($ok === 1) {
                        $valido = true;
                    } else {
                        $erroMsg = 'A assinatura digital do certificado não corresponde ao conteúdo atual do documento (pode ter havido adulteração).';
                    }
                } else {
                    $erroMsg = 'Chave pública do certificado associado inválida.';
                }
            } else {
                // Caso não tenha certificado, validar o hash simples
                $expectedHash = hash('sha256', $signatureString);
                if ($documento->assinatura_hash === $expectedHash) {
                    $valido = true;
                } else {
                    $erroMsg = 'O hash de integridade do documento é inválido (pode ter havido adulteração).';
                }
            }
        } else {
            $erroMsg = 'Documento não encontrado no sistema ou não foi assinado digitalmente.';
        }

        return view('documentos_internos.verificar', compact('documento', 'valido', 'erroMsg', 'hash'));
    }
}
