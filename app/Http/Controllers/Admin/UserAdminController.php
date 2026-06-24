<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UserAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    protected function ensurePermission()
    {
        $user = Auth::user();
        $isChefeGabinete = $user && $user->role && $user->role->name === 'chefe-gabinete';
        $isAdmin = $user && $user->role && $user->role->name === 'admin';

        if (! $isAdmin && ! $isChefeGabinete) {
            abort(403, 'Acesso restrito.');
        }
    }

    public function index(Request $request)
    {
        $this->ensurePermission();

        $query = User::select(['id', 'name', 'email', 'role_id', 'departamento_id'])
            ->with([
                'role:id,name',
                'departamento:id,nome,gabinete_id',
                'departamento.gabinete:id,nome',
                'departamentos:id,nome,gabinete_id',
                'departamentos.gabinete:id,nome',
            ])
            ->orderBy('name');

        // Filtro de escopo para Chefe de Gabinete
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->role && $currentUser->role->name === 'chefe-gabinete') {
            $gabineteChefiado = Gabinete::where('responsavel_id', $currentUser->id)->first();
            if ($gabineteChefiado) {
                // Usuários que pertencem a departamentos do gabinete
                $query->where(function ($q) use ($gabineteChefiado) {
                    $q->whereHas('departamento', function ($q2) use ($gabineteChefiado) {
                        $q2->where('gabinete_id', $gabineteChefiado->id);
                    })->orWhereHas('departamentos', function ($q3) use ($gabineteChefiado) {
                        $q3->where('gabinete_id', $gabineteChefiado->id);
                    });
                });
            } else {
                // Se não chefia nenhum gabinete, não vê usuários
                $query->where('id', -1);
            }
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%$q%")
                    ->orWhere('email', 'like', "%$q%");
            });
        }
        if ($request->filled('departamento_id')) {
            $depId = (int) $request->input('departamento_id');
            $query->where(function ($q) use ($depId) {
                $q->where('departamento_id', $depId)
                    ->orWhereHas('departamentos', function ($qq) use ($depId) {
                        $qq->where('departamentos.id', $depId);
                    });
            });
        }
        if ($request->filled('role_id')) {
            $query->where('role_id', $request->input('role_id'));
        }
        if ($request->filled('gabinete_id')) {
            $gabId = (int) $request->input('gabinete_id');
            $query->where(function ($q) use ($gabId) {
                $q->whereHas('departamento', function ($qq) use ($gabId) {
                    $qq->where('gabinete_id', $gabId);
                })->orWhereHas('departamentos', function ($qq) use ($gabId) {
                    $qq->where('gabinete_id', $gabId);
                });
            });
            $depOrderSql = "COALESCE((select d.nome from departamentos d where d.id = users.departamento_id and d.gabinete_id = ? limit 1),(select d2.nome from departamentos d2 join departamento_user du2 on du2.departamento_id=d2.id where du2.user_id=users.id and d2.gabinete_id=? limit 1),'')";
            $query->orderByRaw($depOrderSql.' asc', [$gabId, $gabId])->orderBy('name');
        }

        $perPage = (int) ($request->input('per_page', 15));
        if ($perPage < 5) {
            $perPage = 5;
        }
        if ($perPage > 100) {
            $perPage = 100;
        }
        $users = $query->paginate($perPage)->appends($request->query());
        $roles = Role::select('id', 'name')->orderBy('name')->get();
        $departamentos = Departamento::select('id', 'nome')->orderBy('nome')->get();
        $gabinetes = Gabinete::select('id', 'nome')->orderBy('nome')->get();

        if ($request->ajax() || $request->wantsJson()) {
            $items = collect($users->items())->map(function (User $u) {
                $deps = ($u->departamentos && $u->departamentos->count() > 0) ? $u->departamentos : collect([$u->departamento])->filter();
                $depNames = $deps->pluck('nome')->filter()->values();
                $gabs = $deps->map(fn ($d) => optional($d->gabinete)->nome)->filter()->unique()->values();

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => optional($u->role)->name,
                    'departamentos' => $depNames,
                    'gabinetes' => $gabs,
                ];
            });

            return response()->json([
                'items' => $items,
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ]);
        }

        return view('admin.users.index', compact('users', 'roles', 'departamentos', 'gabinetes'));
    }

    public function create()
    {
        $this->ensurePermission();

        $roles = Role::select('id', 'name')->orderBy('name')->get();
        $departamentosQuery = Departamento::select('id', 'nome')->orderBy('nome');

        // Se for Chefe de Gabinete, só vê departamentos do seu gabinete
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->role && $currentUser->role->name === 'chefe-gabinete') {
            $gabineteChefiado = Gabinete::where('responsavel_id', $currentUser->id)->first();
            if ($gabineteChefiado) {
                $departamentosQuery->where('gabinete_id', $gabineteChefiado->id);
            } else {
                $departamentosQuery->where('id', -1);
            }
        }

        $departamentos = $departamentosQuery->get();

        return view('admin.users.create', compact('roles', 'departamentos'));
    }

    public function store(Request $request)
    {
        $this->ensurePermission();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'departamento_id' => ['nullable', 'exists:departamentos,id'],
            'departamentos' => ['nullable', 'array'],
            'departamentos.*' => ['integer', 'exists:departamentos,id'],
        ]);

        // Se for chefe-departamento, exigir departamento explícito
        $chefeRoleId = Role::where('name', 'chefe-departamento')->value('id');
        if ($chefeRoleId && (int) ($data['role_id']) === (int) $chefeRoleId && empty($data['departamento_id'])) {
            return back()->withErrors(['departamento_id' => 'Selecione o departamento para o chefe.'])->withInput();
        }

        // Validação de escopo para Chefe de Gabinete na criação
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->role && $currentUser->role->name === 'chefe-gabinete') {
            $gabineteChefiado = Gabinete::where('responsavel_id', $currentUser->id)->first();

            // Verificar departamento principal
            if (! empty($data['departamento_id'])) {
                $dep = Departamento::find($data['departamento_id']);
                if (! $gabineteChefiado || ! $dep || $dep->gabinete_id !== $gabineteChefiado->id) {
                    abort(403, 'Você só pode adicionar usuários aos departamentos do seu gabinete.');
                }
            }

            // Verificar departamentos múltiplos
            $depsExtras = $request->input('departamentos', []);
            if (! empty($depsExtras)) {
                $countInvalid = Departamento::whereIn('id', $depsExtras)
                    ->where('gabinete_id', '!=', $gabineteChefiado ? $gabineteChefiado->id : -1)
                    ->count();
                if ($countInvalid > 0) {
                    abort(403, 'Você selecionou departamentos que não pertencem ao seu gabinete.');
                }
            }

            // Não permitir atribuir papel de admin ou chefe de gabinete
            $role = Role::find($data['role_id']);
            if ($role && in_array($role->name, ['admin', 'chefe-gabinete'])) {
                abort(403, 'Você não tem permissão para atribuir este papel.');
            }
        }

        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = bcrypt($data['password']);
        $user->role_id = $data['role_id'];
        $user->departamento_id = $data['departamento_id'] ?? null;
        $user->save();

        // Sincronizar Role do Spatie
        $role = Role::find($data['role_id']);
        if ($role) {
            // Remove roles anteriores para evitar acúmulo de lixo
            $user->syncRoles([$role->name]);

            // SE for um usuário comum ('user') e tiver departamento, atribui TAMBÉM a role do departamento
            if ($role->name === 'user' && ! empty($user->departamento_id)) {
                $dep = Departamento::find($user->departamento_id);
                if ($dep) {
                    // Tenta achar a role do departamento (pelo nome)
                    $depRole = Role::where('name', $dep->nome)->where('guard_name', 'web')->first();
                    if ($depRole) {
                        $user->assignRole($depRole);
                    }
                }
            }
        }

        // Sincronizar departamentos múltiplos via pivot
        $user->departamentos()->sync($request->input('departamentos', []));
        if (! empty($user->departamento_id)) {
            $user->departamentos()->syncWithoutDetaching([$user->departamento_id]);
        }

        // Limpar cache de permissões do utilizador
        Cache::forget("user_{$user->id}_departments");
        Cache::forget("user_{$user->id}_responsible_gabinetes");

        // Garantir unicidade do chefe no departamento ao criar
        if ($chefeRoleId && (int) $user->role_id === (int) $chefeRoleId && ! empty($user->departamento_id)) {
            $userRoleId = Role::where('name', 'user')->value('id');
            $chefeAtual = User::where('departamento_id', $user->departamento_id)
                ->where('role_id', $chefeRoleId)
                ->where('id', '!=', $user->id)
                ->first();
            if ($chefeAtual && $userRoleId) {
                $chefeAtual->update(['role_id' => $userRoleId]);
            }
        }

        return redirect()->route('admin.users.index')->with('success', 'Usuário criado com sucesso.');
    }

    public function edit(User $user)
    {
        $this->ensurePermission();

        $user->load('departamentos');

        // Verificar se chefe de gabinete tem acesso a este usuário
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->role && $currentUser->role->name === 'chefe-gabinete') {
            $gabineteChefiado = Gabinete::where('responsavel_id', $currentUser->id)->first();
            $acessoPermitido = false;

            if ($gabineteChefiado) {
                // Verifica se usuário pertence a algum departamento do gabinete
                $pertenceGabinete = false;

                if ($user->departamento && $user->departamento->gabinete_id === $gabineteChefiado->id) {
                    $pertenceGabinete = true;
                } else {
                    $temDepExtra = $user->departamentos()
                        ->where('gabinete_id', $gabineteChefiado->id)
                        ->exists();
                    if ($temDepExtra) {
                        $pertenceGabinete = true;
                    }
                }

                if ($pertenceGabinete) {
                    $acessoPermitido = true;
                }
            }

            if (! $acessoPermitido) {
                abort(403, 'Você não tem permissão para editar este usuário.');
            }
        }

        $roles = Role::select('id', 'name')->orderBy('name')->get();

        $departamentosQuery = Departamento::select('id', 'nome')->orderBy('nome');

        // Filtrar departamentos na edição também
        if ($currentUser && $currentUser->role && $currentUser->role->name === 'chefe-gabinete') {
            $gabineteChefiado = Gabinete::where('responsavel_id', $currentUser->id)->first();
            if ($gabineteChefiado) {
                $departamentosQuery->where('gabinete_id', $gabineteChefiado->id);
            } else {
                $departamentosQuery->where('id', -1);
            }
        }
        $departamentos = $departamentosQuery->get();

        // Buscar todos os gabinetes disponíveis (sem restrições de departamento)
        $gabinetesElegiveis = Gabinete::select('id', 'nome')
            ->orderBy('nome')
            ->get();

        $gabineteAtual = Gabinete::select('id', 'nome')
            ->where('responsavel_id', $user->id)
            ->first();

        return view('admin.users.edit', compact('user', 'roles', 'departamentos', 'gabinetesElegiveis', 'gabineteAtual'));
    }

    public function update(Request $request, User $user)
    {
        $this->ensurePermission();

        // Verificar permissão de edição (repetir lógica do edit)
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->role && $currentUser->role->name === 'chefe-gabinete') {
            $gabineteChefiado = Gabinete::where('responsavel_id', $currentUser->id)->first();

            // 1. Verificar se pode editar este usuário
            $acessoPermitido = false;
            if ($gabineteChefiado) {
                if (($user->departamento && $user->departamento->gabinete_id === $gabineteChefiado->id) ||
                    $user->departamentos()->where('gabinete_id', $gabineteChefiado->id)->exists()
                ) {
                    $acessoPermitido = true;
                }
            }
            if (! $acessoPermitido) {
                abort(403, 'Você não tem permissão para editar este usuário.');
            }

            // 2. Verificar departamentos destino
            if ($request->filled('departamento_id')) {
                $dep = Departamento::find($request->input('departamento_id'));
                if (! $dep || $dep->gabinete_id !== $gabineteChefiado->id) {
                    abort(403, 'Você só pode mover usuários para departamentos do seu gabinete.');
                }
            }

            $depsExtras = $request->input('departamentos', []);
            if (! empty($depsExtras)) {
                $countInvalid = Departamento::whereIn('id', $depsExtras)
                    ->where('gabinete_id', '!=', $gabineteChefiado->id)
                    ->count();
                if ($countInvalid > 0) {
                    abort(403, 'Departamentos extras inválidos selecionados.');
                }
            }

            // 3. Verificar papel proibido
            $roleId = $request->input('role_id');
            if ($roleId) {
                $role = Role::find($roleId);
                if ($role && in_array($role->name, ['admin', 'chefe-gabinete']) && (int) $user->role_id !== (int) $role->id) {
                    abort(403, 'Você não tem permissão para promover usuários a este papel.');
                }
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', "unique:users,email,{$user->id}"],
            'password' => ['nullable', 'string', 'min:8'],
            'role_id' => ['required', 'exists:roles,id'],
            'departamento_id' => ['nullable', 'exists:departamentos,id'],
            'departamentos' => ['nullable', 'array'],
            'departamentos.*' => ['integer', 'exists:departamentos,id'],
            'gabinete_chefiado_id' => ['nullable', 'exists:gabinetes,id'],
        ]);

        // Se for chefe-departamento, exigir departamento explícito
        $chefeDepRoleId = Role::where('name', 'chefe-departamento')->value('id');
        if ($chefeDepRoleId && (int) ($data['role_id']) === (int) $chefeDepRoleId && empty($data['departamento_id'])) {
            return back()->withErrors(['departamento_id' => 'Selecione o departamento para o chefe.'])->withInput();
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        if (! empty($data['password'])) {
            $user->password = bcrypt($data['password']);
        }
        $user->role_id = $data['role_id'];
        $user->departamento_id = $data['departamento_id'] ?? null;
        $user->save();

        // Sincronizar Role do Spatie
        $role = Role::find($data['role_id']);
        if ($role) {
            // Remove roles anteriores para evitar acúmulo de lixo
            $user->syncRoles([$role->name]);

            // SE for um usuário comum ('user') e tiver departamento, atribui TAMBÉM a role do departamento
            if ($role->name === 'user' && ! empty($user->departamento_id)) {
                $dep = Departamento::find($user->departamento_id);
                if ($dep) {
                    // Tenta achar a role do departamento (pelo nome)
                    $depRole = Role::where('name', $dep->nome)->where('guard_name', 'web')->first();
                    if ($depRole) {
                        $user->assignRole($depRole);
                    }
                }
            }
        }

        // Sincronizar departamentos múltiplos via pivot
        $user->departamentos()->sync($request->input('departamentos', []));
        if (! empty($user->departamento_id)) {
            $user->departamentos()->syncWithoutDetaching([$user->departamento_id]);
        }

        // Garantir unicidade do chefe no departamento ao atualizar
        if ($chefeDepRoleId && (int) $user->role_id === (int) $chefeDepRoleId && ! empty($user->departamento_id)) {
            $userRoleId = Role::where('name', 'user')->value('id');
            $chefeAtual = User::where('departamento_id', $user->departamento_id)
                ->where('role_id', $chefeDepRoleId)
                ->where('id', '!=', $user->id)
                ->first();
            if ($chefeAtual && $userRoleId) {
                $chefeAtual->update(['role_id' => $userRoleId]);
            }
        }

        // Chefia de Gabinete pelo usuário
        $chefeGabRoleId = Role::firstOrCreate(['name' => 'chefe-gabinete'])->id;
        $userRoleId = Role::where('name', 'user')->value('id');

        if ((int) $user->role_id === (int) $chefeGabRoleId) {
            $gabId = $request->input('gabinete_chefiado_id');
            if (empty($gabId)) {
                return back()->withErrors(['gabinete_chefiado_id' => 'Selecione o Gabinete chefiado.'])->withInput();
            }

            $gabinete = Gabinete::findOrFail($gabId);
            $prevResponsavelId = $gabinete->responsavel_id;
            $gabinete->responsavel_id = $user->id;
            $gabinete->save();

            // Demover chefe anterior se era chefe-gabinete
            if ($prevResponsavelId && (int) $prevResponsavelId !== (int) $user->id) {
                $prev = User::find($prevResponsavelId);
                if ($prev && $prev->role && $prev->role->name === 'chefe-gabinete' && $userRoleId) {
                    $prev->update(['role_id' => $userRoleId]);
                }
            }

            // Garantir exclusividade: não pode chefiar mais de um gabinete
            Gabinete::where('responsavel_id', $user->id)
                ->where('id', '!=', $gabinete->id)
                ->update(['responsavel_id' => null]);
        } else {
            // Se trocou o papel para algo diferente de chefe-gabinete, remover chefia existente
            Gabinete::where('responsavel_id', $user->id)->update(['responsavel_id' => null]);
        }

        // Limpar cache de permissões do utilizador
        Cache::forget("user_{$user->id}_departments");
        Cache::forget("user_{$user->id}_responsible_gabinetes");

        return redirect()->route('admin.users.index')->with('success', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $user)
    {
        $this->ensurePermission();

        // Verificar permissão para Chefe de Gabinete
        $currentUser = Auth::user();
        if ($currentUser && $currentUser->role && $currentUser->role->name === 'chefe-gabinete') {
            $gabineteChefiado = Gabinete::where('responsavel_id', $currentUser->id)->first();
            $acessoPermitido = false;

            if ($gabineteChefiado) {
                if (($user->departamento && $user->departamento->gabinete_id === $gabineteChefiado->id) ||
                    $user->departamentos()->where('gabinete_id', $gabineteChefiado->id)->exists()
                ) {
                    $acessoPermitido = true;
                }
            }

            if (! $acessoPermitido) {
                abort(403, 'Você não tem permissão para excluir este usuário.');
            }

            // Não permitir excluir admins ou outros chefes de gabinete
            if ($user->role && in_array($user->role->name, ['admin', 'chefe-gabinete'])) {
                abort(403, 'Você não pode excluir usuários com este nível de acesso.');
            }
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Usuário excluído com sucesso.');
    }
}
