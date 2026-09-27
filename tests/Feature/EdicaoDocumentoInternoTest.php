<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 2: ecrã de edição (marcadores por resolver, painel de variáveis, barra de acções).
 */
class EdicaoDocumentoInternoTest extends TestCase
{
    use RefreshDatabase;

    private User $autor;

    private DocumentoInterno $doc;

    protected function setUp(): void
    {
        parent::setUp();

        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $dep = Departamento::create(['nome' => 'Dep A', 'sigla' => 'DA', 'gabinete_id' => $gab->id]);
        $especie = DocumentoEspecie::firstOrCreate(['nome' => 'Memorando'], ['ativo' => true]);
        $this->autor = User::factory()->create(['departamento_id' => $dep->id]);

        $this->doc = DocumentoInterno::create([
            'titulo' => 'Doc',
            'conteudo_final' => '<p>Texto</p>',
            'status' => DocumentoStatus::RASCUNHO,
            'criado_por' => $this->autor->id,
            'departamento_id' => $dep->id,
            'documento_especie_id' => $especie->id,
            'numero_referencia' => 'EDIT/001',
        ]);
    }

    public function test_update_recusa_marcadores_por_resolver(): void
    {
        $this->actingAs($this->autor)
            ->from(route('documentos-internos.edit', $this->doc))
            ->put(route('documentos-internos.update', $this->doc), [
                'titulo' => 'Doc',
                'conteudo_final' => '<p>Lubango, {{DATA_ATUAL}} e {{ USUARIO_NOME }}</p>',
            ])
            ->assertRedirect(route('documentos-internos.edit', $this->doc))
            ->assertSessionHasErrors(['conteudo_final' => 'O documento contém marcadores por resolver: {{DATA_ATUAL}}, {{ USUARIO_NOME }}. Substitua-os pelo texto final.']);

        $this->assertSame('<p>Texto</p>', $this->doc->fresh()->conteudo_final);
    }

    public function test_update_sem_marcadores_grava(): void
    {
        $this->actingAs($this->autor)->put(route('documentos-internos.update', $this->doc), [
            'titulo' => 'Doc',
            'conteudo_final' => '<p>Texto final</p>',
        ])->assertRedirect(route('documentos-internos.index'));

        $this->assertSame('<p>Texto final</p>', $this->doc->fresh()->conteudo_final);
    }

    public function test_ecra_de_edicao_sem_painel_de_variaveis_e_com_barra_de_accoes(): void
    {
        $this->actingAs($this->autor)->get(route('documentos-internos.edit', $this->doc))
            ->assertOk()
            ->assertDontSee('Variáveis Disponíveis')
            ->assertSee('doc-edit-actions', false)
            ->assertSee('#tab-preview.active', false);
    }

    public function test_botao_de_colaboracao_em_documento_estruturado_segue_o_interruptor(): void
    {
        if (! config('app.feature_collab')) {
            $this->markTestSkipped('FEATURE_COLLAB desligado neste ambiente.');
        }

        $this->doc->update(['conteudo_final' => '<p><span class="campo-vinculado campo-nome">X</span></p>']);
        $resposta = $this->actingAs($this->autor)->get(route('documentos-internos.edit', $this->doc))->assertOk();

        if (\App\Http\Controllers\DocumentoColaboracaoController::EDITOR_PRESERVA_ESTRUTURA) {
            $resposta->assertSee('Editar em colaboração')->assertDontSee('Colaboração indisponível para este modelo');
        } else {
            $resposta->assertSee('Colaboração indisponível para este modelo')->assertDontSee('Editar em colaboração');
        }
    }
}
