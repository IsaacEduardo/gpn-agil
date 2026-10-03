<?php

namespace App\Notifications;

use App\Models\DocumentoComentario;
use App\Notifications\Concerns\CanonicalPayload;
use App\Notifications\Concerns\QueuedRetryPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Comentário novo num documento em edição colaborativa (para o autor do documento)
 * ou resposta a um comentário (para quem participa nessa conversa). Só in-app: os
 * comentários são frequentes e o e-mail virava ruído.
 */
class ComentarioColaborativoNotification extends Notification implements ShouldQueue
{
    use CanonicalPayload, Queueable, QueuedRetryPolicy;

    public function __construct(public DocumentoComentario $comentario)
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
        $documento = $this->comentario->documento;
        $autor = $this->comentario->autor?->name ?? 'Alguém';
        $resposta = $this->comentario->parent_id !== null;

        return $this->canonicalPayload(
            title: $resposta
                ? $autor.' respondeu a um comentário em "'.$documento->titulo.'"'
                : $autor.' comentou "'.$documento->titulo.'"',
            url: route('documentos-internos.collab.editor', $documento),
            body: Str::limit($this->comentario->texto, 140),
            priority: 'normal',
            type: 'comentario_colaboracao',
            icon: 'fas fa-comment-dots',
            extra: [
                'documento_interno_id' => $documento->id,
                'comentario_id' => $this->comentario->id,
            ],
        );
    }
}
