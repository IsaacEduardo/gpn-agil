<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use Illuminate\Console\Command;

class NotifyTestCommand extends Command
{
    protected $signature = 'notify:test
        {--user= : ID ou e-mail do usuário alvo}
        {--all : Enviar para todos os usuários}
        {--title=Notificação de teste : Título da notificação}
        {--message=Olá! Isto é um teste de notificação. : Mensagem da notificação}
        {--url=/notifications : URL ao clicar}';

    protected $description = 'Dispara uma notificação simples (database + broadcast) para validar tempo real';

    public function handle(): int
    {
        $title = (string) $this->option('title');
        $message = (string) $this->option('message');
        $url = (string) $this->option('url');

        $notification = new SimpleBroadcastNotification($title, $message, url($url));

        if ($this->option('all')) {
            $count = 0;
            User::chunk(200, function ($users) use ($notification, &$count) {
                foreach ($users as $user) {
                    $user->notify(clone $notification);
                    $count++;
                }
            });
            $this->info("Notificação enviada para {$count} usuário(s).");

            return self::SUCCESS;
        }

        $userOption = $this->option('user');
        $user = null;
        if ($userOption) {
            if (is_numeric($userOption)) {
                $user = User::find((int) $userOption);
            } else {
                $user = User::where('email', $userOption)->first();
            }
            if (! $user) {
                $this->error('Usuário não encontrado pelo parâmetro --user.');

                return self::INVALID;
            }
        } else {
            $user = User::first();
            if (! $user) {
                $this->error('Nenhum usuário encontrado. Crie um usuário ou informe --user=ID|email.');

                return self::INVALID;
            }
            $this->warn("Parâmetro --user não informado. Usando o primeiro usuário: {$user->id} <{$user->email}>.");
        }

        $user->notify($notification);
        $this->info("Notificação enviada para {$user->id} <{$user->email}>.");

        return self::SUCCESS;
    }
}
