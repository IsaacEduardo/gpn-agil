<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado quando um documento é arquivado com sucesso.
 *
 * Usado pelo ArchiveDocumentJob após o ArchiveService concluir.
 * Assinatura alinhada com a invocação no Job:
 *   event(new DocumentoArquivado($type, $id, $userId, $pastaId))
 *
 * Não implementa ShouldBroadcast porque o Echo/WebSockets não está
 * configurado nesta aplicação. Pode ser adicionado no futuro.
 */
class DocumentoArquivado
{
    use Dispatchable, SerializesModels;

    /**
     * @param  class-string  $documentableType  Classe do modelo (DocumentoInterno|DocumentoEntrada)
     * @param  int  $documentId  ID do documento arquivado
     * @param  int  $userId  ID do utilizador que iniciou o arquivamento
     * @param  int  $pastaId  ID da pasta de destino
     */
    public function __construct(
        public string $documentableType,
        public int $documentId,
        public int $userId,
        public int $pastaId,
    ) {}
}
