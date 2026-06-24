<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class SyncUserRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:sync-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza os papéis do Spatie com a coluna role_id antiga dos usuários';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando sincronização de roles...');

        $users = User::all();
        $bar = $this->output->createProgressBar(count($users));

        foreach ($users as $user) {
            if ($user->role_id) {
                $role = Role::find($user->role_id);
                if ($role) {
                    $user->syncRoles([$role->name]);
                } else {
                    $this->warn("\nRole ID {$user->role_id} não encontrada para usuário {$user->id}");
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Sincronização concluída com sucesso!');
    }
}
