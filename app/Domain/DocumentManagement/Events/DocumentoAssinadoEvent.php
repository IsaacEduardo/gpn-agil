<?php

namespace App\Domain\DocumentManagement\Events;

use App\Domain\DocumentManagement\ValueObjects\HashAssinaturaValueObject;

/**
 * Evento de Domínio puro disparado quando um documento é assinado digitalmente.
 */
final class DocumentoAssinadoEvent
{
    private int $documentoId;
    private int $assinadoPorUserId;
    private HashAssinaturaValueObject $hashAssinatura;
    private \DateTimeImmutable $assinadoEm;

    public function __construct(
        int $documentoId,
        int $assinadoPorUserId,
        HashAssinaturaValueObject $hashAssinatura,
        ?\DateTimeImmutable $assinadoEm = null
    ) {
        $this->documentoId = $documentoId;
        $this->assinadoPorUserId = $assinadoPorUserId;
        $this->hashAssinatura = $hashAssinatura;
        $this->assinadoEm = $assinadoEm ?? new \DateTimeImmutable();
    }

    public function getDocumentoId(): int
    {
        return $this->documentoId;
    }

    public function getAssinadoPorUserId(): int
    {
        return $this->assinadoPorUserId;
    }

    public function getHashAssinatura(): HashAssinaturaValueObject
    {
        return $this->hashAssinatura;
    }

    public function getAssinadoEm(): \DateTimeImmutable
    {
        return $this->assinadoEm;
    }
}
