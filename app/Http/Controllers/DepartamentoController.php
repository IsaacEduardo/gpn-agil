<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartamentoController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) ($request->input('per_page', 15));
        if ($perPage < 5) {
            $perPage = 5;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }

        $departamentos = Departamento::with(['chefe', 'gabinete'])
            ->orderBy('nome')
            ->paginate($perPage)
            ->withQueryString();

        return view('departamentos.index', compact('departamentos'));
    }

    public function create()
    {
        $gabinetes = Gabinete::orderBy('nome')->get();

        return view('departamentos.create', compact('gabinetes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255', 'unique:departamentos,nome'],
            'sigla' => ['nullable', 'string', 'max:10'],
            'gabinete_id' => ['required', 'integer', 'exists:gabinetes,id'],
        ]);

        Departamento::create($data);

        return redirect()->route('departamentos.index')->with('success', 'Departamento criado com sucesso.');
    }

    public function edit(Departamento $departamento)
    {
        // Carregar usuários do departamento e o chefe atual, se existir
        $userRole = Role::where('name', 'user')->first();
        $chefeRole = Role::where('name', 'chefe-departamento')->first();
        $usuariosQuery = User::where('departamento_id', $departamento->id)
            ->with('role')
            ->orderBy('name');
        if ($userRole && $chefeRole) {
            $usuariosQuery->whereIn('role_id', [$userRole->id, $chefeRole->id]);
        } elseif ($userRole) {
            $usuariosQuery->where('role_id', $userRole->id);
        } elseif ($chefeRole) {
            $usuariosQuery->where('role_id', $chefeRole->id);
        }
        $usuarios = $usuariosQuery->get();

        $chefeAtual = null;
        if ($chefeRole) {
            $chefeAtual = User::where('departamento_id', $departamento->id)
                ->where('role_id', $chefeRole->id)
                ->first();
        }

        $gabinetes = Gabinete::orderBy('nome')->get();

        return view('departamentos.edit', compact('departamento', 'usuarios', 'chefeAtual', 'gabinetes'));
    }

    public function update(Request $request, Departamento $departamento)
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255', Rule::unique('departamentos', 'nome')->ignore($departamento->id)],
            'sigla' => ['nullable', 'string', 'max:10'],
            'chefe_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'gabinete_id' => ['required', 'integer', 'exists:gabinetes,id'],
        ]);

        $departamento->update($data);

        // Atribuir chefe de departamento, caso informado
        if ($request->filled('chefe_user_id')) {
            $chefeRole = Role::where('name', 'chefe-departamento')->first();
            $userRole = Role::where('name', 'user')->first();

            if ($chefeRole) {
                // Garantir unicidade: remover chefe atual do departamento
                $chefeAtual = User::where('departamento_id', $departamento->id)
                    ->where('role_id', $chefeRole->id)
                    ->first();
                if ($chefeAtual && $chefeAtual->id != $request->chefe_user_id && $userRole) {
                    $chefeAtual->update(['role_id' => $userRole->id]);
                }

                // Promover usuário selecionado a chefe-departamento
                $novoChefe = User::where('id', $request->chefe_user_id)
                    ->where('departamento_id', $departamento->id)
                    ->first();

                if ($novoChefe) {
                    $novoChefe->update(['role_id' => $chefeRole->id]);
                }
            }
        } else {
            // Se nenhum chefe foi selecionado, remover qualquer chefe atual
            $chefeRole = Role::where('name', 'chefe-departamento')->first();
            $userRole = Role::where('name', 'user')->first();
            if ($chefeRole && $userRole) {
                User::where('departamento_id', $departamento->id)
                    ->where('role_id', $chefeRole->id)
                    ->update(['role_id' => $userRole->id]);
            }
        }

        return redirect()->route('departamentos.index')->with('success', 'Departamento atualizado com sucesso. Chefe de departamento ajustado, se aplicável.');
    }

    public function destroy(Departamento $departamento)
    {
        if ($departamento->usuarios()->exists()) {
            return redirect()->route('departamentos.index')->with('error', 'Não é possível excluir: existem usuários associados.');
        }

        $departamento->delete();

        return redirect()->route('departamentos.index')->with('success', 'Departamento excluído com sucesso.');
    }
}
