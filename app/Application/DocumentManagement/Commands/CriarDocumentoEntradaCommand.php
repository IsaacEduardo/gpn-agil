<?php

namespace App\Application\DocumentManagement\Commands;

use App\Application\DocumentManagement\DTOs\CriarDocumentoEntradaDTO;

/**
 * Command encapsulando a intenção de execução da ação de criar documento de entrada.
 */
final class CriarDocumentoEntradaCommand
{
    public function __construct(
        public readonly CriarDocumentoEntradaDTO $dto
    ) {}
}
