<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GabineteAccessTest extends TestCase
{
    use RefreshDatabase;

    protected $gabinete;

    protected $chefeGabinete;

    protected $deptA;

    protected $deptB;

    protected $userA;

    protected $userB;

    protected $docA;

    protected $docB;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Structure
        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'gabinete.view_all', 'guard_name' => 'web']);

        $especie = DocumentoEspecie::create(['nome' => 'MEMORANDO', 'descricao' => 'Memo', 'ativo' => true]);

        // Gabinete & Chefe
        $this->chefeGabinete = User::factory()->create(['name' => 'Chefe Gabinete']);
        $this->gabinete = Gabinete::create(['nome' => 'Gabinete Civil', 'sigla' => 'GABCIV', 'responsavel_id' => $this->chefeGabinete->id]);

        // Depto A (Inside Gabinete)
        $this->deptA = Departamento::create(['nome' => 'Depto A', 'sigla' => 'DPTA', 'gabinete_id' => $this->gabinete->id]);
        $this->userA = User::factory()->create(['name' => 'User A', 'departamento_id' => $this->deptA->id]);

        // Depto B (Inside Gabinete)
        $this->deptB = Departamento::create(['nome' => 'Depto B', 'sigla' => 'DPTB', 'gabinete_id' => $this->gabinete->id]);
        $this->userB = User::factory()->create(['name' => 'User B', 'departamento_id' => $this->deptB->id]);

        // Documents
        $this->docA = DocumentoInterno::create([
            'titulo' => 'Doc A', 'conteudo_final' => 'Content', 'status' => DocumentoStatus::RASCUNHO,
            'criado_por' => $this->userA->id, 'departamento_id' => $this->deptA->id,
            'documento_especie_id' => $especie->id, 'numero_referencia' => 'REF/A',
        ]);

        $this->docB = DocumentoInterno::create([
            'titulo' => 'Doc B', 'conteudo_final' => 'Content', 'status' => DocumentoStatus::EM_ANALISE,
            'criado_por' => $this->userB->id, 'departamento_id' => $this->deptB->id,
            'documento_especie_id' => $especie->id, 'numero_referencia' => 'REF/B',
        ]);
    }

    public function test_cabinet_chief_can_access_dashboard()
    {
        $response = $this->actingAs($this->chefeGabinete)->get(route('gabinete.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Gestão do Gabinete: Gabinete Civil');
        $response->assertSee('Doc B'); // In Analysis list
    }

    public function test_regular_user_cannot_access_dashboard()
    {
        $response = $this->actingAs($this->userA)->get(route('gabinete.dashboard'));
        $response->assertStatus(403);
    }

    public function test_cabinet_chief_can_view_document_from_any_department_in_cabinet()
    {
        // Chefe viewing Doc A (Dept A)
        $response = $this->actingAs($this->chefeGabinete)->get(route('documentos-internos.show', $this->docA));
        $response->assertStatus(200);

        // Chefe viewing Doc B (Dept B)
        $response = $this->actingAs($this->chefeGabinete)->get(route('documentos-internos.show', $this->docB));
        $response->assertStatus(200);
    }

    public function test_regular_user_cannot_view_document_from_other_department()
    {
        // User A viewing Doc B (Dept B) -> Should be Forbidden (via Policy or Scope if applied)
        $response = $this->actingAs($this->userA)->get(route('documentos-internos.show', $this->docB));
        $response->assertStatus(403);
    }

    public function test_cabinet_chief_can_approve_document()
    {
        $response = $this->actingAs($this->chefeGabinete)->post(route('documentos-internos.approve', $this->docB));
        $response->assertSessionHas('success');
        $this->assertEquals(DocumentoStatus::APROVADO, $this->docB->fresh()->status);
    }

    public function test_batch_approval()
    {
        // Create another doc in analysis
        $docC = DocumentoInterno::create([
            'titulo' => 'Doc C', 'conteudo_final' => 'Content', 'status' => DocumentoStatus::EM_ANALISE,
            'criado_por' => $this->userA->id, 'departamento_id' => $this->deptA->id,
            'documento_especie_id' => $this->docA->documento_especie_id, 'numero_referencia' => 'REF/C',
        ]);

        $response = $this->actingAs($this->chefeGabinete)->post(route('gabinete.batch-approve'), [
            'documento_ids' => [$this->docB->id, $docC->id],
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(DocumentoStatus::APROVADO, $this->docB->fresh()->status);
        $this->assertEquals(DocumentoStatus::APROVADO, $docC->fresh()->status);
    }

    public function test_batch_signing()
    {
        // Setup approved documents ready for signing
        $this->docB->update(['status' => DocumentoStatus::APROVADO]);

        $docC = DocumentoInterno::create([
            'titulo' => 'Doc C', 'conteudo_final' => 'Content', 'status' => DocumentoStatus::APROVADO,
            'criado_por' => $this->userA->id, 'departamento_id' => $this->deptA->id,
            'documento_especie_id' => $this->docA->documento_especie_id, 'numero_referencia' => 'REF/C',
        ]);

        $response = $this->actingAs($this->chefeGabinete)->post(route('gabinete.batch-sign'), [
            'documento_ids' => [$this->docB->id, $docC->id],
            'password' => 'password',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(DocumentoStatus::ASSINADO, $this->docB->fresh()->status);
        $this->assertEquals(DocumentoStatus::ASSINADO, $docC->fresh()->status);
        $this->assertNotNull($this->docB->fresh()->assinado_em);
    }

    public function test_super_cabinet_chief_can_access_dashboard_and_has_restricted_actions()
    {
        $superChefe = User::factory()->create(['name' => 'Super Chefe']);
        $roleSuper = Role::firstOrCreate(['name' => 'super-chefe-gabinete', 'guard_name' => 'web']);
        $superChefe->assignRole($roleSuper);
        $superChefe->update(['role_id' => $roleSuper->id]);
        
        $this->gabinete->update(['super_chefe_id' => $superChefe->id]);

        // 1. Dashboard access
        $response = $this->actingAs($superChefe)->get(route('gabinete.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Gestão do Gabinete: Gabinete Civil');
        $response->assertSee('Doc B');
        // Novo Documento button should NOT be present
        $response->assertDontSee('Novo Documento');

        // 2. Cannot approve
        $responseApprove = $this->actingAs($superChefe)->post(route('documentos-internos.approve', $this->docB));
        $responseApprove->assertStatus(403);

        // 3. Cannot batch approve
        $responseBatchApprove = $this->actingAs($superChefe)->post(route('gabinete.batch-approve'), [
            'documento_ids' => [$this->docB->id],
        ]);
        $responseBatchApprove->assertSessionHas('error'); // fails policy check

        // 4. Cannot batch sign
        $this->docB->update(['status' => DocumentoStatus::APROVADO]);
        $responseBatchSign = $this->actingAs($superChefe)->post(route('gabinete.batch-sign'), [
            'documento_ids' => [$this->docB->id],
            'password' => 'password',
        ]);
        $responseBatchSign->assertSessionHas('error'); // fails policy check
    }

    public function test_super_cabinet_chief_task_delegation()
    {
        $superChefe = User::factory()->create(['name' => 'Super Chefe']);
        $roleSuper = Role::firstOrCreate(['name' => 'super-chefe-gabinete', 'guard_name' => 'web']);
        $superChefe->assignRole($roleSuper);
        $superChefe->update(['role_id' => $roleSuper->id]);
        
        $this->gabinete->update(['super_chefe_id' => $superChefe->id]);

        // A. Delegate to Cabinet Chief (allowed)
        $docEntrada = \App\Models\DocumentoEntrada::create([
            'assunto' => 'Doc Teste',
            'procedencia' => 'Externo',
            'numero_sequencial' => 1,
            'ano_referencia' => 2026,
            'departamento_id' => $this->deptA->id,
            'status' => 'recebido',
            'data_entrada' => now(),
            'user_id' => $superChefe->id,
        ]);

        // Mocking list of eligible users in show
        $responseDelegate = $this->actingAs($superChefe)->post(route('documentos-entradas.tarefas.store', $docEntrada), [
            'tipo' => 'usuario',
            'destino_id' => $this->chefeGabinete->id,
            'titulo' => 'Analisar URGENTE',
            'prazo_at' => now()->addDays(2)->format('Y-m-d'),
        ]);
        $responseDelegate->assertSessionHasNoErrors();
        $this->assertDatabaseHas('documento_tarefas', [
            'titulo' => 'Analisar URGENTE',
            'assigned_to_user_id' => $this->chefeGabinete->id
        ]);

        // B. Delegate to regular user (blocked)
        $responseDelegateUser = $this->actingAs($superChefe)->post(route('documentos-entradas.tarefas.store', $docEntrada), [
            'tipo' => 'usuario',
            'destino_id' => $this->userA->id,
            'titulo' => 'Fazer algo',
            'prazo_at' => now()->addDays(2)->format('Y-m-d'),
        ]);
        $responseDelegateUser->assertSessionHasErrors(['destino_id']);

        // C. Delegate to department (blocked)
        $responseDelegateDept = $this->actingAs($superChefe)->post(route('documentos-entradas.tarefas.store', $docEntrada), [
            'tipo' => 'departamento',
            'destino_id' => $this->deptA->id,
            'titulo' => 'Fazer algo depto',
            'prazo_at' => now()->addDays(2)->format('Y-m-d'),
        ]);
        $responseDelegateDept->assertSessionHasErrors(['tipo']);
    }
}
