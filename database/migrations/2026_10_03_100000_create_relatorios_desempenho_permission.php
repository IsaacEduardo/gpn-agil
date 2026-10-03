<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Relatórios › Desempenho da equipa. Chefias veem a sua estrutura por
     * inerência (ver DesempenhoEquipaService::escopo); esta permissão alarga ao
     * próprio departamento quem não chefia nada. Concedida aos mesmos papéis
     * que relatorios.view; outros cargos recebem-na na Matriz de Acesso.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'relatorios.desempenho',
            'guard_name' => 'web',
        ]);

        foreach (['admin', 'chefe-departamento'] as $nome) {
            $papel = Role::where('name', $nome)->where('guard_name', 'web')->first();
            if ($papel && ! $papel->hasPermissionTo($permission)) {
                $papel->givePermissionTo($permission);
            }
        }
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', 'relatorios.desempenho')->where('guard_name', 'web')->delete();
    }
};
