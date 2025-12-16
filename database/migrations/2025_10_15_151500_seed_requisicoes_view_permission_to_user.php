<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create permission if not exist
        $perm = DB::table('permissions')->where('name', 'requisicoes.view')->first();
        if (! $perm) {
            DB::table('permissions')->insert([
                'name' => 'requisicoes.view',
                'description' => 'Pode visualizar suas requisições',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $perm = DB::table('permissions')->where('name', 'requisicoes.view')->first();
        }

        // Ensure role 'user' exists
        $role = DB::table('roles')->where('name', 'user')->first();
        if (! $role) {
            DB::table('roles')->insert([
                'name' => 'user',
                'description' => 'Usuário padrão com acesso básico',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $role = DB::table('roles')->where('name', 'user')->first();
        }

        // Attach permission to role 'user' if not yet attached
        if ($perm && $role) {
            $exists = DB::table('permission_role')
                ->where('permission_id', $perm->id)
                ->where('role_id', $role->id)
                ->exists();
            if (! $exists) {
                DB::table('permission_role')->insert([
                    'permission_id' => $perm->id,
                    'role_id' => $role->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        $perm = DB::table('permissions')->where('name', 'requisicoes.view')->first();
        $role = DB::table('roles')->where('name', 'user')->first();
        if ($perm && $role) {
            DB::table('permission_role')
                ->where('permission_id', $perm->id)
                ->where('role_id', $role->id)
                ->delete();
        }
        // Remove only the permission; keep 'user' role intact
        DB::table('permissions')->where('name', 'requisicoes.view')->delete();
    }
};
