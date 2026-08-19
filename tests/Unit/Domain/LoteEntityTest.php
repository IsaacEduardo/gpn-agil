<?php

namespace Tests\Unit\Domain;

use App\Domain\LandManagement\Entities\LoteEntity;
use App\Domain\LandManagement\ValueObjects\CodigoLoteValueObject;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class LoteEntityTest extends TestCase
{
    public function test_pode_criar_entidade_de_lote_valida(): void
    {
        $codigo = new CodigoLoteValueObject('LOTE-NAMIBE-001');
        $lote = new LoteEntity(
            id: 1,
            codigo: $codigo,
            areaM2: 500.0,
            localizacao: 'Bairro Valódia, Moçâmedes',
            status: 'disponivel'
        );

        $this->assertEquals(1, $lote->getId());
        $this->assertEquals('LOTE-NAMIBE-001', $lote->getCodigo()->getCodigo());
        $this->assertEquals(500.0, $lote->getAreaM2());
        $this->assertEquals('disponivel', $lote->getStatus());
    }

    public function test_atribuicao_de_requerente_altera_status(): void
    {
        $codigo = new CodigoLoteValueObject('LOTE-NAMIBE-002');
        $lote = new LoteEntity(
            id: 2,
            codigo: $codigo,
            areaM2: 350.0,
            localizacao: 'Zona Industrial'
        );

        $lote->atribuirRequerente(requerenteId: 15);

        $this->assertEquals('atribuido', $lote->getStatus());
        $this->assertEquals(15, $lote->getRequerenteId());
    }

    public function test_lanca_excecao_para_area_invalida(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $codigo = new CodigoLoteValueObject('LOTE-INVALIDO');

        new LoteEntity(
            id: null,
            codigo: $codigo,
            areaM2: 0.0,
            localizacao: 'Centro'
        );
    }
}
