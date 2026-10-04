<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Events\EventoColaborativo;
use App\Jobs\EnviarResumoEdicoesColaborativas;
use App\Models\AuditLog;
use App\Models\Departamento;
use App\Models\DocumentoColaborador;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\User;
use App\Notifications\EdicaoColaborativaNotification;
use App\Services\ActividadeColaborativaService;
use App\Support\DescricaoAuditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Colaboração (2026-10-04): os convidados chegam ao editor a partir da ficha e da lista,
 * e o histórico diz quem fez o quê (separador Auditoria e resumo enviado ao autor).
 */
class ColaboracaoActividadeTest extends TestCase
{
    use RefreshDatabase;

    private User $autor;

    private User $vizinho;   // outro departamento do mesmo gabinete

    private User $leitora;

    private DocumentoInterno $doc;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([EventoColaborativo::class]);
        Bus::fake([EnviarResumoEdicoesColaborativas::class]);

        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $dep = Departamento::create(['nome' => 'Dep A', 'sigla' => 'DA', 'gabinete_id' => $gab->id]);
        $dep2 = Departamento::create(['nome' => 'Dep A2', 'sigla' => 'DA2', 'gabinete_id' => $gab->id]);
        $especie = DocumentoEspecie::create(['nome' => 'MEMORANDO', 'ativo' => true]);

        $this->autor = User::factory()->create(['departamento_id' => $dep->id, 'name' => 'Ana Autora']);
        $this->vizinho = User::factory()->create(['departamento_id' => $dep2->id, 'name' => 'Vítor Vizinho']);
        $this->leitora = User::factory()->create(['departamento_id' => $dep->id, 'name' => 'Lia Leitora']);

