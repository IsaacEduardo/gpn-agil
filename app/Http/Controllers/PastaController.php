<?php

namespace App\Http\Controllers;

use App\Models\DocumentoEntrada;
use App\Models\DocumentoInterno;
use App\Models\Pasta;
use App\Services\ArchiveService;
use App\Services\PastaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PastaController extends Controller
{
    protected $pastaService;

    protected $archiveService;

    public function __construct(PastaService $pastaService, ArchiveService $archiveService)
    {
        $this->pastaService = $pastaService;
        $this->archiveService = $archiveService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $parentId = $request->query('folder');

        $query = Pasta::with(['children', 'departamento.gabinete'])
            ->withCount(['children', 'documentos', 'documentosInternos'])
            ->orderBy('is_system', 'desc') // System folders first
            ->orderBy('nome');

        $documentosEntrada = collect();
        $documentosInternos = collect();

        if ($parentId) {
            $currentFolder = Pasta::with('parent')->findOrFail($parentId);
            // Security check: ensure user can access this folder
            if ($currentFolder->departamento_id && $currentFolder->departamento_id !== $user->departamento_id) {
                // Allow if admin or specific logic
            }
            $query->where('parent_id', $parentId);
            $breadcrumbs = $this->pastaService->getBreadcrumbs($currentFolder);

            // Fetch Documents in this folder with Filters
            $search = $request->input('search');
            $type = $request->input('type', 'all'); // all, entrada, interno
            $dateStart = $request->input('date_start');
            $dateEnd = $request->input('date_end');

            // --- Documentos de Entrada ---
            if ($type === 'all' || $type === 'entrada') {
                $queryEntrada = DocumentoEntrada::arquivados()
                    ->where('pasta_id', $parentId);

                if ($search) {
                    $queryEntrada->where(function ($q) use ($search) {
                        $q->where('assunto', 'like', "%{$search}%")
                            ->orWhere('numero_sequencial', 'like', "%{$search}%")
                            ->orWhere('procedencia', 'like', "%{$search}%");
                    });
                }

                if ($dateStart) {
                    $queryEntrada->whereDate('arquivado_em', '>=', $dateStart);
                }
                if ($dateEnd) {
                    $queryEntrada->whereDate('arquivado_em', '<=', $dateEnd);
                }

                $documentosEntrada = $queryEntrada->orderBy('arquivado_em', 'desc')->get();
            }

            // --- Documentos Internos ---
            if ($type === 'all' || $type === 'interno') {
                $queryInterno = DocumentoInterno::where('arquivado', true)
                    ->where('pasta_id', $parentId);

                if ($search) {
                    $queryInterno->where(function ($q) use ($search) {
                        $q->where('titulo', 'like', "%{$search}%")
                            ->orWhere('numero_referencia', 'like', "%{$search}%");
                    });
                }

                if ($dateStart) {
                    $queryInterno->whereDate('arquivado_em', '>=', $dateStart);
                }
                if ($dateEnd) {
                    $queryInterno->whereDate('arquivado_em', '<=', $dateEnd);
                }

                $documentosInternos = $queryInterno->orderBy('arquivado_em', 'desc')->get();
            }
        } else {
            $currentFolder = null;
            $breadcrumbs = [];
            $query->whereNull('parent_id');

            // Filter roots by department
            if ($user->departamento_id) {
                $query->where('departamento_id', $user->departamento_id);

                // Initialize if empty (Self-healing)
                if ($query->count() === 0 && $user->departamento) {
                    $this->pastaService->initializeDepartmentFolders($user->departamento);
                }
            } else {
                $query->where('created_by', $user->id);
            }
        }

        $pastas = $query->get();

        return view('pastas.index', compact('pastas', 'currentFolder', 'breadcrumbs', 'documentosEntrada', 'documentosInternos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
            'parent_id' => 'nullable|exists:pastas,id',
        ]);

        $user = Auth::user();
        $gabineteId = $user->departamento ? $user->departamento->gabinete_id : null;

        Pasta::create([
            'nome' => $request->nome,
            'descricao' => $request->descricao,
            'departamento_id' => $user->departamento_id,
            'gabinete_id' => $gabineteId,
            'created_by' => $user->id,
            'parent_id' => $request->parent_id,
            'type' => 'custom',
        ]);

        return redirect()->back()->with('success', 'Pasta criada com sucesso.');
    }

    public function arquivar(Request $request, $documentoId)
    {
        $request->validate([
            'pasta_id' => 'required',
            'tipo' => 'nullable|in:entrada,interno', // Optional type to distinguish
        ]);

        $tipo = $request->input('tipo', 'entrada');

        $documento = $tipo === 'interno'
            ? DocumentoInterno::findOrFail($documentoId)
            : DocumentoEntrada::findOrFail($documentoId);

        // Autorização por perfil — mesma Policy 'archive' do fluxo drag-and-drop
        // (admin / dono de rascunho / chefe de departamento / chefe de gabinete).
        if (Gate::denies('archive', $documento)) {
            abort(403, 'Você não tem permissão para arquivar este documento.');
        }

        try {
            // Fonte ÚNICA da regra de arquivar: status + flags + pasta + auditoria,
            // com validação forte da pasta (acessível ao utilizador + compatível com o tipo).
            // 'auto' => arquivamento cronológico automático.
            $this->archiveService->archive($documento, Auth::user(), $request->pasta_id);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->back()->with('success', 'Documento arquivado com sucesso.');
    }

    public function show($id)
    {
        $pasta = Pasta::with([
            'documentos' => function ($query) {
                $query->arquivados()->orderBy('arquivado_em', 'desc');
            },
            'documentosInternos' => function ($query) {
                $query->where('arquivado', true)->orderBy('arquivado_em', 'desc');
            },
            'departamento.gabinete',
        ])->findOrFail($id);

        return view('pastas.show', compact('pasta'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
        ]);

        $pasta = Pasta::findOrFail($id);
        $pasta->update($request->only('nome', 'descricao'));

        return redirect()->route('pastas.index')->with('success', 'Pasta atualizada com sucesso.');
    }

    public function destroy($id)
    {
        $pasta = Pasta::findOrFail($id);
        $pasta->delete();

        return redirect()->route('pastas.index')->with('success', 'Pasta excluída com sucesso.');
    }

    public function desarquivar($documentoId)
    {
        $documento = DocumentoEntrada::findOrFail($documentoId);
        $documento->update([
            'pasta_id' => null,
            'arquivado' => false,
            'arquivado_em' => null,
            'arquivado_por' => null,
            'status' => 'registrado', // Revert to registered or something else? Maybe keep previous status?
            // For now 'registrado' seems safe or maybe just leave it 'arquivado' in status but false in arquivado flag?
            // No, consistency. Let's set to 'registrado'.
        ]);

        return redirect()->back()->with('success', 'Documento desarquivado com sucesso.');
    }

    public function search(Request $request)
    {
        $query = $request->input('query');
        $user = Auth::user();

        $documentos = DocumentoEntrada::arquivados()
            ->where('departamento_id', $user->departamento_id)
            ->where(function ($q) use ($query) {
                $q->where('assunto', 'like', "%{$query}%")
                    ->orWhere('numero_sequencial', 'like', "%{$query}%")
                    ->orWhere('procedencia', 'like', "%{$query}%");
            })
            ->with(['pasta.departamento.gabinete'])
            ->orderBy('arquivado_em', 'desc')
            ->get();

        return view('pastas.search', compact('documentos', 'query'));
    }
}
