<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Events\EventoColaborativo;
use App\Models\Departamento;
use App\Models\DocumentoColaborador;
use App\Models\DocumentoCollabUpdate;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\User;
use App\Services\DocumentoCollaborationService;
use App\Services\PdfRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Correções da edição colaborativa (2026-10-03): regra única de gravação (nível E
 * estado), alterações só pelo servidor, compactação por ids e visibilidade dos
 * convidados de outro departamento do gabinete.
 */
class ColaboracaoSegurancaTest extends TestCase
{
    use RefreshDatabase;

    private User $autor;

    private User $colega;   // mesmo departamento

    private User $vizinho;  // outro departamento do mesmo gabinete

    private DocumentoInterno $doc;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([EventoColaborativo::class]);

        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $depA = Departamento::create(['nome' => 'Dep A', 'sigla' => 'DA', 'gabinete_id' => $gab->id]);
        $depA2 = Departamento::create(['nome' => 'Dep A2', 'sigla' => 'DA2', 'gabinete_id' => $gab->id]);
        $especie = DocumentoEspecie::create(['nome' => 'MEMORANDO', 'ativo' => true]);

        $this->autor = User::factory()->create(['departamento_id' => $depA->id]);
        $this->colega = User::factory()->create(['departamento_id' => $depA->id]);
        $this->vizinho = User::factory()->create(['departamento_id' => $depA2->id, 'name' => 'Vizinho Convidado']);

