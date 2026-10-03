<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Events\EventoColaborativo;
use App\Jobs\EnviarResumoEdicoesColaborativas;
use App\Models\Departamento;
use App\Models\DocumentoColaborador;
use App\Models\DocumentoCollabUpdate;
use App\Models\DocumentoComentario;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\DocumentoVersao;
use App\Models\Gabinete;
use App\Models\User;
use App\Notifications\ComentarioColaborativoNotification;
use App\Notifications\EdicaoColaborativaNotification;
use App\Services\DocumentoCollaborationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Elevação da edição colaborativa (2026-10-03): autoria das versões, comparação entre
 * versões, compactação automática, comentários e resumo de edições para o autor.
 */
class ColaboracaoElevacaoTest extends TestCase
{
    use RefreshDatabase;

    private User $autor;

    private User $editor;

    private User $comentador;

    private User $leitor;

    private User $forasteiro;

    private DocumentoInterno $doc;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([EventoColaborativo::class]);

        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $dep = Departamento::create(['nome' => 'Dep A', 'sigla' => 'DA', 'gabinete_id' => $gab->id]);
        $outroGab = Gabinete::create(['nome' => 'Gabinete B']);
        $outroDep = Departamento::create(['nome' => 'Dep B', 'sigla' => 'DB', 'gabinete_id' => $outroGab->id]);
        $especie = DocumentoEspecie::create(['nome' => 'MEMORANDO', 'ativo' => true]);

        $this->autor = User::factory()->create(['departamento_id' => $dep->id, 'name' => 'Ana Autora']);
        $this->editor = User::factory()->create(['departamento_id' => $dep->id, 'name' => 'Edu Editor']);
        $this->comentador = User::factory()->create(['departamento_id' => $dep->id, 'name' => 'Carla Comenta']);
        $this->leitor = User::factory()->create(['departamento_id' => $dep->id, 'name' => 'Lia Leitora']);
        $this->forasteiro = User::factory()->create(['departamento_id' => $outroDep->id]);

        $this->doc = DocumentoInterno::create([
            'titulo' => 'Doc Colaborativo',
            'conteudo_final' => '<p>Primeiro parágrafo.</p><p>Segundo parágrafo antigo.</p>',
            'status' => DocumentoStatus::RASCUNHO,
            'criado_por' => $this->autor->id,
            'departamento_id' => $dep->id,
            'documento_especie_id' => $especie->id,
            'numero_referencia' => 'COLAB/ELEV/001',
        ]);

