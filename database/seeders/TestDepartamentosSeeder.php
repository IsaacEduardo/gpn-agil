<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Gabinete;
use Illuminate\Database\Seeder;

class TestDepartamentosSeeder extends Seeder
{
    public function run(): void
    {
        // Cria um Gabinete padrão para vincular departamentos
        $gabinete = Gabinete::firstOrCreate(
            ['nome' => 'Gabinete de Teste'],
            ['sigla' => 'GT']
        );

        // Departamentos A e B
        Departamento::firstOrCreate(
            ['nome' => 'Departamento A', 'sigla' => 'A', 'gabinete_id' => $gabinete->id]
        );

        Departamento::firstOrCreate(
            ['nome' => 'Departamento B', 'sigla' => 'B', 'gabinete_id' => $gabinete->id]
        );
    }
}
