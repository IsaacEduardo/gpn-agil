<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentoEntradaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre o fluxo de encaminhamento de Documentos de Entrada entre departamentos:
 * transições de estado, movimento de departamento no recebimento, idempotência
 * (anti-duplo-clique), reversão coerente no cancelamento, auditoria central e lote.
 */
class EncaminhamentoFlowTest extends TestCase
{
    use RefreshDatabase;

    private Gabinete $gab;

    private Departamento $depA;

    private Departamento $depB;

    private Departamento $depC;

    private User $userA;

    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        // A listagem exige 'documentos_entrada.listar' (policy viewAny), pelo que
        // os utilizadores do teste precisam de um perfil com permissões reais.
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
        Role::firstOrCreate(['name' => 'admin'], ['description' => 'Admin']);
        $roleUser = Role::firstOrCreate(['name' => 'user'], ['description' => 'User']);

        $this->gab = Gabinete::create(['nome' => 'Gabinete', 'sigla' => 'G']);
        $this->depA = Departamento::create(['nome' => 'Dep A', 'sigla' => 'A', 'gabinete_id' => $this->gab->id]);
        $this->depB = Departamento::create(['nome' => 'Dep B', 'sigla' => 'B', 'gabinete_id' => $this->gab->id]);
        $this->depC = Departamento::create(['nome' => 'Dep C', 'sigla' => 'C', 'gabinete_id' => $this->gab->id]);

