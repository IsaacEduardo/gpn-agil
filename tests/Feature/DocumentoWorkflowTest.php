<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\ModeloDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentoWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $chefe;

    protected $admin;

    protected $especie;

    protected $modelo;

    protected $departamento;

    protected $gabinete;

    protected function setUp(): void
    {
        parent::setUp();

        // Basic Data
        $responsavel = User::factory()->create(['name' => 'Resp Gabinete']);
        $this->gabinete = \App\Models\Gabinete::create(['nome' => 'Gabinete Teste', 'responsavel_id' => $responsavel->id]);
        $this->departamento = \App\Models\Departamento::create(['nome' => 'Departamento Teste', 'sigla' => 'DPT', 'gabinete_id' => $this->gabinete->id]);

        $this->especie = DocumentoEspecie::create(['nome' => 'MEMORANDO', 'descricao' => 'Memo', 'ativo' => true]);
        $this->modelo = ModeloDocumento::create([
            'nome' => 'Modelo Memo',
            'conteudo' => '<p>Conteúdo</p>',
            'documento_especie_id' => $this->especie->id,
            'ativo' => true,
        ]);

        // Setup Users
        $this->user = User::factory()->create([
            'name' => 'Autor',
            'email' => 'autor@example.com',
            'departamento_id' => $this->departamento->id,
        ]);
        $this->chefe = User::factory()->create([
            'name' => 'Chefe',
            'email' => 'chefe@example.com',
            'departamento_id' => $this->departamento->id,
        ]);
        $this->admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'departamento_id' => $this->departamento->id,
        ]);

        // Roles
        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $roleChefe = Role::firstOrCreate(['name' => 'chefe_departamento', 'guard_name' => 'web']);

        $this->admin->assignRole($roleAdmin);
        $this->chefe->assignRole($roleChefe);
    }

    public function test_document_starts_as_draft()
    {
        $response = $this->actingAs($this->user)->post(route('documentos-internos.store'), [
            'titulo' => 'Meu Documento',
            'conteudo_final' => '<p>Texto</p>',
            'documento_especie_id' => $this->especie->id,
            'modelo_documento_id' => $this->modelo->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('documento_internos', [
            'titulo' => 'Meu Documento',
            'status' => DocumentoStatus::RASCUNHO->value,
            'versao_atual' => 1, // Starts at 1? Controller sets version? No, controller relies on defaults.
            // Model migration defaults versao_atual to 1? Let's check logic.
            // UpdateWithVersioning sets +1. Create usually sets 1?
            // Actually controller sets: $doc = new DocumentoInterno($validated); ... $doc->save();
            // Database default for versao_atual? Migration: $table->integer('versao_atual')->default(1); (Usually)
            // Or null.
        ]);
    }

    public function test_can_submit_draft_for_review()
    {
        $doc = DocumentoInterno::create([
            'titulo' => 'Draft',
            'conteudo_final' => 'Content',
            'status' => DocumentoStatus::RASCUNHO,
            'criado_por' => $this->user->id,
            'departamento_id' => $this->departamento->id,
            'documento_especie_id' => $this->especie->id,
            'numero_referencia' => 'TEST/001',
        ]);

        $response = $this->actingAs($this->user)->post(route('documentos-internos.submit', $doc));

        $response->assertSessionHas('success');
        $this->assertEquals(DocumentoStatus::EM_ANALISE, $doc->fresh()->status);
    }

    public function test_cannot_approve_draft_directly()
    {
        $doc = DocumentoInterno::create([
            'titulo' => 'Draft',
            'conteudo_final' => 'Content',
            'status' => DocumentoStatus::RASCUNHO, // Still draft
            'criado_por' => $this->user->id,
            'departamento_id' => $this->departamento->id,
            'documento_especie_id' => $this->especie->id,
            'numero_referencia' => 'TEST/002',
        ]);

        // Try to approve as boss
        $response = $this->actingAs($this->chefe)->post(route('documentos-internos.approve', $doc));

        $response->assertSessionHas('error'); // Should return error because it's not in review
        $this->assertEquals(DocumentoStatus::RASCUNHO, $doc->fresh()->status);
    }

    public function test_boss_can_approve_document_in_review()
    {
        $doc = DocumentoInterno::create([
            'titulo' => 'In Review',
            'conteudo_final' => 'Content',
            'status' => DocumentoStatus::EM_ANALISE,
            'criado_por' => $this->user->id,
            'departamento_id' => $this->departamento->id,
            'documento_especie_id' => $this->especie->id,
            'numero_referencia' => 'TEST/003',
        ]);

        // Assume chefe has permission (service simplified check or we bypass for now)
        // Service: "// Validar se usuário é chefe..." currently commented out or minimal.
        // We will test if the state transition works.

        $response = $this->actingAs($this->chefe)->post(route('documentos-internos.approve', $doc));

        $response->assertSessionHas('success');
        $doc->refresh();
        $this->assertEquals(DocumentoStatus::APROVADO, $doc->status);
        $this->assertTrue((bool) $doc->bloqueado_edicao);
    }

    public function test_boss_can_reject_document()
    {
        $doc = DocumentoInterno::create([
            'titulo' => 'In Review',
            'conteudo_final' => 'Content',
            'status' => DocumentoStatus::EM_ANALISE,
            'criado_por' => $this->user->id,
            'departamento_id' => $this->departamento->id,
            'documento_especie_id' => $this->especie->id,
            'numero_referencia' => 'TEST/004',
        ]);

        $response = $this->actingAs($this->chefe)->post(route('documentos-internos.reject', $doc), [
            'motivo' => 'Revisar texto',
        ]);

        $response->assertSessionHas('success');
        $doc->refresh();
        $this->assertEquals(DocumentoStatus::RASCUNHO, $doc->status);
        $this->assertFalse((bool) $doc->bloqueado_edicao);
    }

    public function test_cannot_sign_draft_or_review_document()
    {
        $doc = DocumentoInterno::create([
            'titulo' => 'Draft',
            'conteudo_final' => 'Content',
            'status' => DocumentoStatus::RASCUNHO,
            'criado_por' => $this->user->id,
            'departamento_id' => $this->departamento->id,
            'documento_especie_id' => $this->especie->id,
            'numero_referencia' => 'TEST/005',
        ]);

        $response = $this->actingAs($this->user)->post(route('documentos-internos.sign', $doc), [
            'password' => 'password', // Assuming factory uses 'password'
        ]);

        $response->assertSessionHas('error'); // Service throws validation exception
        $this->assertNull($doc->fresh()->assinado_em);
    }

    public function test_can_sign_approved_document()
    {
        $doc = DocumentoInterno::create([
            'titulo' => 'Approved',
            'conteudo_final' => 'Content',
            'status' => DocumentoStatus::APROVADO,
            'criado_por' => $this->admin->id, // Admin can sign usually
            'departamento_id' => $this->departamento->id,
            'documento_especie_id' => $this->especie->id,
            'numero_referencia' => 'TEST/006',
            'versao_major' => 0,
            'versao_minor' => 1,
            'versao_patch' => 0,
        ]);

        // Create UserCertificate for real signature test? No, basic hash is enough for this feature test.

        $response = $this->actingAs($this->admin)->post(route('documentos-internos.sign', $doc), [
            'password' => 'password',
        ]);

        $response->assertSessionHas('success');
        $doc->refresh();
        $this->assertEquals(DocumentoStatus::ASSINADO, $doc->status);
        $this->assertNotNull($doc->assinado_em);
        $this->assertEquals(1, $doc->versao_major); // 0.1.0 -> 1.0.0
    }

    public function test_cannot_edit_approved_document()
    {
        $doc = DocumentoInterno::create([
            'titulo' => 'Approved',
            'conteudo_final' => 'Content',
            'status' => DocumentoStatus::APROVADO,
            'bloqueado_edicao' => true,
            'criado_por' => $this->user->id,
            'departamento_id' => $this->departamento->id,
            'documento_especie_id' => $this->especie->id,
            'numero_referencia' => 'TEST/007',
        ]);

        $response = $this->actingAs($this->user)->put(route('documentos-internos.update', $doc), [
            'titulo' => 'New Title',
            'conteudo_final' => 'New Content',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals('Approved', $doc->fresh()->titulo);
    }
}
