<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DepartamentoDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected $departamento;

    protected $chefe;

    protected $user;

    protected $docPendente;

    protected $reqPendente;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Roles (Use dash to match UserRole enum and RequisicaoPolicy)
        $roleChefe = Role::firstOrCreate(['name' => 'chefe-departamento', 'guard_name' => 'web']);

        // Setup Permission for Requisitions (Required by RequisicaoPolicy if role check fails or as override)
        $permAprovar = Permission::firstOrCreate(['name' => 'requisicoes.aprovar', 'guard_name' => 'web']);
        $roleChefe->givePermissionTo($permAprovar);

        // Create Gabinete (Required)
        $gabinete = \App\Models\Gabinete::create([
            'nome' => 'Gabinete Teste',
            'sigla' => 'GABTEST',
            'responsavel_id' => User::factory()->create()->id,
        ]);

        // Departamento e Usuários
        $this->departamento = Departamento::create([
            'nome' => 'TI',
            'sigla' => 'DTI',
            'gabinete_id' => $gabinete->id,
        ]);

        $this->chefe = User::factory()->create([
            'name' => 'Chefe TI',
            'departamento_id' => $this->departamento->id,
            'role_id' => $roleChefe->id, // Explicitly set role_id for legacy policy support
        ]);
        $this->chefe->assignRole($roleChefe);

        $this->user = User::factory()->create([
            'name' => 'Funcionario TI',
            'departamento_id' => $this->departamento->id,
        ]);

        // Dados de Teste
        $especie = DocumentoEspecie::create(['nome' => 'MEMO', 'ativo' => true]);

        // Documento Pendente (Em Análise)
        $this->docPendente = DocumentoInterno::create([
            'titulo' => 'Doc Teste',
            'conteudo_final' => 'Conteúdo',
            'status' => DocumentoStatus::EM_ANALISE,
            'criado_por' => $this->user->id,
            'departamento_id' => $this->departamento->id,
            'documento_especie_id' => $especie->id,
            'numero_referencia' => 'REF/001',
        ]);

        // Requisição Pendente
        $this->reqPendente = Requisicao::create([
            'tipo' => TipoRequisicao::PRODUTO,
            'status' => StatusRequisicao::PENDENTE,
            'usuario_id' => $this->user->id,
            'empresa_destinataria' => 'Empresa X',
            'data_requisicao' => now(),
            'codigo_sequencial' => 'REQ/001',
        ]);
    }

    public function test_chefe_can_access_dashboard()
    {
        $response = $this->actingAs($this->chefe)->get(route('departamento.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Gestão Departamental: TI');
        $response->assertSee('REF/001'); // Doc reference
        $response->assertSee('REQ/001'); // Requisicao code
    }

    public function test_regular_user_cannot_access_dashboard()
    {
        $response = $this->actingAs($this->user)->get(route('departamento.dashboard'));
        $response->assertStatus(403);
    }

    public function test_batch_approve_documents()
    {
        $response = $this->actingAs($this->chefe)->post(route('departamento.batch.approve.docs'), [
            'documento_ids' => [$this->docPendente->id],
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(DocumentoStatus::APROVADO, $this->docPendente->fresh()->status);
    }

    public function test_batch_approve_requisicoes()
    {
        $response = $this->actingAs($this->chefe)->post(route('departamento.batch.approve.reqs'), [
            'requisicao_ids' => [$this->reqPendente->id],
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(StatusRequisicao::APROVADO, $this->reqPendente->fresh()->status);
    }

    public function test_batch_sign_documents()
    {
        // Setup doc for signing
        $doc = DocumentoInterno::create([
            'titulo' => 'Doc para Assinar',
            'conteudo_final' => 'Conteúdo',
            'status' => DocumentoStatus::APROVADO,
            'criado_por' => $this->chefe->id,
            'departamento_id' => $this->departamento->id,
            'documento_especie_id' => $this->docPendente->documento_especie_id,
            'numero_referencia' => 'REF/SIGN/001',
        ]);

        // Especie needs to be NOTA or REQUERIMENTO for Chefe de Depto to sign
        $especie = DocumentoEspecie::create(['nome' => 'NOTA', 'ativo' => true]);
        $doc->update(['documento_especie_id' => $especie->id]);

        $response = $this->actingAs($this->chefe)->post(route('departamento.batch.sign.docs'), [
            'documento_ids' => [$doc->id],
            'password' => 'password',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(DocumentoStatus::ASSINADO, $doc->fresh()->status);
    }
}
