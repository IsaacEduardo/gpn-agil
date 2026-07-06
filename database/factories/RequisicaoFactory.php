<?php

namespace Database\Factories;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requisicao>
 */
class RequisicaoFactory extends Factory
{
    protected $model = Requisicao::class;

    public function definition(): array
    {
        // codigo_sequencial é gerado automaticamente pelo RequisicaoObserver
        return [
            'tipo' => TipoRequisicao::PRODUTO,
            'data_requisicao' => now(),
            'usuario_id' => User::factory(),
            'status' => StatusRequisicao::PENDENTE,
            'empresa_destinataria' => fake()->company(),
            'observacoes' => fake()->sentence(),
        ];
    }
}
