<?php

namespace App\Notifications\Concerns;

/**
 * Política de reentrega para notificações enfileiradas: tenta várias vezes com
 * backoff crescente; esgotadas as tentativas, o job cai em failed_jobs (a
 * dead-letter queue) e o evento NotificationFailed alimenta a auditoria de entrega.
 *
 * O Laravel copia estas propriedades/método da notificação para o job
 * SendQueuedNotifications.
 */
trait QueuedRetryPolicy
{
    public int $tries = 3;

    /**
     * Espera (em segundos) antes de cada nova tentativa.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }
}
