<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Http\Controllers\DocumentoColaboracaoController;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\DocumentoVersao;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 1: nenhuma edição (clássica ou colaborativa) pode perder o original nem degradar
 * documentos com marcadores estruturados.
 */
class ProtecaoColaboracaoTest extends TestCase
{
    use RefreshDatabase;

    private const ESTRUTURADO = '<div style="margin: 0 0 0 55%;">Ao<br>'
        .'<span class="campo-vinculado campo-nome">RODRIGUES JAMBA</span></div>'
        .'<p><span class="ref-nossa-referencia">1/SEC.GOV.PROV.HLA.DLP/2026</span></p>';

    private User $autor;

    private Departamento $dep;

    private DocumentoEspecie $especie;

    protected function setUp(): void
    {
        parent::setUp();

        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $this->dep = Departamento::create(['nome' => 'Dep A', 'sigla' => 'DA', 'gabinete_id' => $gab->id]);
        $this->especie = DocumentoEspecie::firstOrCreate(['nome' => 'Memorando'], ['ativo' => true]);
        $this->autor = User::factory()->create(['departamento_id' => $this->dep->id]);
    }

    private function documento(string $conteudo): DocumentoInterno
    {
        return DocumentoInterno::create([
            'titulo' => 'Doc',
            'conteudo_final' => $conteudo,
            'status' => DocumentoStatus::RASCUNHO,
            'criado_por' => $this->autor->id,
            'departamento_id' => $this->dep->id,
            'documento_especie_id' => $this->especie->id,
            'numero_referencia' => 'PROT/'.uniqid(),
        ]);
    }

    public function test_store_cria_versao_inicial(): void
    {
        $this->actingAs($this->autor)->post(route('documentos-internos.store'), [
            'titulo' => 'Memorando novo',
            'documento_especie_id' => $this->especie->id,
            'conteudo_final' => '<p>Texto original</p>',
        ])->assertRedirect();

        $doc = DocumentoInterno::latest('id')->first();
        $versao = DocumentoVersao::where('documento_interno_id', $doc->id)->sole();
        $this->assertSame('Versão inicial', $versao->change_log);
        $this->assertSame($doc->conteudo_final, $versao->conteudo_final);
    }

    public function test_primeira_abertura_colaborativa_cria_salvaguarda_uma_so_vez(): void
    {
        $doc = $this->documento('<p>Conteúdo sem versão</p>');

        $this->actingAs($this->autor)->getJson(route('documentos-internos.collab.state', $doc))->assertOk();
        $this->actingAs($this->autor)->getJson(route('documentos-internos.collab.state', $doc))->assertOk();

        $versoes = DocumentoVersao::where('documento_interno_id', $doc->id)->get();
        $this->assertCount(1, $versoes);
        $this->assertSame('Salvaguarda antes da edição colaborativa', $versoes->first()->change_log);
        $this->assertSame('<p>Conteúdo sem versão</p>', $versoes->first()->conteudo_final);
    }

    public function test_seed_e_registado_sem_alterar_o_documento_e_so_com_log_vazio(): void
    {
        $doc = $this->documento('<p>Original</p>');

        $this->actingAs($this->autor)->postJson(route('documentos-internos.collab.sync', $doc), [
            'update' => base64_encode('seed-1'), 'seed' => true,
        ])->assertOk();
        $this->assertSame('<p>Original</p>', $doc->fresh()->conteudo_final);
        $this->assertDatabaseCount('documento_collab_updates', 1);

        // Segundo seed concorrente: recusado, para não duplicar o conteúdo no CRDT.
        $this->actingAs($this->autor)->postJson(route('documentos-internos.collab.sync', $doc), [
            'update' => base64_encode('seed-2'), 'seed' => true,
        ])->assertStatus(409)->assertJson(['seed_rejeitado' => true]);
        $this->assertDatabaseCount('documento_collab_updates', 1);
    }

