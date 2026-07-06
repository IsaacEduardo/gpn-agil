<?php

namespace Database\Factories;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentoEntrada>
 */
class DocumentoEntradaFactory extends Factory
{
    protected $model = DocumentoEntrada::class;

    public function definition(): array
    {
        return [
            'numero_sequencial' => fake()->unique()->numberBetween(1, 999999),
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => fake()->sentence(5),
            'procedencia' => fake()->company(),
            'departamento_id' => Departamento::factory(),
            'user_id' => User::factory(),
            'status' => 'registrado',
            'arquivado' => false,
        ];
    }
}
