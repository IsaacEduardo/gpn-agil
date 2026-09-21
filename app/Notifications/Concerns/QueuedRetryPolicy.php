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

    /**
     * Ligação de fila por canal.
     *
     * O aviso in-app (canal 'database') é a via principal — e era a única que
     * o destinatário tinha. Estando tudo enfileirado, bastava não haver worker
     * a correr para nenhuma notificação chegar: o registo de entrada, o despacho
     * e a tarefa delegada ficavam parados na tabela `jobs` e o ecrã de
     * notificações do utilizador mostrava-se vazio, sem erro nem sinal.
     * Notavelmente, as notificações que funcionavam eram as não enfileiradas.
     *
     * O canal 'database' passa a ser gravado em 'sync', no próprio pedido: é uma
     * escrita local, barata, e deixa de depender de infraestrutura para o
     * utilizador ser avisado. O e-mail e o broadcast — que falam com serviços
     * externos (SMTP, Reverb) e não podem atrasar nem fazer falhar o pedido —
     * continuam enfileirados, com a política de retentativa acima.
     *
     * @return array<string, string|null>
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
        ];
    }
}
