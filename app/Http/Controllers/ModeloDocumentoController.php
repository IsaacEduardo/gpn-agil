<?php

namespace App\Http\Controllers;

use App\Models\DocumentoEspecie;
use App\Models\ModeloDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModeloDocumentoController extends Controller
{
    public function index(Request $request)
    {
        $this->checkAccess();

        $query = ModeloDocumento::with('especie');

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhereHas('especie', function ($sq) use ($search) {
                        $sq->where('nome', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by Status
        if ($request->filled('ativo')) {
            $query->where('ativo', $request->input('ativo') === '1');
        }

        // Sort
        $sort = $request->input('sort', 'nome');
        $direction = $request->input('direction', 'asc');

        $allowedSorts = ['nome', 'created_at', 'updated_at'];
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('nome', 'asc');
        }

        $modelos = $query->paginate(20)->appends($request->query());

        return view('modelos.index', compact('modelos'));
    }

    public function create()
    {
        $this->checkAccess();

        $especies = DocumentoEspecie::where('ativo', true)->orderBy('nome')->get();

        return view('modelos.create', compact('especies'));
    }

    public function store(Request $request)
    {
        $this->checkAccess();

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'documento_especie_id' => 'required|exists:documento_especies,id',
            'conteudo' => 'required|string',
            'ativo' => 'boolean',
        ]);

        $modelo = new ModeloDocumento($validated);
        $modelo->user_id = Auth::id();

        if ($request->filled('campos_dinamicos_json')) {
            $modelo->campos_dinamicos = json_decode($request->input('campos_dinamicos_json'), true);
        }

        $modelo->save();

        return redirect()->route('modelos.index')->with('success', 'Modelo criado com sucesso.');
    }

    public function show(Request $request, ModeloDocumento $modelo)
    {
        $modelo->load('especie', 'gabinete');

        if ($request->boolean('preview')) {
            $service = app(\App\Services\DocumentoInternoService::class);
            $user = Auth::user() ?? new \App\Models\User;
            $processedHtml = $service->processarTemplate($modelo->conteudo, null, $user);

            return response()->json([
                'modelo' => $modelo,
                'conteudo_processado' => $processedHtml,
            ]);
        }

        return response()->json($modelo);
    }

    public function edit(ModeloDocumento $modelo)
    {
        $this->checkAccess();

        $especies = DocumentoEspecie::where('ativo', true)->orderBy('nome')->get();

        return view('modelos.edit', compact('modelo', 'especies'));
    }

    public function update(Request $request, ModeloDocumento $modelo)
    {
        $this->checkAccess();

        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'documento_especie_id' => 'required|exists:documento_especies,id',
            'conteudo' => 'required|string',
            'ativo' => 'boolean',
        ]);

        // Handle checkbox not sending value when unchecked
        if (! $request->has('ativo')) {
            $validated['ativo'] = false;
        }

        if ($request->filled('campos_dinamicos_json')) {
            $validated['campos_dinamicos'] = json_decode($request->input('campos_dinamicos_json'), true);
        } else {
            $validated['campos_dinamicos'] = null;
        }

        $modelo->update($validated);

        return redirect()->route('modelos.index')->with('success', 'Modelo atualizado com sucesso.');
    }

    public function destroy(ModeloDocumento $modelo)
    {
        $this->checkAccess();

        $modelo->delete();

        return redirect()->route('modelos.index')->with('success', 'Modelo removido com sucesso.');
    }

    /**
     * Verifica se o utilizador autenticado tem permissão para gerir modelos (Admin ou Chefe de Gabinete).
     */
    private function checkAccess()
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        $isAdmin = $user->isAdmin() || $user->hasRole('admin') || $user->hasRole('Admin');
        $isChefeGabinete = (method_exists($user, 'isChefeGabinete') && $user->isChefeGabinete())
            || (method_exists($user, 'isSuperChefeGabinete') && $user->isSuperChefeGabinete());

        if (! $isAdmin && ! $isChefeGabinete) {
            abort(403, 'Apenas Administradores e Chefes de Gabinete podem gerir modelos de documentos.');
        }
    }
}
