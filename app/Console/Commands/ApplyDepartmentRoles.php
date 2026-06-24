<?php

namespace App\Console\Commands;

use App\Models\Departamento;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class ApplyDepartmentRoles extends Command
{
    protected $signature = 'acl:apply-dept-roles';

    protected $description = 'Atribui a role do departamento a todos os usuários comuns';

    public function handle()
    {
        $this->info('Aplicando roles de departamento...');

        $users = User::whereHas('role', function ($q) {
            $q->where('name', 'user');
        })->whereNotNull('departamento_id')->get();

        $bar = $this->output->createProgressBar(count($users));

        foreach ($users as $user) {
            // Garante que tem a role 'user' limpa
            $user->syncRoles(['user']);

            $dep = Departamento::find($user->departamento_id);
            if ($dep) {
                // Tenta achar ou criar a role do departamento
                $depRole = Role::firstOrCreate(['name' => $dep->nome, 'guard_name' => 'web']);

                // Adiciona a role do departamento (User + Departamento)
                $user->assignRole($depRole);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Processo concluído!');
    }
}
