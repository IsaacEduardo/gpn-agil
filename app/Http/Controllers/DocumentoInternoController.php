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
use App\Services\DocumentoPermissionService;
use App\Services\DocumentoWorkflowService;
use App\Services\EmissorDocumentoService;
use App\Services\PdfRenderService;
use App\Support\CamposVinculados;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class DocumentoInternoController extends Controller
{
    protected $service;

    protected $signatureService;

    protected $workflowService;

    protected $pdfService;

    public function __construct(
        DocumentoInternoService $service,
        \App\Services\SignatureService $signatureService,
        DocumentoWorkflowService $workflowService,
        PdfRenderService $pdfService,
        protected EmissorDocumentoService $emissores
    ) {
        $this->service = $service;
        $this->signatureService = $signatureService;
        $this->workflowService = $workflowService;
        $this->pdfService = $pdfService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $profile = $user ? $this->service->getUserWorkflowProfile($user) : 'gabinete';
        $workflowTabs = $this->service->getRoleWorkflowTabs($user, $request);
        $activeTab = $request->input('tab') ?: $this->service->getDefaultTabForProfile($profile);

        // Visibilidade, separador activo e filtros: a mesma consulta que os cartões do painel contam.
        $documentos = $this->service->queryListagem($request, $user)->paginate(15)->withQueryString();

        $especies = DocumentoEspecie::where('ativo', true)->orderBy('nome')->get();

        // Departamentos para filtro (apenas para Chefe de Gabinete / Admin)
        $isChefeGabinete = $user->isAdmin() || $user->isChefeGabinete() || $user->isSuperChefeGabinete();
        $departamentos = collect();
        if ($isChefeGabinete) {
            if ($user->isAdmin()) {
                $departamentos = \App\Models\Departamento::orderBy('nome')->get();
            } elseif ($user->gabineteGerenciado) {
                $departamentos = \App\Models\Departamento::where('gabinete_id', $user->gabineteGerenciado->id)->orderBy('nome')->get();
            } elseif ($user->gabineteSuperGerenciado) {
                $departamentos = \App\Models\Departamento::where('gabinete_id', $user->gabineteSuperGerenciado->id)->orderBy('nome')->get();
            }
        }

        // Autores disponíveis para filtro
        $autoresQuery = User::query();
        if ($isChefeGabinete && $departamentos->isNotEmpty()) {
            $autoresQuery->whereIn('departamento_id', $departamentos->pluck('id'));
        } elseif ($user->departamento_id) {
            $autoresQuery->where('departamento_id', $user->departamento_id);
        }
        $autores = $autoresQuery->orderBy('name')->get();

        return view('documentos_internos.index', compact(
            'documentos',
            'especies',
            'autores',
            'departamentos',
            'workflowTabs',
            'profile',
            'activeTab',
            'isChefeGabinete'
        ));
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new DocumentosInternosExport($request), 'documentos_internos_'.now()->format('Ymd_His').'.xlsx');
    }

    public function exportPdf(Request $request)
    {
        $user = Auth::user();
        $profile = $user ? $this->service->getUserWorkflowProfile($user) : 'gabinete';
        $activeTab = $request->input('tab') ?: $this->service->getDefaultTabForProfile($profile);

        $query = DocumentoInterno::accessibleBy($user)
            ->with(['especie', 'autor', 'departamento', 'gabinete']);

        $this->service->applyRoleTabFilter($query, $activeTab, $user, $profile);

        // Apply same filters as index
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titulo', 'like', "%{$search}%")
                    ->orWhere('numero_referencia', 'like', "%{$search}%")
                    ->orWhere('conteudo_final', 'like', "%{$search}%");
            });
        }

        if ($request->filled('especie_id')) {
            $query->where('documento_especie_id', $request->especie_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('departamento_id')) {
            $query->where('departamento_id', $request->departamento_id);
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

        $user = Auth::user();
        $especies = DocumentoEspecie::where('ativo', true)->orderBy('nome')->get();
        $modelos = $this->service->getTemplatesForUser($user)->groupBy('documento_especie_id');
        $chefesDepartamento = $this->service->getChefesDepartamentoForUser($user);

        // Quem pode emitir (departamentos e/ou o próprio gabinete) e a escolha por omissão.
        $emissores = $this->emissores->opcoesPara($user);
        $emissorPadrao = $this->emissores->padraoPara($user);

        return view('documentos_internos.create', compact('documentoEntrada', 'especies', 'modelos', 'chefesDepartamento', 'emissores', 'emissorPadrao'));
    }

    public function preview(Request $request)
    {
        $request->validate([
            'modelo_id' => 'required|exists:modelo_documentos,id',
            'documento_entrada_id' => 'nullable|exists:documentos_entradas,id',
            'departamento_id' => 'nullable|integer',
            'emissor' => 'nullable|string',
        ]);

        $modelo = ModeloDocumento::find($request->modelo_id);
        $docEntrada = $request->documento_entrada_id ? DocumentoEntrada::find($request->documento_entrada_id) : null;
        $emissor = $this->emissores->resolver(Auth::user(), $request->input('emissor'), $request->input('departamento_id'), $modelo->especie);

        $content = $this->service->processarTemplate(
            $modelo->conteudo,
            $docEntrada,
            Auth::user(),
            $request->except(['modelo_id', 'documento_entrada_id', 'departamento_id', 'emissor', '_token', 'nossa_referencia']),
            $emissor['departamento'] ?? $emissor['gabinete'],
            $modelo->especie
        );

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
            'departamento_id' => 'nullable|integer',
            'emissor' => 'nullable|string',
            'destinatario_nome' => 'nullable|string|max:255',
            'destinatario_cargo' => 'nullable|string|max:255',
            'destinatario_orgao' => 'nullable|string|max:255',
            'destinatario_local' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();

        // Departamento ou o próprio gabinete; valida que o utilizador pode emitir por ele.
        $especie = DocumentoEspecie::find($validated['documento_especie_id']);
        $emissor = $this->emissores->resolver($user, $request->input('emissor'), $request->input('departamento_id'), $especie);

        $doc = new DocumentoInterno(Arr::except($validated, ['departamento_id', 'emissor']));
        $doc->criado_por = $user->id;
        $doc->departamento_id = $emissor['departamento']?->id;
        $doc->gabinete_id = $emissor['gabinete']?->id;
        $doc->status = DocumentoStatus::RASCUNHO; // Default

        $doc->setRelation('especie', $especie);
        $doc->setRelation('departamento', $emissor['departamento']);
        $doc->setRelation('gabinete', $emissor['gabinete']);

        // Reserva do número e gravação na mesma transacção: o bloqueio da sequência
        // só é libertado depois de a referência existir na tabela.
        DB::transaction(function () use ($doc, $validated, $user) {
            $doc->conteudo_final = $this->service->prepararCamposVinculados($doc->conteudo_final, $validated);
            $reserva = $this->service->reservarNumero($doc);
            $doc->numero_referencia = $reserva['referencia'];
            // Referência no corpo e, nas Ordens de Serviço, o "Nº" do título.
            $doc->conteudo_final = $this->service->aplicarReferenciaDefinitiva($doc->conteudo_final, $reserva['referencia'], $reserva['numero']);
            $doc->save();

            // O original fica no histórico: qualquer edição posterior pode ser revertida.
            $this->service->garantirVersaoDoConteudoActual($doc, $user, 'Versão inicial');
        });

        // Persistir Vínculo N:N automaticamente se gerado a partir de uma Entrada
        if (! empty($validated['documento_entrada_id'])) {
            $tipoRelacao = $request->input('tipo_relacao', 'RESPOSTA');
            $justificativa = $request->input('vinculo_justificativa', 'Resposta ou parecer gerado para a entrada');

            \App\Models\DocumentoVinculo::firstOrCreate([
                'origem_tipo' => 'EXTERNO',
                'origem_id' => (int) $validated['documento_entrada_id'],
                'destino_tipo' => 'INTERNO',
                'destino_id' => (int) $doc->id,
            ], [
                'tipo_relacao' => strtoupper($tipoRelacao),
                'vinculado_por_id' => Auth::id(),
                'justificativa' => $justificativa,
            ]);
        }

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
        // Rascunho: autor e chefia; em análise: só a chefia (regra na policy).
        $this->authorize('update', $documentoInterno);

        if (! $documentoInterno->aceitaEdicao()) {
            return back()->with('error', 'Documento aprovado, assinado ou arquivado não pode ser editado.');
        }

        $especies = DocumentoEspecie::where('ativo', true)->orderBy('nome')->get();

        return view('documentos_internos.edit', compact('documentoInterno', 'especies'));
    }

    public function update(Request $request, DocumentoInterno $documentoInterno)
    {
        $this->authorize('update', $documentoInterno);

        if (! $documentoInterno->aceitaEdicao()) {
            return back()->with('error', 'Documento aprovado, assinado ou arquivado não pode ser editado.');
        }

        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'conteudo_final' => 'required|string',
            'change_type' => 'nullable|in:patch,minor,major',
            'change_log' => 'nullable|string',
            'destinatario_nome' => 'nullable|string|max:255',
            'destinatario_cargo' => 'nullable|string|max:255',
            'destinatario_orgao' => 'nullable|string|max:255',
            'destinatario_local' => 'nullable|string|max:255',
        ]);

        // Na edição os marcadores {{...}} não são resolvidos: gravá-los imprimi-los-ia literalmente.
        if (preg_match_all('/\{\{\s*[\w.]+\s*\}\}/u', $validated['conteudo_final'], $marcadores)) {
            throw ValidationException::withMessages([
                'conteudo_final' => 'O documento contém marcadores por resolver: '
                    .implode(', ', array_unique($marcadores[0])).'. Substitua-os pelo texto final.',
            ]);
        }

        DB::transaction(function () use ($documentoInterno, $validated, $request) {
            // O ecrã de edição envia o destinatário; antes era descartado em silêncio.
            $destinatario = array_intersect_key($validated, array_flip([
                'destinatario_nome', 'destinatario_cargo', 'destinatario_orgao', 'destinatario_local',
            ]));
            if ($destinatario !== []) {
                $documentoInterno->update($destinatario);
            }

            $validated['conteudo_final'] = $this->service->prepararCamposVinculados(
                $validated['conteudo_final'],
                $validated + $documentoInterno->only(array_keys(CamposVinculados::MAPA))
            );

            $this->service->updateWithVersioning(
                $documentoInterno,
                $validated,
                Auth::user(),
                $request->input('change_type', 'patch'),
                $request->input('change_log')
            );

            // O estado colaborativo (Yjs) passa a estar desactualizado: é descartado, e as
            // sessões abertas antes desta gravação deixam de poder gravar HTML (revisao_classica).
            $documentoInterno->collabUpdates()->delete();
            DocumentoInterno::whereKey($documentoInterno->id)->increment('revisao_classica'); // sem eventos de auditoria
        });

        $this->workflowService->notificarEdicaoPelaChefia($documentoInterno, Auth::user());

        return redirect()->route('documentos-internos.index')
            ->with('success', 'Documento atualizado com sucesso.');
    }

    public function submit(DocumentoInterno $documentoInterno)
    {
        $this->authorize('update', $documentoInterno);

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
        $this->authorize('reject', $documentoInterno);
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
        $this->authorize('update', $documentoInterno);

        if (! $documentoInterno->aceitaEdicao()) {
            return back()->with('error', 'Documento aprovado, assinado ou arquivado não pode ser restaurado.');
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
        $this->authorize('view', $documentoInterno);

        // O botão Imprimir da ficha pede este mesmo PDF (?imprimir=1) para o mandar
        // à impressora: no histórico fica como impressão, não como descarga.
        $documentoInterno->logAudit($request->boolean('imprimir') ? 'print' : 'download');
        $documentoInterno->load(['especie', 'departamento.gabinete', 'autor']);

        $html = view('documentos_internos.pdf', compact('documentoInterno'))->render();
        $filename = 'Documento_'.str_replace('/', '-', $documentoInterno->numero_referencia).'.pdf';
        $isAttachment = $request->query('download') === '1';

        return $this->pdfService->createPdfResponse($html, $filename, $isAttachment);
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
        // 'certificado' = assinatura digital real; 'visto' = hash de integridade sem certificado
        $tipoAssinatura = 'certificado';

        if ($documento && $documento->assinado_em) {
            // Verificar a integridade criptográfica
            $content = $documento->conteudo_final;
            $contentHash = hash('sha256', $content);
            $signatureString = "DOC:{$documento->id}|TS:{$documento->assinado_em}|USER:{$documento->assinado_por_user_id}|CONTENT_HASH:{$contentHash}";

            $storedHash = (string) $documento->assinatura_hash;
            $vistoPrefix = \App\Services\SignatureService::VISTO_PREFIX;

            if (str_starts_with($storedHash, $vistoPrefix) || preg_match('/^[a-f0-9]{64}$/', $storedHash)) {
                // Visto eletrónico (com prefixo) ou registo legado sem prefixo:
                // valida apenas a integridade do conteúdo, sem valor de assinatura digital.
                $tipoAssinatura = 'visto';
                $expectedHash = hash('sha256', $signatureString);
                $storedIntegrity = str_starts_with($storedHash, $vistoPrefix)
                    ? substr($storedHash, strlen($vistoPrefix))
                    : $storedHash;
                if (hash_equals($expectedHash, $storedIntegrity)) {
                    $valido = true;
                } else {
                    $erroMsg = 'O hash de integridade do documento é inválido (pode ter havido adulteração).';
                }
            } else {
                // Assinatura digital: verificar com a chave pública do certificado do signatário
                $certificate = \App\Models\UserCertificate::where('user_id', $documento->assinado_por_user_id)->latest()->first();

                if ($certificate && $certificate->public_key) {
                    $binarySignature = base64_decode($storedHash);
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
                    $erroMsg = 'Certificado do signatário não encontrado para validar a assinatura digital.';
                }
            }
        } else {
            $erroMsg = 'Documento não encontrado no sistema ou não foi assinado digitalmente.';
        }

        return view('documentos_internos.verificar', compact('documento', 'valido', 'erroMsg', 'hash', 'tipoAssinatura'));
    }

    public function batchDownloadZip(Request $request)
    {
        $request->validate([
            'documento_ids' => 'required|array',
            'documento_ids.*' => 'exists:documento_internos,id',
        ]);

        $ids = $request->documento_ids;
        $zipFileName = 'documentos_lote_' . now()->format('Ymd_His') . '.zip';
        $zipPath = storage_path('app/public/' . $zipFileName);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            foreach ($ids as $id) {
                $doc = DocumentoInterno::accessibleBy(Auth::user())->find($id);
                if (! $doc) {
                    continue;
                }

                $html = view('documentos_internos.pdf', ['documentoInterno' => $doc])->render();
                $pdfBin = $this->pdfService->renderHtmlToPdf($html);

                $refSegura = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $doc->numero_referencia ?: 'DOC-' . $doc->id);
                $zip->addFromString("Documento_{$refSegura}.pdf", $pdfBin);
            }
            $zip->close();
        }

        return response()->streamDownload(function () use ($zipPath) {
            if (file_exists($zipPath)) {
                readfile($zipPath);
                @unlink($zipPath);
            }
        }, $zipFileName, [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function autoSave(Request $request, ?DocumentoInterno $documentoInterno = null)
    {
        $request->validate([
            'titulo' => 'nullable|string|max:255',
            'conteudo_final' => 'required|string',
            'documento_especie_id' => 'nullable|exists:documento_especies,id',
            'departamento_id' => 'nullable|exists:departamentos,id',
        ]);

        if ($documentoInterno && $documentoInterno->exists) {
            // Mesma regra do edit/update: sem isto qualquer autenticado sobrescrevia rascunhos alheios.
            $this->authorize('update', $documentoInterno);

            // Gravação sem versão: só em rascunho. Em análise a chefia grava pelo update (versionado).
            if ($documentoInterno->status !== DocumentoStatus::RASCUNHO) {
                abort(403, 'Fora de rascunho as alterações gravam-se pelo ecrã de edição.');
            }

            $documentoInterno->update([
                'conteudo_final' => $request->conteudo_final,
                'titulo' => $request->titulo ?: $documentoInterno->titulo,
            ]);
            $doc = $documentoInterno;
        } else {
            $user = Auth::user();
            if ($request->filled('departamento_id') && ! $user->isAdmin()
                && ! in_array((int) $request->departamento_id, app(DocumentoPermissionService::class)->getUserDepartments($user), true)) {
                abort(403, 'Não pode criar documentos noutro departamento.');
            }

            $doc = DocumentoInterno::create([
                'titulo' => $request->titulo ?: 'Novo Documento (Rascunho)',
                'conteudo_final' => $request->conteudo_final,
                'criado_por' => Auth::id(),
                'documento_especie_id' => $request->documento_especie_id ?: DocumentoEspecie::first()?->id,
                'departamento_id' => $request->departamento_id ?: Auth::user()->departamento_id,
                'status' => 'rascunho',
                'versao_atual' => 1,
            ]);
        }

        return response()->json([
            'success' => true,
            'documento_id' => $doc->id,
            'saved_at' => now()->format('H:i:s'),
        ]);
    }
}
