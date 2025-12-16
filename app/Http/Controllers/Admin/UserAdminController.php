<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserAdminController extends Controller
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

    public function index(Request $request)
    {
        $this->ensureAdmin();

        $query = User::select(['id', 'name', 'email', 'role_id', 'departamento_id'])
            ->with([
                'role:id,name',
                'departamento:id,nome,gabinete_id',
                'departamento.gabinete:id,nome',
                'departamentos:id,nome,gabinete_id',
                'departamentos.gabinete:id,nome',
            ])
            ->orderBy('name');

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
        $this->ensureAdmin();

        $roles = Role::select('id', 'name')->orderBy('name')->get();
        $departamentos = Departamento::select('id', 'nome')->orderBy('nome')->get();

        return view('admin.users.create', compact('roles', 'departamentos'));
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

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

        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = bcrypt($data['password']);
        $user->role_id = $data['role_id'];
        $user->departamento_id = $data['departamento_id'] ?? null;
        $user->save();
        // Sincronizar departamentos múltiplos via pivot
        $user->departamentos()->sync($request->input('departamentos', []));
        if (! empty($user->departamento_id)) {
            $user->departamentos()->syncWithoutDetaching([$user->departamento_id]);
        }

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
        $this->ensureAdmin();

        $roles = Role::select('id', 'name')->orderBy('name')->get();
        $departamentos = Departamento::select('id', 'nome')->orderBy('nome')->get();

        // Gabinetes elegíveis: qualquer gabinete que contenha pelo menos um departamento do usuário
        $user->load('departamentos:id,nome');
        $depIds = collect([$user->departamento_id])
            ->merge($user->departamentos->pluck('id'))
            ->filter()
            ->unique()
            ->values();

        $gabinetesElegiveis = Gabinete::select('id', 'nome')
            ->whereHas('departamentos', function ($q) use ($depIds) {
                $q->whereIn('departamentos.id', $depIds);
            })
            ->orderBy('nome')
            ->get();

        $gabineteAtual = Gabinete::select('id', 'nome')
            ->where('responsavel_id', $user->id)
            ->first();

        return view('admin.users.edit', compact('user', 'roles', 'departamentos', 'gabinetesElegiveis', 'gabineteAtual'));
    }

    public function update(Request $request, User $user)
    {
        $this->ensureAdmin();

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

            // Recalcular departamentos do usuário já atualizados
            $user->load('departamentos');
            $depIds = collect([$user->departamento_id])
                ->merge($user->departamentos->pluck('id'))
                ->filter()
                ->unique()
                ->values();

            $elegivelNoGabinete = Gabinete::where('id', $gabId)
                ->whereHas('departamentos', function ($q) use ($depIds) {
                    $q->whereIn('departamentos.id', $depIds);
                })
                ->exists();

            if (! $elegivelNoGabinete) {
                return back()->withErrors(['gabinete_chefiado_id' => 'O usuário precisa participar de algum departamento deste Gabinete.'])->withInput();
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

        return redirect()->route('admin.users.index')->with('success', 'Usuário atualizado com sucesso.');
    }
}
