<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if (! $adminRoleId) {
            return; // no admin role; safely noop
        }

        $permNames = ['visto_departamento_requisicoes', 'visto_departamento_reservas'];
        $permIds = DB::table('permissions')->whereIn('name', $permNames)->pluck('id')->all();

        foreach ($permIds as $pid) {
            $exists = DB::table('permission_role')
                ->where('role_id', $adminRoleId)
                ->where('permission_id', $pid)
                ->exists();
            if (! $exists) {
                DB::table('permission_role')->insert([
                    'role_id' => $adminRoleId,
                    'permission_id' => $pid,
                ]);
            }
        }
    }

    public function down(): void
    {
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if (! $adminRoleId) {
            return;
        }
        $permNames = ['visto_departamento_requisicoes', 'visto_departamento_reservas'];
        $permIds = DB::table('permissions')->whereIn('name', $permNames)->pluck('id')->all();
        DB::table('permission_role')
            ->where('role_id', $adminRoleId)
            ->whereIn('permission_id', $permIds)
            ->delete();
    }
};
