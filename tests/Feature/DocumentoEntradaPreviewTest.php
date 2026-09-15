<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Gaveta de pré-visualização de documentos de entrada.
 *
 * O botão do rodapé era desenhado para toda a gente, incluindo perfis sem
 * ramo no formulário (área de expediente), que submetiam um pedido vazio só
 * para receber 403. Estes testes fixam a regra: o botão existe apenas quando
 * há ação submetível, e anuncia a ação real do perfil.
 */
class DocumentoEntradaPreviewTest extends TestCase
{
    use RefreshDatabase;

    private Gabinete $gabinete;

    private Departamento $departamento;

    private DocumentoEntrada $documento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gabinete = Gabinete::factory()->create();
        $this->departamento = Departamento::factory()->create([
            'gabinete_id' => $this->gabinete->id,
            'is_area_expediente' => false,
        ]);

        $this->documento = DocumentoEntrada::factory()->create([
            'departamento_id' => $this->departamento->id,
            'user_id' => User::factory()->create(['departamento_id' => $this->departamento->id])->id,
            'status' => 'registrado',
        ]);
    }

    public function test_chefe_do_gabinete_do_documento_ve_despachar(): void
    {
        $chefe = $this->utilizadorNoDepartamento();
        $this->gabinete->update(['responsavel_id' => $chefe->id]);

        $resposta = $this->actingAs($chefe)->get($this->urlPreview());

        $resposta->assertOk();
        $resposta->assertSee('Despachar', false);
        $resposta->assertSee('Gabinete Executivo', false);
        // Rótulo exclusivo do formulário de despacho executivo.
        $resposta->assertSee('Departamentos de Destino', false);
    }

    public function test_chefe_de_outro_gabinete_nao_ve_botao_de_despacho(): void
    {
        // Registou o documento (logo pode vê-lo), mas o documento pertence ao
        // departamento de outro gabinete: não é ele quem o despacha.
        $outroGabinete = Gabinete::factory()->create();
        $chefeAlheio = User::factory()->create([
            'departamento_id' => Departamento::factory()->create(['gabinete_id' => $outroGabinete->id])->id,
        ]);
        $outroGabinete->update(['responsavel_id' => $chefeAlheio->id]);
        $this->documento->update(['user_id' => $chefeAlheio->id]);

        $resposta = $this->actingAs($chefeAlheio)->get($this->urlPreview());

        $resposta->assertOk();
        $resposta->assertDontSee('id="btnSubmitDrawerQuickAction"', false);
        $resposta->assertDontSee('Departamentos de Destino', false);
        $resposta->assertSee('pertence a outro gabinete', false);
    }

    public function test_chefe_de_departamento_ve_delegar_tarefa_e_nao_despachar(): void
    {
        $chefe = $this->utilizadorNoDepartamento();
        $chefe->assignRole(Role::findOrCreate('chefe-departamento', 'web'));

        $resposta = $this->actingAs($chefe)->get($this->urlPreview());

        $resposta->assertOk();
        $resposta->assertSee('Delegar tarefa', false);
        $resposta->assertSee('Chefia de Departamento', false);
        // O formulário é o de delegação, não o de despacho executivo.
        $resposta->assertSee('assigned_to_user_id', false);
        $resposta->assertDontSee('Departamentos de Destino', false);
    }

    public function test_tecnico_com_tarefa_pendente_ve_registar_parecer(): void
    {
        $tecnico = $this->utilizadorNoDepartamento();

        DocumentoTarefa::create([
            'documento_entrada_id' => $this->documento->id,
            'titulo' => 'Demanda técnica',
            'descricao' => 'Emitir parecer sobre o pedido.',
            'assigned_by_id' => $this->documento->user_id,
            'assigned_to_user_id' => $tecnico->id,
            'prazo_at' => now()->addDays(3),
            'status' => 'pendente',
        ]);

        $resposta = $this->actingAs($tecnico)->get($this->urlPreview());

        $resposta->assertOk();
        $resposta->assertSee('Registar parecer', false);
        $resposta->assertDontSee('Despachar / Delegar', false);
        $resposta->assertSee('name="observacao"', false);
    }

    public function test_tecnico_sem_tarefa_nao_tem_botao_submetivel(): void
    {
        $tecnico = $this->utilizadorNoDepartamento();

        $resposta = $this->actingAs($tecnico)->get($this->urlPreview());

        $resposta->assertOk();
        $resposta->assertDontSee('id="btnSubmitDrawerQuickAction"', false);
        $resposta->assertSee('Não há demandas sob a sua atribuição ativa', false);
    }

    public function test_area_de_expediente_nao_tem_botao_submetivel(): void
    {
        // O perfil 'expediente' nunca teve ramo no formulário: mostrar-lhe um
        // botão era prometer uma ação que o servidor recusa sempre.
        $expediente = Departamento::factory()->create([
            'gabinete_id' => $this->gabinete->id,
            'is_area_expediente' => true,
        ]);

        $documento = DocumentoEntrada::factory()->create([
            'departamento_id' => $expediente->id,
            'user_id' => User::factory()->create()->id,
            'status' => 'registrado',
        ]);

        $utilizador = User::factory()->create(['departamento_id' => $expediente->id]);

        $resposta = $this->actingAs($utilizador)
            ->get(route('documentos-entradas.preview-ajax', $documento));

        $resposta->assertOk();
        $resposta->assertDontSee('id="btnSubmitDrawerQuickAction"', false);
        $resposta->assertSee('Área de Expediente', false);
        $resposta->assertSee('não tem ação rápida sobre este documento', false);
    }

    public function test_expediente_continua_a_receber_403_no_quick_action(): void
    {
        // A guarda da view é usabilidade; a autoridade continua no servidor.
        $expediente = Departamento::factory()->create([
            'gabinete_id' => $this->gabinete->id,
            'is_area_expediente' => true,
        ]);

        $documento = DocumentoEntrada::factory()->create([
            'departamento_id' => $expediente->id,
            'user_id' => User::factory()->create()->id,
            'status' => 'registrado',
        ]);

        $utilizador = User::factory()->create(['departamento_id' => $expediente->id]);

        $this->actingAs($utilizador)
            ->postJson(route('documentos-entradas.quick-action', $documento), [
                'texto_despacho' => 'Tentativa de despacho.',
            ])
            ->assertForbidden()
            ->assertJsonPath('error', 'Ação não permitida para o perfil atual.');
    }

    public function test_tecnico_nao_consegue_despachar_pelo_endpoint(): void
    {
        $tecnico = $this->utilizadorNoDepartamento();

        $this->actingAs($tecnico)
            ->postJson(route('documentos-entradas.quick-action', $this->documento), [
                'destino_departamento_ids' => [$this->departamento->id],
                'texto_despacho' => 'Despacho indevido.',
            ])
            ->assertStatus(422); // Ramo do técnico: exige tarefa_id e observação.
    }

    private function utilizadorNoDepartamento(): User
    {
        return User::factory()->create(['departamento_id' => $this->departamento->id]);
    }

    private function urlPreview(): string
    {
        return route('documentos-entradas.preview-ajax', $this->documento);
    }
}
