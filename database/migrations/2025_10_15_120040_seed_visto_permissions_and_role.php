<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create permissions if not exist
        $permRequis = DB::table('permissions')->where('name', 'visto_departamento_requisicoes')->first();
        if (! $permRequis) {
            DB::table('permissions')->insert([
                'name' => 'visto_departamento_requisicoes',
                'description' => 'Pode dar visto de departamento em requisições',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permReserv = DB::table('permissions')->where('name', 'visto_departamento_reservas')->first();
        if (! $permReserv) {
            DB::table('permissions')->insert([
                'name' => 'visto_departamento_reservas',
                'description' => 'Pode dar visto de departamento em reservas de espaços',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Create role chefe-departamento if not exist
        $role = DB::table('roles')->where('name', 'chefe-departamento')->first();
        if (! $role) {
            DB::table('roles')->insert([
                'name' => 'chefe-departamento',
                'description' => 'Responsável por visto de departamento',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $role = DB::table('roles')->where('name', 'chefe-departamento')->first();
        }

        // Attach permissions to role
        $permIds = DB::table('permissions')
            ->whereIn('name', ['visto_departamento_requisicoes', 'visto_departamento_reservas'])
            ->pluck('id')
            ->all();

        foreach ($permIds as $pid) {
            $exists = DB::table('permission_role')
                ->where('permission_id', $pid)
                ->where('role_id', $role->id)
                ->exists();
            if (! $exists) {
                DB::table('permission_role')->insert([
                    'permission_id' => $pid,
                    'role_id' => $role->id,
                ]);
            }
        }
    }

    public function down(): void
    {
        $role = DB::table('roles')->where('name', 'chefe-departamento')->first();
        if ($role) {
            DB::table('permission_role')->where('role_id', $role->id)->delete();
            DB::table('roles')->where('id', $role->id)->delete();
        }

        DB::table('permissions')->whereIn('name', [
            'visto_departamento_requisicoes',
            'visto_departamento_reservas',
        ])->delete();
    }
};
