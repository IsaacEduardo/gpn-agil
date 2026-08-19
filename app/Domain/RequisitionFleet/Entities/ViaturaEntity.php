<?php

namespace App\Domain\RequisitionFleet\Entities;

use App\Domain\RequisitionFleet\ValueObjects\MatriculaViaturaValueObject;
use App\Domain\RequisitionFleet\Events\ViaturaEmManutencaoEvent;
use InvalidArgumentException;

/**
 * Entidade / Agregado de Domínio puro para Gestão de Viaturas da Frota.
 */
class ViaturaEntity
{
    private ?int $id;
    private MatriculaViaturaValueObject $matricula;
    private string $marcaModelo;
    private string $statusOperacional;
    private ?int $departamentoId;
    /** Código interno administrativo (coluna 'identificacao' na BD). */
    private ?string $codigoInterno;
    private array $recordedEvents = [];

    public function __construct(
        ?int $id,
        MatriculaViaturaValueObject $matricula,
        string $marcaModelo,
        string $statusOperacional = 'operacional',
        ?int $departamentoId = null,
        ?string $codigoInterno = null
    ) {
        if (empty($marcaModelo)) {
            throw new InvalidArgumentException('Marca e modelo da viatura são obrigatórios.');
        }

        $this->id               = $id;
        $this->matricula        = $matricula;
        $this->marcaModelo      = $marcaModelo;
        $this->statusOperacional = $statusOperacional;
        $this->departamentoId   = $departamentoId;
        $this->codigoInterno    = $codigoInterno;
    }

    public function enviarParaManutencao(): void
    {
        $this->statusOperacional = 'manutencao';
        $this->recordEvent(new ViaturaEmManutencaoEvent($this->id ?? 0, $this->matricula));
    }

    public function marcarComoOperacional(): void
    {
        $this->statusOperacional = 'operacional';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMatricula(): MatriculaViaturaValueObject
    {
        return $this->matricula;
    }

    public function getMarcaModelo(): string
    {
        return $this->marcaModelo;
    }

    public function getStatusOperacional(): string
    {
        return $this->statusOperacional;
    }

    public function getDepartamentoId(): ?int
    {
        return $this->departamentoId;
    }

    public function getCodigoInterno(): ?string
    {
        return $this->codigoInterno;
    }

    private function recordEvent($event): void
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
