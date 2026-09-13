<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEncaminhamentoExterno;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DocumentoEntradaRoutesAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): array
    {
        $admin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrador']);
        $user = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
        $chefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['description' => 'Chefe de departamento']);

        return compact('admin', 'user', 'chefe');
    }

    private function makeDoc(User $owner, Departamento $dep, int $seq = 1): DocumentoEntrada
    {
        return DocumentoEntrada::create([
            'numero_sequencial' => $seq,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => 'Teste',
            'departamento_id' => $dep->id,
            'user_id' => $owner->id,
            'status' => 'registrado',
        ]);
    }

    public function test_encaminhar_route_authorization(): void
    {
        config()->set('queue.default', 'sync');
        Event::fake([BroadcastNotificationCreated::class]);
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gab']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);
        $depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gab->id]);

        $memberA = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $memberB = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depB->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $doc = $this->makeDoc($owner, $depA);

        $this->actingAs($memberA)
            ->followingRedirects()
            ->post(route('documentos-entradas.encaminhar', $doc), [
                'destino_departamento_id' => $depB->id,
                'observacao' => 'Teste',
            ])
            ->assertStatus(200);
        $doc->refresh();
        $this->assertSame('encaminhado', $doc->status);
        $this->assertNotNull($doc->encaminhamento_data);
        $this->assertTrue(
            $doc->encaminhamentos()->whereNull('recebido_em')->where('destino_departamento_id', $depB->id)->exists()
        );

        Event::assertDispatched(BroadcastNotificationCreated::class);
        $notif = DatabaseNotification::where('notifiable_id', $memberB->id)->latest()->first();
        $this->assertNotNull($notif);
        $this->assertSame('encaminhado_interno', data_get($notif->data, 'acao'));
        $this->assertSame(route('documentos-entradas.show', $doc->id), data_get($notif->data, 'url'));
        $this->assertSame(sprintf('%03d/%d', $doc->numero_sequencial, $doc->ano_referencia), data_get($notif->data, 'numero'));
        $actorInternalNotif = DatabaseNotification::where('notifiable_id', $memberA->id)->where('data->acao', 'encaminhado_interno')->first();
        $this->assertNull($actorInternalNotif);

        $this->actingAs($memberB)
            ->post(route('documentos-entradas.encaminhar', $doc), [
                'destino_departamento_id' => $depB->id,
            ])
            ->assertStatus(403);
        $this->assertSame(1, $doc->encaminhamentos()->count());
    }

    public function test_receber_route_authorization(): void
    {
        config()->set('queue.default', 'sync');
        Event::fake([BroadcastNotificationCreated::class]);
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gab']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);
        $depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gab->id]);

        $memberA = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $memberB = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depB->id]);
        $chefeB = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depB->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $doc = $this->makeDoc($owner, $depA);

        $enc = DocumentoEncaminhamento::create([
            'documento_entrada_id' => $doc->id,
            'origem_departamento_id' => $depA->id,
            'destino_departamento_id' => $depB->id,
            'usuario_id' => $owner->id,
            'encaminhado_em' => now(),
            'status' => 'encaminhado',
        ]);

        $this->actingAs($memberA)
            ->patch(route('documentos-entradas.encaminhamentos.receber', ['documento' => $doc->id, 'encaminhamento' => $enc->id]))
            ->assertStatus(403);

        $this->actingAs($memberB)
            ->followingRedirects()
            ->patch(route('documentos-entradas.encaminhamentos.receber', ['documento' => $doc->id, 'encaminhamento' => $enc->id]))
            ->assertStatus(200);
        $doc->refresh();
        $enc->refresh();
        $this->assertSame('recebido', $doc->status);
        $this->assertSame($depB->id, $doc->departamento_id);
        $this->assertNotNull($enc->recebido_em);
        $this->assertSame($memberB->id, $enc->recebido_por_id);

        Event::assertDispatched(BroadcastNotificationCreated::class);
        $notif2 = DatabaseNotification::where('notifiable_id', $chefeB->id)->latest()->first();
        $this->assertNotNull($notif2);
        $this->assertSame('Documento '.sprintf('%03d/%d', $doc->numero_sequencial, $doc->ano_referencia).' recebido no departamento '.$depB->nome, data_get($notif2->data, 'title'));
        $this->assertSame('Aguardando ação do chefe', data_get($notif2->data, 'message'));
        $this->assertSame(route('documentos-entradas.show', $doc->id), data_get($notif2->data, 'url'));
    }

    public function test_saida_gabinete_route_authorization(): void
    {
        $roles = $this->seedRoles();

        $gabA = Gabinete::create(['nome' => 'Gab A']);
        $gabB = Gabinete::create(['nome' => 'Gab B']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gabA->id]);
        $depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gabB->id]);

        $chefeGab = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depA->id]);
        $gabA->responsavel_id = $chefeGab->id;
        $gabA->save();

        $member = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $doc = $this->makeDoc($owner, $depA);

        $saidaData = now()->toDateString();
        config()->set('queue.default', 'sync');
        Event::fake([BroadcastNotificationCreated::class]);
        $destChief = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depB->id]);
        $gabB->responsavel_id = $destChief->id;
        $gabB->save();
        $destMember = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depB->id]);
        $depB2 = Departamento::create(['nome' => 'Dept B2', 'gabinete_id' => $gabB->id]);
        $destMember2 = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depB2->id]);
        $destMember3 = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depB->id]);
        $pivotUser = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $pivotUser->departamentos()->syncWithoutDetaching([$depB->id]);
        $gabC = Gabinete::create(['nome' => 'Gab C']);
        $depC = Departamento::create(['nome' => 'Dept C', 'gabinete_id' => $gabC->id]);
        $outsider = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depC->id]);

        $this->actingAs($chefeGab)
            ->followingRedirects()
            ->post(route('documentos-entradas.saida-gabinete', $doc), [
                'destino_gabinete_id' => $gabB->id,
                'saida_gabinete_data' => $saidaData,
                'encaminhamento_oficio_numero' => '123',
            ])
            ->assertStatus(200);
        $doc->refresh();
        $this->assertSame('encaminhado_externo', $doc->status);
        $this->assertNotNull($doc->saida_gabinete_data);
        $this->assertSame('Gab B', $doc->encaminhamento_orgao);
        $this->assertTrue(
            DocumentoEncaminhamentoExterno::where('documento_entrada_id', $doc->id)
                ->where('destino_gabinete_id', $gabB->id)
                ->where('status', 'enviado')
                ->exists()
        );

        Event::assertDispatched(BroadcastNotificationCreated::class);
        $destChiefNotif = DatabaseNotification::where('notifiable_id', $destChief->id)->latest()->first();
        $this->assertNotNull($destChiefNotif);
        $this->assertSame('encaminhado_externo', data_get($destChiefNotif->data, 'acao'));
        $this->assertSame(route('documentos-entradas.show', $doc->id), data_get($destChiefNotif->data, 'url'));
        $this->assertSame('Documento '.sprintf('%03d/%d', $doc->numero_sequencial, $doc->ano_referencia).' encaminhado para Gab B', data_get($destChiefNotif->data, 'title'));

        $destMemberNotif = DatabaseNotification::where('notifiable_id', $destMember->id)->latest()->first();
        $this->assertNotNull($destMemberNotif);
        $this->assertSame('encaminhado_externo', data_get($destMemberNotif->data, 'acao'));
        $this->assertSame(route('documentos-entradas.show', $doc->id), data_get($destMemberNotif->data, 'url'));

        foreach ([$destMember2, $destMember3] as $usr) {
            $n = DatabaseNotification::where('notifiable_id', $usr->id)->latest()->first();
            $this->assertNotNull($n);
            $this->assertSame('encaminhado_externo', data_get($n->data, 'acao'));
            $this->assertSame(route('documentos-entradas.show', $doc->id), data_get($n->data, 'url'));
        }
        $pivotNotif = DatabaseNotification::where('notifiable_id', $pivotUser->id)->latest()->first();
        $this->assertNotNull($pivotNotif);
        $this->assertSame('encaminhado_externo', data_get($pivotNotif->data, 'acao'));
        $this->assertSame(route('documentos-entradas.show', $doc->id), data_get($pivotNotif->data, 'url'));
        $outsiderNotif = DatabaseNotification::where('notifiable_id', $outsider->id)->where('data->acao', 'encaminhado_externo')->first();
        $this->assertNull($outsiderNotif);
        $actorNotif = DatabaseNotification::where('notifiable_id', $chefeGab->id)->where('data->acao', 'encaminhado_externo')->first();
        $this->assertNull($actorNotif);

        $depIds = \App\Models\Departamento::where('gabinete_id', $gabB->id)->pluck('id')->all();
        $expectedUsers = \App\Models\User::where('id', '!=', $chefeGab->id)
            ->where(function ($q) use ($depIds) {
                $q->whereIn('departamento_id', $depIds)
                    ->orWhereHas('departamentos', function ($q2) use ($depIds) {
                        $q2->whereIn('departamentos.id', $depIds);
                    });
            })->get();
        $expectedIds = $expectedUsers->pluck('id')->all();
        if ($gabB->responsavel_id) {
            $expectedIds[] = $gabB->responsavel_id;
        }
        $expectedIds = array_values(array_unique($expectedIds));

        $notifiedIds = \Illuminate\Notifications\DatabaseNotification::where('data->acao', 'encaminhado_externo')
            ->where('data->documento_id', $doc->id)
            ->pluck('notifiable_id')->unique()->values()->all();
        sort($expectedIds);
        sort($notifiedIds);
        $this->assertSame($expectedIds, $notifiedIds);

        $doc2 = $this->makeDoc($owner, $depA, 2);
        $this->actingAs($member)
            ->post(route('documentos-entradas.saida-gabinete', $doc2), [
                'destino_gabinete_id' => $gabB->id,
                'saida_gabinete_data' => now()->toDateString(),
            ])
            ->assertStatus(403);
    }

    /**
     * quickAction protegia-se apenas com canViewDocument — que é largo de
     * propósito (histórico de encaminhamentos, tarefas, departamentos destino).
     * Conseguir ver nunca pode bastar para despachar.
     */
    public function test_quick_action_nega_despacho_em_documento_de_outro_gabinete(): void
    {
        config()->set('queue.default', 'sync');
        Event::fake([BroadcastNotificationCreated::class]);
        $roles = $this->seedRoles();

        $gabA = Gabinete::create(['nome' => 'Gabinete A']);
        $gabB = Gabinete::create(['nome' => 'Gabinete B']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gabA->id]);
        $depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gabB->id]);

        $respA = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $gabA->update(['responsavel_id' => $respA->id]);

        $ownerB = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depB->id]);
        $doc = $this->makeDoc($ownerB, $depB, 10);

        // Dá visibilidade ao respA por histórico: o documento passou pelo Dept A.
        DocumentoEncaminhamento::create([
            'documento_entrada_id' => $doc->id,
            'origem_departamento_id' => $depA->id,
            'destino_departamento_id' => $depB->id,
            'usuario_id' => $ownerB->id,
            'encaminhado_em' => now()->subDay(),
            'recebido_em' => now(),
            'recebido_por_id' => $ownerB->id,
        ]);

        $this->actingAs($respA)
            ->postJson(route('documentos-entradas.quick-action', $doc), [
                'destino_departamento_ids' => [$depA->id],
                'texto_despacho' => 'Despacho indevido noutro gabinete',
            ])
            ->assertStatus(403);

        $doc->refresh();
        $this->assertNull($doc->texto_despacho);
        $this->assertNull($doc->visto_gabinete_status);
        $this->assertSame('registrado', $doc->status);
    }

    public function test_quick_action_permite_despacho_no_proprio_gabinete(): void
    {
        config()->set('queue.default', 'sync');
        Event::fake([BroadcastNotificationCreated::class]);
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);
        $depDestino = Departamento::create(['nome' => 'Dept Destino', 'gabinete_id' => $gab->id]);

        $resp = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $gab->update(['responsavel_id' => $resp->id]);

        $doc = $this->makeDoc($resp, $depA, 11);

        $this->actingAs($resp)
            ->postJson(route('documentos-entradas.quick-action', $doc), [
                'destino_departamento_ids' => [$depDestino->id],
                'texto_despacho' => 'Para tratamento urgente',
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $doc->refresh();
        $this->assertSame('tratado', $doc->status);
        $this->assertSame('aprovado', $doc->visto_gabinete_status);
    }

    /**
     * O ramo chefe_departamento aceitava qualquer exists:users,id. O
     * DocumentoEntradaTarefaController já validava o destinatário — o
     * quickAction era o caminho paralelo sem essa validação.
     */
    public function test_quick_action_nega_delegacao_a_utilizador_de_outro_departamento(): void
    {
        config()->set('queue.default', 'sync');
        Event::fake([BroadcastNotificationCreated::class]);
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);
        $depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gab->id]);

        $chefeA = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depA->id]);
        $depA->update(['responsavel_id' => $chefeA->id]);
        $estranho = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depB->id]);

        $doc = $this->makeDoc($chefeA, $depA, 12);

        $this->actingAs($chefeA)
            ->postJson(route('documentos-entradas.quick-action', $doc), [
                'assigned_to_user_id' => $estranho->id,
                'descricao' => 'Trate disto',
                'prazo_at' => now()->addDays(3)->toDateString(),
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('documento_tarefas', 0);
        $doc->refresh();
        $this->assertNull($doc->visto_departamento_status);
    }

    public function test_quick_action_permite_delegacao_dentro_do_departamento(): void
    {
        config()->set('queue.default', 'sync');
        Event::fake([BroadcastNotificationCreated::class]);
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);

        $chefeA = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depA->id]);
        $depA->update(['responsavel_id' => $chefeA->id]);
        $tecnico = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);

        $doc = $this->makeDoc($chefeA, $depA, 13);

        $this->actingAs($chefeA)
            ->postJson(route('documentos-entradas.quick-action', $doc), [
                'assigned_to_user_id' => $tecnico->id,
                'descricao' => 'Elabore o parecer técnico',
                'prazo_at' => now()->addDays(3)->toDateString(),
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseCount('documento_tarefas', 1);
        $doc->refresh();
        $this->assertSame('aprovado', $doc->visto_departamento_status);
    }

    /**
     * A rota declara {documento} e o método declarava $documentos_entrada, pelo
     * que o Laravel injetava um DocumentoEntrada vazio e o despacho respondia
     * sempre 403 — o botão "Despachar Documento" da listagem estava morto.
     */
    public function test_despacho_resolve_o_documento_da_rota(): void
    {
        config()->set('queue.default', 'sync');
        Event::fake([BroadcastNotificationCreated::class]);
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);
        $depDestino = Departamento::create(['nome' => 'Dept Destino', 'gabinete_id' => $gab->id]);

        $resp = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $gab->update(['responsavel_id' => $resp->id]);

        $doc = $this->makeDoc($resp, $depA, 30);

        $this->actingAs($resp)
            ->post(route('documentos-entradas.despachar', $doc), [
                'texto_despacho' => 'Para tratamento do departamento destino',
                'departamentos_ids' => [$depDestino->id],
            ])
            ->assertRedirect();

        $doc->refresh();
        $this->assertSame('tratado', $doc->status);
        $this->assertSame('Para tratamento do departamento destino', $doc->texto_despacho);
        $this->assertSame([$depDestino->id], $doc->departamentosDestino()->pluck('departamentos.id')->all());
    }

    /**
     * Despachar para departamento de outro gabinete concedia-lhe visibilidade do
     * documento via departamentosDestino. A saída inter-gabinete tem caminho
     * próprio (saida-gabinete).
     */
    public function test_despacho_recusa_departamento_de_outro_gabinete(): void
    {
        config()->set('queue.default', 'sync');
        Event::fake([BroadcastNotificationCreated::class]);
        $roles = $this->seedRoles();

        $gabA = Gabinete::create(['nome' => 'Gabinete A']);
        $gabB = Gabinete::create(['nome' => 'Gabinete B']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gabA->id]);
        $depAlheio = Departamento::create(['nome' => 'Dept Alheio', 'gabinete_id' => $gabB->id]);

        $resp = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $gabA->update(['responsavel_id' => $resp->id]);

        $doc = $this->makeDoc($resp, $depA, 20);

        $this->actingAs($resp)
            ->postJson(route('documentos-entradas.despachar', $doc), [
                'texto_despacho' => 'Despacho para fora do gabinete',
                'departamentos_ids' => [$depAlheio->id],
            ])
            ->assertStatus(422);

        $this->assertSame(0, $doc->departamentosDestino()->count());

        // O mesmo bloqueio no caminho rápido.
        $this->actingAs($resp)
            ->postJson(route('documentos-entradas.quick-action', $doc), [
                'destino_departamento_ids' => [$depAlheio->id],
                'texto_despacho' => 'Despacho para fora do gabinete',
            ])
            ->assertStatus(422);

        $doc->refresh();
        $this->assertNull($doc->texto_despacho);
    }
}
