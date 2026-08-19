<?php

namespace App\Application\LandManagement\Commands;

use App\Application\LandManagement\DTOs\CriarLoteDTO;

/**
 * Command encapsulando a intenção de execução do caso de uso de criação de Lote Territorial.
 */
final class CriarLoteCommand
{
    public function __construct(
        public readonly CriarLoteDTO $dto
    ) {}
}
