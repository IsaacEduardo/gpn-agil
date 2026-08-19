<?php

namespace App\Domain\LandManagement\Entities;

use App\Domain\LandManagement\ValueObjects\CodigoLoteValueObject;
use App\Domain\LandManagement\Events\LoteAtribuidoEvent;
use InvalidArgumentException;

/**
 * Entidade / Agregado de Domínio puro para Gestão de Lotes Territoriais.
 */
class LoteEntity
{
    private ?int $id;
    private CodigoLoteValueObject $codigo;
    private float $areaM2;
    private string $localizacao;
    private string $status;
    private ?int $requerenteId;
    private array $recordedEvents = [];

    public function __construct(
        ?int $id,
        CodigoLoteValueObject $codigo,
        float $areaM2,
        string $localizacao,
        string $status = 'disponivel',
        ?int $requerenteId = null
    ) {
        if ($areaM2 <= 0) {
            throw new InvalidArgumentException('A área do lote deve ser maior que zero.');
        }

        if (empty($localizacao)) {
            throw new InvalidArgumentException('A localização do lote é obrigatória.');
        }

        $this->id = $id;
        $this->codigo = $codigo;
        $this->areaM2 = $areaM2;
        $this->localizacao = $localizacao;
        $this->status = $status;
        $this->requerenteId = $requerenteId;
    }

    public function atribuirRequerente(int $requerenteId): void
    {
        $this->requerenteId = $requerenteId;
        $this->status = 'atribuido';
        $this->recordEvent(new LoteAtribuidoEvent($this->id ?? 0, $this->codigo, $requerenteId));
    }

    public function liberar(): void
    {
        $this->requerenteId = null;
        $this->status = 'disponivel';
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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodigo(): CodigoLoteValueObject
    {
        return $this->codigo;
    }

    public function getAreaM2(): float
    {
        return $this->areaM2;
    }

    public function getLocalizacao(): string
    {
        return $this->localizacao;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRequerenteId(): ?int
    {
        return $this->requerenteId;
    }
}
