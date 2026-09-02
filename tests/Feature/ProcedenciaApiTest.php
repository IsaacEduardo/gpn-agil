<?php

namespace Tests\Feature;

use App\Models\Procedencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcedenciaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_procedencias()
    {
        $user = User::factory()->create();
        Procedencia::create(['nome' => 'Tribunal de Comarca do Namibe', 'ativo' => true]);
        Procedencia::create(['nome' => 'Governo Provincial', 'ativo' => true]);
        Procedencia::create(['nome' => 'Inativo Teste', 'ativo' => false]);

        $response = $this->actingAs($user)->getJson('/api/procedencias');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['nome' => 'Tribunal de Comarca do Namibe']);
        $response->assertJsonMissing(['nome' => 'Inativo Teste']);
    }

    public function test_can_search_procedencias()
    {
        $user = User::factory()->create();
        Procedencia::create(['nome' => 'Tribunal do Namibe', 'ativo' => true]);
        Procedencia::create(['nome' => 'Ministério da Justiça', 'ativo' => true]);

        $response = $this->actingAs($user)->getJson('/api/procedencias?q=Namibe');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['nome' => 'Tribunal do Namibe']);
    }

    public function test_can_create_procedencia_via_api()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/procedencias', [
            'nome' => '  Nova Procedência Teste  ',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['nome' => 'Nova Procedência Teste']);

        $this->assertDatabaseHas('procedencias', [
            'nome' => 'Nova Procedência Teste',
            'ativo' => true,
        ]);
    }

    public function test_cannot_create_duplicate_procedencia_case_insensitive()
    {
        $user = User::factory()->create();
        Procedencia::create(['nome' => 'Tribunal de Contas', 'ativo' => true]);

        $response = $this->actingAs($user)->postJson('/api/procedencias', [
            'nome' => 'tribunal de contas',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors', 'data']);
    }
}
