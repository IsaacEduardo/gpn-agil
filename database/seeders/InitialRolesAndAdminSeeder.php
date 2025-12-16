<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialRolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $chefe = Role::firstOrCreate(['name' => 'chefe-departamento']);
        $user = Role::firstOrCreate(['name' => 'user']);

        if (! User::where('email', 'admin@empresa.com')->exists()) {
            User::create([
                'name' => 'Administrador',
                'email' => 'admin@empresa.com',
                'password' => Hash::make('TroqueEstaSenha123!'),
                'role_id' => $admin->id,
            ]);
        }
    }
}
