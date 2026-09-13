<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P5 — a show.blade.php recalculava as autorizações em blocos @php, ignorando as
 * que o controller já enviava, e as duas versões tinham divergido. A listagem
 * oferecia "Editar" e "Eliminar" sem qualquer guarda.
 */
class DocumentoEntradaViewAutorizacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
    }

    private function papeis(): array
    {
        return [
            'user' => Role::where('name', 'user')->firstOrFail(),
            'chefe' => Role::where('name', 'chefe-departamento')->firstOrFail(),
        ];
    }

    public function test_quem_so_ve_por_historico_nao_recebe_o_botao_de_editar(): void
    {
        $papeis = $this->papeis();

        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);
        $depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gab->id]);

        $dono = User::factory()->create(['role_id' => $papeis['user']->id, 'departamento_id' => $depA->id]);
        $externo = User::factory()->create(['role_id' => $papeis['user']->id, 'departamento_id' => $depB->id]);
        $externo->assignRole($papeis['user']);

        $doc = DocumentoEntrada::create([
            'numero_sequencial' => 40,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => 'Documento tramitado',
            'departamento_id' => $depA->id,
            'user_id' => $dono->id,
            'status' => 'recebido',
        ]);

        // Visibilidade apenas por histórico: o Dept B já viu o documento passar.
        DocumentoEncaminhamento::create([
            'documento_entrada_id' => $doc->id,
            'origem_departamento_id' => $depB->id,
            'destino_departamento_id' => $depA->id,
            'usuario_id' => $dono->id,
            'encaminhado_em' => now()->subDay(),
            'recebido_em' => now(),
            'recebido_por_id' => $dono->id,
        ]);

        $resposta = $this->actingAs($externo)->get(route('documentos-entradas.show', $doc));

        $resposta->assertStatus(200);
        $resposta->assertDontSee(route('documentos-entradas.edit', $doc), false);
    }

    public function test_show_renderiza_com_as_autorizacoes_vindas_do_controller(): void
    {
        $papeis = $this->papeis();

        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $dep = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);

        $chefe = User::factory()->create(['role_id' => $papeis['chefe']->id, 'departamento_id' => $dep->id]);
        $chefe->assignRole($papeis['chefe']);
        $dep->update(['responsavel_id' => $chefe->id]);

        $doc = DocumentoEntrada::create([
            'numero_sequencial' => 41,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => 'Documento no departamento',
            'departamento_id' => $dep->id,
            'user_id' => $chefe->id,
            'status' => 'recebido',
        ]);

        \App\Models\DocumentoTarefa::create([
            'documento_entrada_id' => $doc->id,
            'titulo' => 'Elaborar parecer',
            'assigned_by_id' => $chefe->id,
            'assigned_to_user_id' => $chefe->id,
            'status' => 'pendente',
        ]);

        $this->actingAs($chefe)
            ->get(route('documentos-entradas.show', $doc))
            ->assertStatus(200)
            ->assertSee('Elaborar parecer', false);
    }

    /**
     * A listagem oferecia "Editar" a toda a gente; o servidor respondia 403.
     */
    public function test_listagem_nao_oferece_editar_a_quem_nao_pode(): void
    {
        $papeis = $this->papeis();

        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);
        $depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gab->id]);

        $dono = User::factory()->create(['role_id' => $papeis['user']->id, 'departamento_id' => $depA->id]);
        $externo = User::factory()->create(['role_id' => $papeis['user']->id, 'departamento_id' => $depB->id]);
        $externo->assignRole($papeis['user']);

        $doc = DocumentoEntrada::create([
            'numero_sequencial' => 42,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => 'Documento do Dept A',
            'departamento_id' => $depA->id,
            'user_id' => $dono->id,
            'status' => 'registrado',
        ]);

        DocumentoEncaminhamento::create([
            'documento_entrada_id' => $doc->id,
            'origem_departamento_id' => $depB->id,
            'destino_departamento_id' => $depA->id,
            'usuario_id' => $dono->id,
            'encaminhado_em' => now()->subDay(),
            'recebido_em' => now(),
            'recebido_por_id' => $dono->id,
        ]);

        $resposta = $this->actingAs($externo)->get(route('documentos-entradas.index', ['tab' => 'todos']));

        $resposta->assertStatus(200);
        $resposta->assertSee('Documento do Dept A', false);
        $resposta->assertDontSee(route('documentos-entradas.edit', $doc), false);
    }
}
