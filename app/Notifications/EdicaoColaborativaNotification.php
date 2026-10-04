<?php

namespace App\Notifications;

use App\Models\DocumentoInterno;
use App\Notifications\Concerns\CanonicalPayload;
use App\Notifications\Concerns\QueuedRetryPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Resumo para o autor: quem editou o documento dele na edição colaborativa durante
 * a última janela (ver EnviarResumoEdicoesColaborativas). Uma notificação por janela,
 * não uma por alteração.
 */
class EdicaoColaborativaNotification extends Notification implements ShouldQueue
{
    use CanonicalPayload, Queueable, QueuedRetryPolicy;

    /**
     * @param  array<int, string>  $editores  nomes
     * @param  array<int, string>  $detalhes  uma frase por pessoa: o que fez
     */
    public function __construct(public DocumentoInterno $documento, public array $editores, public array $detalhes = [])
    {
        $this->queue = 'notifications';
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $nomes = count($this->editores) > 1
            ? implode(', ', array_slice($this->editores, 0, -1)).' e '.end($this->editores)
            : ($this->editores[0] ?? 'Alguém');

        return $this->canonicalPayload(
            title: 'Edições em "'.$this->documento->titulo.'"',
            url: route('documentos-internos.collab.editor', $this->documento),
            // "Josuelma: 14 alterações ao texto e 1 comentário. Nicolau: guardou a v1.0.0."
            body: $this->detalhes
                ? \Illuminate\Support\Str::limit(implode(' ', $this->detalhes), 400)
                : $nomes.(count($this->editores) > 1 ? ' editaram' : ' editou').' o seu documento na edição colaborativa.',
            priority: 'low',
            type: 'edicao_colaborativa',
            icon: 'fas fa-users',
            extra: [
                'documento_interno_id' => $this->documento->id,
                'editores' => $this->editores,
                'detalhes' => $this->detalhes,
            ],
        );
    }
}
