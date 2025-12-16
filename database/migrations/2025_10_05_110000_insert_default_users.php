<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Primeiro, verificamos se já existem roles no sistema
        // Se não existirem, criamos os roles básicos
        if (DB::table('roles')->count() === 0) {
            DB::table('roles')->insert([
                [
                    'name' => 'admin',
                    'description' => 'Administrador do sistema com acesso total',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'user',
                    'description' => 'Usuário comum do sistema',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        // Obtém os IDs dos roles
        $adminRoleId = DB::table('roles')->where('name', 'admin')->first()->id;
        $userRoleId = DB::table('roles')->where('name', 'user')->first()->id;

        // Insere os usuários padrão
        DB::table('users')->insert([
            [
                'name' => 'Administrador',
                'email' => 'admin@gpnagil.com',
                'password' => Hash::make('admin123'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Usuário',
                'email' => 'user@gpnagil.com',
                'password' => Hash::make('admin123'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Atualiza os usuários com os roles
        DB::table('users')->where('email', 'admin@gpnagil.com')->update(['role_id' => $adminRoleId]);
        DB::table('users')->where('email', 'user@gpnagil.com')->update(['role_id' => $userRoleId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove os usuários padrão
        DB::table('users')->whereIn('email', ['admin@gpnagil.com', 'user@gpnagil.com'])->delete();
    }
};
