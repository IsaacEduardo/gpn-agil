<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Garante que apenas administradores possam gerir papéis e permissões.
     *
     * A gestão de RBAC é uma operação privilegiada: sem esta verificação,
     * qualquer utilizador autenticado poderia conceder a si próprio qualquer
     * permissão (escalonamento de privilégios).
     */
    protected function ensureAdmin(): void
    {
        $user = Auth::user();
        $isAdmin = $user && ($user->isAdmin() || ($user->role && $user->role->name === 'admin'));

        if (! $isAdmin) {
            abort(403, 'Acesso restrito a administradores.');
        }
    }

    /**
     * Exibe a matriz de permissões.
     */
    public function index()
    {
        $this->ensureAdmin();

        $allRoles = Role::orderBy('name')->get();

        $systemRoleNames = ['admin', 'user', 'chefe-departamento', 'chefe-gabinete', 'gestor-logistica'];

        $systemRoles = $allRoles->filter(function ($role) use ($systemRoleNames) {
            return in_array($role->name, $systemRoleNames);
        })->sortBy(function ($role) use ($systemRoleNames) {
            return array_search($role->name, $systemRoleNames);
        });

        $departmentRoles = $allRoles->reject(function ($role) use ($systemRoleNames) {
            return in_array($role->name, $systemRoleNames);
        });

        $permissions = Permission::all()->groupBy(function ($perm) {
            $parts = explode('.', $perm->name);

            return count($parts) > 1 ? ucfirst(str_replace('_', ' ', $parts[0])) : 'Geral';
        });

        return view('configuracoes.permissoes.index', compact('systemRoles', 'departmentRoles', 'permissions'));
    }

    /**
     * Atualiza as permissões de um papel (Role/Departamento).
     */
    public function update(Request $request)
    {
        $this->ensureAdmin();

        // Validação básica
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::findById($request->role_id);

        // Sincroniza as permissões (remove as que não estão no array e adiciona as que estão)
        $role->syncPermissions($request->permissions ?? []);

        return back()->with('success', "Permissões do perfil '{$role->name}' atualizadas com sucesso!");
    }

    /**
     * Alterna uma permissão específica via AJAX.
     */
    public function toggle(Request $request)
    {
        $this->ensureAdmin();

        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'permission' => 'required|exists:permissions,name',
            'attach' => 'required|boolean',
        ]);

        $role = Role::findById($request->role_id);

        if ($request->attach) {
            $role->givePermissionTo($request->permission);
        } else {
            $role->revokePermissionTo($request->permission);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Criação rápida de novas permissões (Opcional, mas útil).
     */
    public function storePermission(Request $request)
    {
        $this->ensureAdmin();

        $request->validate(['name' => 'required|unique:permissions,name']);
        Permission::create(['name' => $request->name, 'guard_name' => 'web']);

        return back()->with('success', 'Permissão criada.');
    }

    /**
     * Criação rápida de novos perfis/departamentos (Roles).
     */
    public function storeRole(Request $request)
    {
        $this->ensureAdmin();

        $request->validate(['name' => 'required|unique:roles,name']);
        Role::create(['name' => $request->name, 'guard_name' => 'web']);

        return back()->with('success', 'Perfil criado.');
    }
}
