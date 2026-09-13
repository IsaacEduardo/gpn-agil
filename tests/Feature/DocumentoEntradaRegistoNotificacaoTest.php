<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Notifications\DocumentoEntradaRegistado;
use App\Services\DocumentoEntradaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * O formulário de registo prometia que o documento seguia "automaticamente para
 * a caixa de entrada do departamento", mas o registo não notificava ninguém: o
 * arranque do fluxo dependia de alguém abrir a listagem por iniciativa própria.
 */
class DocumentoEntradaRegistoNotificacaoTest extends TestCase
{
    use RefreshDatabase;

    private function cenario(): array
    {
        $roleUser = Role::firstOrCreate(['name' => 'user'], ['description' => 'Utilizador']);
        $roleChefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['description' => 'Chefe']);

        $gab = Gabinete::create(['nome' => 'Gabinete A', 'sigla' => 'GABA']);
        $dep = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);

        $chefe = User::factory()->create(['role_id' => $roleChefe->id, 'departamento_id' => $dep->id]);
        $respGab = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $dep->id]);
        $gab->update(['responsavel_id' => $respGab->id]);

        $balcao = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $dep->id]);

        return compact('gab', 'dep', 'chefe', 'respGab', 'balcao');
    }

    public function test_registo_notifica_chefia_do_departamento_de_destino(): void
    {
        Storage::fake('public');
        Notification::fake();

        ['dep' => $dep, 'chefe' => $chefe, 'respGab' => $respGab, 'balcao' => $balcao] = $this->cenario();

        $this->actingAs($balcao);

        $doc = app(DocumentoEntradaService::class)->createDocument([
            'assunto' => 'Pedido de parecer urgente',
            'departamento_id' => $dep->id,
            'procedencia' => 'Ministério das Finanças',
        ]);

        Notification::assertSentTo($chefe, DocumentoEntradaRegistado::class);
        Notification::assertSentTo($respGab, DocumentoEntradaRegistado::class);

        // Quem regista não é notificado do seu próprio registo.
        Notification::assertNotSentTo($balcao, DocumentoEntradaRegistado::class);

        $this->assertNotNull($doc->id);
    }

    public function test_payload_do_registo_segue_o_contrato_canonico(): void
    {
        Storage::fake('public');
        Notification::fake();

        ['dep' => $dep, 'chefe' => $chefe, 'balcao' => $balcao] = $this->cenario();

        $this->actingAs($balcao);

        app(DocumentoEntradaService::class)->createDocument([
            'assunto' => 'Pedido de parecer urgente',
            'departamento_id' => $dep->id,
        ]);

        Notification::assertSentTo($chefe, DocumentoEntradaRegistado::class, function ($notification) use ($chefe) {
            $payload = $notification->toArray($chefe);

            return $payload['type'] === 'documento_registado'
                && str_contains($payload['title'], '001/'.date('Y'))
                && str_contains((string) $payload['body'], 'Pedido de parecer urgente')
                && ! empty($payload['url'])
                && ! empty($payload['documento_id']);
        });
    }

    /**
     * A notificação nunca pode fazer falhar o registo — nem, por via da retentativa
     * de numeração, provocar um segundo documento.
     */
    public function test_registo_conclui_mesmo_sem_chefia_definida(): void
    {
        Storage::fake('public');
        Notification::fake();

        $roleUser = Role::firstOrCreate(['name' => 'user'], ['description' => 'Utilizador']);
        $gab = Gabinete::create(['nome' => 'Gabinete Sem Chefia']);
        $dep = Departamento::create(['nome' => 'Departamento Órfão', 'gabinete_id' => $gab->id]);
        $balcao = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $dep->id]);

        $this->actingAs($balcao);

        $doc = app(DocumentoEntradaService::class)->createDocument([
            'assunto' => 'Documento sem chefia de destino',
            'departamento_id' => $dep->id,
        ]);

        $this->assertDatabaseHas('documentos_entradas', ['id' => $doc->id]);
        $this->assertDatabaseCount('documentos_entradas', 1);
        Notification::assertNothingSent();
    }
}
