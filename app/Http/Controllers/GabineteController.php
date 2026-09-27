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

    /**
     * Regras do código de ofícios. O valor é normalizado antes (maiúsculas, sem pontos
     * nas pontas) e tem de ser único: cada código tem a sua própria sequência de ofícios.
     */
    protected function regrasCodigoOficios(Request $request, ?Gabinete $gabinete = null): array
    {
        if ($request->filled('codigo_oficios')) {
            $request->merge([
                'codigo_oficios' => mb_strtoupper(trim((string) $request->input('codigo_oficios'), " .\t\n\r\0\x0B")),
            ]);
        }

        return [
            'nullable', 'string', 'max:40',
            'regex:/^[A-Z0-9]+(\.[A-Z0-9]+)*$/',
            Rule::unique('gabinetes', 'codigo_oficios')->ignore($gabinete?->id),
        ];
    }

    protected function mensagensCodigoOficios(): array
    {
        return [
            'codigo_oficios.regex' => 'O código de ofícios só pode ter letras, números e pontos entre blocos (ex.: SEC.GOV.PROV.HLA).',
            'codigo_oficios.unique' => 'Este código de ofícios já está atribuído a outro gabinete.',
        ];
    }

    public function index()
    {
        $this->ensureAdmin();
        $gabinetes = Gabinete::with(['responsavel', 'superChefe'])->orderBy('nome')->paginate(15);

        return view('gabinetes.index', compact('gabinetes'));
    }

    public function create()
    {
        $this->ensureAdmin();
        $userRole = Role::where('name', 'user')->first();
        $chefeDepRole = Role::where('name', 'chefe-departamento')->first();
        $chefeGabRole = Role::firstOrCreate(['name' => 'chefe-gabinete']);
        $superChefeRole = Role::firstOrCreate(['name' => 'super-chefe-gabinete']);
        $query = User::with('role')->orderBy('name');
        $roleIds = collect([$userRole?->id, $chefeDepRole?->id, $chefeGabRole?->id, $superChefeRole?->id])->filter()->values();
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
            'codigo_oficios' => $this->regrasCodigoOficios($request),
            'responsavel_id' => ['nullable', 'exists:users,id'],
            'super_chefe_id' => ['nullable', 'exists:users,id'],
        ], $this->mensagensCodigoOficios());

        $gabinete = Gabinete::create($data);

        // Promover responsável, se informado
        if (! empty($data['responsavel_id'])) {
            $chefeGabRole = Role::firstOrCreate(['name' => 'chefe-gabinete']);
            $responsavel = User::find($data['responsavel_id']);
            if ($responsavel && (! $responsavel->role || $responsavel->role->name !== 'admin')) {
                $responsavel->update(['role_id' => $chefeGabRole->id]);
                $responsavel->syncRoles([$chefeGabRole->name]);
            }
        }

        // Promover super chefe, se informado
        if (! empty($data['super_chefe_id'])) {
            $superChefeRole = Role::firstOrCreate(['name' => 'super-chefe-gabinete']);
            $superChefe = User::find($data['super_chefe_id']);
            if ($superChefe && (! $superChefe->role || $superChefe->role->name !== 'admin')) {
                $superChefe->update(['role_id' => $superChefeRole->id]);
                $superChefe->syncRoles([$superChefeRole->name]);
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
        $superChefeRole = Role::firstOrCreate(['name' => 'super-chefe-gabinete']);
        $roleIds = collect([$userRole?->id, $chefeDepRole?->id, $chefeGabRole?->id, $superChefeRole?->id])->filter()->values();

        // Usuários elegíveis: qualquer usuário com papel apropriado
        $usuarios = User::with('role')
            ->whereIn('role_id', $roleIds)
            ->orWhere('id', $gabinete->responsavel_id) // manter atual na lista
            ->orWhere('id', $gabinete->super_chefe_id) // manter super chefe atual na lista
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
        $superChefeRole = Role::firstOrCreate(['name' => 'super-chefe-gabinete']);
        $roleIds = collect([$userRole?->id, $chefeDepRole?->id, $chefeGabRole?->id, $superChefeRole?->id])->filter()->values();

        $hasDepartamentos = $gabinete->departamentos()->exists();
        $prevResponsavelId = $gabinete->responsavel_id;
        $prevSuperChefeId = $gabinete->super_chefe_id;

        $rules = [
            'nome' => ['required', 'string', 'max:255', "unique:gabinetes,nome,{$gabinete->id}"],
            'sigla' => ['nullable', 'string', 'max:10'],
            'codigo_oficios' => $this->regrasCodigoOficios($request, $gabinete),
            'responsavel_id' => ['nullable', 'exists:users,id'],
            'super_chefe_id' => ['nullable', 'exists:users,id'],
        ];

        $data = $request->validate($rules, $this->mensagensCodigoOficios());

        $gabinete->update($data);

        // Gerenciar promoção/demissão de chefe de gabinete
        $newResponsavelId = $gabinete->responsavel_id;
        if ($newResponsavelId) {
            $responsavel = User::find($newResponsavelId);
            if ($responsavel && (! $responsavel->role || $responsavel->role->name !== 'admin')) {
                $responsavel->update(['role_id' => $chefeGabRole->id]);
                $responsavel->syncRoles([$chefeGabRole->name]);
            }
        }
        if ($prevResponsavelId && $prevResponsavelId !== $newResponsavelId) {
            $prev = User::find($prevResponsavelId);
            if ($prev && $prev->role && $prev->role->name === 'chefe-gabinete') {
                $isStillChief = Gabinete::where('responsavel_id', $prev->id)->exists();
                if (! $isStillChief) {
                    $userRoleModel = Role::where('name', 'user')->first();
                    if ($userRoleModel) {
                        $prev->update(['role_id' => $userRoleModel->id]);
                        $prev->syncRoles([$userRoleModel->name]);
                    }
                }
            }
        }
        if (! $newResponsavelId && $prevResponsavelId) {
            $prev = User::find($prevResponsavelId);
            if ($prev && $prev->role && $prev->role->name === 'chefe-gabinete') {
                $userRoleModel = Role::where('name', 'user')->first();
                if ($userRoleModel) {
                    $prev->update(['role_id' => $userRoleModel->id]);
                    $prev->syncRoles([$userRoleModel->name]);
                }
            }
        }

        // Gerenciar promoção/demissão de super chefe de gabinete
        $newSuperChefeId = $gabinete->super_chefe_id;
        if ($newSuperChefeId) {
            $superChefe = User::find($newSuperChefeId);
            if ($superChefe && (! $superChefe->role || $superChefe->role->name !== 'admin')) {
                $superChefe->update(['role_id' => $superChefeRole->id]);
                $superChefe->syncRoles([$superChefeRole->name]);
            }
        }
        if ($prevSuperChefeId && $prevSuperChefeId !== $newSuperChefeId) {
            $prev = User::find($prevSuperChefeId);
            if ($prev && $prev->role && $prev->role->name === 'super-chefe-gabinete') {
                $isStillSuper = Gabinete::where('super_chefe_id', $prev->id)->exists();
                if (! $isStillSuper) {
                    $userRoleModel = Role::where('name', 'user')->first();
                    if ($userRoleModel) {
                        $prev->update(['role_id' => $userRoleModel->id]);
                        $prev->syncRoles([$userRoleModel->name]);
                    }
                }
            }
        }
        if (! $newSuperChefeId && $prevSuperChefeId) {
            $prev = User::find($prevSuperChefeId);
            if ($prev && $prev->role && $prev->role->name === 'super-chefe-gabinete') {
                $userRoleModel = Role::where('name', 'user')->first();
                if ($userRoleModel) {
                    $prev->update(['role_id' => $userRoleModel->id]);
                    $prev->syncRoles([$userRoleModel->name]);
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
