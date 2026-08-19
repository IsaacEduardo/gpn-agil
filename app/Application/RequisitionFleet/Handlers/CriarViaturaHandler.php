<?php

namespace App\Application\RequisitionFleet\Handlers;

use App\Application\RequisitionFleet\Commands\CriarViaturaCommand;
use App\Domain\RequisitionFleet\Entities\ViaturaEntity;
use App\Domain\RequisitionFleet\Repositories\ViaturaRepositoryInterface;
use App\Domain\RequisitionFleet\ValueObjects\MatriculaViaturaValueObject;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Handler responsável por orquestrar o caso de uso de criação de Viatura da Frota.
 * Coordena validação de domínio, persistência via repositório e despacho de eventos.
 */
final class CriarViaturaHandler
{
    public function __construct(
        private readonly ViaturaRepositoryInterface $repository,
        private readonly Dispatcher $eventDispatcher
    ) {}

    public function handle(CriarViaturaCommand $command): ViaturaEntity
    {
        $dto = $command->dto;

        // Cria o Value Object — valida invariante da matrícula (não vazia).
        $matricula = new MatriculaViaturaValueObject($dto->matricula);

        // Cria a Entidade de Domínio — valida invariante de marca/modelo obrigatório.
        $entity = new ViaturaEntity(
            id: null,
            matricula: $matricula,
            marcaModelo: $dto->marcaModelo,
            statusOperacional: $dto->statusOperacional,
            departamentoId: $dto->departamentoId,
            codigoInterno: $dto->codigoInterno,
        );

        // Persiste via Repositório de Domínio (Infraestrutura cuida do ORM).
        $savedEntity = $this->repository->save($entity);

        // Despacha eventos de domínio registados na Entidade (se existirem).
        foreach ($savedEntity->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return $savedEntity;
    }
}
