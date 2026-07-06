<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\User;
use App\Services\Ai\FakeLlmClient;
use App\Services\Ai\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiDecisionAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected Departamento $dep;
    protected User $user;
    protected DocumentoEntrada $doc;

    protected function setUp(): void
    {
        parent::setUp();

        // Garante que o assistente está ativo e usa a IA fake
        $this->app->singleton(LlmClient::class, fn() => new FakeLlmClient());
        config(['app.feature_assistente' => true]);

        $gab = Gabinete::create(['nome' => 'Gabinete Teste', 'sigla' => 'GT']);
        $this->dep = Departamento::create(['nome' => 'Departamento Teste', 'sigla' => 'DT', 'gabinete_id' => $gab->id]);
        $this->user = User::factory()->create(['departamento_id' => $this->dep->id]);
        
        // Garante a permissão para utilizar o assistente
        if (\Spatie\Permission\Models\Permission::where('name', 'assistente.usar')->doesntExist()) {
            \Spatie\Permission\Models\Permission::create(['name' => 'assistente.usar']);
        }
        $this->user->givePermissionTo('assistente.usar');

        $this->doc = DocumentoEntrada::create([
            'numero_sequencial' => 1,
            'ano_referencia' => 2026,
            'data_entrada' => now(),
            'assunto' => 'Documento de teste para IA',
            'departamento_id' => $this->dep->id,
            'user_id' => $this->user->id,
            'status' => 'registrado',
        ]);
    }

    public function test_can_generate_cabinet_note(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('documentos-entradas.gerar-nota-gab', $this->doc));

        $response->assertStatus(200)
            ->assertJsonStructure(['nota']);
            
        $this->assertStringContainsString('demonstração', $response->json('nota'));
    }

    public function test_can_suggest_actions_and_tasks(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('documentos-entradas.sugerir-acoes', $this->doc));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'classificacao_sugerida',
                'assunto_resumido',
                'prioridade',
                'encaminhar_para_departamento_id',
                'justificativa_encaminhamento',
                'tarefas_sugeridas'
            ]);
            
        $this->assertEquals('Ofício Técnico', $response->json('classificacao_sugerida'));
        $this->assertCount(2, $response->json('tarefas_sugeridas'));
    }

    public function test_cannot_access_without_permission(): void
    {
        $userWithoutPerm = User::factory()->create(['departamento_id' => $this->dep->id]);

        $this->actingAs($userWithoutPerm)
            ->postJson(route('documentos-entradas.gerar-nota-gab', $this->doc))
            ->assertStatus(403);
            
        $this->actingAs($userWithoutPerm)
            ->postJson(route('documentos-entradas.sugerir-acoes', $this->doc))
            ->assertStatus(403);
    }
}
