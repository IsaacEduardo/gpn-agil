<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TarefasInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $department;

    protected $gabinete;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gabinete = Gabinete::create([
            'nome' => 'Gabinete Teste',
            'sigla' => 'GT',
        ]);

        $this->department = Departamento::create([
            'nome' => 'Departamento Teste',
            'sigla' => 'DT',
            'email' => 'teste@example.com',
            'gabinete_id' => $this->gabinete->id,
        ]);

        $this->user = User::factory()->create([
            'departamento_id' => $this->department->id,
        ]);
    }

    public function test_can_access_tarefas_index()
    {
        $response = $this->actingAs($this->user)->get(route('tarefas.index'));

        $response->assertStatus(200);
        $response->assertViewIs('tarefas.index');
        $response->assertSee('Minhas Tarefas');
    }

    public function test_can_filter_tarefas()
    {
        // Create a task
        $doc = DocumentoEntrada::create([
            'departamento_id' => $this->department->id,
            'user_id' => $this->user->id,
            'numero_sequencial' => 1,
            'ano_referencia' => 2024,
            'assunto' => 'Doc Teste',
            'status' => 'recebido',
            'data_entrada' => now(),
        ]);

        $tarefa1 = DocumentoTarefa::create([
            'documento_entrada_id' => $doc->id,
            'assigned_by_id' => $this->user->id,
            'assigned_to_user_id' => $this->user->id,
            'titulo' => 'Tarefa Pendente Teste',
            'status' => 'pendente',
            'created_at' => now()->subDays(2),
        ]);

        $tarefa2 = DocumentoTarefa::create([
            'documento_entrada_id' => $doc->id,
            'assigned_by_id' => $this->user->id,
            'assigned_to_user_id' => $this->user->id,
            'titulo' => 'Tarefa Concluida Teste',
            'status' => 'concluida',
            'created_at' => now()->subDay(),
        ]);

        // Filter Pending
        $response = $this->actingAs($this->user)->get(route('tarefas.index', ['status' => 'pendente']));
        $response->assertSee('Tarefa Pendente Teste');
        $response->assertDontSee('Tarefa Concluida Teste');

        // Filter Completed
        $response = $this->actingAs($this->user)->get(route('tarefas.index', ['status' => 'concluido']));
        $response->assertSee('Tarefa Concluida Teste');
        $response->assertDontSee('Tarefa Pendente Teste');
    }

    public function test_can_sort_tarefas()
    {
        $doc = DocumentoEntrada::create([
            'departamento_id' => $this->department->id,
            'user_id' => $this->user->id,
            'numero_sequencial' => 2,
            'ano_referencia' => 2024,
            'assunto' => 'Doc Sort',
            'status' => 'recebido',
            'data_entrada' => now(),
        ]);

        $tarefaA = DocumentoTarefa::create([
            'documento_entrada_id' => $doc->id,
            'assigned_by_id' => $this->user->id,
            'assigned_to_user_id' => $this->user->id,
            'titulo' => 'AAA Tarefa',
            'status' => 'pendente',
            'created_at' => now(),
        ]);

        $tarefaB = DocumentoTarefa::create([
            'documento_entrada_id' => $doc->id,
            'assigned_by_id' => $this->user->id,
            'assigned_to_user_id' => $this->user->id,
            'titulo' => 'ZZZ Tarefa',
            'status' => 'pendente',
            'created_at' => now()->subHour(),
        ]);

        // Sort by Title ASC
        $response = $this->actingAs($this->user)->get(route('tarefas.index', ['sort' => 'titulo', 'direction' => 'asc']));
        $response->assertSeeInOrder(['AAA Tarefa', 'ZZZ Tarefa']);

        // Sort by Title DESC
        $response = $this->actingAs($this->user)->get(route('tarefas.index', ['sort' => 'titulo', 'direction' => 'desc']));
        $response->assertSeeInOrder(['ZZZ Tarefa', 'AAA Tarefa']);
    }

    public function test_ajax_task_completion_returns_json()
    {
        $doc = DocumentoEntrada::create([
            'departamento_id' => $this->department->id,
            'user_id' => $this->user->id,
            'numero_sequencial' => 3,
            'ano_referencia' => 2024,
            'assunto' => 'Doc Ajax',
            'status' => 'recebido',
            'data_entrada' => now(),
        ]);

        $tarefa = DocumentoTarefa::create([
            'documento_entrada_id' => $doc->id,
            'assigned_by_id' => $this->user->id,
            'assigned_to_user_id' => $this->user->id,
            'titulo' => 'Tarefa Drag Drop',
            'status' => 'pendente',
        ]);

        // Concluir exige o parecer: é o produto da tarefa e era descartado.
        $response = $this->actingAs($this->user)->patchJson(
            route('documentos-entradas.tarefas.concluir', [$doc->id, $tarefa->id]),
            ['observacao' => 'Demanda executada conforme solicitado.']
        );

        $response->assertStatus(200)
            ->assertJson(['message' => 'Tarefa marcada como concluída.']);

        $this->assertDatabaseHas('documento_tarefas', [
            'id' => $tarefa->id,
            'status' => 'concluida',
            'resposta' => 'Demanda executada conforme solicitado.',
        ]);
    }

    public function test_ajax_task_cancellation_returns_json()
    {
        $doc = DocumentoEntrada::create([
            'departamento_id' => $this->department->id,
            'user_id' => $this->user->id,
            'numero_sequencial' => 4,
            'ano_referencia' => 2024,
            'assunto' => 'Doc Cancel',
            'status' => 'recebido',
            'data_entrada' => now(),
        ]);

        $tarefa = DocumentoTarefa::create([
            'documento_entrada_id' => $doc->id,
            'assigned_by_id' => $this->user->id,
            'assigned_to_user_id' => $this->user->id,
            'titulo' => 'Tarefa Cancelar',
            'status' => 'pendente',
        ]);

        $response = $this->actingAs($this->user)->patchJson(
            route('documentos-entradas.tarefas.cancelar', [$doc->id, $tarefa->id]),
            []
        );

        $response->assertStatus(200)
            ->assertJson(['message' => 'Tarefa cancelada com sucesso.']);

        $this->assertDatabaseHas('documento_tarefas', [
            'id' => $tarefa->id,
            'status' => 'cancelada',
        ]);
    }
}
