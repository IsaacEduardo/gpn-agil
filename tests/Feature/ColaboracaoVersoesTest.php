<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Events\EventoColaborativo;
use App\Models\Departamento;
use App\Models\DocumentoColaborador;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\DocumentoVersao;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * "Guardar versão" na edição colaborativa (2026-10-04): sem versões repetidas, botão
 * com o estado do documento e aviso aos colegas. Antes cada clique criava uma versão
 * igual à anterior (15 cópias seguidas em produção, de 0.11.0 a 4.0.0).
 */
class ColaboracaoVersoesTest extends TestCase
{
    use RefreshDatabase;

    private User $autor;

    private User $editor;

    private DocumentoInterno $doc;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([EventoColaborativo::class]);

        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $dep = Departamento::create(['nome' => 'Dep A', 'sigla' => 'DA', 'gabinete_id' => $gab->id]);
        $especie = DocumentoEspecie::create(['nome' => 'MEMORANDO', 'ativo' => true]);

        $this->autor = User::factory()->create(['departamento_id' => $dep->id, 'name' => 'Ana Autora']);
        $this->editor = User::factory()->create(['departamento_id' => $dep->id]);

        $this->doc = DocumentoInterno::create([
            'titulo' => 'Doc Colaborativo',
            'conteudo_final' => '<p>Inicial</p>',
            'status' => DocumentoStatus::RASCUNHO,
            'criado_por' => $this->autor->id,
            'departamento_id' => $dep->id,
            'documento_especie_id' => $especie->id,
            'numero_referencia' => 'COLAB/VER/001',
        ]);
        DocumentoColaborador::create([
            'documento_interno_id' => $this->doc->id, 'user_id' => $this->editor->id,
            'nivel' => 'editar', 'convidado_por' => $this->autor->id,
        ]);
    }

    private function guardar(string $html, User $quem = null)
    {
        return $this->actingAs($quem ?? $this->autor)
            ->postJson(route('documentos-internos.collab.checkpoint', $this->doc), ['html' => $html, 'change_type' => 'major']);
    }

    private function versoes(): int
    {
        return DocumentoVersao::where('documento_interno_id', $this->doc->id)->count();
    }

    public function test_cliques_repetidos_nao_criam_versoes_iguais(): void
    {
        $this->guardar('<p>Texto novo</p>')->assertOk()->assertJson(['versao' => '1.0.0']);
        $depois = $this->versoes();

        foreach (range(1, 3) as $_) {
            $this->guardar('<p>Texto novo</p>')
                ->assertStatus(409)
                ->assertJson(['sem_alteracoes' => true, 'versao' => '1.0.0']);
        }

        $this->assertSame($depois, $this->versoes(), 'nenhuma versão repetida');
        $this->assertSame('1.0.0', $this->doc->fresh()->versao_semantica, 'a numeração não sobe sem alterações');
    }

    public function test_alterar_o_texto_ou_o_titulo_permite_nova_versao(): void
    {
        $this->guardar('<p>Texto novo</p>')->assertOk();

        $this->guardar('<p>Texto mais novo</p>')->assertOk()->assertJson(['versao' => '2.0.0']);

        // Só o título mudou (autosave do título): também é alteração.
        $this->doc->forceFill(['titulo' => 'Outro assunto'])->saveQuietly();
        $this->guardar('<p>Texto mais novo</p>')->assertOk()->assertJson(['versao' => '3.0.0']);
    }

    public function test_os_colegas_sao_avisados_da_versao_nova(): void
    {
        $this->guardar('<p>Texto novo</p>', $this->editor)->assertOk();

        Event::assertDispatched(EventoColaborativo::class, fn ($e) => $e->tipo === EventoColaborativo::VERSAO
            && $e->dados['versao'] === '1.0.0'
            && $e->dados['user_id'] === $this->editor->id);
    }

    public function test_sem_alteracoes_nao_avisa_ninguem(): void
    {
        $this->guardar('<p>Texto novo</p>')->assertOk();
        Event::fake([EventoColaborativo::class]);

        $this->guardar('<p>Texto novo</p>')->assertStatus(409);

        Event::assertNotDispatched(EventoColaborativo::class, fn ($e) => $e->tipo === EventoColaborativo::VERSAO);
    }

    public function test_botao_mostra_o_estado_do_documento(): void
    {
        // Sem nenhuma versão guardada: há o que guardar.
        $this->actingAs($this->autor)->get(route('documentos-internos.collab.editor', $this->doc))
            ->assertOk()
            ->assertSee('Guardar versão')
            ->assertSee('Há alterações desde a v');

        $this->guardar('<p>Inicial</p>')->assertOk();

        // Igual à última versão: o botão vem desactivado e diz porquê.
        $pagina = $this->actingAs($this->autor)->get(route('documentos-internos.collab.editor', $this->doc))->assertOk();
        $pagina->assertSee('Sem alterações desde a v1.0.0')
            ->assertSee('id="collab-versao-atual">v1.0.0<', false);
        $this->assertMatchesRegularExpression('/id="collab-checkpoint"[^>]*disabled/', $pagina->getContent());

        // O autosave muda o conteúdo: volta a haver o que guardar.
        $this->doc->forceFill(['conteudo_final' => '<p>Editado depois</p>'])->saveQuietly();
        $this->actingAs($this->autor)->get(route('documentos-internos.collab.editor', $this->doc))
            ->assertSee('Há alterações desde a v1.0.0');
    }
}
