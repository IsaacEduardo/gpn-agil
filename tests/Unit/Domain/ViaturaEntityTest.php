<?php

namespace Tests\Unit\Domain;

use App\Domain\RequisitionFleet\Entities\ViaturaEntity;
use App\Domain\RequisitionFleet\ValueObjects\MatriculaViaturaValueObject;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class ViaturaEntityTest extends TestCase
{
    public function test_pode_criar_entidade_de_viatura_valida(): void
    {
        $matricula = new MatriculaViaturaValueObject('LD-12-34-AB');
        $viatura = new ViaturaEntity(
            id: 1,
            matricula: $matricula,
            marcaModelo: 'Toyota Hilux 4x4',
            statusOperacional: 'operacional'
        );

        $this->assertEquals(1, $viatura->getId());
        $this->assertEquals('LD-12-34-AB', $viatura->getMatricula()->getMatricula());
        $this->assertEquals('Toyota Hilux 4x4', $viatura->getMarcaModelo());
        $this->assertEquals('operacional', $viatura->getStatusOperacional());
    }

    public function test_mudanca_de_status_operacional(): void
    {
        $matricula = new MatriculaViaturaValueObject('LD-99-88-ZZ');
        $viatura = new ViaturaEntity(
            id: 5,
            matricula: $matricula,
            marcaModelo: 'Nissan Hardbody'
        );

        $viatura->enviarParaManutencao();
        $this->assertEquals('manutencao', $viatura->getStatusOperacional());

        $viatura->marcarComoOperacional();
        $this->assertEquals('operacional', $viatura->getStatusOperacional());
    }

    public function test_lanca_excecao_para_marca_modelo_vazio(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $matricula = new MatriculaViaturaValueObject('LD-00-00-XX');

        new ViaturaEntity(
            id: null,
            matricula: $matricula,
            marcaModelo: ''
        );
    }
}
