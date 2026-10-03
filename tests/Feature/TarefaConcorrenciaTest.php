<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use App\Services\DocumentoEntradaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Delegação em concorrência (2026-10-02): o chefe oferece a mesma tarefa a vários
 * técnicos e o primeiro a assumi-la fica com ela; para os restantes desaparece.
 * O modo "todos executam" continua a ser o de sempre (DelegacaoChefeDepartamentoTest).
 *
 * Origem e destino distintos: o documento é registado em A e despachado para B.
 */
class TarefaConcorrenciaTest extends TestCase
{
    use RefreshDatabase;

    private const TITULO = 'Parecer em concorrência';

    private Departamento $depA;

    private Departamento $depB;

    private User $chefeGab;

    private User $chefeB;

    private User $tec1;

    private User $tec2;

    private User $tec3;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();

        $roleUser = Role::firstOrCreate(['name' => 'user'], ['description' => 'Utilizador']);
        $roleChefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['description' => 'Chefe']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'chefe-departamento', 'guard_name' => 'web']);

        $gab = Gabinete::create(['nome' => 'Gabinete Concorrência', 'sigla' => 'GCON']);
        $this->depA = Departamento::create(['nome' => 'Departamento Origem', 'gabinete_id' => $gab->id]);
        $this->depB = Departamento::create(['nome' => 'Departamento Destino', 'gabinete_id' => $gab->id]);

        $this->chefeGab = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->depA->id]);
        $gab->update(['responsavel_id' => $this->chefeGab->id]);

        $this->chefeB = User::factory()->create(['role_id' => $roleChefe->id, 'departamento_id' => $this->depB->id]);
        $this->chefeB->syncRoles(['chefe-departamento']);
        $this->depB->update(['responsavel_id' => $this->chefeB->id]);

        $this->tec1 = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->depB->id]);
        $this->tec2 = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->depB->id]);
        $this->tec3 = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->depB->id]);
    }

    private function documentoPorReceberEmB(): DocumentoEntrada
    {
        $this->actingAs($this->chefeGab);
        $service = app(DocumentoEntradaService::class);
        $doc = $service->createDocument(['assunto' => 'Pedido de parecer', 'departamento_id' => $this->depA->id])->fresh();
        $service->despacharDocumento($doc, 'Ao destino, para parecer.', [$this->depB->id], $this->chefeGab);

        return $doc->fresh();
    }

    /** Documento com a tarefa oferecida em concorrência ao tec1 e ao tec2. */
    private function delegarEmConcorrencia(?DocumentoEntrada $doc = null, string $modo = DocumentoTarefa::MODO_CONCORRENCIA): DocumentoEntrada
    {
        $doc ??= $this->documentoPorReceberEmB();

        $this->actingAs($this->chefeB)->post(route('documentos-entradas.tarefas.store', $doc), [
            'tipo' => 'usuario',
            'destino_ids' => [$this->tec1->id, $this->tec2->id],
            'titulo' => self::TITULO,
            'prazo_at' => now()->addDays(5)->toDateString(),
            'modo' => $modo,
        ])->assertSessionHasNoErrors();

        return $doc->fresh();
    }

    private function tarefaDe(DocumentoEntrada $doc, User $tecnico): DocumentoTarefa
    {
        return DocumentoTarefa::where('documento_entrada_id', $doc->id)->where('assigned_to_user_id', $tecnico->id)->firstOrFail();
    }

    private function assumir(User $tecnico, DocumentoEntrada $doc)
    {
        return $this->actingAs($tecnico)->post(route('documentos-entradas.tarefas.assumir', [$doc, $this->tarefaDe($doc, $tecnico)]));
    }

    // --- Delegação ------------------------------------------------------------------

    public function test_delegar_em_concorrencia_cria_uma_linha_por_tecnico_no_mesmo_grupo(): void
    {
        $doc = $this->delegarEmConcorrencia();

        $tarefas = DocumentoTarefa::where('documento_entrada_id', $doc->id)->get();
        $this->assertCount(2, $tarefas);
        $this->assertSame(1, $tarefas->pluck('grupo_tarefa_uuid')->unique()->count());
        $this->assertTrue($tarefas->every(fn ($t) => $t->modo_grupo === DocumentoTarefa::MODO_CONCORRENCIA && $t->status === 'pendente' && $t->assumida_em === null));
    }

    public function test_sem_modo_a_delegacao_multipla_e_todos_executam(): void
    {
        $doc = $this->documentoPorReceberEmB();

        $this->actingAs($this->chefeB)->post(route('documentos-entradas.tarefas.store', $doc), [
            'tipo' => 'usuario',
            'destino_ids' => [$this->tec1->id, $this->tec2->id],
            'titulo' => self::TITULO,
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            [DocumentoTarefa::MODO_TODOS],
            DocumentoTarefa::where('documento_entrada_id', $doc->id)->pluck('modo_grupo')->unique()->values()->all()
        );
    }

    public function test_um_so_tecnico_nao_tem_modo(): void
    {
        $doc = $this->documentoPorReceberEmB();

        $this->actingAs($this->chefeB)->post(route('documentos-entradas.tarefas.store', $doc), [
            'tipo' => 'usuario',
            'destino_ids' => [$this->tec1->id],
            'titulo' => self::TITULO,
            'modo' => DocumentoTarefa::MODO_CONCORRENCIA,
        ])->assertSessionHasNoErrors();

        $tarefa = $this->tarefaDe($doc, $this->tec1);
        $this->assertNull($tarefa->modo_grupo);
        $this->assertFalse($tarefa->emConcorrencia());
    }

    public function test_painel_rapido_delega_em_concorrencia(): void
    {
        $doc = $this->documentoPorReceberEmB();

        $this->actingAs($this->chefeB)->postJson(route('documentos-entradas.quick-action', $doc), [
            'assigned_to_user_ids' => [$this->tec1->id, $this->tec2->id],
            'descricao' => 'Elaborar parecer técnico.',
            'prazo_at' => now()->addDays(5)->toDateString(),
            'modo' => DocumentoTarefa::MODO_CONCORRENCIA,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertTrue($this->tarefaDe($doc, $this->tec1)->emConcorrencia());
        $this->assertTrue($this->tarefaDe($doc, $this->tec2)->emConcorrencia());
    }

    public function test_modo_invalido_e_recusado(): void
    {
        $doc = $this->documentoPorReceberEmB();

        $this->actingAs($this->chefeB)->post(route('documentos-entradas.tarefas.store', $doc), [
            'tipo' => 'usuario',
            'destino_ids' => [$this->tec1->id, $this->tec2->id],
            'titulo' => self::TITULO,
            'modo' => 'sorteio',
        ])->assertSessionHasErrors('modo');

        $this->assertSame(0, DocumentoTarefa::where('documento_entrada_id', $doc->id)->count());
    }

    // --- Assumir --------------------------------------------------------------------

    public function test_quem_assume_primeiro_fica_com_ela_e_desaparece_para_os_outros(): void
    {
        $doc = $this->delegarEmConcorrencia();

        $this->assumir($this->tec1, $doc)->assertSessionHas('success');

        $this->assertNotNull($this->tarefaDe($doc, $this->tec1)->assumida_em);
        $this->assertSame('pendente', $this->tarefaDe($doc, $this->tec1)->status);
        $this->assertSame(DocumentoTarefa::STATUS_RETIRADA, $this->tarefaDe($doc, $this->tec2)->status);

        // Desaparece para o colega: "Atribuídos a Mim", Minhas Tarefas e painel rápido.
        $this->actingAs($this->tec2)->get(route('documentos-entradas.index', ['tab' => 'atribuidos_mim']))
            ->assertOk()->assertDontSee(self::TITULO);
        $this->actingAs($this->tec2)->get(route('tarefas.index'))
            ->assertOk()->assertDontSee(self::TITULO);
        $this->actingAs($this->tec2)->get(route('documentos-entradas.preview-ajax', $doc))
            ->assertOk()->assertDontSee('name="tarefa_id"', false);

        // E continua com quem a assumiu.
        $this->actingAs($this->tec1)->get(route('documentos-entradas.index', ['tab' => 'atribuidos_mim']))
            ->assertOk()->assertSee(self::TITULO);

        Notification::assertSentTo($this->tec2, SimpleBroadcastNotification::class);
        Notification::assertSentTo($this->chefeB, SimpleBroadcastNotification::class);
    }

    public function test_quem_chega_depois_e_recusado_sem_alterar_nada(): void
    {
        $doc = $this->delegarEmConcorrencia();
        $tarefa2 = $this->tarefaDe($doc, $this->tec2);
        $this->assumir($this->tec1, $doc);

        // O tec2 ainda tinha a página antiga com o botão.
        $this->actingAs($this->tec2)->post(route('documentos-entradas.tarefas.assumir', [$doc, $tarefa2]))
            ->assertSessionHas('warning', 'Esta tarefa já foi assumida por '.$this->tec1->name.'.');
        $this->actingAs($this->tec2)->postJson(route('documentos-entradas.tarefas.assumir', [$doc, $tarefa2]))
            ->assertStatus(409);

        $this->assertNull($this->tarefaDe($doc, $this->tec2)->assumida_em);
        $this->assertSame(DocumentoTarefa::STATUS_RETIRADA, $this->tarefaDe($doc, $this->tec2)->status);
        $this->assertSame('pendente', $this->tarefaDe($doc, $this->tec1)->status);
    }

    public function test_assumir_duas_vezes_e_idempotente(): void
    {
        $doc = $this->delegarEmConcorrencia();
        $this->assumir($this->tec1, $doc);
        $primeira = $this->tarefaDe($doc, $this->tec1)->assumida_em;

        $this->travel(5)->minutes();
        $this->assumir($this->tec1, $doc)->assertSessionHas('success');

        $this->assertEquals($primeira, $this->tarefaDe($doc, $this->tec1)->assumida_em);
    }

    public function test_tecnico_fora_do_grupo_nao_assume(): void
    {
        $doc = $this->delegarEmConcorrencia();

        $this->actingAs($this->tec3)->postJson(route('documentos-entradas.tarefas.assumir', [$doc, $this->tarefaDe($doc, $this->tec1)]))
            ->assertStatus(403);

        $this->assertNull($this->tarefaDe($doc, $this->tec1)->assumida_em);
        $this->assertSame('pendente', $this->tarefaDe($doc, $this->tec2)->status);
    }

    public function test_tarefa_de_outro_documento_da_404(): void
    {
        $doc = $this->delegarEmConcorrencia();
        $outro = $this->documentoPorReceberEmB();

        $this->actingAs($this->tec1)->post(route('documentos-entradas.tarefas.assumir', [$outro, $this->tarefaDe($doc, $this->tec1)]))
            ->assertNotFound();
    }

    public function test_no_modo_todos_nao_ha_nada_a_assumir(): void
    {
        $doc = $this->delegarEmConcorrencia(null, DocumentoTarefa::MODO_TODOS);

        $this->actingAs($this->tec1)->postJson(route('documentos-entradas.tarefas.assumir', [$doc, $this->tarefaDe($doc, $this->tec1)]))
            ->assertStatus(403);
        $this->assertSame('pendente', $this->tarefaDe($doc, $this->tec2)->status);
    }

    // --- Concluir -------------------------------------------------------------------

    public function test_concluir_sem_assumir_assume_e_fecha_o_documento(): void
    {
        $doc = $this->delegarEmConcorrencia();

        $this->actingAs($this->tec2)->post(route('documentos-entradas.tarefas.concluir', [$doc, $this->tarefaDe($doc, $this->tec2)]), [
            'observacao' => 'Parecer emitido.',
        ])->assertSessionHas('success');

        $tarefa2 = $this->tarefaDe($doc, $this->tec2);
        $this->assertSame('concluida', $tarefa2->status);
        $this->assertNotNull($tarefa2->assumida_em);
        $this->assertSame(DocumentoTarefa::STATUS_RETIRADA, $this->tarefaDe($doc, $this->tec1)->status);
        $this->assertSame(DocumentoStatus::TRATADO->value, $doc->fresh()->status, 'Sem tarefas pendentes, o documento fecha.');
    }

    public function test_concluir_depois_de_um_colega_assumir_e_recusado(): void
    {
        $doc = $this->delegarEmConcorrencia();
        $tarefa2 = $this->tarefaDe($doc, $this->tec2);
        $this->assumir($this->tec1, $doc);

        $this->actingAs($this->tec2)->post(route('documentos-entradas.tarefas.concluir', [$doc, $tarefa2]), [
            'observacao' => 'Também fiz.',
        ])->assertSessionMissing('success');

        $this->assertSame(DocumentoTarefa::STATUS_RETIRADA, $this->tarefaDe($doc, $this->tec2)->status);
        $this->assertNull($this->tarefaDe($doc, $this->tec2)->resposta);
    }

    public function test_painel_rapido_recusa_concluir_tarefa_retirada(): void
    {
        $doc = $this->delegarEmConcorrencia();
        $tarefa2 = $this->tarefaDe($doc, $this->tec2);
        $this->assumir($this->tec1, $doc);

        $this->actingAs($this->tec2)->postJson(route('documentos-entradas.quick-action', $doc), [
            'tarefa_id' => $tarefa2->id,
            'observacao' => 'Também fiz.',
        ])->assertStatus(409);

        $this->assertSame(DocumentoTarefa::STATUS_RETIRADA, $this->tarefaDe($doc, $this->tec2)->status);
    }

    public function test_painel_rapido_concluir_sem_assumir_tambem_assume(): void
    {
        $doc = $this->delegarEmConcorrencia();

        $this->actingAs($this->tec1)->postJson(route('documentos-entradas.quick-action', $doc), [
            'tarefa_id' => $this->tarefaDe($doc, $this->tec1)->id,
            'observacao' => 'Parecer pelo painel.',
        ])->assertOk();

        $this->assertSame('concluida', $this->tarefaDe($doc, $this->tec1)->status);
        $this->assertSame(DocumentoTarefa::STATUS_RETIRADA, $this->tarefaDe($doc, $this->tec2)->status);
    }

    // --- Cancelar e libertar ----------------------------------------------------------

    public function test_cancelar_antes_de_assumir_cancela_o_grupo(): void
    {
        $doc = $this->delegarEmConcorrencia();

        $this->actingAs($this->chefeB)->patch(route('documentos-entradas.tarefas.cancelar', [$doc, $this->tarefaDe($doc, $this->tec1)]))
            ->assertSessionHas('success');

        $this->assertSame('cancelada', $this->tarefaDe($doc, $this->tec1)->status);
        $this->assertSame('cancelada', $this->tarefaDe($doc, $this->tec2)->status);
    }

    public function test_chefe_devolve_ao_grupo_uma_tarefa_assumida(): void
    {
        $doc = $this->delegarEmConcorrencia();
        $this->assumir($this->tec1, $doc);

        $this->actingAs($this->chefeB)->patch(route('documentos-entradas.tarefas.libertar', [$doc, $this->tarefaDe($doc, $this->tec1)]))
            ->assertSessionHas('success');

        $this->assertNull($this->tarefaDe($doc, $this->tec1)->assumida_em);
        $this->assertSame('pendente', $this->tarefaDe($doc, $this->tec1)->status);
        $this->assertSame('pendente', $this->tarefaDe($doc, $this->tec2)->status);

        // E fica de novo disponível: agora pode ser o tec2.
        $this->assumir($this->tec2, $doc)->assertSessionHas('success');
        $this->assertSame(DocumentoTarefa::STATUS_RETIRADA, $this->tarefaDe($doc, $this->tec1)->status);
    }

    public function test_tecnico_nao_devolve_ao_grupo(): void
    {
        $doc = $this->delegarEmConcorrencia();
        $this->assumir($this->tec1, $doc);

        $this->actingAs($this->tec1)->patchJson(route('documentos-entradas.tarefas.libertar', [$doc, $this->tarefaDe($doc, $this->tec1)]))
            ->assertStatus(403);

        $this->assertNotNull($this->tarefaDe($doc, $this->tec1)->assumida_em);
    }

    // --- Ficha --------------------------------------------------------------------------

    public function test_ficha_mostra_assumir_ao_tecnico_e_esconde_a_retirada(): void
    {
        $doc = $this->delegarEmConcorrencia();

        $this->actingAs($this->tec1)->get(route('documentos-entradas.show', $doc))
            ->assertOk()
            ->assertSee(route('documentos-entradas.tarefas.assumir', [$doc, $this->tarefaDe($doc, $this->tec1)]), false)
            ->assertDontSee(route('documentos-entradas.tarefas.assumir', [$doc, $this->tarefaDe($doc, $this->tec2)]), false);

        $this->assumir($this->tec1, $doc);

        $ficha = $this->actingAs($this->chefeB)->get(route('documentos-entradas.show', $doc))->assertOk();
        $ficha->assertSee('Tarefa Assumida')
            ->assertSee(route('documentos-entradas.tarefas.libertar', [$doc, $this->tarefaDe($doc, $this->tec1)]), false);
    }
}
