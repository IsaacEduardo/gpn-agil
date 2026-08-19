<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use Illuminate\Http\Request;

class EmpresaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $perPage = (int) $request->integer('per_page', 10);
        if ($perPage < 5) {
            $perPage = 5;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }
        $sort = $request->input('sort', 'nome');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['nome', 'contacto', 'endereco', 'created_at'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'nome';
        }

        $query = Empresa::query();

        if ($q !== '') {
            $query->where(function ($inner) use ($q) {
                $inner->where('nome', 'like', "%{$q}%")
                    ->orWhere('contacto', 'like', "%{$q}%")
                    ->orWhere('endereco', 'like', "%{$q}%");
            });
        }

        $empresas = $query->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return view('empresas.index', compact('empresas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('empresas.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'contacto' => ['nullable', 'string', 'max:255'],
            'endereco' => ['nullable', 'string', 'max:255'],
        ]);

        $empresa = Empresa::create($data);

        return redirect()->route('empresas.show', $empresa)->with('status', 'Empresa criada com sucesso.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Empresa $empresa)
    {
        return view('empresas.show', compact('empresa'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Empresa $empresa)
    {
        return view('empresas.edit', compact('empresa'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Empresa $empresa)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'contacto' => ['nullable', 'string', 'max:255'],
            'endereco' => ['nullable', 'string', 'max:255'],
        ]);

        $empresa->update($data);

        return redirect()->route('empresas.show', $empresa)->with('status', 'Empresa atualizada com sucesso.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Empresa $empresa)
    {
        $empresa->delete();

        return redirect()->route('empresas.index')->with('status', 'Empresa excluída com sucesso.');
    }

    public function apiDetails(Empresa $empresa)
    {
        return response()->json([
            'id' => $empresa->id,
            'nome' => $empresa->nome,
            'contacto' => $empresa->contacto,
            'nif' => $empresa->nif,
            'telefone_principal' => $empresa->telefone_principal,
            'email_institucional' => $empresa->email_institucional,
            'endereco' => $empresa->endereco,
        ]);
    }
}
