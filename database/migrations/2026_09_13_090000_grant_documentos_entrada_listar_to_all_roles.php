<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * A policy DocumentoEntradaPolicy::viewAny exige 'documentos_entrada.listar',
 * mas o index() nunca a invocava — a permissão era letra morta. Antes de a
 * passar a exigir, é preciso garantir que todos os perfis existentes a têm:
 * nos seeders só 'admin' e 'chefe-departamento' a recebiam, e 'user'/'tecnico'
 * (onde está o pessoal de balcão e expediente) ficavam de fora. Perfis criados
 * em runtime pelo ecrã de permissões também não a teriam.
 *
 * Concede a permissão a todos os perfis para que ninguém perca o acesso à
 * listagem. A partir daqui a permissão existe de facto e pode ser revogada por
 * perfil, deliberadamente, no ecrã de gestão de permissões.
 */
return new class extends Migration
{
    private const PERMISSION = 'documentos_entrada.listar';

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(
            ['name' => self::PERMISSION, 'guard_name' => 'web']
        );

        Role::where('guard_name', 'web')->get()->each(function (Role $role) use ($permission) {
            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Não revoga: não é possível distinguir os perfis que já tinham a
        // permissão antes desta migração dos que a receberam aqui, e revogar
        // às cegas trancaria utilizadores fora da listagem de entradas.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
