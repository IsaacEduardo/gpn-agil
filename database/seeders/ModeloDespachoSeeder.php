<?php

namespace Database\Seeders;

use App\Models\ModeloDespacho;
use Illuminate\Database\Seeder;

class ModeloDespachoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modelos = [
            [
                'titulo' => 'Para Análise',
                'texto' => 'Encaminho o presente documento para análise e parecer técnico. Aguardo retorno.',
            ],
            [
                'titulo' => 'Para Conhecimento e Arquivamento',
                'texto' => 'Encaminho para conhecimento. Após leitura, proceder com o arquivamento.',
            ],
            [
                'titulo' => 'De Acordo - Prosseguir',
                'texto' => 'De acordo com o exposto. Encaminho para prosseguimento conforme solicitado.',
            ],
            [
                'titulo' => 'Devolução para Correção',
                'texto' => 'Devolvo o documento para as devidas correções e ajustes apontados. Favor reapresentar após regularização.',
            ],
            [
                'titulo' => 'Solicitação de Informações',
                'texto' => 'Solicito informações adicionais sobre o teor deste documento para subsidiar a tomada de decisão.',
            ],
        ];

        foreach ($modelos as $modelo) {
            ModeloDespacho::firstOrCreate(['titulo' => $modelo['titulo']], $modelo);
        }
    }
}