    public function test_sync_com_html_degradado_nao_altera_o_documento_mas_regista_o_update(): void
    {
        $doc = $this->documento(self::ESTRUTURADO);

        $this->actingAs($this->autor)->postJson(route('documentos-internos.collab.sync', $doc), [
            'update' => base64_encode('delta'),
            'html' => '<p>Ao<br>RODRIGUES JAMBA</p><p>1/SEC.GOV.PROV.HLA.DLP/2026</p>',
        ])->assertOk()->assertJson(['html_rejeitado' => true]);

        $this->assertSame(self::ESTRUTURADO, $doc->fresh()->conteudo_final);
        $this->assertDatabaseHas('documento_collab_updates', ['documento_interno_id' => $doc->id]);
    }

    public function test_sync_que_preserva_os_marcadores_continua_a_gravar(): void
    {
        $doc = $this->documento(self::ESTRUTURADO);
        $novo = str_replace('Ao<br>', 'Ao Exmo.<br>', self::ESTRUTURADO);

        $this->actingAs($this->autor)->postJson(route('documentos-internos.collab.sync', $doc), [
            'update' => base64_encode('delta'), 'html' => $novo,
        ])->assertOk()->assertJsonMissing(['html_rejeitado' => true]);

        $this->assertStringContainsString('Ao Exmo.', $doc->fresh()->conteudo_final);
    }

    public function test_checkpoint_degradado_e_recusado_com_422(): void
    {
        $doc = $this->documento(self::ESTRUTURADO);

        $this->actingAs($this->autor)->postJson(route('documentos-internos.collab.checkpoint', $doc), [
            'html' => '<p>RODRIGUES JAMBA</p>',
        ])->assertStatus(422)->assertJsonFragment(['message' => 'Não foi possível guardar a versão: o conteúdo perderia campos do modelo (Assunto, destinatário ou referência). Use o editor clássico para alterar esses campos.']);

        $this->assertSame(self::ESTRUTURADO, $doc->fresh()->conteudo_final);
    }

    public function test_documento_estruturado_segue_o_interruptor_do_editor(): void
    {
        $doc = $this->documento(self::ESTRUTURADO);
        $resposta = $this->actingAs($this->autor)->get(route('documentos-internos.collab.editor', $doc));

        if (DocumentoColaboracaoController::EDITOR_PRESERVA_ESTRUTURA) {
            // O esquema preserva os marcadores: abre, com os campos do destinatário.
            $resposta->assertOk()->assertSee('collab-destinatario_nome', false);
        } else {
            $resposta->assertRedirect(route('documentos-internos.edit', $doc))->assertSessionHas('warning');
        }
    }

    public function test_campos_do_destinatario_sao_gravados_pelo_editor_colaborativo(): void
    {
        $doc = $this->documento(self::ESTRUTURADO);

        $this->actingAs($this->autor)->postJson(route('documentos-internos.collab.campos', $doc), [
            'destinatario_cargo' => 'Director Provincial',
        ])->assertOk();

        $this->assertSame('Director Provincial', $doc->fresh()->destinatario_cargo);
        $this->assertDatabaseCount('documento_versaos', 0); // autosave, como o título
    }

    public function test_campos_colaborativos_exigem_nivel_editar_e_validam(): void
    {
        $doc = $this->documento(self::ESTRUTURADO);
        $outro = User::factory()->create(['departamento_id' => $this->dep->id]);

        $this->actingAs($outro)->postJson(route('documentos-internos.collab.campos', $doc), [
            'destinatario_cargo' => 'X',
        ])->assertForbidden();

        $this->actingAs($this->autor)->postJson(route('documentos-internos.collab.campos', $doc), [
            'destinatario_cargo' => str_repeat('a', 256),
        ])->assertUnprocessable();
    }

    public function test_documento_simples_abre_o_editor_colaborativo(): void
    {
        $doc = $this->documento('<p>Memorando simples</p>');

        $this->actingAs($this->autor)->get(route('documentos-internos.collab.editor', $doc))->assertOk();
    }
}
