<?php

namespace App\Http\Controllers;

use App\Models\Requerente;
use Illuminate\Http\Request;

class RequerenteController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Requerente::class, 'requerente');
    }

    public function index(Request $request)
    {
        $query = Requerente::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nome_razao_social', 'like', "%{$search}%")
                  ->orWhere('nif_bi', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telefone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tipo_pessoa')) {
            $query->where('tipo_pessoa', $request->tipo_pessoa);
        }

        $requerentes = $query->orderBy('nome_razao_social')->paginate(15);

        return view('requerentes.index', compact('requerentes'));
    }

    public function create()
    {
        return view('requerentes.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo_pessoa' => 'required|in:FISICA,JURIDICA',
            'nome_razao_social' => 'required|string|max:255',
            'nif_bi' => 'required|string|max:50|unique:requerentes,nif_bi',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:50',
            'telemovel_alternativo' => 'nullable|string|max:50',
            'representante_nome' => 'nullable|string|max:255',
            'representante_nif_bi' => 'nullable|string|max:50',
            'endereco_completo' => 'nullable|string',
            'municipio' => 'nullable|string|max:100',
            'comuna' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'observacoes' => 'nullable|string',
        ]);

        $requerente = Requerente::create($validated);

        return redirect()->route('requerentes.show', $requerente)
            ->with('success', 'Requerente cadastrado com sucesso.');
    }

    public function show(Requerente $requerente)
    {
        $requerente->load('solicitacoes.lote');
        return view('requerentes.show', compact('requerente'));
    }

    public function edit(Requerente $requerente)
    {
        return view('requerentes.edit', compact('requerente'));
    }

    public function update(Request $request, Requerente $requerente)
    {
        $validated = $request->validate([
            'tipo_pessoa' => 'required|in:FISICA,JURIDICA',
            'nome_razao_social' => 'required|string|max:255',
            'nif_bi' => 'required|string|max:50|unique:requerentes,nif_bi,' . $requerente->id,
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:50',
            'telemovel_alternativo' => 'nullable|string|max:50',
            'representante_nome' => 'nullable|string|max:255',
            'representante_nif_bi' => 'nullable|string|max:50',
            'endereco_completo' => 'nullable|string',
            'municipio' => 'nullable|string|max:100',
            'comuna' => 'nullable|string|max:100',
            'bairro' => 'nullable|string|max:100',
            'observacoes' => 'nullable|string',
        ]);

        $requerente->update($validated);

        return redirect()->route('requerentes.show', $requerente)
            ->with('success', 'Dados do requerente atualizados com sucesso.');
    }

    public function destroy(Requerente $requerente)
    {
        $requerente->delete();
        return redirect()->route('requerentes.index')
            ->with('success', 'Requerente removido com sucesso.');
    }
}