        $this->userA = User::factory()->create(['departamento_id' => $this->depA->id, 'role_id' => $roleUser->id]);
        $this->userB = User::factory()->create(['departamento_id' => $this->depB->id, 'role_id' => $roleUser->id]);
        $this->userA->assignRole($roleUser);
        $this->userB->assignRole($roleUser);
    }

    private function service(): DocumentoEntradaService
    {
        return app(DocumentoEntradaService::class);
    }

    private function doc(int $seq = 1, ?Departamento $dep = null, string $status = 'registrado'): DocumentoEntrada
    {
        return DocumentoEntrada::create([
            'numero_sequencial' => $seq,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'procedencia' => 'Origem',
            'assunto' => 'Assunto',
            'departamento_id' => ($dep ?? $this->depA)->id,
            'user_id' => $this->userA->id,
            'status' => $status,
        ]);
    }

    public function test_forward_creates_encaminhamento_and_audits(): void
    {
        $doc = $this->doc();

        $enc = $this->service()->forwardDocument($doc, $this->depB->id, 'Despacho', $this->userA);

        $this->assertSame($this->depA->id, (int) $enc->origem_departamento_id);
        $this->assertSame($this->depB->id, (int) $enc->destino_departamento_id);
        $this->assertNull($enc->recebido_em);
        $this->assertSame('encaminhado', $doc->fresh()->status);
        $this->assertSame($this->depA->id, (int) $doc->fresh()->departamento_id, 'O departamento só muda no recebimento.');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'documento.encaminhado',
            'auditable_type' => DocumentoEntrada::class,
            'auditable_id' => $doc->id,
        ]);
    }

    public function test_forward_blocks_when_pending_exists(): void
    {
        $doc = $this->doc();
        $this->service()->forwardDocument($doc, $this->depB->id, null, $this->userA);

        $this->expectException(\RuntimeException::class);
        $this->service()->forwardDocument($doc, $this->depC->id, null, $this->userA);
    }

    public function test_receive_moves_department_and_is_idempotent(): void
    {
        $doc = $this->doc();
        $enc = $this->service()->forwardDocument($doc, $this->depB->id, null, $this->userA);

        $first = $this->service()->receiveDocument($doc, $enc, $this->userB);
        $this->assertTrue($first);

        $doc->refresh();
        $this->assertSame($this->depB->id, (int) $doc->departamento_id);
        $this->assertSame('recebido', $doc->status);
        $this->assertNotNull($enc->fresh()->recebido_em);
        $this->assertSame($this->userB->id, (int) $enc->fresh()->recebido_por_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'documento.recebido',
            'auditable_id' => $doc->id,
        ]);

        // Segunda chamada (duplo-clique/corrida) não repete a ação.
        $second = $this->service()->receiveDocument($doc, $enc, $this->userB);
        $this->assertFalse($second);
        $this->assertSame(1, DocumentoEncaminhamento::where('documento_entrada_id', $doc->id)
            ->whereNotNull('recebido_em')->count());
    }

    public function test_cancel_reverts_to_registrado_when_never_received(): void
    {
        $doc = $this->doc();
        $enc = $this->service()->forwardDocument($doc, $this->depB->id, null, $this->userA);

        $this->service()->cancelForwarding($enc, $this->userA);

        $this->assertSame('registrado', $doc->fresh()->status);
        $this->assertDatabaseMissing('documento_encaminhamentos', ['id' => $enc->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'documento.encaminhamento_cancelado',
            'auditable_id' => $doc->id,
        ]);
    }

    public function test_cancel_reverts_to_recebido_when_previously_received(): void
    {
        $doc = $this->doc();
        // 1.º ciclo: encaminha A→B e recebe em B.
        $enc1 = $this->service()->forwardDocument($doc, $this->depB->id, null, $this->userA);
        $this->service()->receiveDocument($doc, $enc1, $this->userB);

        // 2.º encaminhamento B→C, ainda não recebido, e depois cancelado.
        $enc2 = $this->service()->forwardDocument($doc->fresh(), $this->depC->id, null, $this->userB);
        $this->service()->cancelForwarding($enc2, $this->userB);

        $this->assertSame('recebido', $doc->fresh()->status, 'Como já houve recebimento anterior, volta a RECEBIDO.');
    }

    public function test_forward_batch_respects_authorization_and_pending(): void
    {
        $okDoc = $this->doc(1, $this->depA);                 // userA pode (mesmo dep)
        $alheio = $this->doc(2, $this->depB);                // userA não pertence ao dep B
        $pendente = $this->doc(3, $this->depA);
        $this->service()->forwardDocument($pendente, $this->depC->id, null, $this->userA); // já pendente

        $result = $this->service()->forwardBatch(
            [$okDoc->id, $alheio->id, $pendente->id],
            $this->depB->id,
            'Lote',
            $this->userA
        );

        $this->assertSame(1, $result['success']);
        $this->assertSame(2, $result['failed']);
        $this->assertSame('encaminhado', $okDoc->fresh()->status);
        $this->assertSame($this->depB->id, (int) $alheio->fresh()->departamento_id, 'Documento alheio não foi tocado.');
    }

    public function test_endpoint_encaminhar_returns_json_and_blocks_self_department(): void
    {
        $doc = $this->doc();

        // Para o próprio departamento → 422.
        $this->actingAs($this->userA)
            ->postJson(route('documentos-entradas.encaminhar', $doc), [
                'destino_departamento_id' => $this->depA->id,
            ])
            ->assertStatus(422);

        // Para outro departamento → sucesso JSON.
        $this->actingAs($this->userA)
            ->postJson(route('documentos-entradas.encaminhar', $doc), [
                'destino_departamento_id' => $this->depB->id,
                'observacao' => 'ok',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('destino', $this->depB->nome);
    }

    public function test_index_renders_with_forwarding_ui(): void
    {
        $this->doc(1, $this->depA);

        $response = $this->actingAs($this->userA)->get(route('documentos-entradas.index'));

        $response->assertOk();
        // Modal de encaminhamento (individual + lote) e combobox pesquisável presentes.
        $response->assertSee('modalEncaminharEntrada', false);
        $response->assertSee('dep-combobox', false);
        $response->assertSee('btnEncaminharLote', false);
    }

    public function test_endpoint_batch_encaminhar_returns_json(): void
    {
        $d1 = $this->doc(1, $this->depA);
        $d2 = $this->doc(2, $this->depA);

        $this->actingAs($this->userA)
            ->postJson(route('documentos-entradas.batch.encaminhar'), [
                'ids' => json_encode([$d1->id, $d2->id]),
                'destino_departamento_id' => $this->depB->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('success', true);

        $this->assertSame('encaminhado', $d1->fresh()->status);
        $this->assertSame('encaminhado', $d2->fresh()->status);
    }
}
