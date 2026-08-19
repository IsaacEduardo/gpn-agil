<?php

namespace App\Application\DocumentManagement\Handlers;

use App\Application\DocumentManagement\Commands\CriarDocumentoEntradaCommand;
use App\Domain\DocumentManagement\Entities\DocumentoEntradaEntity;
use App\Domain\DocumentManagement\Repositories\DocumentoEntradaRepositoryInterface;
use App\Domain\DocumentManagement\ValueObjects\NumeroProtocoloValueObject;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Handler responsável por orquestrar o caso de uso de criação de documento de entrada.
 */
final class CriarDocumentoEntradaHandler
{
    public function __construct(
        private readonly DocumentoEntradaRepositoryInterface $repository,
        private readonly Dispatcher $eventDispatcher
    ) {}

    public function handle(CriarDocumentoEntradaCommand $command): DocumentoEntradaEntity
    {
        $dto = $command->dto;

        $protocolo = new NumeroProtocoloValueObject($dto->numeroProtocolo);

        $entity = new DocumentoEntradaEntity(
            id: null,
            protocolo: $protocolo,
            assunto: $dto->assunto,
            remetente: $dto->remetente,
            status: 'pendente',
            gabineteId: $dto->gabineteId,
            departamentoId: $dto->departamentoId
        );

        $savedEntity = $this->repository->save($entity);

        // Dispara os eventos de domínio registrados na entidade
        foreach ($savedEntity->releaseEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return $savedEntity;
    }

    /**
     * Valida apenas as invariantes de domínio sem persistir.
     *
     * Utilizado quando a persistência é gerida por infraestrutura específica
     * (ex: DocumentoEntrada requer numeração sequencial transaccional).
     *
     * @throws \InvalidArgumentException se as invariantes de domínio forem violadas
     */
    public function validateOnly(CriarDocumentoEntradaCommand $command): void
    {
        $dto = $command->dto;

        // Instanciar a entidade executa os guards do construtor.
        // Se o assunto estiver vazio ou o protocolo inválido, é aqui que falha.
        new DocumentoEntradaEntity(
            id: null,
            protocolo: new NumeroProtocoloValueObject($dto->numeroProtocolo),
            assunto: $dto->assunto,
            remetente: $dto->remetente,
            status: 'pendente',
            gabineteId: $dto->gabineteId,
            departamentoId: $dto->departamentoId
        );
    }
}
