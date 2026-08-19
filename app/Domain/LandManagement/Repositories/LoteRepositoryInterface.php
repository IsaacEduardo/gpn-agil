<?php

namespace App\Domain\LandManagement\Repositories;

use App\Domain\LandManagement\Entities\LoteEntity;
use App\Domain\LandManagement\ValueObjects\CodigoLoteValueObject;

/**
 * Contrato de Repositório de Domínio para Lotes Territoriais.
 */
interface LoteRepositoryInterface
{
    public function findById(int $id): ?LoteEntity;

    public function findByCodigo(CodigoLoteValueObject $codigo): ?LoteEntity;

    public function save(LoteEntity $entity): LoteEntity;

    public function delete(int $id): bool;
}
