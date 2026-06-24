<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Cria a permissão de uso do Assistente de IA e concede aos cargos existentes.
     * Nota: o controlo de acesso real é por documento (RAG com permissões); esta
     * permissão apenas liga/desliga o recurso por cargo via a Matriz de Acesso.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'assistente.usar',
            'guard_name' => 'web',
        ]);

        Role::all()->each(function (Role $role) use ($permission) {
            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        });
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::where('name', 'assistente.usar')->where('guard_name', 'web')->first();
        if ($permission) {
            $permission->delete();
        }
    }
};