        foreach ([[$this->editor, 'editar'], [$this->comentador, 'comentar'], [$this->leitor, 'visualizar']] as [$user, $nivel]) {
            DocumentoColaborador::create([
                'documento_interno_id' => $this->doc->id,
                'user_id' => $user->id,
                'nivel' => $nivel,
                'convidado_por' => $this->autor->id,
            ]);
        }
    }

    private function sync(User $user, string $update = 'delta')
    {
        return $this->actingAs($user)->postJson(route('documentos-internos.collab.sync', $this->doc), ['update' => base64_encode($update)]);
    }

    // --- Autoria e histórico ----------------------------------------------------------

    public function test_versao_regista_quem_contribuiu(): void
    {
        $idEditor = $this->sync($this->editor, 'do-editor')->json('id');
        $idAutor = $this->sync($this->autor, 'da-autora')->json('id');

        $this->actingAs($this->autor)->postJson(route('documentos-internos.collab.checkpoint', $this->doc), [
            'html' => '<p>Versão conjunta</p>',
            'snapshot' => 'SNAP',
            'ids_aplicados' => [$idEditor, $idAutor],
        ])->assertOk();

        $versao = DocumentoVersao::where('documento_interno_id', $this->doc->id)->latest('id')->first();
        $this->assertEqualsCanonicalizing([$this->editor->id, $this->autor->id], $versao->contribuidores);
        $this->assertSame(['Edu Editor'], $versao->nomesDosContribuidores(), 'quem guardou não se repete');

        $this->actingAs($this->autor)->get(route('documentos-internos.show', $this->doc))
            ->assertOk()
            ->assertSee('com Edu Editor');
    }

    public function test_comparacao_mostra_o_acrescentado_e_o_removido_escapados(): void
    {
        $servico = app(\App\Services\DocumentoInternoService::class);
        $servico->updateWithVersioning($this->doc, ['titulo' => 'Doc', 'conteudo_final' => '<p>Primeiro parágrafo.</p><p>Segundo parágrafo antigo.</p>'], $this->autor, 'patch');
        $servico->updateWithVersioning($this->doc->fresh(), ['titulo' => 'Doc', 'conteudo_final' => '<p>Primeiro parágrafo.</p><p>Segundo parágrafo novo &lt;script&gt;x&lt;/script&gt;.</p>'], $this->autor, 'patch');
        $versao = DocumentoVersao::where('documento_interno_id', $this->doc->id)->latest('id')->first();

        $html = $this->actingAs($this->editor)
            ->getJson(route('documentos-internos.versoes.diff', [$this->doc->id, $versao->versao]))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('<del>antigo.</del>', $html);
        $this->assertStringContainsString('<ins>novo</ins>', $html);
        $this->assertStringNotContainsString('<script>', $html, 'o texto vem escapado');

        $this->actingAs($this->forasteiro)
            ->getJson(route('documentos-internos.versoes.diff', [$this->doc->id, $versao->versao]))
            ->assertForbidden();
    }

    // --- Compactação automática ---------------------------------------------------------

    public function test_servidor_pede_compactacao_acima_do_limite(): void
    {
        $this->assertFalse($this->sync($this->editor)->json('compactar'));

        $linhas = array_fill(0, DocumentoCollaborationService::LIMITE_LOG, [
            'documento_interno_id' => $this->doc->id,
            'user_id' => $this->editor->id,
            'update' => 'x',
            'is_snapshot' => false,
            'created_at' => now(),
        ]);
        DocumentoCollabUpdate::insert($linhas);

        $this->assertTrue($this->sync($this->editor)->json('compactar'));
    }

    public function test_compactar_apaga_so_os_aplicados_e_nao_cria_versao(): void
    {
        $visto = $this->sync($this->editor, 'visto')->json('id');
        $porChegar = $this->sync($this->autor, 'por-chegar')->json('id');

        $this->actingAs($this->editor)->postJson(route('documentos-internos.collab.compactar', $this->doc), [
            'snapshot' => 'SNAP',
            'ids_aplicados' => [$visto],
        ])->assertOk()->assertJsonStructure(['snapshot_id']);

        $this->assertNull(DocumentoCollabUpdate::find($visto));
        $this->assertNotNull(DocumentoCollabUpdate::find($porChegar));
        $this->assertSame(0, DocumentoVersao::where('documento_interno_id', $this->doc->id)->count());

        $this->doc->forceFill(['status' => DocumentoStatus::EM_ANALISE])->saveQuietly();
        $this->actingAs($this->editor)->postJson(route('documentos-internos.collab.compactar', $this->doc), [
            'snapshot' => 'SNAP', 'ids_aplicados' => [$porChegar],
        ])->assertStatus(409);
    }

    // --- Comentários --------------------------------------------------------------------

    public function test_comentar_responder_e_notificar(): void
    {
        Notification::fake();

        $raiz = $this->actingAs($this->comentador)->postJson(route('documentos-internos.collab.comentarios.store', $this->doc), [
            'texto' => 'Este parágrafo está ambíguo.',
            'trecho' => 'Segundo parágrafo antigo.',
        ])->assertCreated()->json('id');
        Notification::assertSentTo($this->autor, ComentarioColaborativoNotification::class);

        $this->actingAs($this->editor)->postJson(route('documentos-internos.collab.comentarios.store', $this->doc), [
            'texto' => 'Concordo, vou rever.',
            'parent_id' => $raiz,
        ])->assertCreated();
        Notification::assertSentTo($this->comentador, ComentarioColaborativoNotification::class);
        Notification::assertNotSentTo($this->editor, ComentarioColaborativoNotification::class);

        $conversas = $this->actingAs($this->leitor)->getJson(route('documentos-internos.collab.comentarios', $this->doc))
            ->assertOk()
            ->assertJson(['pode_comentar' => false])
            ->json('conversas');
        $this->assertCount(1, $conversas);
        $this->assertSame('Segundo parágrafo antigo.', $conversas[0]['trecho']);
        $this->assertSame('Concordo, vou rever.', $conversas[0]['respostas'][0]['texto']);
        Event::assertDispatched(EventoColaborativo::class, fn ($e) => $e->tipo === EventoColaborativo::COMENTARIOS);
    }

    public function test_visualizar_nao_comenta_e_fora_de_rascunho_ninguem_comenta(): void
    {
        $this->actingAs($this->leitor)->postJson(route('documentos-internos.collab.comentarios.store', $this->doc), ['texto' => 'x'])
            ->assertForbidden();

        $this->doc->forceFill(['status' => DocumentoStatus::EM_ANALISE])->saveQuietly();
        $this->actingAs($this->comentador)->postJson(route('documentos-internos.collab.comentarios.store', $this->doc), ['texto' => 'x'])
            ->assertForbidden();
    }

    public function test_resolver_e_reabrir(): void
    {
        $raiz = DocumentoComentario::create([
            'documento_interno_id' => $this->doc->id, 'user_id' => $this->comentador->id, 'texto' => 'Rever',
        ]);
        $url = route('documentos-internos.collab.comentarios.resolver', [$this->doc, $raiz]);

        // Outro com nível Comentar não resolve a conversa de alguém.
        $outro = User::factory()->create(['departamento_id' => $this->autor->departamento_id]);
        DocumentoColaborador::create(['documento_interno_id' => $this->doc->id, 'user_id' => $outro->id, 'nivel' => 'comentar', 'convidado_por' => $this->autor->id]);
        $this->actingAs($outro)->patchJson($url, ['resolvido' => true])->assertForbidden();

        $this->actingAs($this->editor)->patchJson($url, ['resolvido' => true])->assertOk();
        $this->assertNotNull($raiz->fresh()->resolvido_em);
        $this->assertSame($this->editor->id, $raiz->fresh()->resolvido_por);

        // Uma resposta reabre a conversa.
        Notification::fake();
        $this->actingAs($this->comentador)->postJson(route('documentos-internos.collab.comentarios.store', $this->doc), [
            'texto' => 'Ainda falta.', 'parent_id' => $raiz->id,
        ])->assertCreated();
        $this->assertNull($raiz->fresh()->resolvido_em);
    }

    // --- Resumo de edições -------------------------------------------------------------------

    public function test_edicoes_de_outros_geram_um_resumo_por_janela(): void
    {
        Bus::fake([EnviarResumoEdicoesColaborativas::class]);

        $this->sync($this->autor);              // as do próprio autor não contam
        Bus::assertNotDispatched(EnviarResumoEdicoesColaborativas::class);

        $this->sync($this->editor);
        $this->sync($this->editor);
        Bus::assertDispatchedTimes(EnviarResumoEdicoesColaborativas::class, 1);
    }

    public function test_resumo_notifica_o_autor_com_os_nomes(): void
    {
        Notification::fake();
        Bus::fake([EnviarResumoEdicoesColaborativas::class]);
        $this->sync($this->editor);

        (new EnviarResumoEdicoesColaborativas($this->doc->id))->handle();

        Notification::assertSentTo($this->autor, EdicaoColaborativaNotification::class,
            fn ($n) => $n->editores === ['Edu Editor']);
    }

    // --- Tempo real só quando ligado -------------------------------------------------------------

    public function test_layout_diz_ao_browser_se_ha_tempo_real(): void
    {
        config(['app.tempo_real' => false]);
        $this->actingAs($this->autor)->get(route('documentos-internos.index'))
            ->assertSee('<meta name="tempo-real" content="0">', false);

        config(['app.tempo_real' => true]);
        $this->actingAs($this->autor)->get(route('documentos-internos.index'))
            ->assertSee('<meta name="tempo-real" content="1">', false);
    }
}
