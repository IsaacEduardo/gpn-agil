<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use App\Services\DocumentoEntradaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Execução de tarefas pelo técnico.
 *
 * O parecer é o produto da tarefa e era deitado fora: o modal da listagem
 * exigia-o no HTML, o controlador nunca o lia e completeTask() nunca o
 * gravava — 25 das 26 tarefas concluídas ficaram sem registo. Estes testes
 * fixam que o parecer é obrigatório, é guardado por qualquer via e chega a
 * quem pediu o trabalho; e que o documento só fica TRATADO quando já não há
 * tarefas por fazer.
 */
class ExecucaoTarefaTecnicoTest extends TestCase
{
    use RefreshDatabase;

    private Departamento $departamento;

    private User $chefe;

    private User $tecnico;

    private DocumentoEntrada $documento;

    protected function setUp(): void
    {
        parent::setUp();

        $gabinete = Gabinete::factory()->create();
        $this->departamento = Departamento::factory()->create([
            'gabinete_id' => $gabinete->id,
            'is_area_expediente' => false,
        ]);

        $this->chefe = User::factory()->create(['departamento_id' => $this->departamento->id]);
        $this->chefe->assignRole(Role::findOrCreate('chefe-departamento', 'web'));

        $this->tecnico = User::factory()->create(['departamento_id' => $this->departamento->id]);

        $this->documento = DocumentoEntrada::factory()->create([
            'departamento_id' => $this->departamento->id,
            'user_id' => $this->chefe->id,
            'status' => 'recebido',
        ]);
    }

    // -----------------------------------------------------------------
    // O parecer deixa de se perder
    // -----------------------------------------------------------------

    public function test_concluir_pelo_modal_da_listagem_grava_o_parecer(): void
    {
        Notification::fake();
        $tarefa = $this->tarefa();

        $this->actingAs($this->tecnico)
            ->patch(route('documentos-entradas.tarefas.concluir', [$this->documento, $tarefa]), [
                'observacao' => 'Vistoria realizada; recomenda-se a adjudicação.',
            ])
            ->assertRedirect();

        $tarefa->refresh();

        $this->assertSame('concluida', $tarefa->status);
        $this->assertSame('Vistoria realizada; recomenda-se a adjudicação.', $tarefa->resposta);
        $this->assertNotNull($tarefa->concluida_em);
    }

