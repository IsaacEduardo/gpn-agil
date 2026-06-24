<?php

namespace App\Listeners;

use App\Events\DocumentoArquivado;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Ouvinte do evento DocumentoArquivado.
 *
 * A auditoria persistente (tabela audit_logs) já é feita pelo ArchiveService; este
 * ouvinte regista uma entrada de log operacional (útil para agregadores de logs)
 * e serve de ponto de extensão para efeitos secundários desacoplados — notificações,
 * broadcasting (quando Echo/WebSockets for configurado), reindexação, etc.
 *
 * Corre na fila para não acrescentar latência ao Job de arquivamento.
 */
class LogDocumentoArquivado implements ShouldQueue
{
    public function handle(DocumentoArquivado $event): void
    {
        Log::info('Documento arquivado', [
            'documento_tipo' => $event->documentableType,
            'documento_id' => $event->documentId,
            'user_id' => $event->userId,
            'pasta_id' => $event->pastaId,
        ]);
    }
}
