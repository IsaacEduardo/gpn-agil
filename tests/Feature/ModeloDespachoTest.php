<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\ModeloDespacho;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Os modelos de despacho existiam no modelo de dados e no ecrã de detalhe, mas
 * não havia forma de os criar — zero na base — nem apareciam no painel de ação
 * rápida, onde o gabinete despacha com mais frequência.
 */
class ModeloDespachoTest extends TestCase
{
    use RefreshDatabase;

    private User $resp;

    private Departamento $dep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);

        $papel = Role::where('name', 'user')->firstOrFail();
        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $this->dep = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);

        $this->resp = User::factory()->create(['role_id' => $papel->id, 'departamento_id' => $this->dep->id]);
        $this->resp->assignRole($papel);
        $gab->update(['responsavel_id' => $this->resp->id]);
    }

    public function test_quem_despacha_cria_o_seu_modelo(): void
    {
        $this->actingAs($this->resp)
            ->post(route('modelos-despacho.store'), [
                'titulo' => 'Encaminhar para parecer técnico',
                'texto' => 'Ao Departamento competente para parecer técnico no prazo de 5 dias.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('modelo_despachos', [
            'titulo' => 'Encaminhar para parecer técnico',
            'user_id' => $this->resp->id,
        ]);
    }

    public function test_quem_nao_despacha_nao_acede(): void
    {
        $papel = Role::where('name', 'user')->firstOrFail();
        $comum = User::factory()->create(['role_id' => $papel->id, 'departamento_id' => $this->dep->id]);
        $comum->assignRole($papel);

        $this->actingAs($comum)->get(route('modelos-despacho.index'))->assertStatus(403);
    }

    /** Um modelo de outra pessoa não é editável nem visível. */
    public function test_nao_mexe_no_modelo_de_outrem(): void
    {
        $papel = Role::where('name', 'user')->firstOrFail();
        $outro = User::factory()->create(['role_id' => $papel->id, 'departamento_id' => $this->dep->id]);

        $alheio = ModeloDespacho::create([
            'titulo' => 'Modelo alheio',
            'texto' => 'Texto alheio',
            'user_id' => $outro->id,
            'ativo' => true,
        ]);

        $this->actingAs($this->resp)
            ->delete(route('modelos-despacho.destroy', $alheio))
            ->assertStatus(403);

        $this->assertDatabaseHas('modelo_despachos', ['id' => $alheio->id]);
    }

    /** Modelos globais (sem dono) são visíveis a todos os que despacham. */
    public function test_modelos_globais_aparecem_na_listagem(): void
    {
        ModeloDespacho::create([
            'titulo' => 'Modelo institucional',
            'texto' => 'Texto institucional',
            'user_id' => null,
            'ativo' => true,
        ]);

        $this->actingAs($this->resp)
            ->get(route('modelos-despacho.index'))
            ->assertStatus(200)
            ->assertSee('Modelo institucional', false);
    }

    /** O painel de ação rápida é onde o gabinete despacha — tem de os oferecer. */
    public function test_painel_de_acao_rapida_oferece_os_modelos(): void
    {
        ModeloDespacho::create([
            'titulo' => 'Autorizo o prosseguimento',
            'texto' => 'Autorizo o prosseguimento nos termos propostos.',
            'user_id' => null,
            'ativo' => true,
        ]);

        $doc = DocumentoEntrada::create([
            'numero_sequencial' => 1,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => 'Teste',
            'departamento_id' => $this->dep->id,
            'user_id' => $this->resp->id,
            'status' => 'pendente_tratamento',
        ]);

        $this->actingAs($this->resp)
            ->get(route('documentos-entradas.preview-ajax', $doc))
            ->assertStatus(200)
            ->assertSee('Autorizo o prosseguimento', false);
    }
}
