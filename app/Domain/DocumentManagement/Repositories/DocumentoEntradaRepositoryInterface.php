<?php

namespace App\Domain\DocumentManagement\Repositories;

use App\Domain\DocumentManagement\Entities\DocumentoEntradaEntity;
use App\Domain\DocumentManagement\ValueObjects\NumeroProtocoloValueObject;

/**
 * Contrato de Repositório de Domínio para Documentos de Entrada.
 * Define persistência e busca sem expor detalhes do SGBD ou ORM.
 */
interface DocumentoEntradaRepositoryInterface
{
    public function findById(int $id): ?DocumentoEntradaEntity;

    public function findByProtocolo(NumeroProtocoloValueObject $protocolo): ?DocumentoEntradaEntity;

    public function save(DocumentoEntradaEntity $entity): DocumentoEntradaEntity;

    public function delete(int $id): bool;
}
