<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Notifications\TarefaDelegadaNotification;
use App\Services\DocumentoEntradaService;
use App\Services\DocumentoPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * O chefe de departamento delega em um ou mais técnicos do departamento que tem
 * o documento — recebido ou ainda por receber —, pela ficha ou pelo painel
 * rápido, e a lista que vê é exactamente a que o servidor aceita.
 *
 * Decisões (2026-09-30): o chefe não se atribui tarefas; pode atribuí-las a um
 * adjunto do mesmo departamento; cada técnico conclui a sua; o admin delega.
 */
class DelegacaoChefeDepartamentoTest extends TestCase
{
    use RefreshDatabase;

    private Gabinete $gab;

    private Departamento $depA;

    private Departamento $depB;

    private User $chefeGab;

    private User $admin;

    private User $chefeA;

    private User $chefeB;

    private User $adjuntoB;

    private User $tecA;

    private User $tecB1;

    private User $tecB2;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Notification::fake();

        $roleUser = Role::firstOrCreate(['name' => 'user'], ['description' => 'Utilizador']);
        $roleChefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['description' => 'Chefe']);
        $roleAdmin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'chefe-departamento', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->gab = Gabinete::create(['nome' => 'Gabinete Delegação', 'sigla' => 'GDEL']);
        $this->depA = Departamento::create(['nome' => 'Departamento Origem', 'gabinete_id' => $this->gab->id]);
        $this->depB = Departamento::create(['nome' => 'Departamento Destino', 'gabinete_id' => $this->gab->id]);

        $this->chefeGab = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->depA->id]);
        $this->gab->update(['responsavel_id' => $this->chefeGab->id]);

        $outroGab = Gabinete::create(['nome' => 'Gabinete Informática', 'sigla' => 'GINF']);
        $depInf = Departamento::create(['nome' => 'Informática', 'gabinete_id' => $outroGab->id]);
        $this->admin = User::factory()->create(['role_id' => $roleAdmin->id, 'departamento_id' => $depInf->id]);
        $this->admin->syncRoles(['admin']);

        $this->chefeA = $this->chefe($roleChefe, $this->depA);
        $this->chefeB = $this->chefe($roleChefe, $this->depB);
        // Adjunto: também com o papel de chefe, mas o chefe designado é o chefeB.
        $this->adjuntoB = User::factory()->create(['role_id' => $roleChefe->id, 'departamento_id' => $this->depB->id]);
        $this->adjuntoB->syncRoles(['chefe-departamento']);

        $this->tecA = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->depA->id]);
        $this->tecB1 = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->depB->id]);
        $this->tecB2 = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->depB->id]);
    }

    private function chefe(Role $role, Departamento $dep): User
    {
        $chefe = User::factory()->create(['role_id' => $role->id, 'departamento_id' => $dep->id]);
        $chefe->syncRoles(['chefe-departamento']);
        $dep->update(['responsavel_id' => $chefe->id]);

        return $chefe;
    }

    private function documentoEm(Departamento $dep): DocumentoEntrada
    {
        $this->actingAs($this->chefeGab);

        return app(DocumentoEntradaService::class)->createDocument([
            'assunto' => 'Pedido de parecer',
            'departamento_id' => $dep->id,
        ])->fresh();
    }

    /** Registado na origem e despachado para o destino, ainda por receber. */
    private function documentoPorReceberEmB(): DocumentoEntrada
    {
        $doc = $this->documentoEm($this->depA);
        app(DocumentoEntradaService::class)->despacharDocumento($doc, 'Ao destino, para parecer.', [$this->depB->id], $this->chefeGab);

        return $doc->fresh();
    }

    private function delegarPelaFicha(User $actor, DocumentoEntrada $doc, array $destinos)
    {
        return $this->actingAs($actor)->post(route('documentos-entradas.tarefas.store', $doc), [
            'tipo' => 'usuario',
            'destino_ids' => array_map(fn (User $u) => $u->id, $destinos),
            'titulo' => 'Elaborar parecer',
            'prazo_at' => now()->addDays(5)->toDateString(),
        ]);
    }

    private function idsDestinatarios(DocumentoEntrada $doc, User $actor): array
    {
        return app(DocumentoEntradaService::class)->destinatariosTarefa($doc, $actor)->pluck('id')->sort()->values()->all();
    }

    private function ids(User ...$users): array
    {
        return collect($users)->pluck('id')->sort()->values()->all();
    }

    // --- Delegar em vários técnicos ---------------------------------------------------

    public function test_chefe_delega_em_varios_tecnicos_pela_ficha(): void
    {
        $doc = $this->documentoEm($this->depB);

        $this->delegarPelaFicha($this->chefeB, $doc, [$this->tecB1, $this->tecB2])->assertSessionHasNoErrors();

        $tarefas = DocumentoTarefa::where('documento_entrada_id', $doc->id)->get();
        $this->assertCount(2, $tarefas);
        $this->assertEqualsCanonicalizing([$this->tecB1->id, $this->tecB2->id], $tarefas->pluck('assigned_to_user_id')->all());
        $this->assertNotNull($tarefas->first()->grupo_tarefa_uuid);
        $this->assertSame(1, $tarefas->pluck('grupo_tarefa_uuid')->unique()->count(), 'mesmo grupo');
        Notification::assertSentTo($this->tecB1, TarefaDelegadaNotification::class);
        Notification::assertSentTo($this->tecB2, TarefaDelegadaNotification::class);
    }

    public function test_documento_por_receber_o_chefe_do_destino_ve_e_delega_nos_seus_tecnicos(): void
    {
        $doc = $this->documentoPorReceberEmB();
        $this->assertSame($this->depA->id, (int) $doc->departamento_id, 'Pré-condição: custódia na origem.');

        $this->actingAs($this->chefeB)->get(route('documentos-entradas.show', $doc))
            ->assertOk()
            ->assertSee($this->tecB1->name)
            ->assertSee($this->tecB2->name)
            ->assertDontSee($this->tecA->name);

        $this->delegarPelaFicha($this->chefeB, $doc, [$this->tecB1, $this->tecB2])->assertSessionHasNoErrors();

        $this->assertSame(2, DocumentoTarefa::where('documento_entrada_id', $doc->id)->count());
        $this->assertSame($this->depB->id, (int) $doc->fresh()->departamento_id, 'Delegar vale como recibo.');
    }

    // --- Quem pode ser destinatário ---------------------------------------------------

    public function test_tecnico_de_outro_departamento_e_recusado(): void
    {
        $doc = $this->documentoPorReceberEmB();

        $this->delegarPelaFicha($this->chefeB, $doc, [$this->tecB1, $this->tecA])->assertSessionHasErrors('destino_ids');

        $this->assertSame(0, DocumentoTarefa::where('documento_entrada_id', $doc->id)->count(), 'Nada é criado a meio.');
    }

    public function test_o_chefe_nao_se_atribui_tarefas(): void
    {
        $doc = $this->documentoEm($this->depB);

        $this->assertNotContains($this->chefeB->id, $this->idsDestinatarios($doc, $this->chefeB));
        $this->delegarPelaFicha($this->chefeB, $doc, [$this->chefeB])->assertSessionHasErrors('destino_ids');
        $this->assertSame(0, DocumentoTarefa::where('documento_entrada_id', $doc->id)->count());
    }

    public function test_adjunto_do_mesmo_departamento_e_aceite(): void
    {
        $doc = $this->documentoEm($this->depB);

        $this->delegarPelaFicha($this->chefeB, $doc, [$this->adjuntoB])->assertSessionHasNoErrors();

        $this->assertSame(1, DocumentoTarefa::where('assigned_to_user_id', $this->adjuntoB->id)->count());
    }

    // --- Quem pode delegar ------------------------------------------------------------

    public function test_chefe_de_outro_departamento_membro_secundario_nao_delega(): void
    {
        $this->chefeA->departamentos()->attach($this->depB->id);
        $doc = $this->documentoEm($this->depB);

        $this->assertFalse(app(DocumentoPermissionService::class)->canManageTasks($this->chefeA->fresh(), $doc));
        $this->delegarPelaFicha($this->chefeA, $doc, [$this->tecB1])->assertSessionHas('danger');
        $this->assertSame(0, DocumentoTarefa::where('documento_entrada_id', $doc->id)->count());
    }

    public function test_o_adjunto_nao_delega_por_ter_o_papel(): void
    {
        $doc = $this->documentoEm($this->depB);

        $this->assertFalse(app(DocumentoPermissionService::class)->canManageTasks($this->adjuntoB, $doc));
    }

    public function test_admin_delega(): void
    {
        $doc = $this->documentoEm($this->depB);

        $this->delegarPelaFicha($this->admin, $doc, [$this->tecB1])->assertSessionHasNoErrors();

        $this->assertSame(1, DocumentoTarefa::where('documento_entrada_id', $doc->id)->count());
    }

    // --- Painel rápido ----------------------------------------------------------------

    public function test_painel_rapido_aceita_varios_tecnicos(): void
    {
        $doc = $this->documentoPorReceberEmB();

        $this->actingAs($this->chefeB)->postJson(route('documentos-entradas.quick-action', $doc), [
            'assigned_to_user_ids' => [$this->tecB1->id, $this->tecB2->id],
            'descricao' => 'Elaborar parecer técnico.',
            'prazo_at' => now()->addDays(5)->toDateString(),
        ])->assertOk()->assertJson(['success' => true]);

        $tarefas = DocumentoTarefa::where('documento_entrada_id', $doc->id)->get();
        $this->assertCount(2, $tarefas);
        $this->assertSame(1, $tarefas->pluck('grupo_tarefa_uuid')->unique()->count());
    }

    public function test_painel_rapido_recusa_quem_nao_esta_na_lista(): void
    {
        $doc = $this->documentoEm($this->depB);

        $this->actingAs($this->chefeB)->postJson(route('documentos-entradas.quick-action', $doc), [
            'assigned_to_user_ids' => [$this->tecB1->id, $this->tecA->id],
            'descricao' => 'x',
            'prazo_at' => now()->addDays(5)->toDateString(),
        ])->assertStatus(422);

        $this->assertSame(0, DocumentoTarefa::where('documento_entrada_id', $doc->id)->count());
    }

    public function test_chefe_que_tambem_e_do_expediente_ve_e_usa_a_delegacao_no_painel(): void
    {
        $expediente = Departamento::create(['nome' => 'Expediente', 'gabinete_id' => $this->gab->id, 'is_area_expediente' => true]);
        $this->chefeB->departamentos()->attach($expediente->id);
        $this->assertSame('expediente', app(DocumentoPermissionService::class)->getUserWorkflowProfile($this->chefeB->fresh()), 'Pré-condição.');
        $doc = $this->documentoEm($this->depB);

        $this->actingAs($this->chefeB)->get(route('documentos-entradas.preview-ajax', $doc))
            ->assertOk()
            ->assertSee('Delegar tarefa');

        $this->actingAs($this->chefeB)->postJson(route('documentos-entradas.quick-action', $doc), [
            'assigned_to_user_ids' => [$this->tecB1->id],
            'descricao' => 'Elaborar parecer técnico.',
            'prazo_at' => now()->addDays(5)->toDateString(),
        ])->assertOk();
    }

    // --- Lista mostrada = conjunto aceite ----------------------------------------------

    public function test_lista_mostrada_e_exactamente_o_que_o_servidor_aceita(): void
    {
        $doc = $this->documentoPorReceberEmB();
        $service = app(DocumentoEntradaService::class);
        $mostrados = $this->idsDestinatarios($doc, $this->chefeB);

        $this->assertSame($this->ids($this->adjuntoB, $this->tecB1, $this->tecB2), $mostrados);

        foreach (User::all() as $candidato) {
            $aceite = $service->validarDestinatarioTarefa($doc, $candidato, $this->chefeB) === null;
            $this->assertSame(in_array($candidato->id, $mostrados, true), $aceite, "divergência para o utilizador {$candidato->id}");
        }

        // A ficha e o painel desenham essa mesma lista.
        $ficha = $this->actingAs($this->chefeB)->get(route('documentos-entradas.show', $doc))->getContent();
        $painel = $this->actingAs($this->chefeB)->get(route('documentos-entradas.preview-ajax', $doc))->getContent();
        foreach ([$this->adjuntoB, $this->tecB1, $this->tecB2] as $u) {
            $this->assertStringContainsString('name="destino_ids[]" value="'.$u->id.'"', $ficha);
            $this->assertStringContainsString('name="assigned_to_user_ids[]" value="'.$u->id.'"', $painel);
        }
        $this->assertStringNotContainsString('value="'.$this->chefeB->id.'" data-destinatario', $ficha);
    }

    // --- Conclusão ----------------------------------------------------------------------

    public function test_documento_so_fica_tratado_quando_a_ultima_tarefa_e_concluida(): void
    {
        $doc = $this->documentoEm($this->depB);
        $this->delegarPelaFicha($this->chefeB, $doc, [$this->tecB1, $this->tecB2]);
        [$t1, $t2] = DocumentoTarefa::where('documento_entrada_id', $doc->id)->orderBy('id')->get()->all();
        $concluir = fn (User $u, DocumentoTarefa $t) => $this->actingAs($u)
            ->post(route('documentos-entradas.tarefas.concluir', [$doc, $t]), ['observacao' => 'Parecer emitido.']);

        $concluir(User::find($t1->assigned_to_user_id), $t1);
        $this->assertNotSame(DocumentoStatus::TRATADO->value, $doc->fresh()->status);

        $concluir(User::find($t2->assigned_to_user_id), $t2);
        $this->assertSame(DocumentoStatus::TRATADO->value, $doc->fresh()->status);
    }
}
