<?php

namespace App\Application\RequisitionFleet\Commands;

use App\Application\RequisitionFleet\DTOs\CriarViaturaDTO;

/**
 * Command encapsulando a intenção de execução do caso de uso de criação de Viatura da Frota.
 */
final class CriarViaturaCommand
{
    public function __construct(
        public readonly CriarViaturaDTO $dto
    ) {}
}
