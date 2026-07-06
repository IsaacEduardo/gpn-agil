<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\AuditLog;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\Pasta;
use App\Models\User;
use App\Services\EdmsStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EdmsProTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected $admin;

    protected $departamento;

    protected $departamentoRH;

    protected $gabinete;

    protected $especie;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup base structure
        $this->gabinete = Gabinete::create(['nome' => 'Gabinete Provincial', 'sigla' => 'GP']);

        $this->departamento = Departamento::create([
            'nome' => 'Departamento de Finanças',
            'sigla' => 'DF',
            'gabinete_id' => $this->gabinete->id,
        ]);

        $this->departamentoRH = Departamento::create([
            'nome' => 'Departamento de Recursos Humanos',
            'sigla' => 'DRH',
            'gabinete_id' => $this->gabinete->id,
        ]);

        $this->user = User::factory()->create(['departamento_id' => $this->departamento->id]);
        $this->admin = User::factory()->create(['departamento_id' => $this->departamento->id]);

        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin->assignRole($roleAdmin);

        $this->especie = DocumentoEspecie::firstOrCreate(['nome' => 'Memorando'], ['ativo' => true]);
    }

    public function test_admin_can_access_retention_config_and_store_rules()
    {
        $response = $this->actingAs($this->admin)->get(route('edms.retention'));
        $response->assertStatus(200);
        $response->assertSee('Tabela de Temporalidade Documental');

        // Store retention rules
        $responseStore = $this->actingAs($this->admin)->post(route('edms.retention-store'), [
            'rules' => [
                [
                    'documento_especie_id' => $this->especie->id,
                    'temporalidade_anos' => 3,
                    'acao_final' => 'eliminar',
                    'observacoes' => 'Despacho Provincial nº 12/2026',
                ],
            ],
        ]);

        $responseStore->assertRedirect();
        $this->assertDatabaseHas('retention_schedules', [
            'documento_especie_id' => $this->especie->id,
            'temporalidade_anos' => 3,
            'acao_final' => 'eliminar',
            'observacoes' => 'Despacho Provincial nº 12/2026',
        ]);
    }

    public function test_regular_user_cannot_access_retention_config()
    {
        $response = $this->actingAs($this->user)->get(route('edms.retention'));
        $response->assertStatus(403);

        $responseStore = $this->actingAs($this->user)->post(route('edms.retention-store'), [
            'rules' => [
                [
                    'documento_especie_id' => $this->especie->id,
                    'temporalidade_anos' => 3,
                    'acao_final' => 'eliminar',
                ],
            ],
        ]);
        $responseStore->assertStatus(403);
    }

    public function test_direct_file_upload_to_folder()
    {
        Storage::fake('local');

        $pasta = Pasta::create([
            'nome' => 'Finanças 2026',
            'departamento_id' => $this->departamento->id,
            'created_by' => $this->user->id,
        ]);

        $fakeFile = UploadedFile::fake()->create('balanco_financeiro.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)->post(route('edms.upload-file'), [
            'file' => $fakeFile,
            'pasta_id' => $pasta->id,
            'titulo' => 'Balanço Geral de Contas',
        ]);

        $response->assertRedirect();

        // Assert DocumentoInterno was created with special attributes
        $this->assertDatabaseHas('documento_internos', [
            'titulo' => 'Balanço Geral de Contas',
            'pasta_id' => $pasta->id,
            'arquivado' => true,
            'status' => DocumentoStatus::ARQUIVADO->value,
        ]);

        // Assert audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'upload_file',
        ]);
    }

    public function test_stream_version_decrates_correctly()
    {
        Storage::fake();

        $pasta = Pasta::create([
            'nome' => 'Relatórios',
            'departamento_id' => $this->departamento->id,
            'created_by' => $this->user->id,
        ]);

        $doc = DocumentoInterno::create([
            'titulo' => 'Ficheiro Criptografado',
            'conteudo_final' => '<p>HTML do documento</p>',
            'documento_especie_id' => $this->especie->id,
            'departamento_id' => $this->departamento->id,
            'criado_por' => $this->user->id,
            'status' => DocumentoStatus::ARQUIVADO,
            'pasta_id' => $pasta->id,
            'numero_referencia' => 'EDMS/TEST1234',
        ]);

        $fakeFile = UploadedFile::fake()->create('relatorio.pdf', 50, 'application/pdf');

        $storageService = app(EdmsStorageService::class);
        $versao = $storageService->storeDocumentVersion($doc, $fakeFile, $this->user, 'Upload de teste');

        $response = $this->actingAs($this->user)->get(route('edms.stream-version', $versao->id));

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'inline; filename="Ficheiro Criptografado"');
        $response->assertHeader('Content-Type', 'application/pdf');

        // Check audit log was stored
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'action' => 'view_file_version',
            'auditable_id' => $doc->id,
        ]);
    }

    public function test_folder_sharing_restricts_and_grants_access()
    {
        $userRH = User::factory()->create(['departamento_id' => $this->departamentoRH->id]);

        $pasta = Pasta::create([
            'nome' => 'Processos Seletivos',
            'departamento_id' => $this->departamento->id, // Pertence a Finanças
            'created_by' => $this->user->id,
        ]);

        // User do RH não deve ter acesso de início
        $responseAntes = $this->actingAs($userRH)->get(route('edms.index', $pasta->id));
        $responseAntes->assertStatus(403);

        // Partilhar a pasta com o RH
        $responseShare = $this->actingAs($this->user)->post(route('edms.share-folder', $pasta->id), [
            'departamento_ids' => [$this->departamentoRH->id],
        ]);

        $responseShare->assertRedirect();

        // Assert metadata saved the sharing options
        $this->assertDatabaseHas('file_metadata', [
            'metadatable_type' => Pasta::class,
            'metadatable_id' => $pasta->id,
            'key' => 'shared_departments',
            'value' => json_encode([$this->departamentoRH->id]),
        ]);

        // Agora user de RH deve ter acesso
        $responseDepois = $this->actingAs($userRH)->get(route('edms.index', $pasta->id));
        $responseDepois->assertStatus(200);
    }

    public function test_folder_history_returns_json_logs()
    {
        $pasta = Pasta::create([
            'nome' => 'Pasta Auditada',
            'departamento_id' => $this->departamento->id,
            'created_by' => $this->user->id,
        ]);

        // Gerar um log de auditoria
        AuditLog::create([
            'user_id' => $this->user->id,
            'action' => 'create_folder',
            'auditable_type' => Pasta::class,
            'auditable_id' => $pasta->id,
            'new_values' => ['nome' => $pasta->nome],
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($this->user)->get(route('edms.folder-history', $pasta->id));

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'user_name' => $this->user->name,
            'action' => 'Criou pasta',
            'ip' => '127.0.0.1',
        ]);
    }
}
