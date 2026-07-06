<?php

namespace Database\Factories;

use App\Models\Departamento;
use App\Models\Gabinete;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Departamento>
 */
class DepartamentoFactory extends Factory
{
    protected $model = Departamento::class;

    public function definition(): array
    {
        return [
            'nome' => 'Departamento de '.fake()->unique()->jobTitle(),
            'sigla' => strtoupper(fake()->unique()->lexify('D??')),
            'gabinete_id' => Gabinete::factory(),
        ];
    }
}
