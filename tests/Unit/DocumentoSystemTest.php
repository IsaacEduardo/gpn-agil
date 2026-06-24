<?php

namespace Tests\Unit;

use App\Enums\DocumentoStatus;
use App\Models\DocumentoInterno;
use App\Models\User;
use App\Services\DocumentoInternoService;
use App\Services\DocumentoWorkflowService;
use App\Services\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DocumentoSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $workflowService;

    protected $versioningService;

    protected $signatureService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->versioningService = new DocumentoInternoService;
        $this->workflowService = new DocumentoWorkflowService($this->versioningService);
        $this->signatureService = new SignatureService;
    }

    public function test_full_workflow_and_versioning()
    {
        // Setup Roles
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        // Setup Dependencies
        $responsavel = User::factory()->create(['name' => 'Gabinete Chief']);
        $gabinete = \App\Models\Gabinete::create(['nome' => 'Gabinete 1', 'responsavel_id' => $responsavel->id]);

        $especie = \App\Models\DocumentoEspecie::create(['nome' => 'MEMORANDO', 'descricao' => 'Memo']);
        $departamento = \App\Models\Departamento::create(['nome' => 'TI', 'sigla' => 'DTI', 'gabinete_id' => $gabinete->id]);
        $modelo = \App\Models\ModeloDocumento::create(['nome' => 'Modelo 1', 'conteudo' => 'Test', 'documento_especie_id' => $especie->id, 'ativo' => true]);

        // 1. Create User (Author)
        $author = User::factory()->create(['name' => 'Author', 'password' => Hash::make('password'), 'departamento_id' => $departamento->id]);

        // 2. Create Document (Draft)
        $doc = DocumentoInterno::create([
            'titulo' => 'Draft 1',
            'conteudo_final' => 'Content 1',
            'status' => DocumentoStatus::RASCUNHO,
            'criado_por' => $author->id,
            'versao_major' => 0,
            'versao_minor' => 0,
            'versao_patch' => 1,
            'versao_atual' => 1,
            'documento_especie_id' => $especie->id,
            'modelo_documento_id' => $modelo->id,
            'departamento_id' => $departamento->id,
            'numero_referencia' => 'REF/001',
        ]);

        $this->assertEquals('0.0.1', $doc->versao_semantica);
        $this->assertEquals(DocumentoStatus::RASCUNHO, $doc->status);

        // 3. Update Draft (Versioning Patch)
        $this->versioningService->updateWithVersioning($doc, [
            'titulo' => 'Draft 2',
            'conteudo_final' => 'Content 2',
        ], $author, 'patch');

        $doc->refresh();
        $this->assertEquals('0.0.2', $doc->versao_semantica);
        $this->assertEquals('Draft 2', $doc->titulo);

        // 4. Submit for Review (Workflow)
        $this->workflowService->submitForReview($doc, $author);
        $this->assertEquals(DocumentoStatus::EM_ANALISE, $doc->status);

        // 5. Approve (Workflow - by Chief)
        $chief = User::factory()->create(['name' => 'Chief', 'password' => Hash::make('password')]);
        // Give Admin role to Chief to bypass strict hierarchy checks in this unit test
        // In real integration test we would set up Departments/Cabinets
        $chief->assignRole('admin');

        $this->workflowService->approve($doc, $chief);
        $this->assertEquals(DocumentoStatus::APROVADO, $doc->status);
        $this->assertTrue($doc->bloqueado_edicao);

        // 6. Sign (SignatureService)
        // Sign with password
        $signedDoc = $this->signatureService->sign($doc, $chief, 'password');

        $this->assertEquals(DocumentoStatus::ASSINADO, $signedDoc->status);
        $this->assertNotNull($signedDoc->assinado_em);
        $this->assertNotNull($signedDoc->assinatura_hash);

        // Verify Version Jump to Major (1.0.0)
        $this->assertEquals(1, $signedDoc->versao_major);
        $this->assertEquals(0, $signedDoc->versao_minor);
        $this->assertEquals(0, $signedDoc->versao_patch);
        $this->assertEquals('1.0.0', $signedDoc->versao_semantica);
    }
}
