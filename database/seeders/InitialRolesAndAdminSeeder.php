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

        // O admin inicial só é criado se as credenciais forem fornecidas via
        // ambiente — nunca existem credenciais padrão versionadas no código.
        $email = env('SEED_ADMIN_EMAIL');
        $password = env('SEED_ADMIN_PASSWORD');

        if (empty($email) || empty($password)) {
            $this->command?->warn(
                'Admin inicial NÃO criado: defina SEED_ADMIN_EMAIL e SEED_ADMIN_PASSWORD no .env e volte a correr o seeder.'
            );

            return;
        }

        if (strlen($password) < 12) {
            $this->command?->error(
                'Admin inicial NÃO criado: SEED_ADMIN_PASSWORD deve ter pelo menos 12 caracteres.'
            );

            return;
        }

        if (! User::where('email', $email)->exists()) {
            User::create([
                'name' => env('SEED_ADMIN_NAME', 'Administrador'),
                'email' => $email,
                'password' => $password,
                'role_id' => $admin->id,
            ]);
            $this->command?->info("Admin inicial criado: {$email}");
        }
    }
}