    public function test_concluir_pela_gaveta_grava_o_mesmo_parecer(): void
    {
        Notification::fake();
        $tarefa = $this->tarefa();

        $this->actingAs($this->tecnico)
            ->postJson(route('documentos-entradas.quick-action', $this->documento), [
                'tarefa_id' => $tarefa->id,
                'observacao' => 'Parecer emitido pela gaveta.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $tarefa->refresh();

        $this->assertSame('concluida', $tarefa->status);
        $this->assertSame('Parecer emitido pela gaveta.', $tarefa->resposta);
        $this->assertNotNull($tarefa->concluida_em);
    }

    public function test_concluir_sem_parecer_e_recusado(): void
    {
        Notification::fake();
        $tarefa = $this->tarefa();

        $this->actingAs($this->tecnico)
            ->from(route('documentos-entradas.show', $this->documento))
            ->patch(route('documentos-entradas.tarefas.concluir', [$this->documento, $tarefa]), [])
            ->assertSessionHasErrors('observacao');

        $tarefa->refresh();

        $this->assertSame('pendente', $tarefa->status);
        $this->assertNull($tarefa->concluida_em);
    }

    public function test_parecer_aparece_na_linha_do_tempo_do_documento(): void
    {
        Notification::fake();
        $tarefa = $this->tarefa();

        app(DocumentoEntradaService::class)->completeTask(
            $tarefa,
            $this->tecnico,
            null,
            'Conclusão técnica: material conforme o caderno de encargos.'
        );

        $this->actingAs($this->chefe)
            ->get(route('documentos-entradas.show', $this->documento))
            ->assertOk()
            ->assertSee('Conclusão técnica: material conforme o caderno de encargos.', false);
    }

    public function test_quem_delegou_e_notificado_na_conclusao(): void
    {
        Notification::fake();
        $tarefa = $this->tarefa();

        app(DocumentoEntradaService::class)->completeTask($tarefa, $this->tecnico, null, 'Feito.');

        Notification::assertSentTo($this->chefe, SimpleBroadcastNotification::class);
    }

    // -----------------------------------------------------------------
    // Separadores
    // -----------------------------------------------------------------

    public function test_atribuidos_a_mim_e_do_meu_setor_nao_se_sobrepoem(): void
    {
        // Tarefa minha, com dono.
        $this->tarefa();

        // Tarefa do setor, ainda sem dono: é a que se pode assumir.
        $doSetor = DocumentoEntrada::factory()->create([
            'departamento_id' => $this->departamento->id,
            'user_id' => $this->chefe->id,
            'status' => 'recebido',
        ]);
        DocumentoTarefa::create([
            'documento_entrada_id' => $doSetor->id,
            'titulo' => 'Demanda do setor',
            'descricao' => 'Por assumir.',
            'assigned_by_id' => $this->chefe->id,
            'assigned_to_departamento_id' => $this->departamento->id,
            'prazo_at' => now()->addDays(2),
            'status' => 'pendente',
        ]);

        $minhas = $this->documentosDoSeparador('atribuidos_mim');
        $doMeuSetor = $this->documentosDoSeparador('em_execucao');

        $this->assertSame([$this->documento->id], $minhas);
        $this->assertSame([$doSetor->id], $doMeuSetor);
        $this->assertEmpty(array_intersect($minhas, $doMeuSetor));
    }

    // -----------------------------------------------------------------
    // Fecho do ciclo
    // -----------------------------------------------------------------

    public function test_delegar_nao_marca_o_documento_como_tratado(): void
    {
        Notification::fake();

        app(DocumentoEntradaService::class)->createTask($this->documento, [
            'titulo' => 'Emitir parecer',
            'descricao' => 'Analisar o pedido.',
            'assigned_to_user_id' => $this->tecnico->id,
            'prazo_at' => now()->addDays(3),
        ], $this->chefe);

        $this->assertSame('recebido', $this->documento->fresh()->status);
    }

    public function test_documento_so_fica_tratado_na_ultima_tarefa(): void
    {
        Notification::fake();

        $primeira = $this->tarefa('Primeira demanda');
        $segunda = $this->tarefa('Segunda demanda');

        $servico = app(DocumentoEntradaService::class);

        $servico->completeTask($primeira, $this->tecnico, null, 'Parte um concluída.');
        $this->assertSame('recebido', $this->documento->fresh()->status);

        $servico->completeTask($segunda, $this->tecnico, null, 'Parte dois concluída.');
        $this->assertSame('tratado', $this->documento->fresh()->status);
    }

    // -----------------------------------------------------------------
    // Apoio
    // -----------------------------------------------------------------

    private function tarefa(string $titulo = 'Despacho Executivo / Demanda Técnica'): DocumentoTarefa
    {
        return DocumentoTarefa::create([
            'documento_entrada_id' => $this->documento->id,
            'titulo' => $titulo,
            'descricao' => 'Atender com urgência.',
            'assigned_by_id' => $this->chefe->id,
            'assigned_to_user_id' => $this->tecnico->id,
            'prazo_at' => now()->addDays(3),
            'status' => 'pendente',
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function documentosDoSeparador(string $separador): array
    {
        $query = DocumentoEntrada::query()->where('arquivado', false);

        app(DocumentoEntradaService::class)
            ->applyRoleTabFilter($query, $separador, $this->tecnico, 'tecnico');

        return $query->orderBy('id')->pluck('id')->all();
    }
}
