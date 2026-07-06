<?php

namespace Database\Factories;

use App\Models\DocumentoEspecie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentoEspecie>
 */
class DocumentoEspecieFactory extends Factory
{
    protected $model = DocumentoEspecie::class;

    public function definition(): array
    {
        return [
            // Nome único para não colidir com as espécies semeadas por migração
            'nome' => 'ESPECIE '.strtoupper(fake()->unique()->lexify('????')),
            'ativo' => true,
        ];
    }
}
