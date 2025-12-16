<?php

namespace App\Http\Controllers;

use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class GabineteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    protected function ensureAdmin()
    {
        $user = Auth::user();
        if (! $user || ! $user->role || $user->role->name !== 'admin') {
            abort(403, 'Acesso restrito ao administrador.');
        }
    }

    public function index()
    {
        $this->ensureAdmin();
        $gabinetes = Gabinete::with('responsavel')->orderBy('nome')->paginate(15);

        return view('gabinetes.index', compact('gabinetes'));
    }

    public function create()
    {
        $this->ensureAdmin();
        $userRole = Role::where('name', 'user')->first();
        $chefeDepRole = Role::where('name', 'chefe-departamento')->first();
        $chefeGabRole = Role::firstOrCreate(['name' => 'chefe-gabinete']);
        $query = User::with('role')->orderBy('name');
        $roleIds = collect([$userRole?->id, $chefeDepRole?->id, $chefeGabRole?->id])->filter()->values();
        if ($roleIds->isNotEmpty()) {
            $query->whereIn('role_id', $roleIds);
        }
        $usuarios = $query->get();

        return view('gabinetes.create', compact('usuarios'));
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255', 'unique:gabinetes,nome'],
            'sigla' => ['nullable', 'string', 'max:10'],
            'responsavel_id' => ['nullable', 'exists:users,id'],
        ]);

        $gabinete = Gabinete::create($data);

        // Promover responsável, se informado
        if (! empty($data['responsavel_id'])) {
            $chefeGabRole = Role::firstOrCreate(['name' => 'chefe-gabinete']);
            $responsavel = User::find($data['responsavel_id']);
            if ($responsavel && (! $responsavel->role || $responsavel->role->name !== 'admin')) {
                $responsavel->update(['role_id' => $chefeGabRole->id]);
            }
        }

        return redirect()->route('gabinetes.index')->with('success', 'Gabinete criado com sucesso.');
    }

    public function edit(Gabinete $gabinete)
    {
        $this->ensureAdmin();

        $userRole = Role::where('name', 'user')->first();
        $chefeDepRole = Role::where('name', 'chefe-departamento')->first();
        $chefeGabRole = Role::firstOrCreate(['name' => 'chefe-gabinete']);
        $roleIds = collect([$userRole?->id, $chefeDepRole?->id, $chefeGabRole?->id])->filter()->values();

        // Usuários elegíveis: pertencem a departamentos deste gabinete (via FK direta ou pivot)
        $usuarios = User::with('role')
            ->whereIn('role_id', $roleIds)
            ->where(function ($q) use ($gabinete) {
                $q->whereHas('departamento', function ($q2) use ($gabinete) {
                    $q2->where('gabinete_id', $gabinete->id);
                })->orWhereHas('departamentos', function ($q3) use ($gabinete) {
                    $q3->where('gabinete_id', $gabinete->id);
                });
            })
            ->orWhere('id', $gabinete->responsavel_id) // manter atual na lista
            ->orderBy('name')
            ->get();

        return view('gabinetes.edit', compact('gabinete', 'usuarios'));
    }

    public function update(Request $request, Gabinete $gabinete)
    {
        $this->ensureAdmin();

        $userRole = Role::where('name', 'user')->first();
        $chefeDepRole = Role::where('name', 'chefe-departamento')->first();
        $chefeGabRole = Role::firstOrCreate(['name' => 'chefe-gabinete']);
        $roleIds = collect([$userRole?->id, $chefeDepRole?->id, $chefeGabRole?->id])->filter()->values();

        $usuariosElegiveis = User::whereIn('role_id', $roleIds)
            ->where(function ($q) use ($gabinete) {
                $q->whereHas('departamento', function ($q2) use ($gabinete) {
                    $q2->where('gabinete_id', $gabinete->id);
                })->orWhereHas('departamentos', function ($q3) use ($gabinete) {
                    $q3->where('gabinete_id', $gabinete->id);
                });
            })
            ->pluck('id')
            ->toArray();

        $hasDepartamentos = $gabinete->departamentos()->exists();
        $prevResponsavelId = $gabinete->responsavel_id;

        $rules = [
            'nome' => ['required', 'string', 'max:255', "unique:gabinetes,nome,{$gabinete->id}"],
            'sigla' => ['nullable', 'string', 'max:10'],
        ];

        if ($hasDepartamentos) {
            $rules['responsavel_id'] = ['required', 'exists:users,id', Rule::in($usuariosElegiveis)];
        } else {
            $rules['responsavel_id'] = ['nullable', 'exists:users,id'];
        }

        $data = $request->validate($rules);

        $gabinete->update($data);

        // Gerenciar promoção/demissão de chefe de gabinete
        $newResponsavelId = $gabinete->responsavel_id;
        if ($newResponsavelId) {
            $responsavel = User::find($newResponsavelId);
            if ($responsavel && (! $responsavel->role || $responsavel->role->name !== 'admin')) {
                $responsavel->update(['role_id' => $chefeGabRole->id]);
            }
        }
        if ($prevResponsavelId && $prevResponsavelId !== $newResponsavelId) {
            $prev = User::find($prevResponsavelId);
            if ($prev && $prev->role && $prev->role->name === 'chefe-gabinete') {
                $userRoleModel = Role::where('name', 'user')->first();
                if ($userRoleModel) {
                    $prev->update(['role_id' => $userRoleModel->id]);
                }
            }
        }
        if (! $newResponsavelId && $prevResponsavelId) {
            $prev = User::find($prevResponsavelId);
            if ($prev && $prev->role && $prev->role->name === 'chefe-gabinete') {
                $userRoleModel = Role::where('name', 'user')->first();
                if ($userRoleModel) {
                    $prev->update(['role_id' => $userRoleModel->id]);
                }
            }
        }

        return redirect()->route('gabinetes.index')->with('success', 'Gabinete atualizado com sucesso.');
    }

    public function destroy(Gabinete $gabinete)
    {
        $this->ensureAdmin();

        // Impedir exclusão se houver departamentos vinculados
        if ($gabinete->departamentos()->exists()) {
            return redirect()->route('gabinetes.index')
                ->with('error', 'Não é possível excluir um Gabinete com Departamentos vinculados. Reatribua ou exclua os departamentos primeiro.');
        }

        $gabinete->delete();

        return redirect()->route('gabinetes.index')->with('success', 'Gabinete excluído com sucesso.');
    }
}
