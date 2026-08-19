<?php

namespace App\Application\LandManagement\Handlers;

use App\Application\LandManagement\Commands\CriarLoteCommand;
use App\Domain\LandManagement\Entities\LoteEntity;
use App\Domain\LandManagement\Repositories\LoteRepositoryInterface;
use App\Domain\LandManagement\ValueObjects\CodigoLoteValueObject;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Handler responsável por orquestrar o caso de uso de criação de Lote Territorial.
 * Coordena validação de domínio, persistência via repositório e despacho de eventos.
 */
final class CriarLoteHandler
{
    public function __construct(
        private readonly LoteRepositoryInterface $repository,
        private readonly Dispatcher $eventDispatcher
    ) {}

    public function handle(CriarLoteCommand $command): LoteEntity
    {
        $dto = $command->dto;

        // Cria o Value Object — valida invariante de formato do código.
        $codigo = new CodigoLoteValueObject($dto->codigo);

        // Cria a Entidade de Domínio — valida invariantes de negócio
        // (área > 0, localização obrigatória).
        $entity = new LoteEntity(
            id: null,
            codigo: $codigo,
            areaM2: $dto->areaM2,
            localizacao: $dto->localizacao,
            status: $dto->status,
            requerenteId: $dto->requerenteId,
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
