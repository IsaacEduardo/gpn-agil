<?php

namespace App\Domain\RequisitionFleet\Repositories;

use App\Domain\RequisitionFleet\Entities\ViaturaEntity;
use App\Domain\RequisitionFleet\ValueObjects\MatriculaViaturaValueObject;

/**
 * Contrato de Repositório de Domínio para Viaturas da Frota.
 */
interface ViaturaRepositoryInterface
{
    public function findById(int $id): ?ViaturaEntity;

    public function findByMatricula(MatriculaViaturaValueObject $matricula): ?ViaturaEntity;

    public function save(ViaturaEntity $entity): ViaturaEntity;

    public function delete(int $id): bool;
}