        $this->doc = DocumentoInterno::create([
            'titulo' => 'Doc Colaborativo',
            'conteudo_final' => '<p>Texto inicial.</p>',
            'status' => DocumentoStatus::RASCUNHO,
            'criado_por' => $this->autor->id,
            'departamento_id' => $dep->id,
            'documento_especie_id' => $especie->id,
            'numero_referencia' => 'COLAB/ACT/001',
        ]);
    }

    private function convidar(User $user, string $nivel)
    {
        return $this->actingAs($this->autor)->postJson(route('documentos-internos.collab.convidar', $this->doc), [
            'user_id' => $user->id, 'nivel' => $nivel,
        ])->assertCreated();
    }

    private function sync(User $user)
    {
        return $this->actingAs($user)->postJson(route('documentos-internos.collab.sync', $this->doc), ['update' => base64_encode('delta')])->assertOk();
    }

    // --- 1. Os convidados chegam ao editor -------------------------------------------------

    public function test_convidado_tem_o_botao_na_ficha_e_na_lista(): void
    {
        $this->convidar($this->vizinho, 'editar');
        $this->convidar($this->leitora, 'visualizar');
        $url = route('documentos-internos.collab.editor', $this->doc);

        $this->actingAs($this->vizinho)->get(route('documentos-internos.show', $this->doc))
            ->assertOk()->assertSee($url, false)->assertSee('Editar em colaboração');
        $this->actingAs($this->vizinho)->get(route('documentos-internos.index', ['tab' => 'partilhados']))
            ->assertOk()->assertSee($url, false)->assertSee('Editar em colaboração');

        $this->actingAs($this->leitora)->get(route('documentos-internos.show', $this->doc))
            ->assertOk()->assertSee('Abrir em colaboração')->assertDontSee('Editar em colaboração');
    }

    public function test_fora_de_rascunho_nao_ha_botao_de_colaboracao(): void
    {
        $this->convidar($this->vizinho, 'editar');
        $this->doc->forceFill(['status' => DocumentoStatus::EM_ANALISE])->saveQuietly();

        // (O URL do editor continua na notificação do convite, no sino; o botão é que não aparece.)
        $this->actingAs($this->vizinho)->get(route('documentos-internos.show', $this->doc))
            ->assertOk()->assertDontSee('Editar em colaboração')->assertDontSee('Abrir em colaboração');
    }

    // --- 2. Histórico de quem fez o quê -------------------------------------------------------

    public function test_auditoria_diz_quem_fez_o_que(): void
    {
        $this->convidar($this->vizinho, 'editar');

        $this->sync($this->vizinho);
        $this->travel(6)->seconds();
        $this->sync($this->vizinho);

        $this->actingAs($this->vizinho)->postJson(route('documentos-internos.collab.titulo', $this->doc), ['titulo' => 'Novo título'])->assertOk();
        $this->actingAs($this->vizinho)->postJson(route('documentos-internos.collab.comentarios.store', $this->doc), [
            'texto' => 'Rever esta frase.', 'trecho' => 'Texto inicial.',
        ])->assertCreated();
        $this->actingAs($this->autor)->postJson(route('documentos-internos.collab.checkpoint', $this->doc), [
            'html' => '<p>Texto revisto.</p>', 'change_log' => 'Ajuste no texto',
        ])->assertOk();

        $this->assertSame(1, AuditLog::where('action', ActividadeColaborativaService::EDICAO)->count(), 'uma linha por sessão de trabalho');

        $this->actingAs($this->autor)->get(route('documentos-internos.show', $this->doc))
            ->assertOk()
            ->assertSee('Convidou Vítor Vizinho (Editar).')
            ->assertSee('Editou o texto — 2 alterações')
            ->assertSee('Mudou o título para «Novo título».')
            ->assertSee('Comentou o trecho «Texto inicial.»: «Rever esta frase.».')
            ->assertSee('Guardou a v0.1.0 na edição colaborativa — «Ajuste no texto».')
            ->assertSee('O que fez');
    }

    public function test_nova_sessao_depois_de_uma_pausa_abre_outra_linha(): void
    {
        $this->convidar($this->vizinho, 'editar');
        $this->sync($this->vizinho);
        $this->travel(ActividadeColaborativaService::JANELA_MINUTOS + 1)->minutes();
        $this->sync($this->vizinho);

        $this->assertSame(2, AuditLog::where('action', ActividadeColaborativaService::EDICAO)->count());
    }

    public function test_permissoes_ficam_no_historico(): void
    {
        $this->convidar($this->vizinho, 'editar');
        $this->actingAs($this->autor)->patchJson(route('documentos-internos.collab.colaborador.update', [$this->doc, $this->vizinho]), ['nivel' => 'comentar'])->assertOk();
        $this->actingAs($this->autor)->deleteJson(route('documentos-internos.collab.colaborador.destroy', [$this->doc, $this->vizinho]))->assertOk();

        $detalhes = $this->doc->audits()->get()->map(fn ($a) => DescricaoAuditoria::de($a)['detalhe'])->all();
        $this->assertContains('Mudou o nível de Vítor Vizinho para Comentar.', $detalhes);
        $this->assertContains('Removeu Vítor Vizinho da colaboração.', $detalhes);
    }

    public function test_accoes_antigas_tambem_ficam_legiveis(): void
    {
        $this->doc->update(['status' => DocumentoStatus::EM_ANALISE]);
        $this->doc->logAudit('print');

        $detalhes = $this->doc->audits()->get()->map(fn ($a) => DescricaoAuditoria::de($a)['detalhe'])->all();
        $this->assertContains('Criou o documento.', $detalhes);
        $this->assertContains('Mudou o estado para «'.DocumentoStatus::EM_ANALISE->label().'».', $detalhes);
        $this->assertContains('Imprimiu o documento.', $detalhes);
    }

    // --- 3. Resumo ao autor com o que cada um fez ---------------------------------------------

    public function test_resumo_diz_o_que_cada_um_fez(): void
    {
        Notification::fake();
        $this->convidar($this->vizinho, 'editar');

        foreach (range(1, 3) as $_) {
            $this->sync($this->vizinho);
            $this->travel(2)->seconds(); // a última contagem fica só na cache: o resumo lê-a de lá
        }
        $this->actingAs($this->vizinho)->postJson(route('documentos-internos.collab.comentarios.store', $this->doc), ['texto' => 'Ver isto.'])->assertCreated();

        app()->call([new EnviarResumoEdicoesColaborativas($this->doc->id), 'handle']);

        Notification::assertSentTo($this->autor, EdicaoColaborativaNotification::class, function ($n) {
            return $n->detalhes === ['Vítor Vizinho: 3 alterações ao texto e 1 comentário.']
                && str_contains($n->toArray($this->autor)['body'], 'Vítor Vizinho: 3 alterações ao texto e 1 comentário.');
        });
    }
}
