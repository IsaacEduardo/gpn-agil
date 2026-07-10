<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoColaborador;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\User;
use App\Notifications\ConviteColaboracaoNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ColaboracaoDocumentoTest extends TestCase
{
    use RefreshDatabase;

    protected Gabinete $gabineteA;

    protected Gabinete $gabineteB;

    protected Departamento $depA;

    protected Departamento $depB;

    protected DocumentoEspecie $especie;

    protected User $autor;

    protected User $colegaA;   // mesmo gabinete do documento

    protected User $forasteiro; // gabinete diferente

    protected DocumentoInterno $doc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gabineteA = Gabinete::create(['nome' => 'Gabinete A']);
        $this->gabineteB = Gabinete::create(['nome' => 'Gabinete B']);
        $this->depA = Departamento::create(['nome' => 'Dep A', 'sigla' => 'DA', 'gabinete_id' => $this->gabineteA->id]);
        $this->depB = Departamento::create(['nome' => 'Dep B', 'sigla' => 'DB', 'gabinete_id' => $this->gabineteB->id]);

        $this->especie = DocumentoEspecie::create(['nome' => 'MEMORANDO', 'descricao' => 'Memo', 'ativo' => true]);

        $this->autor = User::factory()->create(['name' => 'Autor', 'departamento_id' => $this->depA->id]);
        $this->colegaA = User::factory()->create(['name' => 'Colega A', 'departamento_id' => $this->depA->id]);
        $this->forasteiro = User::factory()->create(['name' => 'Forasteiro', 'departamento_id' => $this->depB->id]);

        $this->doc = DocumentoInterno::create([
            'titulo' => 'Doc Colaborativo',
            'conteudo_final' => '<p>Inicial</p>',
            'status' => DocumentoStatus::RASCUNHO,
            'criado_por' => $this->autor->id,
            'departamento_id' => $this->depA->id,
            'documento_especie_id' => $this->especie->id,
            'numero_referencia' => 'COLAB/001',
        ]);
    }

    public function test_autor_acede_ao_estado_inicial(): void
    {
        $this->actingAs($this->autor)
            ->getJson(route('documentos-internos.collab.state', $this->doc))
            ->assertOk()
            ->assertJsonStructure(['updates', 'html', 'hasState']);
    }

    public function test_forasteiro_de_outro_gabinete_e_bloqueado(): void
    {
        $this->actingAs($this->forasteiro)
            ->getJson(route('documentos-internos.collab.state', $this->doc))
            ->assertForbidden();
    }

    public function test_colaborador_com_nivel_editar_pode_sincronizar(): void
    {
        DocumentoColaborador::create([
            'documento_interno_id' => $this->doc->id,
            'user_id' => $this->colegaA->id,
            'nivel' => 'editar',
            'convidado_por' => $this->autor->id,
        ]);

        $this->actingAs($this->colegaA)
            ->postJson(route('documentos-internos.collab.sync', $this->doc), [
                'update' => base64_encode('delta-yjs'),
                'html' => '<p>Editado pelo colega</p>',
            ])
            ->assertOk();

        // Autosave persiste o HTML SEM criar versão.
        $this->assertStringContainsString('Editado pelo colega', $this->doc->fresh()->conteudo_final);
        $this->assertDatabaseCount('documento_versaos', 0);
        $this->assertDatabaseHas('documento_collab_updates', ['documento_interno_id' => $this->doc->id]);
    }

    public function test_colaborador_apenas_visualizar_nao_pode_sincronizar(): void
    {
        DocumentoColaborador::create([
            'documento_interno_id' => $this->doc->id,
            'user_id' => $this->colegaA->id,
            'nivel' => 'visualizar',
            'convidado_por' => $this->autor->id,
        ]);

        // Pode ver o estado...
        $this->actingAs($this->colegaA)
            ->getJson(route('documentos-internos.collab.state', $this->doc))
            ->assertOk();

        // ...mas não pode editar.
        $this->actingAs($this->colegaA)
            ->postJson(route('documentos-internos.collab.sync', $this->doc), [
                'update' => base64_encode('delta'),
            ])
            ->assertForbidden();
    }

    public function test_checkpoint_cria_versao_e_sanitiza_html(): void
    {
        $this->actingAs($this->autor)
            ->postJson(route('documentos-internos.collab.checkpoint', $this->doc), [
                'html' => '<p>Versão oficial</p><script>alert(1)</script>',
                'change_type' => 'minor',
                'change_log' => 'Checkpoint de teste',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $fresh = $this->doc->fresh();
        $this->assertDatabaseCount('documento_versaos', 1);
        // O sanitizador do projeto preserva o texto (codificando acentos em entidades) e remove
        // a tag <script> (defesa server-side contra payloads enviados diretamente ao endpoint).
        $this->assertStringContainsString('oficial', $fresh->conteudo_final);
        $this->assertStringNotContainsString('<script', $fresh->conteudo_final);
    }

    public function test_administrador_convida_colega_do_mesmo_gabinete(): void
    {
        Notification::fake();

        $this->actingAs($this->autor)
            ->postJson(route('documentos-internos.collab.convidar', $this->doc), [
                'user_id' => $this->colegaA->id,
                'nivel' => 'editar',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('documento_colaboradores', [
            'documento_interno_id' => $this->doc->id,
            'user_id' => $this->colegaA->id,
            'nivel' => 'editar',
        ]);

        Notification::assertSentTo($this->colegaA, ConviteColaboracaoNotification::class);
    }

    public function test_nao_pode_convidar_utilizador_de_outro_gabinete(): void
    {
        $this->actingAs($this->autor)
            ->postJson(route('documentos-internos.collab.convidar', $this->doc), [
                'user_id' => $this->forasteiro->id,
                'nivel' => 'editar',
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('documento_colaboradores', [
            'documento_interno_id' => $this->doc->id,
            'user_id' => $this->forasteiro->id,
        ]);
    }

    public function test_colaborador_sem_nivel_administrar_nao_convida(): void
    {
        DocumentoColaborador::create([
            'documento_interno_id' => $this->doc->id,
            'user_id' => $this->colegaA->id,
            'nivel' => 'editar',
            'convidado_por' => $this->autor->id,
        ]);

        $outro = User::factory()->create(['departamento_id' => $this->depA->id]);

        $this->actingAs($this->colegaA)
            ->postJson(route('documentos-internos.collab.convidar', $this->doc), [
                'user_id' => $outro->id,
                'nivel' => 'editar',
            ])
            ->assertForbidden();
    }

    public function test_documento_nao_rascunho_bloqueia_colaboracao(): void
    {
        $this->doc->update(['status' => DocumentoStatus::EM_ANALISE]);

        $this->actingAs($this->autor)
            ->getJson(route('documentos-internos.collab.state', $this->doc))
            ->assertForbidden();
    }
}