        $this->doc = DocumentoInterno::create([
            'titulo' => 'Doc Colaborativo',
            'conteudo_final' => '<p>Inicial</p>',
            'status' => DocumentoStatus::RASCUNHO,
            'criado_por' => $this->autor->id,
            'departamento_id' => $depA->id,
            'documento_especie_id' => $especie->id,
            'numero_referencia' => 'COLAB/SEG/001',
        ]);
    }

    private function convidar(User $user, string $nivel): void
    {
        DocumentoColaborador::create([
            'documento_interno_id' => $this->doc->id,
            'user_id' => $user->id,
            'nivel' => $nivel,
            'convidado_por' => $this->autor->id,
        ]);
    }

    /** As cinco gravações colaborativas, com dados válidos. */
    private function gravacoes(): array
    {
        return [
            'sync' => ['documentos-internos.collab.sync', ['update' => base64_encode('delta'), 'html' => '<p>Reescrito</p>']],
            'seed' => ['documentos-internos.collab.sync', ['update' => base64_encode('seed'), 'seed' => true]],
            'checkpoint' => ['documentos-internos.collab.checkpoint', ['html' => '<p>Reescrito</p>']],
            'titulo' => ['documentos-internos.collab.titulo', ['titulo' => 'Outro título']],
            'campos' => ['documentos-internos.collab.campos', ['destinatario_nome' => 'Outro']],
        ];
    }

    // --- Regra única de gravação ------------------------------------------------------

    public function test_fora_de_rascunho_nenhuma_gravacao_e_aceite(): void
    {
        $estados = [
            'em análise' => ['status' => DocumentoStatus::EM_ANALISE],
            'aprovado e bloqueado' => ['status' => DocumentoStatus::APROVADO, 'bloqueado_edicao' => true],
            'assinado' => ['status' => DocumentoStatus::ASSINADO, 'assinado_em' => now(), 'bloqueado_edicao' => true],
        ];

        foreach ($estados as $nome => $campos) {
            $this->doc->forceFill(['status' => DocumentoStatus::RASCUNHO, 'bloqueado_edicao' => false, 'assinado_em' => null])->saveQuietly();
            $this->doc->forceFill($campos)->saveQuietly();

            foreach ($this->gravacoes() as $acao => [$rota, $dados]) {
                $this->actingAs($this->autor)->postJson(route($rota, $this->doc), $dados)
                    ->assertStatus(409)
                    ->assertJson(['sessao_encerrada' => true], "{$acao} com o documento {$nome}");
            }

            $fresco = $this->doc->fresh();
            $this->assertSame('<p>Inicial</p>', $fresco->conteudo_final, "conteúdo intacto ({$nome})");
            $this->assertSame('Doc Colaborativo', $fresco->titulo);
            $this->assertSame(0, DocumentoCollabUpdate::where('documento_interno_id', $this->doc->id)->count());
        }
    }

    public function test_visualizar_e_comentar_nao_gravam(): void
    {
        foreach (['visualizar', 'comentar'] as $nivel) {
            DocumentoColaborador::where('documento_interno_id', $this->doc->id)->delete();
            $this->convidar($this->colega, $nivel);

            foreach ($this->gravacoes() as $acao => [$rota, $dados]) {
                $this->actingAs($this->colega)->postJson(route($rota, $this->doc), $dados)
                    ->assertForbidden();
            }
        }

        $this->assertSame('<p>Inicial</p>', $this->doc->fresh()->conteudo_final);
        Event::assertNotDispatched(EventoColaborativo::class, fn ($e) => $e->tipo === EventoColaborativo::ALTERACAO);
    }

    public function test_colaborador_removido_deixa_de_gravar(): void
    {
        $this->convidar($this->colega, 'editar');
        $this->actingAs($this->colega)->postJson(route('documentos-internos.collab.sync', $this->doc), ['update' => base64_encode('a')])->assertOk();

        app(DocumentoCollaborationService::class)->remover($this->doc, $this->colega);

        $this->actingAs($this->colega)->postJson(route('documentos-internos.collab.sync', $this->doc), ['update' => base64_encode('b')])
            ->assertForbidden();
        Event::assertDispatched(EventoColaborativo::class, fn ($e) => $e->tipo === EventoColaborativo::PERMISSOES
            && $e->dados['user_id'] === $this->colega->id);
    }

    // --- Alterações pelo servidor -------------------------------------------------------

    public function test_sync_aceite_grava_e_retransmite_com_o_id(): void
    {
        $resposta = $this->actingAs($this->autor)
            ->postJson(route('documentos-internos.collab.sync', $this->doc), ['update' => base64_encode('delta-1')])
            ->assertOk();

        $id = $resposta->json('id');
        $this->assertNotNull($id);
        $this->assertSame(base64_encode('delta-1'), DocumentoCollabUpdate::find($id)->update);
        Event::assertDispatched(EventoColaborativo::class, fn ($e) => $e->tipo === EventoColaborativo::ALTERACAO
            && $e->documentoId === $this->doc->id
            && $e->dados['id'] === $id
            && $e->dados['update'] === base64_encode('delta-1'));
    }

    public function test_update_grande_segue_so_com_o_id(): void
    {
        $grande = str_repeat('A', DocumentoCollaborationService::MAX_UPDATE_NA_MENSAGEM + 1);

        $this->actingAs($this->autor)
            ->postJson(route('documentos-internos.collab.sync', $this->doc), ['update' => $grande])
            ->assertOk();

        Event::assertDispatched(EventoColaborativo::class, fn ($e) => $e->tipo === EventoColaborativo::ALTERACAO
            && ! array_key_exists('update', $e->dados));
    }

    public function test_html_sozinho_e_aceite_sem_criar_linha_no_log(): void
    {
        $this->actingAs($this->autor)
            ->postJson(route('documentos-internos.collab.sync', $this->doc), ['html' => '<p>Apenas o HTML</p>'])
            ->assertOk()
            ->assertJson(['id' => null]);

        $this->assertStringContainsString('Apenas o HTML', $this->doc->fresh()->conteudo_final);
        $this->assertSame(0, DocumentoCollabUpdate::where('documento_interno_id', $this->doc->id)->count());
    }

    public function test_updates_devolve_o_log_com_os_ids(): void
    {
        $a = app(DocumentoCollaborationService::class)->registarUpdate($this->doc, $this->autor, 'AAA');
        $b = app(DocumentoCollaborationService::class)->registarUpdate($this->doc, $this->autor, 'BBB');

        $this->actingAs($this->autor)->getJson(route('documentos-internos.collab.updates', $this->doc))
            ->assertOk()
            ->assertExactJson(['updates' => ['AAA', 'BBB'], 'ids' => [$a->id, $b->id]]);
    }

    // --- Compactação por ids -------------------------------------------------------------

    public function test_checkpoint_apaga_so_os_ids_aplicados(): void
    {
        $servico = app(DocumentoCollaborationService::class);
        $visto = $servico->registarUpdate($this->doc, $this->autor, 'VISTO');
        $porChegar = $servico->registarUpdate($this->doc, $this->colega, 'AINDA-NAO-CHEGOU');

        $resposta = $this->actingAs($this->autor)->postJson(route('documentos-internos.collab.checkpoint', $this->doc), [
            'html' => '<p>Versão</p>',
            'snapshot' => 'SNAPSHOT',
            'ids_aplicados' => [$visto->id],
        ])->assertOk();

        $restantes = DocumentoCollabUpdate::where('documento_interno_id', $this->doc->id)->orderBy('id')->pluck('update', 'id');
        $this->assertFalse($restantes->has($visto->id), 'o aplicado é compactado');
        $this->assertTrue($restantes->has($porChegar->id), 'o que o cliente não tinha sobrevive');
        $this->assertSame('SNAPSHOT', $restantes[$resposta->json('snapshot_id')]);
    }

    // --- Fim da sessão -----------------------------------------------------------------------

    public function test_sair_de_rascunho_avisa_as_sessoes_abertas(): void
    {
        $this->doc->update(['status' => DocumentoStatus::EM_ANALISE]);

        Event::assertDispatched(EventoColaborativo::class, fn ($e) => $e->tipo === EventoColaborativo::ENCERRADA
            && $e->documentoId === $this->doc->id);
    }

    public function test_editar_em_rascunho_nao_encerra(): void
    {
        $this->doc->update(['titulo' => 'Só o título']);

        Event::assertNotDispatched(EventoColaborativo::class, fn ($e) => $e->tipo === EventoColaborativo::ENCERRADA);
    }

    // --- Convidado de outro departamento -------------------------------------------------

    public function test_convidado_de_outro_departamento_ve_enquanto_colabora(): void
    {
        $this->mock(PdfRenderService::class, fn ($m) => $m->shouldReceive('createPdfResponse')
            ->andReturn(response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf'])));

        $this->actingAs($this->vizinho)->get(route('documentos-internos.show', $this->doc))->assertForbidden();

        $this->convidar($this->vizinho, 'editar');

        $this->actingAs($this->vizinho)->get(route('documentos-internos.show', $this->doc))->assertOk();
        $this->actingAs($this->vizinho)->get(route('documentos-internos.pdf', $this->doc))->assertOk();
        $this->actingAs($this->vizinho)->get(route('documentos-internos.index', ['tab' => 'partilhados']))
            ->assertOk()
            ->assertSee('Partilhados comigo')
            ->assertSee('Doc Colaborativo');

        app(DocumentoCollaborationService::class)->remover($this->doc, $this->vizinho);

        $this->actingAs($this->vizinho)->get(route('documentos-internos.show', $this->doc))->assertForbidden();
        $this->actingAs($this->vizinho)->get(route('documentos-internos.pdf', $this->doc))->assertForbidden();
    }
}
