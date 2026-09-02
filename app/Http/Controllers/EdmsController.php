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

        // 1. Alternância de Modo de Visualização (table vs grid)
        $viewMode = $request->input('view_mode', 'table');
        if (! in_array($viewMode, ['table', 'grid'])) {
            $viewMode = 'table';
        }

        // 2. Parâmetros de Seleção da Árvore (Virtuais ou Físicas)
        $activeTreeKey = $request->input('tree', $folderId ? 'dossies' : 'entradas'); // entradas, internos, dossies, pendentes
        $treeYear = $request->input('year');
        $treeMonth = $request->input('month'); // ex: '08' ou '8'
        $treeMonthName = $treeMonth ? $this->formatMonthName((int) $treeMonth) : null;
        $treeEspecie = $request->input('especie');

        // Resolver pasta física se fornecida
        $currentFolder = null;
        if ($folderId) {
            $currentFolder = Pasta::with('parent')->findOrFail($folderId);

            if (! $this->canAccessFolder($user, $currentFolder)) {
                abort(403, 'Acesso negado a esta pasta.');
            }
            $activeTreeKey = 'dossies';
        }

        // 3. Buscar Pastas Manuais / Dossiês (Custom Folders com RBAC)
        $pastasQuery = Pasta::query();
        if ($currentFolder) {
            $pastasQuery->where('parent_id', $currentFolder->id);
        } else {
            if (! $request->filled('search')) {
                $pastasQuery->whereNull('parent_id');
            }
        }
        if ($request->filled('search')) {
            $term = $request->search;
            $pastasQuery->where('nome', 'like', "%{$term}%");
        }
        $pastas = $pastasQuery->accessibleBy($user)
            ->withCount(['children', 'documentos', 'documentosInternos'])
            ->orderBy('is_system', 'desc')
            ->orderBy('nome')
            ->get();

        // 4. Construir Árvore de Diretórios Virtuais Cronológicos (Tree Structure)
        $virtualTree = $this->buildVirtualDirectoryTree($user);

        // 5. Query e Filtros para Documentos no Main Workspace
        $documentosInternos = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
        $documentosEntrada = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);

        $term = $request->input('search');
        $dateStart = $request->input('date_start');
        $dateEnd = $request->input('date_end');
        $isGlobalSearch = $request->filled('search') && ! $request->filled('tree') && ! $request->filled('type') && ! $currentFolder;

        // A. DOCUMENTOS DE ENTRADA ARQUIVADOS
        if ($activeTreeKey === 'entradas' || $isGlobalSearch || ($request->filled('type') && $request->type === 'entrada')) {
            $entradaQuery = DocumentoEntrada::where('arquivado', true)
                ->with(['pasta', 'arquivadoPor', 'anexos', 'departamento']);
            $this->filterDocumentoEntradaByAccess($entradaQuery, $user);

            if ($currentFolder) {
                $entradaQuery->where('pasta_id', $currentFolder->id);
            }
            if ($treeYear) {
                $entradaQuery->where('ano_referencia', (int) $treeYear);
            }
            if ($treeMonth) {
                $entradaQuery->whereMonth('data_entrada', (int) $treeMonth);
            }
            if ($treeEspecie) {
                $entradaQuery->where('classificacao_especie', $treeEspecie);
            }
            if ($term) {
                $entradaQuery->where(function ($q) use ($term) {
                    $q->where('assunto', 'like', "%{$term}%")
                        ->orWhere('numero_sequencial', 'like', "%{$term}%")
                        ->orWhere('procedencia', 'like', "%{$term}%")
                        ->orWhere('classificacao_ref_numero', 'like', "%{$term}%")
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

            $documentosEntrada = $entradaQuery->latest('arquivado_em')->paginate(15, ['*'], 'page_ent')->withQueryString();
        }

        // B. DOCUMENTOS INTERNOS ARQUIVADOS
        if ($activeTreeKey === 'internos' || $isGlobalSearch || ($request->filled('type') && $request->type === 'interno')) {
            $internoQuery = DocumentoInterno::where('arquivado', true)
                ->accessibleBy($user)
                ->with(['autor', 'versoes', 'pasta', 'departamento', 'documentoEspecie.retentionSchedule']);

            if ($currentFolder) {
                $internoQuery->where('pasta_id', $currentFolder->id);
            }
            if ($treeYear) {
                $internoQuery->whereYear('created_at', (int) $treeYear);
            }
            if ($treeMonth) {
                $internoQuery->whereMonth('created_at', (int) $treeMonth);
            }
            if ($treeEspecie) {
                $internoQuery->whereHas('documentoEspecie', function ($e) use ($treeEspecie) {
                    $e->where('nome', $treeEspecie);
                });
            }
            if ($term) {
                $internoQuery->where(function ($q) use ($term) {
                    $q->where('titulo', 'like', "%{$term}%")
                        ->orWhere('numero_referencia', 'like', "%{$term}%")
                        ->orWhere('conteudo_final', 'like', "%{$term}%");
                });
            }
            if ($dateStart) {
                $internoQuery->whereDate('arquivado_em', '>=', $dateStart);
            }
            if ($dateEnd) {
                $internoQuery->whereDate('arquivado_em', '<=', $dateEnd);
            }

            $documentosInternos = $internoQuery->latest('arquivado_em')->paginate(15, ['*'], 'page_int')->withQueryString();
        }

        // C. DOCUMENTOS DE PASTA FÍSICA SELECIONADA
        if ($currentFolder && $activeTreeKey === 'dossies') {
            if (! $request->filled('type') || $request->type === 'entrada') {
                $eQuery = DocumentoEntrada::where('arquivado', true)
                    ->where('pasta_id', $currentFolder->id)
                    ->with(['pasta', 'arquivadoPor', 'anexos', 'departamento']);
                $this->filterDocumentoEntradaByAccess($eQuery, $user);
                $documentosEntrada = $eQuery->latest('arquivado_em')->paginate(15, ['*'], 'page_ent')->withQueryString();
            }
            if (! $request->filled('type') || $request->type === 'interno') {
                $iQuery = DocumentoInterno::where('arquivado', true)
                    ->where('pasta_id', $currentFolder->id)
                    ->accessibleBy($user)
                    ->with(['autor', 'versoes', 'pasta', 'departamento', 'documentoEspecie.retentionSchedule']);
                $documentosInternos = $iQuery->latest('arquivado_em')->paginate(15, ['*'], 'page_int')->withQueryString();
            }
        }

        // 6. DOCUMENTOS PENDENTES DE ARQUIVAMENTO
        $pendentesInternos = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);
        $pendentesEntrada = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);

        $pIntQuery = DocumentoInterno::where('arquivado', false)
            ->where('status', DocumentoStatus::ASSINADO)
            ->accessibleBy($user)
            ->with(['autor', 'departamento']);

        $pendentesInternos = $pIntQuery->latest()->paginate(10, ['*'], 'page_pend_int')->withQueryString();

        $pEntQuery = DocumentoEntrada::naoArquivados()->with(['departamento']);
        $this->filterDocumentoEntradaByAccess($pEntQuery, $user);
        $pendentesEntrada = $pEntQuery->latest()->paginate(10, ['*'], 'page_pend_ent')->withQueryString();

        $pendentesTotal = $pendentesInternos->total() + $pendentesEntrada->total();

        // Departamentos para partilha
        $departamentos = Departamento::orderBy('nome')->get();
        $breadcrumbs = $currentFolder ? $this->getBreadcrumbs($currentFolder) : [];

        return view('edms.index', compact(
            'currentFolder',
            'pastas',
            'virtualTree',
            'activeTreeKey',
            'treeYear',
            'treeMonth',
            'treeMonthName',
            'treeEspecie',
            'viewMode',
            'documentosInternos',
            'documentosEntrada',
            'pendentesInternos',
            'pendentesEntrada',
            'pendentesTotal',
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

    /**
     * Auxiliar de Formatação dos Meses Arquivísticos (01 - Janeiro ... 12 - Dezembro)
     */
    private function formatMonthName(int $m): string
    {
        $meses = [
            1 => '01 - Janeiro',
            2 => '02 - Fevereiro',
            3 => '03 - Março',
            4 => '04 - Abril',
            5 => '05 - Maio',
            6 => '06 - Junho',
            7 => '07 - Julho',
            8 => '08 - Agosto',
            9 => '09 - Setembro',
            10 => '10 - Outubro',
            11 => '11 - Novembro',
            12 => '12 - Dezembro',
        ];

        return $meses[$m] ?? sprintf('%02d - Mês %d', $m, $m);
    }

    /**
     * Endpoint API JSON da Árvore Hierárquica de Pastas (GET /api/edms/arvore-pastas)
     */
    public function arvorePastas(Request $request)
    {
        $user = Auth::user();
        $virtualTree = $this->buildVirtualDirectoryTree($user);

        $result = [];

        foreach (['entradas', 'internos'] as $key) {
            $item = $virtualTree[$key];
            $anosFormatted = [];

            foreach ($item['years'] as $anoStr => $anoData) {
                $mesesFormatted = [];

                foreach ($anoData['months'] as $mStr => $mGroup) {
                    $speciesFormatted = [];
                    foreach ($mGroup['species'] as $eNome => $eCount) {
                        $speciesFormatted[] = [
                            'nome' => $eNome,
                            'total' => $eCount,
                        ];
                    }

                    $mesesFormatted[] = [
                        'mes_numero' => $mGroup['mes_numero'],
                        'mes_nome' => $mGroup['mes_nome'],
                        'total' => $mGroup['count'],
                        'especies' => $speciesFormatted,
                    ];
                }

                $anosFormatted[] = [
                    'ano' => (string) $anoStr,
                    'total' => $anoData['count'],
                    'meses' => $mesesFormatted,
                ];
            }

            $result[] = [
                'id' => $key,
                'nome' => $item['label'],
                'tipo' => 'raiz',
                'total' => $item['total'],
                'anos' => $anosFormatted,
            ];
        }

        return response()->json($result);
    }

    /**
     * Gerador de Estrutura de Diretórios Virtuais (Tree View) por Tipo, Ano, Mês e Espécie.
     */
    private function buildVirtualDirectoryTree($user): array
    {
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $isSqlite = $driver === 'sqlite';
        $isPgsql = $driver === 'pgsql';

        // Expressões SQL compatíveis com MySQL, SQLite e PostgreSQL
        if ($isSqlite) {
            $monthExprEntrada = "strftime('%m', COALESCE(data_entrada, created_at))";
            $yearExprInterno = "strftime('%Y', created_at)";
            $monthExprInterno = "strftime('%m', created_at)";
        } elseif ($isPgsql) {
            $monthExprEntrada = "EXTRACT(MONTH FROM COALESCE(data_entrada, created_at))";
            $yearExprInterno = "EXTRACT(YEAR FROM created_at)";
            $monthExprInterno = "EXTRACT(MONTH FROM created_at)";
        } else {
            $monthExprEntrada = "MONTH(COALESCE(data_entrada, created_at))";
            $yearExprInterno = "YEAR(created_at)";
            $monthExprInterno = "MONTH(created_at)";
        }

        // 1. ENTRADAS: Agrupamento por Ano, Mês e Espécie
        $qEntradas = DocumentoEntrada::where('arquivado', true);
        $this->filterDocumentoEntradaByAccess($qEntradas, $user);

        $entradasRaw = $qEntradas->select([
            'ano_referencia',
            \Illuminate\Support\Facades\DB::raw("{$monthExprEntrada} as mes_num"),
            'classificacao_especie',
            \Illuminate\Support\Facades\DB::raw('count(*) as total'),
        ])
            ->groupBy('ano_referencia', \Illuminate\Support\Facades\DB::raw($monthExprEntrada), 'classificacao_especie')
            ->orderBy('ano_referencia', 'desc')
            ->orderBy(\Illuminate\Support\Facades\DB::raw($monthExprEntrada), 'desc')
            ->get();

        $entradasTree = [];
        $totalEntradas = 0;
        foreach ($entradasRaw as $row) {
            $ano = (string) ($row->ano_referencia ?: date('Y'));
            $mNum = (int) ($row->mes_num ?: date('n'));
            $mStr = sprintf('%02d', $mNum);
            $mNome = $this->formatMonthName($mNum);
            $especie = $row->classificacao_especie ?: 'Geral';
            $count = (int) $row->total;
            $totalEntradas += $count;

            if (! isset($entradasTree[$ano])) {
                $entradasTree[$ano] = [
                    'count' => 0,
                    'months' => [],
                ];
            }
            $entradasTree[$ano]['count'] += $count;

            if (! isset($entradasTree[$ano]['months'][$mStr])) {
                $entradasTree[$ano]['months'][$mStr] = [
                    'mes_numero' => $mStr,
                    'mes_nome' => $mNome,
                    'count' => 0,
                    'species' => [],
                ];
            }
            $entradasTree[$ano]['months'][$mStr]['count'] += $count;
            $entradasTree[$ano]['months'][$mStr]['species'][$especie] = $count;
        }

        // 2. INTERNOS: Agrupamento por Ano, Mês e Espécie
        $qInternos = DocumentoInterno::where('arquivado', true)->accessibleBy($user);
        $internosRaw = $qInternos->select([
            \Illuminate\Support\Facades\DB::raw("{$yearExprInterno} as ano"),
            \Illuminate\Support\Facades\DB::raw("{$monthExprInterno} as mes_num"),
            'documento_especie_id',
            \Illuminate\Support\Facades\DB::raw('count(*) as total'),
        ])
            ->with('documentoEspecie:id,nome')
            ->groupBy(\Illuminate\Support\Facades\DB::raw($yearExprInterno), \Illuminate\Support\Facades\DB::raw($monthExprInterno), 'documento_especie_id')
            ->orderBy(\Illuminate\Support\Facades\DB::raw($yearExprInterno), 'desc')
            ->orderBy(\Illuminate\Support\Facades\DB::raw($monthExprInterno), 'desc')
            ->get();

        $internosTree = [];
        $totalInternos = 0;
        foreach ($internosRaw as $row) {
            $ano = (string) ($row->ano ?: date('Y'));
            $mNum = (int) ($row->mes_num ?: date('n'));
            $mStr = sprintf('%02d', $mNum);
            $mNome = $this->formatMonthName($mNum);
            $especieNome = $row->documentoEspecie ? $row->documentoEspecie->nome : 'Geral';
            $count = (int) $row->total;
            $totalInternos += $count;

            if (! isset($internosTree[$ano])) {
                $internosTree[$ano] = [
                    'count' => 0,
                    'months' => [],
                ];
            }
            $internosTree[$ano]['count'] += $count;

            if (! isset($internosTree[$ano]['months'][$mStr])) {
                $internosTree[$ano]['months'][$mStr] = [
                    'mes_numero' => $mStr,
                    'mes_nome' => $mNome,
                    'count' => 0,
                    'species' => [],
                ];
            }
            $internosTree[$ano]['months'][$mStr]['count'] += $count;
            $internosTree[$ano]['months'][$mStr]['species'][$especieNome] = $count;
        }

        return [
            'entradas' => [
                'label' => 'Doc. de Entrada',
                'icon' => 'fas fa-file-import text-warning',
                'total' => $totalEntradas,
                'years' => $entradasTree,
            ],
            'internos' => [
                'label' => 'Doc. Internos',
                'icon' => 'fas fa-file-alt text-info',
                'total' => $totalInternos,
                'years' => $internosTree,
            ],
        ];
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

        $permissionService = app(\App\Services\DocumentoPermissionService::class);
        if ($permissionService->isUserInAreaExpediente($user)) {
            return $query;
        }

        return $query->where('departamento_id', $user->departamento_id);
    }
}
