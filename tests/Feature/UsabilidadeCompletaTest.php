<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Enums\StatusRequisicao;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\Requisicao;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DocumentoEspeciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UsabilidadeCompletaTest extends TestCase
{
    use RefreshDatabase;

    protected $gabinete;

    protected $departamentoOrigem;

    protected $departamentoDestino;

    protected $admin;

    protected $chefeOrigem;

    protected $chefeDestino;

    protected $funcionario;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed species
        $this->seed(DocumentoEspeciesSeeder::class);

        // 1. Setup Basic Structure (Gabinetes & Departamentos)
        $this->gabinete = Gabinete::create([
            'nome' => 'Gabinete Principal',
            'sigla' => 'GP',
        ]);

        $this->departamentoOrigem = Departamento::create([
            'nome' => 'Departamento de TI',
            'sigla' => 'DTI',
            'gabinete_id' => $this->gabinete->id,
        ]);

        $this->departamentoDestino = Departamento::create([
            'nome' => 'Recursos Humanos',
            'sigla' => 'RH',
            'gabinete_id' => $this->gabinete->id,
        ]);

        // 2. Setup Roles & Permissions
        $roleAdmin = Role::firstOrCreate(['name' => 'admin']);
        $roleChefe = Role::firstOrCreate(['name' => 'chefe-departamento']);
        $roleUser = Role::firstOrCreate(['name' => 'user']); // Normal user

        // Conceder permissões Spatie ao papel de chefe (linha de roles partilhada)
        $spatieChefe = \Spatie\Permission\Models\Role::findByName($roleChefe->name, 'web');
        $spatieChefe->givePermissionTo(
            Permission::findOrCreate('visto_departamento_requisicoes', 'web'),
            Permission::findOrCreate('requisicoes.aprovar', 'web'),
        );

        // 3. Create Users
        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'departamento_id' => $this->departamentoOrigem->id,
            'role_id' => $roleAdmin->id,
        ]);

        $this->chefeOrigem = User::factory()->create([
            'name' => 'Chefe TI',
            'email' => 'chefe.ti@test.com',
            'departamento_id' => $this->departamentoOrigem->id,
            'role_id' => $roleChefe->id,
        ]);

        $this->chefeDestino = User::factory()->create([
            'name' => 'Chefe RH',
            'email' => 'chefe.rh@test.com',
            'departamento_id' => $this->departamentoDestino->id,
            'role_id' => $roleChefe->id,
        ]);

        $this->funcionario = User::factory()->create([
            'name' => 'Funcionario',
            'email' => 'func@test.com',
            'departamento_id' => $this->departamentoOrigem->id,
            'role_id' => $roleUser->id,
        ]);
    }

    public function test_fluxo_completo_gestao_documental()
    {
        Storage::fake(config('filesystems.docs_disk'));

        // 1. Criar Documento de Entrada (Funcionario)
        $response = $this->actingAs($this->funcionario)->post(route('documentos-entradas.store'), [
            'assunto' => 'Documento de Teste Usabilidade',
            'departamento_id' => $this->departamentoOrigem->id,
            'classificacao_especie' => 'Ofício',
            'arquivo' => UploadedFile::fake()->create('documento.pdf', 100),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('documentos_entradas', [
            'assunto' => 'Documento de Teste Usabilidade',
            'status' => DocumentoStatus::REGISTRADO->value,
        ]);

        $doc = DocumentoEntrada::where('assunto', 'Documento de Teste Usabilidade')->first();
        $this->assertNotNull($doc->arquivo_caminho);

        // 2. Encaminhar para outro departamento (Chefe Origem)
        $response = $this->actingAs($this->chefeOrigem)->post(route('documentos-entradas.encaminhar', $doc), [
            'destino_departamento_id' => $this->departamentoDestino->id,
            'observacao' => 'Encaminhando para análise.',
        ]);

        $response->assertRedirect();
        $this->assertEquals(DocumentoStatus::ENCAMINHADO->value, $doc->refresh()->status);

        // 3. Receber no departamento destino (Chefe Destino)
        $encaminhamento = $doc->encaminhamentos()->whereNull('recebido_em')->first();
        $this->assertNotNull($encaminhamento);

        $response = $this->actingAs($this->chefeDestino)->patch(route('documentos-entradas.encaminhamentos.receber', [$doc, $encaminhamento]));

        $response->assertRedirect();
        $this->assertEquals(DocumentoStatus::RECEBIDO->value, $doc->refresh()->status);
        $this->assertEquals($this->departamentoDestino->id, $doc->departamento_id);

        // 4. Criar Tarefa no documento (Chefe Destino)
        $response = $this->actingAs($this->chefeDestino)->post(route('documentos-entradas.tarefas.store', $doc), [
            'tipo' => 'usuario',
            'destino_ids' => [$this->chefeDestino->id],
            'titulo' => 'Analisar Documento',
            'descricao' => 'Verificar conteúdo.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('documento_tarefas', [
            'documento_entrada_id' => $doc->id,
            'titulo' => 'Analisar Documento',
            'status' => 'pendente',
        ]);
    }

    public function test_fluxo_completo_requisicao()
    {
        // 1. Criar Requisição (Funcionario)
        $response = $this->actingAs($this->funcionario)->post(route('requisicoes.store'), [
            'tipo' => 'produto',
            'empresa_destinataria' => 'Fornecedor XYZ',
            'observacoes' => 'Preciso de canetas.',
        ]);

        // Check redirection (can be to add items or show)
        $response->assertStatus(302);

        $requisicao = Requisicao::where('empresa_destinataria', 'Fornecedor XYZ')->first();
        $this->assertNotNull($requisicao);
        $this->assertEquals(StatusRequisicao::PENDENTE, $requisicao->status);

        // 2. Visto do Departamento (Chefe Origem)
        // Ensure user has permission via Role (already done in setUp)

        $response = $this->actingAs($this->chefeOrigem)->patch(route('requisicoes.visto.aprovar', $requisicao), [
            'observacao' => 'Aprovado pelo chefe.',
        ]);

        $response->assertRedirect();
        $this->assertEquals('aprovado', $requisicao->refresh()->visto_departamento_status);

        // 3. Aprovação Final (Admin)
        $response = $this->actingAs($this->admin)->patch(route('requisicoes.aprovar', $requisicao));

        $response->assertRedirect();
        $this->assertEquals(StatusRequisicao::APROVADO, $requisicao->refresh()->status);
    }
}
