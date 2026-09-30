<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Gestão de empresas passa a exigir permissão própria (antes bastava a
     * sessão). Concedida ao admin; outros cargos recebem-na na Matriz de Acesso.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'empresas.gerir',
            'guard_name' => 'web',
        ]);

        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($admin && ! $admin->hasPermissionTo($permission)) {
            $admin->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', 'empresas.gerir')->where('guard_name', 'web')->delete();
    }
};
