<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentoEntradaService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * O ramo 'tecnico' do quickAction escrevia em documento_tarefas.resposta e
 * .concluida_em — colunas que não existiam. Registar o parecer técnico pelo
 * painel de ação rápida rebentava com "Unknown column", e é o último passo do
 * fluxo do documento externo.
 */
class DocumentoTarefaConclusaoTest extends TestCase
{
    use RefreshDatabase;

    private Departamento $dep;

    private User $chefe;

    private User $tecnico;

    private DocumentoEntrada $doc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        Notification::fake();

        $papelChefe = Role::where('name', 'chefe-departamento')->firstOrFail();
        $papelUser = Role::where('name', 'user')->firstOrFail();

        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $this->dep = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);

        $this->chefe = User::factory()->create(['role_id' => $papelChefe->id, 'departamento_id' => $this->dep->id]);
        $this->chefe->assignRole($papelChefe);
        $this->dep->update(['responsavel_id' => $this->chefe->id]);

        $this->tecnico = User::factory()->create(['role_id' => $papelUser->id, 'departamento_id' => $this->dep->id]);
        $this->tecnico->assignRole($papelUser);

        $this->doc = DocumentoEntrada::create([
            'numero_sequencial' => 1,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => 'Pedido de parecer',
            'departamento_id' => $this->dep->id,
            'user_id' => $this->chefe->id,
            'status' => 'recebido',
        ]);
    }

    private function tarefa(): DocumentoTarefa
    {
        return DocumentoTarefa::create([
            'documento_entrada_id' => $this->doc->id,
            'titulo' => 'Elaborar parecer',
            'assigned_by_id' => $this->chefe->id,
            'assigned_to_user_id' => $this->tecnico->id,
            'status' => 'pendente',
        ]);
    }

    public function test_tecnico_regista_parecer_pelo_painel_de_acao_rapida(): void
    {
        $tarefa = $this->tarefa();

        $this->actingAs($this->tecnico)
            ->postJson(route('documentos-entradas.quick-action', $this->doc), [
                'tarefa_id' => $tarefa->id,
                'observacao' => 'Parecer favorável, nos termos do artigo 12.º.',
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $tarefa->refresh();
        $this->assertSame('concluida', $tarefa->status);
        $this->assertSame('Parecer favorável, nos termos do artigo 12.º.', $tarefa->resposta);
        $this->assertNotNull($tarefa->concluida_em);
    }

    /** O outro caminho de conclusão tem de datar a conclusão da mesma forma. */
    public function test_conclusao_pelo_endpoint_tambem_data_a_conclusao(): void
    {
        $tarefa = $this->tarefa();

        app(DocumentoEntradaService::class)->completeTask($tarefa, $this->tecnico);

        $tarefa->refresh();
        $this->assertSame('concluida', $tarefa->status);
        $this->assertNotNull($tarefa->concluida_em);
    }

    /** A linha do tempo tem de usar a data real de conclusão, não o updated_at. */
    public function test_linha_do_tempo_usa_a_data_de_conclusao(): void
    {
        $tarefa = $this->tarefa();
        app(DocumentoEntradaService::class)->completeTask($tarefa, $this->tecnico);

        // Uma alteração posterior não pode mexer na data do evento.
        $tarefa->refresh();
        $dataConclusao = $tarefa->concluida_em->copy();
        $tarefa->forceFill(['updated_at' => now()->addDays(5)])->save();

        $resposta = $this->actingAs($this->chefe)->get(route('documentos-entradas.show', $this->doc));
        $resposta->assertStatus(200);

        $eventos = $resposta->viewData('timelineEvents');
        $conclusao = collect($eventos)->firstWhere('tipo', 'tarefa_concluida');

        $this->assertNotNull($conclusao, 'A conclusão da tarefa devia constar da linha do tempo.');
        $this->assertSame(
            $dataConclusao->format('Y-m-d H:i'),
            Carbon::parse($conclusao['data'])->format('Y-m-d H:i'),
        );
    }
}
