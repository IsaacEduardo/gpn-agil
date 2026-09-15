<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\User;
use App\Notifications\DocumentoEncaminhadoDepartamento;
use App\Services\DocumentoEntradaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Despachar é entregar.
 *
 * O despacho parava em TRATADO e a distribuição ficava para um segundo gesto
 * do expediente, o que deixava documentos já entregues a figurar como "prontos
 * a encaminhar" e permitia uma segunda entrega ao mesmo departamento. Estes
 * testes fixam a regra nova: as três vias de despacho entregam o documento aos
 * destinos no mesmo ato e terminam em ENCAMINHADO.
 */
class DespachoEncaminhaDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private Gabinete $gabinete;

    private Departamento $expediente;

    private Departamento $destinoA;

    private Departamento $destinoB;

    private User $chefeGabinete;

    private DocumentoEntrada $documento;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gabinete = Gabinete::factory()->create();
        $this->expediente = $this->departamento();
        $this->destinoA = $this->departamento();
        $this->destinoB = $this->departamento();

        $this->chefeGabinete = User::factory()->create(['departamento_id' => $this->expediente->id]);
        $this->gabinete->update(['responsavel_id' => $this->chefeGabinete->id]);

        $this->documento = DocumentoEntrada::factory()->create([
            'departamento_id' => $this->expediente->id,
            'user_id' => $this->chefeGabinete->id,
            'status' => 'registrado',
        ]);
    }

    // -----------------------------------------------------------------
    // As três vias de despacho
    // -----------------------------------------------------------------

    public function test_despacho_pelo_modal_encaminha_e_termina_em_encaminhado(): void
    {
        Notification::fake();

        $this->actingAs($this->chefeGabinete)
            ->post(route('documentos-entradas.despachar', $this->documento), [
                'texto_despacho' => 'Ao departamento, para parecer.',
                'departamentos_ids' => [$this->destinoA->id, $this->destinoB->id],
            ])
            ->assertRedirect();

        $this->assertDespachoEntregue(['Ao departamento, para parecer.', [$this->destinoA->id, $this->destinoB->id]]);
    }

    public function test_despacho_pela_gaveta_encaminha_e_termina_em_encaminhado(): void
    {
        Notification::fake();

        $this->actingAs($this->chefeGabinete)
            ->postJson(route('documentos-entradas.quick-action', $this->documento), [
                'destino_departamento_ids' => [$this->destinoA->id],
                'texto_despacho' => 'Despacho pela ação rápida.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDespachoEntregue(['Despacho pela ação rápida.', [$this->destinoA->id]]);
    }

    public function test_despacho_em_lote_encaminha_e_termina_em_encaminhado(): void
    {
        Notification::fake();

        $segundo = DocumentoEntrada::factory()->create([
            'departamento_id' => $this->expediente->id,
            'user_id' => $this->chefeGabinete->id,
            'status' => 'registrado',
        ]);

        $this->actingAs($this->chefeGabinete)
            ->post(route('documentos-entradas.batch.despachar'), [
                'ids' => json_encode([$this->documento->id, $segundo->id]),
                'texto_despacho' => 'Despacho em lote.',
                'departamentos_ids' => [$this->destinoA->id],
            ]);

        foreach ([$this->documento, $segundo] as $doc) {
            $doc->refresh();
            $this->assertSame('encaminhado', $doc->status);
            $this->assertSame(1, $doc->encaminhamentos()->count());
        }
    }

    // -----------------------------------------------------------------
    // Entrega
    // -----------------------------------------------------------------

    public function test_notifica_uma_vez_por_destino(): void
    {
        Notification::fake();

        $tecnicoA = User::factory()->create(['departamento_id' => $this->destinoA->id]);
        $tecnicoB = User::factory()->create(['departamento_id' => $this->destinoB->id]);

        app(DocumentoEntradaService::class)->despacharDocumento(
            $this->documento,
            'Para tratamento.',
            [$this->destinoA->id, $this->destinoB->id],
            $this->chefeGabinete
        );

        Notification::assertSentToTimes($tecnicoA, DocumentoEncaminhadoDepartamento::class, 1);
        Notification::assertSentToTimes($tecnicoB, DocumentoEncaminhadoDepartamento::class, 1);
        // Quem despacha não se notifica a si próprio.
        Notification::assertNotSentTo($this->chefeGabinete, DocumentoEncaminhadoDepartamento::class);
    }

    public function test_novo_despacho_substitui_os_destinos_antigos(): void
    {
        Notification::fake();

        $antigo = $this->departamento();
        $this->documento->departamentosDestino()->sync([$antigo->id]);

        app(DocumentoEntradaService::class)->despacharDocumento(
            $this->documento,
            'Destinos corrigidos.',
            [$this->destinoA->id, $this->destinoB->id],
            $this->chefeGabinete
        );

        $destinos = $this->documento->encaminhamentos()->pluck('destino_departamento_id')->all();

        $this->assertCount(2, $destinos);
        $this->assertEqualsCanonicalizing([$this->destinoA->id, $this->destinoB->id], $destinos);
        $this->assertNotContains($antigo->id, $destinos);
    }

    public function test_nao_duplica_entrega_a_destino_ainda_por_receber(): void
    {
        Notification::fake();

        $servico = app(DocumentoEntradaService::class);

        $servico->despacharDocumento($this->documento, 'Primeiro despacho.', [$this->destinoA->id], $this->chefeGabinete);
        $servico->despacharDocumento($this->documento, 'Segundo despacho.', [$this->destinoA->id], $this->chefeGabinete);

        $this->assertSame(
            1,
            DocumentoEncaminhamento::where('documento_entrada_id', $this->documento->id)
                ->where('destino_departamento_id', $this->destinoA->id)
                ->count()
        );
    }

    public function test_destino_invalido_nao_deixa_estado_intermedio(): void
    {
        $this->actingAs($this->chefeGabinete)
            ->post(route('documentos-entradas.despachar', $this->documento), [
                'texto_despacho' => 'Despacho inválido.',
                'departamentos_ids' => [999999],
            ])
            ->assertSessionHasErrors('departamentos_ids.0');

        $this->documento->refresh();

        $this->assertSame('registrado', $this->documento->status);
        $this->assertNull($this->documento->data_despacho);
        $this->assertSame(0, $this->documento->encaminhamentos()->count());
    }

    // -----------------------------------------------------------------
    // Separadores
    // -----------------------------------------------------------------

    public function test_documento_despachado_sai_de_tratados_e_entra_em_encaminhados(): void
    {
        Notification::fake();

        app(DocumentoEntradaService::class)->despacharDocumento(
            $this->documento,
            'Para tratamento.',
            [$this->destinoA->id],
            $this->chefeGabinete
        );

        $separadores = $this->separadoresDe($this->chefeGabinete);

        $this->assertSame(0, $separadores['tratados']['count']);
        $this->assertSame(1, $separadores['encaminhados']['count']);
        $this->assertSame('Tratados pelos Departamentos', $separadores['tratados']['label']);
    }

    public function test_documento_so_fica_tratado_quando_a_tarefa_e_concluida(): void
    {
        Notification::fake();

        $chefeDestino = User::factory()->create(['departamento_id' => $this->destinoA->id]);
        $chefeDestino->assignRole(Role::findOrCreate('chefe-departamento', 'web'));
        $tecnico = User::factory()->create(['departamento_id' => $this->destinoA->id]);

        $documento = DocumentoEntrada::factory()->create([
            'departamento_id' => $this->destinoA->id,
            'user_id' => $chefeDestino->id,
            'status' => 'recebido',
        ]);

        $tarefa = app(DocumentoEntradaService::class)->createTask($documento, [
            'titulo' => 'Emitir parecer',
            'descricao' => 'Analisar e responder.',
            'assigned_to_user_id' => $tecnico->id,
            'prazo_at' => now()->addDays(3),
        ], $chefeDestino);

        // Delegar não é tratar: o trabalho ainda nem começou.
        $this->assertSame('recebido', $documento->fresh()->status);
        $this->assertSame(1, DocumentoTarefa::where('documento_entrada_id', $documento->id)->count());

        app(DocumentoEntradaService::class)->completeTask($tarefa, $tecnico, null, 'Parecer emitido.');

        $this->assertSame('tratado', $documento->fresh()->status);
    }

    public function test_expediente_abre_num_separador_util(): void
    {
        $this->expediente->update(['is_area_expediente' => true]);
        $utilizador = User::factory()->create(['departamento_id' => $this->expediente->id]);

        $servico = app(DocumentoEntradaService::class);

        $this->assertSame('carecer_tratamento', $servico->getDefaultTabForProfile('expediente'));

        // O documento por despachar está nesse separador, e não numa lista vazia.
        $separadores = $this->separadoresDe($utilizador);
        $this->assertTrue($separadores['carecer_tratamento']['is_active']);
        $this->assertSame(1, $separadores['carecer_tratamento']['count']);
    }

    // -----------------------------------------------------------------
    // Apoio
    // -----------------------------------------------------------------

    private function departamento(): Departamento
    {
        return Departamento::factory()->create([
            'gabinete_id' => $this->gabinete->id,
            'is_area_expediente' => false,
        ]);
    }

    /**
     * @param  array{0: string, 1: array<int, int>}  $esperado  [texto do despacho, ids de destino]
     */
    private function assertDespachoEntregue(array $esperado): void
    {
        [$texto, $destinos] = $esperado;

        $this->documento->refresh();

        $this->assertSame('encaminhado', $this->documento->status);
        $this->assertNotNull($this->documento->data_despacho);
        $this->assertNotNull($this->documento->encaminhamento_data);
        $this->assertSame('aprovado', $this->documento->visto_gabinete_status);
        // Campo da saída para outro gabinete: o despacho interno não lhe toca.
        $this->assertNull($this->documento->saida_gabinete_data);

        $encaminhamentos = $this->documento->encaminhamentos()->get();

        $this->assertCount(count($destinos), $encaminhamentos);
        $this->assertEqualsCanonicalizing($destinos, $encaminhamentos->pluck('destino_departamento_id')->all());

        foreach ($encaminhamentos as $enc) {
            $this->assertSame($texto, $enc->observacao);
            $this->assertNotNull($enc->encaminhado_em);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function separadoresDe(User $user): array
    {
        $tabs = app(DocumentoEntradaService::class)
            ->getRoleWorkflowTabs($user, request());

        return collect($tabs)->keyBy('key')->all();
    }
}
