<?php

namespace Database\Seeders;

use App\Models\DocumentoEspecie;
use Illuminate\Database\Seeder;

class DocumentoEspeciesSeeder extends Seeder
{
    public function run(): void
    {
        $nomes = [
            'Ofício', 'Carta', 'Memorando', 'Circular', 'Despacho', 'Email', 'Relatório', 'Nota', 'Requerimento', 'Acta', 'Outro', 'Ordem de Serviço',
        ];

        foreach ($nomes as $i => $nome) {
            DocumentoEspecie::updateOrCreate(
                ['nome' => $nome],
                ['ativo' => true, 'ordem' => $i + 1]
            );
        }
    }
}
