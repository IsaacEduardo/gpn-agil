<?php

namespace App\Http\Controllers;

use App\Models\Pasta;
use App\Models\DocumentoEntrada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PastaController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        $pastas = Pasta::with(['departamento.gabinete'])
            ->where('departamento_id', $user->departamento_id)
            ->orWhere('created_by', $user->id)
            ->orderBy('nome')
            ->get();

        return view('pastas.index', compact('pastas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nome' => 'required|string|max:255',
            'descricao' => 'nullable|string',
        ]);

        $user = Auth::user();

        // Find associated Gabinete ID if user has a department
        $gabineteId = null;
        if ($user->departamento) {
            $gabineteId = $user->departamento->gabinete_id;
        }

        Pasta::create([
            'nome' => $request->nome,
            'descricao' => $request->descricao,
            'departamento_id' => $user->departamento_id,
            'gabinete_id' => $gabineteId,
            'created_by' => $user->id,
        ]);

        return redirect()->route('pastas.index')->with('success', 'Pasta criada com sucesso.');
    }

    public function show($id)
    {
        $pasta = Pasta::with(['documentos' => function($query) {
            $query->arquivados()->orderBy('arquivado_em', 'desc');
        }, 'departamento.gabinete'])->findOrFail($id);
        
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
    
    public function arquivar(Request $request, $documentoId)
    {
        $request->validate([
            'pasta_id' => 'required|exists:pastas,id',
        ]);

        $documento = DocumentoEntrada::findOrFail($documentoId);
        $documento->update([
            'pasta_id' => $request->pasta_id,
            'arquivado' => true,
            'arquivado_em' => now(),
            'arquivado_por' => Auth::id(),
            'status' => 'arquivado', // Sync status
        ]);

        return redirect()->back()->with('success', 'Documento arquivado com sucesso.');
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
            ->where(function($q) use ($query) {
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
