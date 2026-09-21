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
