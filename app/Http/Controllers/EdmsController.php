<?php

namespace App\Http\Controllers;

use App\Enums\DocumentoStatus;
use App\Models\Anexo;
use App\Models\AuditLog;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\DocumentoVersao;
use App\Models\Pasta;
use App\Models\RetentionSchedule;
use App\Services\EdmsStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EdmsController extends Controller
{
    protected $storageService;

    public function __construct(EdmsStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    public function index(Request $request, ?string $folderId = null)
    {
        $user = Auth::user();

        // Resolver pasta atual
        $currentFolder = null;
        if ($folderId) {
            $currentFolder = Pasta::with('parent')->findOrFail($folderId);

            // Verificar acesso à pasta
            if (! $this->canAccessFolder($user, $currentFolder)) {
                abort(403, 'Acesso negado a esta pasta.');
            }
        }

        // --- 1. Buscar Pastas ---
        $pastasQuery = Pasta::query();

        if ($currentFolder) {
            $pastasQuery->where('parent_id', $currentFolder->id);
        } else {
            // Se for busca global (raiz e sem termo), mantemos apenas na raiz
            if (! $request->filled('search')) {
                $pastasQuery->whereNull('parent_id');
            }
        }

        // Filtro de termo nas pastas (se for global ou local)
        if ($request->filled('search')) {
            $term = $request->search;
            $pastasQuery->where('nome', 'like', "%{$term}%");
        }

        $pastas = $pastasQuery->accessibleBy($user)
            ->withCount(['children', 'documentosInternos'])
            ->orderBy('is_system', 'desc') // Pastas de sistema primeiro
            ->orderBy('nome')
            ->get();

        // --- 2. Buscar Documentos Arquivados (Filtros e busca) ---
        $documentosInternos = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 12);
        $documentosEntrada = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 12);

        $searchActive = $request->filled('search') || $request->filled('type') || $request->filled('date_start') || $request->filled('date_end');

        // Só buscamos documentos se estiver dentro de uma pasta OU se houver busca activa (busca global)
        if ($currentFolder || $searchActive) {
            $type = $request->input('type'); // interno, entrada, ou vazio para todos
            $term = $request->input('search');
            $dateStart = $request->input('date_start');
            $dateEnd = $request->input('date_end');

            // --- Query Documentos Internos ---
            if (empty($type) || $type === 'interno') {
                $internoQuery = DocumentoInterno::where('arquivado', true)
                    ->accessibleBy($user)
                    ->with(['autor', 'versoes', 'pasta', 'documentoEspecie.retentionSchedule']);

                if ($currentFolder && ! $searchActive) {
                    $internoQuery->where('pasta_id', $currentFolder->id);
                } elseif ($currentFolder && $searchActive) {
                    $internoQuery->where('pasta_id', $currentFolder->id);
                }

                if ($term) {
                    $internoQuery->where(function ($q) use ($term) {
                        $q->where('titulo', 'like', "%{$term}%")
                            ->orWhere('numero_referencia', 'like', "%{$term}%")
                            ->orWhere('conteudo_final', 'like', "%{$term}%")
                            ->orWhereHas('metadata', function ($subQ) use ($term) {
                                $subQ->where('key', 'ocr_text')
                                    ->where('value', 'like', "%{$term}%");
                            });
                    });
                }

                if ($dateStart) {
                    $internoQuery->whereDate('arquivado_em', '>=', $dateStart);
                }
                if ($dateEnd) {
                    $internoQuery->whereDate('arquivado_em', '<=', $dateEnd);
                }

                $documentosInternos = $internoQuery->latest('arquivado_em')->paginate(12, ['*'], 'page_int')->withQueryString();
            }

            // --- Query Documentos Entrada ---
            if (empty($type) || $type === 'entrada') {
                $entradaQuery = DocumentoEntrada::where('arquivado', true)
                    ->with(['pasta', 'arquivadoPor', 'anexos']);
                $entradaQuery = $this->filterDocumentoEntradaByAccess($entradaQuery, $user);

                if ($currentFolder && ! $searchActive) {
                    $entradaQuery->where('pasta_id', $currentFolder->id);
                } elseif ($currentFolder && $searchActive) {
                    $entradaQuery->where('pasta_id', $currentFolder->id);
                }

                if ($term) {
                    $entradaQuery->where(function ($q) use ($term) {
                        $q->where('assunto', 'like', "%{$term}%")
                            ->orWhere('numero_sequencial', 'like', "%{$term}%")
                            ->orWhere('procedencia', 'like', "%{$term}%")
                            ->orWhere('observacoes', 'like', "%{$term}%")
                            ->orWhereHas('anexos', function ($subQ) use ($term) {
                                $subQ->where('texto_extraido', 'like', "%{$term}%")
                                    ->orWhere('nome_original', 'like', "%{$term}%");
                            });
                    });
                }

                if ($dateStart) {
                    $entradaQuery->whereDate('arquivado_em', '>=', $dateStart);
                }
                if ($dateEnd) {
                    $entradaQuery->whereDate('arquivado_em', '<=', $dateEnd);
                }

                $documentosEntrada = $entradaQuery->latest('arquivado_em')->paginate(12, ['*'], 'page_ent')->withQueryString();
            }
        }

        // --- 3. Buscar Documentos Pendentes de Arquivamento (Apenas no Painel Geral / Raiz) ---
        $pendentesInternos = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);
        $pendentesEntrada = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);

        if (! $currentFolder && $user->departamento_id) {
            $pendentesInternos = DocumentoInterno::where('arquivado', false)
                ->where('status', DocumentoStatus::ASSINADO)
                ->where('departamento_id', $user->departamento_id)
                ->with(['autor'])
                ->latest()
                ->paginate(10, ['*'], 'page_pend_int')
                ->withQueryString();

            $pendentesEntrada = DocumentoEntrada::naoArquivados()
                ->where('departamento_id', $user->departamento_id)
                ->latest()
                ->paginate(10, ['*'], 'page_pend_ent')
                ->withQueryString();
        }

        // Departamentos para fins de Partilha
        $departamentos = Departamento::orderBy('nome')->get();

        // Breadcrumbs
        $breadcrumbs = $currentFolder ? $this->getBreadcrumbs($currentFolder) : [];

        return view('edms.index', compact(
            'currentFolder',
            'pastas',
            'documentosInternos',
            'documentosEntrada',
            'pendentesInternos',
            'pendentesEntrada',
            'departamentos',
            'breadcrumbs'
        ));
    }

    public function createFolder(Request $request)
    {
        $request->validate([
            'nome' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:pastas,id',
            'descricao' => 'nullable|string',
        ]);

        $user = Auth::user();

        // Verificar permissão no pai
        if ($request->parent_id) {
            $parent = Pasta::findOrFail($request->parent_id);
            if (! $this->canAccessFolder($user, $parent)) {
                abort(403);
            }
        }

        $pasta = Pasta::create([
            'nome' => $request->nome,
            'descricao' => $request->descricao,
            'parent_id' => $request->parent_id,
            'departamento_id' => $user->departamento_id, // Default para depto do user
            'gabinete_id' => $user->departamento ? $user->departamento->gabinete_id : null,
            'created_by' => $user->id,
            'type' => 'custom',
        ]);

        // Registo de auditoria
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'create_folder',
            'auditable_type' => Pasta::class,
            'auditable_id' => $pasta->id,
            'new_values' => ['nome' => $pasta->nome],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Pasta criada com sucesso.');
    }

    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:20480', // max 20MB
            'pasta_id' => 'required|exists:pastas,id',
            'titulo' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $pasta = Pasta::findOrFail($request->pasta_id);

        if (! $this->canAccessFolder($user, $pasta)) {
            abort(403, 'Acesso negado a esta pasta.');
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $titulo = $request->input('titulo') ?: pathinfo($originalName, PATHINFO_FILENAME);

        $especie = DocumentoEspecie::firstOrCreate(
            ['nome' => 'Ficheiro Externo'],
            ['ativo' => true]
        );

        $documento = DocumentoInterno::create([
            'titulo' => $titulo,
            'conteudo_final' => '<p>Ficheiro carregado externamente: '.$originalName.'</p>',
            'documento_especie_id' => $especie->id,
            'departamento_id' => $user->departamento_id,
            'criado_por' => $user->id,
            'status' => DocumentoStatus::ARQUIVADO,
            'numero_referencia' => 'EDMS/'.strtoupper(Str::random(8)),
            'versao_atual' => 10000,
            'versao_major' => 1,
            'versao_minor' => 0,
            'versao_patch' => 0,
            'pasta_id' => $pasta->id,
            'arquivado' => true,
            'arquivado_em' => now(),
            'arquivado_por' => $user->id,
        ]);

        $versao = $this->storageService->storeDocumentVersion($documento, $file, $user, 'Carregamento inicial de ficheiro.');

        // OCR/Extração síncrona simples para PDFs
        $text = '';
        if ($file->getMimeType() === 'application/pdf') {
            $text = $this->extractPdfText($file->path());
        }

        if (! empty($text)) {
            $documento->metadata()->create([
                'key' => 'ocr_text',
                'value' => $text,
            ]);
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'upload_file',
            'auditable_type' => Pasta::class,
            'auditable_id' => $pasta->id,
            'new_values' => ['documento_id' => $documento->id, 'titulo' => $titulo, 'file_name' => $originalName],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Ficheiro "'.$originalName.'" carregado e arquivado com sucesso.');
    }

    public function streamVersion(Request $request, $versionId)
    {
        $user = Auth::user();
        $versao = DocumentoVersao::with('documentoInterno')->findOrFail($versionId);
        $documento = $versao->documentoInterno;

        if (! $documento) {
            abort(404, 'Documento não encontrado.');
        }

        if ($documento->pasta && ! $this->canAccessFolder($user, $documento->pasta)) {
            abort(403, 'Acesso negado.');
        }

        $content = $this->storageService->retrieveFileContent($versao);
        if ($content === null) {
            abort(404, 'Ficheiro não encontrado ou corrompido.');
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'view_file_version',
            'auditable_type' => get_class($documento),
            'auditable_id' => $documento->id,
            'new_values' => ['version_id' => $versao->id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response($content, 200, \App\Support\SafeFileHeaders::for(
            $versao->mime_type ?? 'application/pdf',
            $versao->titulo
        ));
    }

    public function streamAttachment(Request $request, $anexoId)
    {
        $user = Auth::user();
        $anexo = Anexo::findOrFail($anexoId);
        $documento = $anexo->anexavel;

        if ($documento instanceof DocumentoEntrada) {
            if ($documento->pasta && ! $this->canAccessFolder($user, $documento->pasta)) {
                abort(403, 'Acesso negado.');
            }
        }

        $disk = config('filesystems.docs_disk', 'public');
        if (! Storage::disk($disk)->exists($anexo->caminho_arquivo)) {
            abort(404, 'Ficheiro não encontrado.');
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'view_file_attachment',
            'auditable_type' => get_class($anexo),
            'auditable_id' => $anexo->id,
            'new_values' => ['file_name' => $anexo->nome_original],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $fileContent = Storage::disk($disk)->get($anexo->caminho_arquivo);

        // Valida contra o conteúdo real do ficheiro, não apenas o mime registado na BD
        $detectedMime = Storage::disk($disk)->mimeType($anexo->caminho_arquivo) ?: $anexo->mime_type;

        return response($fileContent, 200, \App\Support\SafeFileHeaders::for(
            $detectedMime,
            $anexo->nome_original ?? 'anexo'
        ));
    }

    public function shareFolder(Request $request, $folderId)
    {
        $user = Auth::user();
        $pasta = Pasta::findOrFail($folderId);

        if ($pasta->created_by !== $user->id && ! $user->hasRole('admin')) {
            abort(403, 'Apenas o proprietário da pasta pode partilhar.');
        }

        $request->validate([
            'departamento_ids' => 'nullable|array',
            'departamento_ids.*' => 'exists:departamentos,id',
        ]);

        $deptIds = $request->input('departamento_ids', []);

        $metadata = $pasta->metadata()->firstOrNew(['key' => 'shared_departments']);
        $metadata->value = json_encode($deptIds);
        $metadata->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'share_folder',
            'auditable_type' => Pasta::class,
            'auditable_id' => $pasta->id,
            'new_values' => ['departments' => $deptIds],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Permissões de partilha da pasta atualizadas com sucesso.');
    }

    public function folderHistory(Request $request, $folderId)
    {
        $user = Auth::user();
        $pasta = Pasta::findOrFail($folderId);

        if (! $this->canAccessFolder($user, $pasta)) {
            abort(403);
        }

        $logs = AuditLog::where(function ($query) use ($pasta) {
            $query->where(function ($q) use ($pasta) {
                $q->where('auditable_type', Pasta::class)
                    ->where('auditable_id', $pasta->id);
            })->orWhere(function ($q) use ($pasta) {
                $q->where('auditable_type', DocumentoInterno::class)
                    ->whereIn('auditable_id', function ($subQuery) use ($pasta) {
                        $subQuery->select('id')->from('documento_internos')->where('pasta_id', $pasta->id);
                    });
            })->orWhere(function ($q) use ($pasta) {
                $q->where('auditable_type', DocumentoEntrada::class)
                    ->whereIn('auditable_id', function ($subQuery) use ($pasta) {
                        $subQuery->select('id')->from('documentos_entradas')->where('pasta_id', $pasta->id);
                    });
            });
        })
            ->with('user')
            ->latest()
            ->get();

        return response()->json($logs->map(function ($log) {
            return [
                'user_name' => $log->user->name ?? 'Sistema',
                'action' => $this->translateAction($log->action),
                'details' => $log->new_values,
                'ip' => $log->ip_address,
                'date' => $log->created_at->format('d/m/Y H:i:s'),
            ];
        }));
    }

    public function retentionConfig(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasRole('admin') && ! $user->hasRole('Admin')) {
            abort(403, 'Apenas administradores podem configurar temporalidades.');
        }

        $especies = DocumentoEspecie::with('retentionSchedule')->get();

        return view('edms.retention', compact('especies'));
    }

    public function retentionStore(Request $request)
    {
        $user = Auth::user();
        if (! $user->hasRole('admin') && ! $user->hasRole('Admin')) {
            abort(403);
        }

        $request->validate([
            'rules' => 'required|array',
            'rules.*.documento_especie_id' => 'required|exists:documento_especies,id',
            'rules.*.temporalidade_anos' => 'required|integer|min:0',
            'rules.*.acao_final' => 'required|in:arquivar,eliminar',
            'rules.*.observacoes' => 'nullable|string',
        ]);

        foreach ($request->input('rules') as $rule) {
            RetentionSchedule::updateOrCreate(
                ['documento_especie_id' => $rule['documento_especie_id']],
                [
                    'temporalidade_anos' => $rule['temporalidade_anos'],
                    'acao_final' => $rule['acao_final'],
                    'observacoes' => $rule['observacoes'] ?? null,
                ]
            );
        }

        return redirect()->route('edms.index')->with('success', 'Tabela de Temporalidade atualizada com sucesso.');
    }

    private function translateAction($action)
    {
        $map = [
            'upload_file' => 'Carregou ficheiro',
            'share_folder' => 'Partilhou pasta',
            'view_file_version' => 'Visualizou versão do ficheiro',
            'view_file_attachment' => 'Visualizou anexo',
            'create_folder' => 'Criou pasta',
            'arquivar' => 'Arquivou documento',
        ];

        return $map[$action] ?? $action;
    }

    private function extractPdfText($path)
    {
        try {
            $parser = new \Smalot\PdfParser\Parser;
            $pdf = $parser->parseFile($path);

            return trim($pdf->getText());
        } catch (\Exception $e) {
            return '';
        }
    }

    // Auxiliar para Breadcrumbs (pode ser movido para Service)
    private function getBreadcrumbs(Pasta $pasta)
    {
        $crumbs = [];
        $curr = $pasta;
        while ($curr->parent) {
            $curr = $curr->parent;
            array_unshift($crumbs, $curr);
        }

        return $crumbs;
    }

    // Auxiliar de permissão (deveria ser Policy)
    private function canAccessFolder($user, $folder)
    {
        // Admin
        if ($user->hasRole('admin') || $user->hasRole('Admin')) {
            return true;
        }

        // Chefe Gabinete
        if ($user->isChefeGabinete()) {
            return $folder->gabinete_id == $user->gabineteGerenciado->id;
        }

        // Usuario Normal
        if ($folder->departamento_id == $user->departamento_id || $folder->type == 'public') {
            return true;
        }

        // Verificar partilhas por departamento nos metadados
        $sharedMetadata = $folder->metadata()->where('key', 'shared_departments')->first();
        if ($sharedMetadata && $user->departamento_id) {
            $sharedDepts = json_decode($sharedMetadata->value, true);
            if (is_array($sharedDepts) && in_array($user->departamento_id, $sharedDepts)) {
                return true;
            }
        }

        return false;
    }

    // Auxiliar para aplicar restrição RBAC aos Documentos de Entrada
    private function filterDocumentoEntradaByAccess($query, $user)
    {
        if ($user->hasRole('admin') || $user->hasRole('Admin')) {
            return $query;
        }

        if (method_exists($user, 'isChefeGabinete') && $user->isChefeGabinete()) {
            $gabinete = $user->gabineteGerenciado;
            if ($gabinete) {
                return $query->whereHas('departamento', function ($q) use ($gabinete) {
                    $q->where('gabinete_id', $gabinete->id);
                });
            }
        }

        return $query->where('departamento_id', $user->departamento_id);
    }
}
