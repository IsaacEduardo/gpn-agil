<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role as SpatieRole;

class CreateOrResetAdmin extends Command
{
    protected $signature = 'edms:create-admin 
                            {--email=admin@gpn.gov.ao : Email do administrador}
                            {--password=AdminGPN2026 : Senha do administrador}
                            {--name=Administrador do Sistema : Nome do administrador}';

    protected $description = 'Cria ou redefine o usuário administrador inicial do sistema';

    public function handle(): int
    {
        $email = $this->option('email');
        $password = $this->option('password');
        $name = $this->option('name');

        $role = Role::firstOrCreate(['name' => 'admin']);

        $user = User::firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = $password;
        $user->role_id = $role->id;
        $user->save();

        try {
            $spatieRole = SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            $user->assignRole($spatieRole);
        } catch (\Throwable $e) {
            // Spatie role assignment fallback
        }

        $this->info("Administrador configurado com sucesso!");
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Nome', $user->name],
                ['E-mail', $user->email],
                ['Senha', $password],
                ['Role ID', $user->role_id],
            ]
        );

        return self::SUCCESS;
    }
}
