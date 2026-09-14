<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Despachar é a operação mais repetitiva do gabinete e era a única sem lote —
 * já havia receber e encaminhar em lote.
 *
 * A ruga: o P3 restringe os destinos ao gabinete DE CADA documento. Uma lista de
 * destinos comum aplicada a documentos de gabinetes diferentes reabriria o
 * buraco que o P3 fechou.
 */
class DocumentoEntradaDespachoLoteTest extends TestCase
{
    use RefreshDatabase;

    private Gabinete $gabA;

    private Departamento $depA;

    private Departamento $destinoA;

    private User $respA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        Notification::fake();

        $papelUser = Role::where('name', 'user')->firstOrFail();

        $this->gabA = Gabinete::create(['nome' => 'Gabinete A']);
        $this->depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $this->gabA->id]);
        $this->destinoA = Departamento::create(['nome' => 'Destino A', 'gabinete_id' => $this->gabA->id]);

        $this->respA = User::factory()->create(['role_id' => $papelUser->id, 'departamento_id' => $this->depA->id]);
        $this->respA->assignRole($papelUser);
        $this->gabA->update(['responsavel_id' => $this->respA->id]);
    }

    private function doc(int $seq, ?Departamento $dep = null): DocumentoEntrada
    {
        return DocumentoEntrada::create([
            'numero_sequencial' => $seq,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => 'Documento '.$seq,
            'departamento_id' => ($dep ?? $this->depA)->id,
            'user_id' => $this->respA->id,
            'status' => 'pendente_tratamento',
        ]);
    }

    public function test_despacha_varios_documentos_de_uma_vez(): void
    {
        $docs = collect([1, 2, 3])->map(fn ($i) => $this->doc($i));

        $this->actingAs($this->respA)
            ->postJson(route('documentos-entradas.batch.despachar'), [
                'ids' => json_encode($docs->pluck('id')->all()),
                'texto_despacho' => 'Para tratamento urgente do departamento de destino.',
                'departamentos_ids' => [$this->destinoA->id],
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true, 'despachados' => 3]);

        foreach ($docs as $d) {
            $d->refresh();
            $this->assertSame('tratado', $d->status);
            $this->assertSame('Para tratamento urgente do departamento de destino.', $d->texto_despacho);
            $this->assertSame($this->respA->id, $d->despachado_por_id);
            $this->assertNotNull($d->data_despacho);
            $this->assertSame([$this->destinoA->id], $d->departamentosDestino()->pluck('departamentos.id')->all());

            // O lote reutiliza despacharDocumento(), o mesmo do endpoint de um
            // documento só — e esse NÃO marca o visto do gabinete, ao contrário
            // do quickAction. Divergência pré-existente entre os dois caminhos,
            // deliberadamente não alterada aqui.
            $this->assertNull($d->visto_gabinete_status);
        }
    }

    /** Uma seleção que atravessa gabinetes é recusada em bloco, não em silêncio. */
    public function test_recusa_selecao_que_atravessa_gabinetes(): void
    {
        $gabB = Gabinete::create(['nome' => 'Gabinete B']);
        $depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gabB->id]);

        $docA = $this->doc(10);
        $docB = $this->doc(11, $depB);

        $this->actingAs($this->respA)
            ->postJson(route('documentos-entradas.batch.despachar'), [
                'ids' => json_encode([$docA->id, $docB->id]),
                'texto_despacho' => 'Despacho comum',
                'departamentos_ids' => [$this->destinoA->id],
            ])
            ->assertStatus(422);

        $this->assertNull($docA->fresh()->texto_despacho);
        $this->assertNull($docB->fresh()->texto_despacho);
    }

    /** O P3 continua a valer: destino tem de ser do gabinete do documento. */
    public function test_recusa_destino_de_outro_gabinete(): void
    {
        $gabB = Gabinete::create(['nome' => 'Gabinete B']);
        $depAlheio = Departamento::create(['nome' => 'Dept Alheio', 'gabinete_id' => $gabB->id]);

        $doc = $this->doc(20);

        $this->actingAs($this->respA)
            ->postJson(route('documentos-entradas.batch.despachar'), [
                'ids' => json_encode([$doc->id]),
                'texto_despacho' => 'Despacho para fora',
                'departamentos_ids' => [$depAlheio->id],
            ])
            ->assertStatus(422);

        $this->assertNull($doc->fresh()->texto_despacho);
    }

    /** Quem não pode despachar o documento não o despacha em lote. */
    public function test_salta_documentos_sem_permissao(): void
    {
        $papelUser = Role::where('name', 'user')->firstOrFail();
        $estranho = User::factory()->create(['role_id' => $papelUser->id, 'departamento_id' => $this->depA->id]);
        $estranho->assignRole($papelUser);

        $doc = $this->doc(30);

        $this->actingAs($estranho)
            ->postJson(route('documentos-entradas.batch.despachar'), [
                'ids' => json_encode([$doc->id]),
                'texto_despacho' => 'Despacho sem permissão',
                'departamentos_ids' => [$this->destinoA->id],
            ])
            ->assertStatus(422);

        $this->assertNull($doc->fresh()->texto_despacho);
    }

    /** O limite de cardinalidade da Fase 5 vale também aqui. */
    public function test_respeita_o_limite_de_lote(): void
    {
        config()->set('documentos.limite_lote', 2);

        $this->actingAs($this->respA)
            ->postJson(route('documentos-entradas.batch.despachar'), [
                'ids' => json_encode(range(1, 50)),
                'texto_despacho' => 'Despacho em massa',
                'departamentos_ids' => [$this->destinoA->id],
            ])
            ->assertStatus(422);
    }
}
