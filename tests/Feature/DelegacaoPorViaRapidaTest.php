<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Notifications\TarefaDelegadaNotification;
use App\Services\DocumentoEntradaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Delegar tem de dar no mesmo, venha do separador Tarefas ou do painel rápido.
 *
 * Havia dois caminhos para criar tarefa e só um chamava o serviço. O painel
 * rápido tinha uma cópia manuscrita que criava a tarefa sem disparar
 * TaskAssigned — o executante NUNCA era notificado por essa via — e punha o
 * status em RECEBIDO à mão, sem tocar no encaminhamento nem na custódia,
 * deixando os dois eixos do documento a afirmar coisas diferentes.
 */
class DelegacaoPorViaRapidaTest extends TestCase
{
    use RefreshDatabase;

    private function cenario(): array
    {
        $roleUser = Role::firstOrCreate(['name' => 'user'], ['description' => 'Utilizador']);
        $roleChefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['description' => 'Chefe']);
        $roleAdmin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'chefe-departamento', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $gab = Gabinete::create(['nome' => 'Gabinete Fluxo', 'sigla' => 'GFLX']);
        $dep = Departamento::create(['nome' => 'Departamento Alfa', 'gabinete_id' => $gab->id]);

        $admin = User::factory()->create(['role_id' => $roleAdmin->id, 'departamento_id' => $dep->id]);
        $admin->syncRoles(['admin']);
        $gab->update(['responsavel_id' => $admin->id]);

        $chefe = User::factory()->create(['role_id' => $roleChefe->id, 'departamento_id' => $dep->id]);
        $chefe->syncRoles(['chefe-departamento']);
        $dep->update(['chefe_user_id' => $chefe->id]);

