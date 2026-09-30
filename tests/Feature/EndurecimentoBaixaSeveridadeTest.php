<?php

namespace Tests\Feature;

use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pontos de baixa gravidade da auditoria de 2026-09-30.
 */
class EndurecimentoBaixaSeveridadeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
    }

    public function test_export_de_viaturas_respeita_a_policy(): void
    {
        $semPermissao = User::factory()->create();

        $this->actingAs($semPermissao)->get(route('viaturas.export.pdf'))->assertForbidden();
        $this->actingAs($semPermissao)->get(route('viaturas.export.excel'))->assertForbidden();
    }

    public function test_direcao_de_ordenacao_invalida_nao_da_erro_500(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        foreach ([
            route('viaturas.index', ['sort' => 'placa', 'direction' => 'xpto']),
            route('tarefas.index', ['sort' => 'titulo', 'direction' => 'xpto']),
            route('modelos.index', ['sort' => 'nome', 'direction' => 'xpto']),
            route('documentos-internos.index', ['sort_by' => 'titulo', 'order' => 'xpto']),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_confirmacao_de_password_na_assinatura_e_limitada(): void
    {
        $user = User::factory()->create();
        $especie = DocumentoEspecie::first() ?? DocumentoEspecie::create(['nome' => 'Ofício Teste', 'ativo' => true]);
        $doc = DocumentoInterno::create([
            'titulo' => 'Para assinar',
            'conteudo_final' => '<p>x</p>',
            'criado_por' => $user->id,
            'documento_especie_id' => $especie->id,
            'status' => 'rascunho',
        ]);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->post(route('documentos-internos.sign', $doc), ['password' => 'errada'.$i])->assertStatus(302);
        }

        $this->actingAs($user)->post(route('documentos-internos.sign', $doc), ['password' => 'errada'])->assertStatus(429);
    }
}
