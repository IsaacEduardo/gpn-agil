<?php

namespace Database\Factories;

use App\Models\Gabinete;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gabinete>
 */
class GabineteFactory extends Factory
{
    protected $model = Gabinete::class;

    public function definition(): array
    {
        return [
            'nome' => 'Gabinete '.fake()->unique()->company(),
            'sigla' => strtoupper(fake()->unique()->lexify('G??')),
        ];
    }
}
