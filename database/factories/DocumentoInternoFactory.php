<?php

namespace Database\Factories;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentoInterno>
 */
class DocumentoInternoFactory extends Factory
{
    protected $model = DocumentoInterno::class;

    public function definition(): array
    {
        return [
            'numero_referencia' => 'DOC/'.fake()->unique()->numberBetween(1, 99999).'/'.date('Y'),
            'titulo' => fake()->sentence(4),
            'conteudo_final' => '<p>'.fake()->paragraph().'</p>',
            'documento_especie_id' => DocumentoEspecie::factory(),
            'criado_por' => User::factory(),
            'departamento_id' => Departamento::factory(),
            'status' => DocumentoStatus::RASCUNHO,
        ];
    }

    public function aprovado(): static
    {
        return $this->state(fn () => ['status' => DocumentoStatus::APROVADO]);
    }

    public function emAnalise(): static
    {
        return $this->state(fn () => ['status' => DocumentoStatus::EM_ANALISE]);
    }
}
