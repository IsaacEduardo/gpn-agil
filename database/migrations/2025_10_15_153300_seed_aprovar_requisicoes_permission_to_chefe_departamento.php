<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure the 'aprovar_requisicoes' permission exists
        $permission = DB::table('permissions')->where('name', 'aprovar_requisicoes')->first();
        if (! $permission) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'aprovar_requisicoes',
                'description' => 'Pode aprovar e rejeitar requisições',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $permissionId = $permission->id;
        }

        // Attach permission to 'chefe-departamento' role
        $role = DB::table('roles')->where('name', 'chefe-departamento')->first();
        if ($role && $permissionId) {
            $exists = DB::table('permission_role')
                ->where('role_id', $role->id)
                ->where('permission_id', $permissionId)
                ->exists();

            if (! $exists) {
                DB::table('permission_role')->insert([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permission = DB::table('permissions')->where('name', 'aprovar_requisicoes')->first();
        $role = DB::table('roles')->where('name', 'chefe-departamento')->first();

        if ($permission && $role) {
            DB::table('permission_role')
                ->where('role_id', $role->id)
                ->where('permission_id', $permission->id)
                ->delete();
        }
        // Keep the permission record to avoid breaking other assignments
    }
};
