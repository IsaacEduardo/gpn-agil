<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'lotes.view',
            'lotes.create',
            'lotes.edit',
            'lotes.delete',
            'requerentes.view',
            'requerentes.create',
            'requerentes.edit',
            'requerentes.delete',
            'solicitacoes_lotes.view',
            'solicitacoes_lotes.create',
            'solicitacoes_lotes.analisar',
            'solicitacoes_lotes.homologar',
        ];

        foreach ($permissions as $permName) {
            Permission::findOrCreate($permName, 'web');
        }

        // Criar ou atualizar papéis
        $adminRole = Role::findOrCreate('Admin', 'web');
        $adminRole->givePermissionTo($permissions);

        $tecnicoRole = Role::findOrCreate('Técnico de Terrenos', 'web');
        $tecnicoRole->givePermissionTo([
            'lotes.view',
            'lotes.create',
            'lotes.edit',
            'requerentes.view',
            'requerentes.create',
            'solicitacoes_lotes.view',
            'solicitacoes_lotes.analisar',
        ]);

        $analistaRole = Role::findOrCreate('Analista de Infraestrutura', 'web');
        $analistaRole->givePermissionTo([
            'lotes.view',
            'requerentes.view',
            'solicitacoes_lotes.view',
            'solicitacoes_lotes.analisar',
            'solicitacoes_lotes.homologar',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Silenciosamente reverter caso necessário
    }
};
