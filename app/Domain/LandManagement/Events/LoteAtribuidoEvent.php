<?php

namespace App\Domain\LandManagement\Events;

use App\Domain\LandManagement\ValueObjects\CodigoLoteValueObject;

/**
 * Evento de Domínio puro disparado quando um Lote Territorial
 * é atribuído a um requerente.
 */
final class LoteAtribuidoEvent
{
    private \DateTimeImmutable $atribuidoEm;

    public function __construct(
        private readonly int                   $loteId,
        private readonly CodigoLoteValueObject $codigo,
        private readonly int                   $requerenteId,
        ?\DateTimeImmutable $atribuidoEm = null
    ) {
        $this->atribuidoEm = $atribuidoEm ?? new \DateTimeImmutable();
    }

    public function getLoteId(): int
    {
        return $this->loteId;
    }

    public function getCodigo(): CodigoLoteValueObject
    {
        return $this->codigo;
    }

    public function getRequerenteId(): int
    {
        return $this->requerenteId;
    }

    public function getAtribuidoEm(): \DateTimeImmutable
    {
        return $this->atribuidoEm;
    }
}