        $tecnico = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $dep->id]);

        return compact('gab', 'dep', 'admin', 'chefe', 'tecnico');
    }

    /**
     * O caso real, que faltava: o documento é registado num departamento e
     * despachado para OUTRO. Quem tem de agir é a chefia do destino.
     *
     * Os testes existentes despachavam o documento para o próprio departamento
     * onde ele já estava, pelo que a custódia já estava certa antes do recibo e
     * as guardas passavam por acidente.
     */
    private function cenarioComDoisDepartamentos(): array
    {
        $c = $this->cenario();

        $roleChefe = Role::where('name', 'chefe-departamento')->firstOrFail();
        $roleUser = Role::where('name', 'user')->firstOrFail();

        $depDestino = Departamento::create(['nome' => 'Departamento Beta', 'gabinete_id' => $c['gab']->id]);

        $chefeDestino = User::factory()->create(['role_id' => $roleChefe->id, 'departamento_id' => $depDestino->id]);
        $chefeDestino->syncRoles(['chefe-departamento']);
        $depDestino->update(['chefe_user_id' => $chefeDestino->id]);

        $tecnicoDestino = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $depDestino->id]);

        return $c + compact('depDestino', 'chefeDestino', 'tecnicoDestino');
    }

    private function documentoDespachadoParaOutroDepartamento(array $c)
    {
        $service = app(DocumentoEntradaService::class);

        $this->actingAs($c['admin']);
        $doc = $service->createDocument([
            'assunto' => 'Pedido de parecer',
            'departamento_id' => $c['dep']->id,
        ]);

        $service->despacharDocumento($doc, 'Ao Beta, para parecer.', [$c['depDestino']->id], $c['admin']);

        return $doc->fresh();
    }

    /**
     * A custódia (departamento_id) só muda no recebimento, e delegar vale como
     * recibo. Exigir o recibo para deixar delegar fechava o ciclo sobre si
     * mesmo e a chefia do destino nunca conseguia agir.
     */
    public function test_chefe_do_destino_delega_com_a_custodia_ainda_na_origem(): void
    {
        Storage::fake('public');
        Notification::fake();

        $c = $this->cenarioComDoisDepartamentos();
        $doc = $this->documentoDespachadoParaOutroDepartamento($c);

        $this->assertSame($c['dep']->id, (int) $doc->departamento_id, 'Pré-condição: a custódia está na origem.');
        $this->assertSame(DocumentoStatus::ENCAMINHADO->value, $doc->status, 'Pré-condição: por receber.');

        $this->actingAs($c['chefeDestino'])
            ->postJson(route('documentos-entradas.quick-action', $doc), [
                'assigned_to_user_id' => $c['tecnicoDestino']->id,
                'descricao' => 'Elaborar parecer técnico.',
                'prazo_at' => now()->addDays(5)->toDateString(),
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('documento_tarefas', [
            'documento_entrada_id' => $doc->id,
            'assigned_to_user_id' => $c['tecnicoDestino']->id,
        ]);

        Notification::assertSentTo($c['tecnicoDestino'], TarefaDelegadaNotification::class);

        $doc->refresh();
        $this->assertSame(
            $c['depDestino']->id,
            (int) $doc->departamento_id,
            'Delegar vale como recibo: a custódia tem de passar ao destino.'
        );
        $this->assertSame(DocumentoStatus::RECEBIDO->value, $doc->status);

        $enc = DocumentoEncaminhamento::where('documento_entrada_id', $doc->id)->latest('id')->first();
        $this->assertNotNull($enc->recebido_em, 'O encaminhamento ficou por receber.');
        $this->assertSame($c['chefeDestino']->id, (int) $enc->recebido_por_id);
    }

    /**
     * A gaveta lateral desenhava "sem ação rápida" à chefia do destino, porque
     * lia a mesma guarda. É o ecrã que o utilizador vê — tem de dizer 'delegar'.
     */
    public function test_gaveta_oferece_delegar_a_chefia_do_destino(): void
    {
        Storage::fake('public');

        $c = $this->cenarioComDoisDepartamentos();
        $doc = $this->documentoDespachadoParaOutroDepartamento($c);

        $this->actingAs($c['chefeDestino'])
            ->get(route('documentos-entradas.preview-ajax', $doc))
            ->assertOk()
            ->assertSee('Delegar tarefa');
    }

    /**
     * Alargar a competência ao destino pendente não pode alargá-la a terceiros:
     * um chefe de um departamento sem relação com o documento continua fora.
     */
    public function test_chefe_alheio_ao_percurso_continua_recusado(): void
    {
        Storage::fake('public');
        Notification::fake();

        $c = $this->cenarioComDoisDepartamentos();

        $roleChefe = Role::where('name', 'chefe-departamento')->firstOrFail();
        $depAlheio = Departamento::create(['nome' => 'Departamento Gama', 'gabinete_id' => $c['gab']->id]);
        $chefeAlheio = User::factory()->create(['role_id' => $roleChefe->id, 'departamento_id' => $depAlheio->id]);
        $chefeAlheio->syncRoles(['chefe-departamento']);
        $depAlheio->update(['chefe_user_id' => $chefeAlheio->id]);

        $doc = $this->documentoDespachadoParaOutroDepartamento($c);

        $this->actingAs($chefeAlheio)
            ->postJson(route('documentos-entradas.quick-action', $doc), [
                'assigned_to_user_id' => $c['tecnicoDestino']->id,
                'descricao' => 'Tentativa indevida.',
                'prazo_at' => now()->addDays(5)->toDateString(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('documento_tarefas', 0);
    }

    private function documentoDespachado(array $c)
    {
        $service = app(DocumentoEntradaService::class);

        $this->actingAs($c['admin']);
        $doc = $service->createDocument([
            'assunto' => 'Pedido de parecer',
            'departamento_id' => $c['dep']->id,
        ]);

        $service->despacharDocumento($doc, 'Ao departamento, para parecer.', [$c['dep']->id], $c['admin']);

        return $doc->fresh();
    }

    public function test_via_rapida_notifica_o_executante(): void
    {
        Storage::fake('public');
        Notification::fake();

        $c = $this->cenario();
        $doc = $this->documentoDespachado($c);

        $this->actingAs($c['chefe'])
            ->postJson(route('documentos-entradas.quick-action', $doc), [
                'assigned_to_user_id' => $c['tecnico']->id,
                'descricao' => 'Elaborar parecer técnico sobre o pedido.',
                'prazo_at' => now()->addDays(5)->toDateString(),
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        Notification::assertSentTo($c['tecnico'], TarefaDelegadaNotification::class);

        $this->assertDatabaseHas('documento_tarefas', [
            'documento_entrada_id' => $doc->id,
            'assigned_to_user_id' => $c['tecnico']->id,
            'titulo' => 'Despacho Executivo / Demanda Técnica',
        ]);
    }

    /**
     * Delegar vale como recibo — mas a sério: o encaminhamento pendente fica
     * recebido e a custódia acompanha. Antes só o status era forjado.
     */
    public function test_via_rapida_recebe_de_facto_o_encaminhamento_pendente(): void
    {
        Storage::fake('public');
        Notification::fake();

        $c = $this->cenario();
        $doc = $this->documentoDespachado($c);

        $this->assertSame(DocumentoStatus::ENCAMINHADO->value, $doc->status, 'Pré-condição: por receber.');

        $this->actingAs($c['chefe'])
            ->postJson(route('documentos-entradas.quick-action', $doc), [
                'assigned_to_user_id' => $c['tecnico']->id,
                'descricao' => 'Elaborar parecer técnico.',
                'prazo_at' => now()->addDays(5)->toDateString(),
            ])
            ->assertOk();

        $doc->refresh();

        $this->assertSame(DocumentoStatus::RECEBIDO->value, $doc->status);
        $this->assertSame($c['dep']->id, (int) $doc->departamento_id, 'A custódia tem de acompanhar o recibo.');

        $enc = DocumentoEncaminhamento::where('documento_entrada_id', $doc->id)->latest('id')->first();
        $this->assertNotNull($enc->recebido_em, 'O encaminhamento ficou por receber: o estado foi forjado.');
        $this->assertSame($c['chefe']->id, (int) $enc->recebido_por_id);
    }

    public function test_via_rapida_grava_o_visto_do_departamento(): void
    {
        Storage::fake('public');
        Notification::fake();

        $c = $this->cenario();
        $doc = $this->documentoDespachado($c);

        $this->actingAs($c['chefe'])
            ->postJson(route('documentos-entradas.quick-action', $doc), [
                'assigned_to_user_id' => $c['tecnico']->id,
                'descricao' => 'Elaborar parecer.',
                'prazo_at' => now()->addDays(5)->toDateString(),
            ])
            ->assertOk();

        $doc->refresh();

        $this->assertSame('aprovado', $doc->visto_departamento_status);
        $this->assertSame($c['chefe']->id, (int) $doc->visto_departamento_por);

        // E não toca no ato do gabinete.
        $this->assertSame($c['admin']->id, (int) $doc->despachado_por_id);
    }

    /**
     * Um documento não pode constar como tratado enquanto tem trabalho por fazer.
     */
    public function test_nova_tarefa_reabre_documento_ja_tratado(): void
    {
        Storage::fake('public');
        Notification::fake();

        $c = $this->cenario();
        $doc = $this->documentoDespachado($c);
        $service = app(DocumentoEntradaService::class);

        $tarefa = $service->createTask($doc, [
            'titulo' => 'Primeira',
            'assigned_to_user_id' => $c['tecnico']->id,
        ], $c['chefe']);

        $service->completeTask($tarefa, $c['tecnico'], null, 'Parecer emitido.');

        $doc->refresh();
        $this->assertSame(DocumentoStatus::TRATADO->value, $doc->status, 'Pré-condição: fechado.');

        // Chega trabalho novo sobre o mesmo documento.
        $service->createTask($doc, [
            'titulo' => 'Segunda',
            'assigned_to_user_id' => $c['tecnico']->id,
        ], $c['chefe']);

        $doc->refresh();

        $this->assertSame(
            DocumentoStatus::RECEBIDO->value,
            $doc->status,
            'Ficou marcado como tratado com uma tarefa pendente.'
        );

        $this->assertSame(1, DocumentoTarefa::where('documento_entrada_id', $doc->id)
            ->where('status', 'pendente')->count());
    }
}
