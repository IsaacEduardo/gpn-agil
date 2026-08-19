<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Migra a autorização legada para Spatie:
 * 1. users.role_id -> model_has_roles (o papel Spatie tem o mesmo nome do legado);
 * 2. pivot legado permission_role -> role_has_permissions, traduzindo nomes de
 *    permissões antigos para os nomes canónicos verificados pelas Policies
 *    (mantendo também o nome original, para verificações diretas).
 *
 * As tabelas roles/permissions já são partilhadas com o Spatie desde
 * 2026_01_20_175500_adapt_roles_permissions_to_spatie (guard_name = 'web').
 */
return new class extends Migration
{
    /**
     * Nome legado => nome canónico verificado pelas Policies.
     */
    private const LEGACY_TO_CANONICAL = [
        'aprovar_reservas' => 'reservas.aprovar',
        'aprovar_requisicoes' => 'requisicoes.aprovar',
        'requisicoes.view_any' => 'requisicoes.listar_todas',
        'requisicoes.view' => 'requisicoes.listar',
        'reservas.view' => 'reservas.listar',
        'reservas.view_any' => 'reservas.listar',
        'encaminhar_documentos_entrada' => 'documentos_entrada.encaminhar',
        'listar_documentos_entrada' => 'documentos_entrada.listar',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        // 1. Espelhar users.role_id em model_has_roles
        $users = DB::table('users')->whereNotNull('role_id')->get(['id', 'role_id']);
        foreach ($users as $user) {
            $exists = DB::table('model_has_roles')
                ->where('role_id', $user->role_id)
                ->where('model_type', User::class)
                ->where('model_id', $user->id)
                ->exists();

            if (! $exists) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $user->role_id,
                    'model_type' => User::class,
                    'model_id' => $user->id,
                ]);
            }
        }

        // 2. Espelhar permission_role em role_has_permissions
        if (Schema::hasTable('permission_role') && Schema::hasTable('role_has_permissions')) {
            $legacyGrants = DB::table('permission_role')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->get(['permission_role.role_id', 'permissions.id as permission_id', 'permissions.name']);

            foreach ($legacyGrants as $grant) {
                $permissionIds = [$grant->permission_id];

                // Conceder também o nome canónico, se diferente
                $canonical = self::LEGACY_TO_CANONICAL[$grant->name] ?? null;
                if ($canonical && $canonical !== $grant->name) {
                    $canonicalId = DB::table('permissions')
                        ->where('name', $canonical)
                        ->where('guard_name', 'web')
                        ->value('id');

                    if (! $canonicalId) {
                        $canonicalId = DB::table('permissions')->insertGetId([
                            'name' => $canonical,
                            'guard_name' => 'web',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    $permissionIds[] = $canonicalId;
                }

                foreach ($permissionIds as $pid) {
                    $exists = DB::table('role_has_permissions')
                        ->where('permission_id', $pid)
                        ->where('role_id', $grant->role_id)
                        ->exists();

                    if (! $exists) {
                        DB::table('role_has_permissions')->insert([
                            'permission_id' => $pid,
                            'role_id' => $grant->role_id,
                        ]);
                    }
                }
            }
        }

        // Limpar a cache de permissões do Spatie
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Migração de dados aditiva e idempotente — sem reversão automática.
    }
};
