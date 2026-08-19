<?php

namespace App\Domain\DocumentManagement\Entities;

use App\Domain\DocumentManagement\ValueObjects\NumeroProtocoloValueObject;
use App\Domain\DocumentManagement\ValueObjects\HashAssinaturaValueObject;
use App\Domain\DocumentManagement\Events\DocumentoAssinadoEvent;
use InvalidArgumentException;

/**
 * Entidade de Domínio / Agregado Raiz (Aggregate Root) para Documentos de Entrada.
 * Isolado de frameworks e persistência Eloquent.
 */
class DocumentoEntradaEntity
{
    private ?int $id;
    private NumeroProtocoloValueObject $protocolo;
    private string $assunto;
    private string $remetente;
    private string $status;
    private ?int $gabineteId;
    private ?int $departamentoId;
    private ?HashAssinaturaValueObject $hashAssinatura;
    private array $recordedEvents = [];

    public function __construct(
        ?int $id,
        NumeroProtocoloValueObject $protocolo,
        string $assunto,
        string $remetente,
        string $status = 'pendente',
        ?int $gabineteId = null,
        ?int $departamentoId = null,
        ?HashAssinaturaValueObject $hashAssinatura = null
    ) {
        if (empty($assunto)) {
            throw new InvalidArgumentException('Assunto do documento é obrigatório.');
        }

        $this->id = $id;
        $this->protocolo = $protocolo;
        $this->assunto = $assunto;
        $this->remetente = $remetente;
        $this->status = $status;
        $this->gabineteId = $gabineteId;
        $this->departamentoId = $departamentoId;
        $this->hashAssinatura = $hashAssinatura;
    }

    public function assinarDigitalmente(int $userId, HashAssinaturaValueObject $hash): void
    {
        $this->hashAssinatura = $hash;
        $this->status = 'assinado';

        $this->recordEvent(new DocumentoAssinadoEvent(
            $this->id ?? 0,
            $userId,
            $hash
        ));
    }

    public function arquivar(): void
    {
        $this->status = 'arquivado';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProtocolo(): NumeroProtocoloValueObject
    {
        return $this->protocolo;
    }

    public function getAssunto(): string
    {
        return $this->assunto;
    }

    public function getRemetente(): string
    {
        return $this->remetente;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getGabineteId(): ?int
    {
        return $this->gabineteId;
    }

    public function getDepartamentoId(): ?int
    {
        return $this->departamentoId;
    }

    public function getHashAssinatura(): ?HashAssinaturaValueObject
    {
        return $this->hashAssinatura;
    }

    protected function recordEvent(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }
}
