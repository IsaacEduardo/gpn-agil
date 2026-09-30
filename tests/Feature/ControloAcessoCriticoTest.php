<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\Anexo;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Falhas críticas da auditoria de 2026-09-30: registo público aberto e
 * objectos acessíveis por ID sequencial sem verificação de acesso.
 */
class ControloAcessoCriticoTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private User $userB;

    private Departamento $depA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
        $gabA = Gabinete::create(['nome' => 'Gabinete A']);
        $gabB = Gabinete::create(['nome' => 'Gabinete B']);
        $this->depA = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gabA->id]);
        $depB = Departamento::create(['nome' => 'Departamento B', 'gabinete_id' => $gabB->id]);

        $this->userA = User::factory()->create(['role_id' => $role->id, 'departamento_id' => $this->depA->id]);
        $this->userB = User::factory()->create(['role_id' => $role->id, 'departamento_id' => $depB->id]);

        Storage::fake(config('filesystems.docs_disk'));
    }

    // --- C1: registo público ---

    public function test_registo_publico_esta_fechado(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
            'password' => 'Segura#2026',
            'password_confirmation' => 'Segura#2026',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.com']);
    }

    public function test_nao_existe_rota_web_que_execute_artisan(): void
    {
        $this->withHeader('X-Deploy-Key', 'qualquer')->get('/deploy-setup')->assertNotFound();
    }

    // --- C2: streaming de anexos ---

    public function test_anexo_de_entrada_sem_pasta_nao_e_servido_a_outro_departamento(): void
    {
        $anexo = $this->anexoDe($this->entradaDoDepA());

        $this->actingAs($this->userB)->get(route('edms.stream-attachment', $anexo->id))->assertNotFound();
        $this->actingAs($this->userA)->get(route('edms.stream-attachment', $anexo->id))->assertOk();
    }

    public function test_anexo_de_documento_interno_nao_e_servido_a_outro_departamento(): void
    {
        $anexo = $this->anexoDe($this->internoDoDepA());

        $this->actingAs($this->userB)->get(route('edms.stream-attachment', $anexo->id))->assertNotFound();
        $this->actingAs($this->userA)->get(route('edms.stream-attachment', $anexo->id))->assertOk();
    }

    // --- C3: auto-save de rascunhos ---

    public function test_auto_save_nao_altera_rascunho_de_outro_utilizador(): void
    {
        $doc = $this->internoDoDepA();

        $this->actingAs($this->userB)->postJson(route('documentos-internos.auto-save', $doc), [
            'conteudo_final' => '<p>Sobrescrito</p>',
        ])->assertForbidden();

        $this->assertDatabaseHas('documento_internos', ['id' => $doc->id, 'conteudo_final' => '<p>Original</p>']);
    }

    public function test_auto_save_nao_cria_rascunho_noutro_departamento(): void
    {
        $this->actingAs($this->userB)->postJson(route('documentos-internos.auto-save'), [
            'conteudo_final' => '<p>Novo</p>',
            'departamento_id' => $this->depA->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('documento_internos', ['departamento_id' => $this->depA->id, 'conteudo_final' => '<p>Novo</p>']);
    }

    private function entradaDoDepA(): DocumentoEntrada
    {
        return DocumentoEntrada::create([
            'numero_sequencial' => 501,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => 'Confidencial A',
            'departamento_id' => $this->depA->id,
            'user_id' => $this->userA->id,
            'status' => 'registrado',
            'origem' => 'externo',
        ]);
    }

    private function internoDoDepA(): DocumentoInterno
    {
        $especie = DocumentoEspecie::first() ?? DocumentoEspecie::create(['nome' => 'Ofício Teste', 'ativo' => true]);

        return DocumentoInterno::create([
            'numero_referencia' => 'OFI/501/2026',
            'titulo' => 'Rascunho A',
            'conteudo_final' => '<p>Original</p>',
            'departamento_id' => $this->depA->id,
            'criado_por' => $this->userA->id,
            'documento_especie_id' => $especie->id,
            'status' => DocumentoStatus::RASCUNHO,
        ]);
    }

    private function anexoDe(DocumentoEntrada|DocumentoInterno $documento): Anexo
    {
        $caminho = 'anexos/teste-'.$documento->id.'.pdf';
        Storage::disk(config('filesystems.docs_disk'))->put($caminho, '%PDF-1.4 teste');

        return Anexo::create([
            'anexavel_type' => $documento::class,
            'anexavel_id' => $documento->id,
            'nome_original' => 'confidencial.pdf',
            'caminho_arquivo' => $caminho,
            'mime_type' => 'application/pdf',
            'tamanho_bytes' => 14,
            'user_id' => $this->userA->id,
        ]);
    }
}
