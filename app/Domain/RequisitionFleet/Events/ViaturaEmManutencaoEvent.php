<?php

namespace App\Domain\RequisitionFleet\Events;

use App\Domain\RequisitionFleet\ValueObjects\MatriculaViaturaValueObject;

/**
 * Evento de Domínio puro disparado quando uma Viatura da Frota
 * é enviada para manutenção.
 */
final class ViaturaEmManutencaoEvent
{
    private \DateTimeImmutable $enviadaEm;

    public function __construct(
        private readonly int                      $viaturaId,
        private readonly MatriculaViaturaValueObject $matricula,
        ?\DateTimeImmutable $enviadaEm = null
    ) {
        $this->enviadaEm = $enviadaEm ?? new \DateTimeImmutable();
    }

    public function getViaturaId(): int
    {
        return $this->viaturaId;
    }

    public function getMatricula(): MatriculaViaturaValueObject
    {
        return $this->matricula;
    }

    public function getEnviadaEm(): \DateTimeImmutable
    {
        return $this->enviadaEm;
    }
}
