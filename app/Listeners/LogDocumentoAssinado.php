<?php

namespace App\Listeners;

use App\Domain\DocumentManagement\Events\DocumentoAssinadoEvent;
use Illuminate\Support\Facades\Log;

/**
 * Listener que reage ao Evento de Domínio DocumentoAssinadoEvent.
 * Regista um log de auditoria de assinatura digital.
 * Pode ser expandido para: notificações, integração com PKI, etc.
 */
class LogDocumentoAssinado
{
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(DocumentoAssinadoEvent $event): void
    {
        Log::info('Documento assinado digitalmente via DDD Domain Event', [
            'documento_id'        => $event->getDocumentoId(),
            'assinado_por_user'   => $event->getAssinadoPorUserId(),
            'hash_assinatura'     => $event->getHashAssinatura()->getHash(),
            'assinado_em'         => $event->getAssinadoEm()->format('Y-m-d H:i:s'),
        ]);
    }
}
