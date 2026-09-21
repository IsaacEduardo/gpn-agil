<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
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

        // Perfis do sistema: os papéis funcionais que o formulário de utilizadores
        // oferece, por oposição aos papéis criados a partir do nome de um
        // departamento (esses aparecem no separador "Departamentos").
        //
        // 'tecnico' e 'super-chefe-gabinete' faltavam nesta lista, pelo que caíam
        // no separador dos departamentos e davam a impressão de não terem
        // configuração possível — sendo o técnico o perfil que mais executa
        // trabalho no sistema.
        $systemRoleNames = [
            'admin',
            'super-chefe-gabinete',
            'chefe-gabinete',
            'chefe-departamento',
            'tecnico',
            'gestor-logistica',
            'user',
        ];

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
        $oldPermissions = $role->permissions()->pluck('name')->all();

        // Sincroniza as permissões (remove as que não estão no array e adiciona as que estão)
        $role->syncPermissions($request->permissions ?? []);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'rbac.role.sync_permissions',
            'auditable_type' => Role::class,
            'auditable_id' => $role->id,
            'old_values' => ['permissions' => $oldPermissions],
            'new_values' => ['permissions' => $request->permissions ?? []],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

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
            $action = 'rbac.role.grant_permission';
        } else {
            $role->revokePermissionTo($request->permission);
            $action = 'rbac.role.revoke_permission';
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => Role::class,
            'auditable_id' => $role->id,
            'old_values' => null,
            'new_values' => [
                'role' => $role->name,
                'permission' => $request->permission,
                'attached' => (bool) $request->attach,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Criação rápida de novas permissões (Opcional, mas útil).
     */
    public function storePermission(Request $request)
    {
        $this->ensureAdmin();

        $request->validate(['name' => 'required|unique:permissions,name']);
        $permission = Permission::create(['name' => $request->name, 'guard_name' => 'web']);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'rbac.permission.create',
            'auditable_type' => Permission::class,
            'auditable_id' => $permission->id,
            'old_values' => null,
            'new_values' => ['name' => $permission->name],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Permissão criada.');
    }

    /**
     * Criação rápida de novos perfis/departamentos (Roles).
     */
    public function storeRole(Request $request)
    {
        $this->ensureAdmin();

        $request->validate(['name' => 'required|unique:roles,name']);
        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'rbac.role.create',
            'auditable_type' => Role::class,
            'auditable_id' => $role->id,
            'old_values' => null,
            'new_values' => ['name' => $role->name],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Perfil criado.');
    }
}
