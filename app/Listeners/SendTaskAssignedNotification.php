<?php

namespace App\Listeners;

use App\Events\TaskAssigned;
use App\Models\Departamento;
use App\Models\User;
use App\Notifications\TarefaDelegadaNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Ouvinte do evento TaskAssigned.
 * Envia notificações por e-mail de forma assíncrona/enfileirada para os destinatários da tarefa.
 */
class SendTaskAssignedNotification
{
    /**
     * Trata o evento.
     *
     * @param  TaskAssigned  $event
     * @return void
     */
    public function handle(TaskAssigned $event): void
    {
        $tarefa = $event->tarefa;

        // Caso a tarefa seja atribuída a um utilizador individual
        if ($tarefa->assigned_to_user_id) {
            $user = User::find($tarefa->assigned_to_user_id);
            if ($user && !empty($user->email)) {
                try {
                    $user->notify(new TarefaDelegadaNotification($tarefa));
                    Log::info('Notificação de e-mail enfileirada para utilizador', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'tarefa_id' => $tarefa->id,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Falha ao enfileirar e-mail para utilizador', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'tarefa_id' => $tarefa->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Caso a tarefa seja atribuída a um departamento
        if ($tarefa->assigned_to_departamento_id) {
            $dep = Departamento::find($tarefa->assigned_to_departamento_id);
            if ($dep) {
                $targets = collect();

                if ($dep->chefe) {
                    $targets->push($dep->chefe);
                }

                $dep->usuarios()->chunk(100, function ($users) use ($targets) {
                    foreach ($users as $user) {
                        $targets->push($user);
                    }
                });

                if ($dep->gabinete && $dep->gabinete->responsavel) {
                    $targets->push($dep->gabinete->responsavel);
                }

                // Filtrar apenas utilizadores únicos com e-mail preenchido
                $targets = $targets->unique('id')->values()->filter(function ($u) {
                    return !empty($u->email);
                });

                if ($targets->count() > 0) {
                    foreach ($targets as $target) {
                        try {
                            $target->notify(new TarefaDelegadaNotification($tarefa));
                            Log::info('Notificação de e-mail enfileirada para membro de departamento', [
                                'user_id' => $target->id,
                                'email' => $target->email,
                                'tarefa_id' => $tarefa->id,
                            ]);
                        } catch (\Throwable $e) {
                            Log::error('Falha ao enfileirar e-mail para membro de departamento', [
                                'user_id' => $target->id,
                                'email' => $target->email,
                                'tarefa_id' => $tarefa->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
        }
    }
}
