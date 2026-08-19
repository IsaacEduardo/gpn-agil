<?php

namespace Tests\Unit\Domain;

use App\Domain\DocumentManagement\Entities\DocumentoEntradaEntity;
use App\Domain\DocumentManagement\ValueObjects\NumeroProtocoloValueObject;
use App\Domain\DocumentManagement\ValueObjects\HashAssinaturaValueObject;
use App\Domain\DocumentManagement\Events\DocumentoAssinadoEvent;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class DocumentoEntradaEntityTest extends TestCase
{
    public function test_pode_criar_entidade_de_documento_valida(): void
    {
        $protocolo = new NumeroProtocoloValueObject('PROT-2026-0001');
        $entity = new DocumentoEntradaEntity(
            id: 1,
            protocolo: $protocolo,
            assunto: 'Ofício de Solicitação de Verba',
            remetente: 'Ministério das Finanças',
            status: 'pendente'
        );

        $this->assertEquals(1, $entity->getId());
        $this->assertEquals('PROT-2026-0001', $entity->getProtocolo()->getValue());
        $this->assertEquals('Ofício de Solicitação de Verba', $entity->getAssunto());
        $this->assertEquals('pendente', $entity->getStatus());
    }

    public function test_lanca_excecao_se_assunto_for_vazio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $protocolo = new NumeroProtocoloValueObject('PROT-2026-0002');

        new DocumentoEntradaEntity(
            id: null,
            protocolo: $protocolo,
            assunto: '',
            remetente: 'Secretaria Geral'
        );
    }

    public function test_assinatura_digital_altera_status_e_registra_evento_de_dominio(): void
    {
        $protocolo = new NumeroProtocoloValueObject('PROT-2026-0003');
        $entity = new DocumentoEntradaEntity(
            id: 10,
            protocolo: $protocolo,
            assunto: 'Relatório Financeiro',
            remetente: 'Gabinete Provincial'
        );

        $hash = new HashAssinaturaValueObject('a1b2c3d4e5f678901234567890abcdef12345678');
        $entity->assinarDigitalmente(userId: 42, hash: $hash);

        $this->assertEquals('assinado', $entity->getStatus());
        $this->assertNotNull($entity->getHashAssinatura());
        $this->assertEquals('a1b2c3d4e5f678901234567890abcdef12345678', $entity->getHashAssinatura()->getHash());

        $events = $entity->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(DocumentoAssinadoEvent::class, $events[0]);
        $this->assertEquals(10, $events[0]->getDocumentoId());
        $this->assertEquals(42, $events[0]->getAssinadoPorUserId());
    }
}
