<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\Gabinete;
use App\Models\Procedencia;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Depois de registar uma entrada, a etiqueta adesiva vai sozinha para impressão
 * — o balcão regista e passa ao seguinte, sem ter de abrir o documento.
 *
 * O disparo é um iframe oculto na listagem, não window.open: um pop-up que não
 * nasce de um clique do utilizador é bloqueado por omissão.
 */
class EtiquetaAutomaticaNoRegistoTest extends TestCase
{
    use RefreshDatabase;

    private User $balcao;

    private Departamento $dep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        Storage::fake('public');
        Notification::fake();

        $papel = Role::where('name', 'user')->firstOrFail();
        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $this->dep = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);

        $this->balcao = User::factory()->create(['role_id' => $papel->id, 'departamento_id' => $this->dep->id]);
        $this->balcao->assignRole($papel);

        DocumentoEspecie::firstOrCreate(['nome' => 'Ofício'], ['ativo' => true, 'ordem' => 1]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'classificacao_especie' => 'Ofício',
            'assunto' => 'Pedido de parecer',
            'departamento_id' => $this->dep->id,
        ], $extra);
    }

    public function test_registo_com_sucesso_sinaliza_a_etiqueta(): void
    {
        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload())
            ->assertRedirect(route('documentos-entradas.index'))
            ->assertSessionHas('etiqueta_para_imprimir', DocumentoEntrada::firstOrFail()->id);
    }

    /** O destino do redirect não muda: o operador cai na listagem, pronto para o seguinte. */
    public function test_continua_a_redirecionar_para_a_listagem(): void
    {
        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload())
            ->assertRedirect(route('documentos-entradas.index'))
            ->assertSessionHas('success');
    }

    /** O aviso de duplicado devolve back() sem ter criado nada — não há etiqueta. */
    public function test_aviso_de_duplicado_nao_sinaliza_etiqueta(): void
    {
        $proc = Procedencia::create(['nome' => 'Ministério das Finanças', 'ativo' => true]);

        $comum = [
            'procedencia_id' => $proc->id,
            'classificacao_ref_numero' => '123/2026',
            'data_documento' => '2026-09-01',
        ];

        $this->actingAs($this->balcao)->post(route('documentos-entradas.store'), $this->payload($comum));

        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload($comum))
            ->assertSessionHasErrors('duplicado')
            ->assertSessionMissing('etiqueta_para_imprimir');
    }

    public function test_listagem_com_o_flash_injeta_o_iframe(): void
    {
        $doc = DocumentoEntrada::create([
            'numero_sequencial' => 7,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => 'Registado agora',
            'departamento_id' => $this->dep->id,
            'user_id' => $this->balcao->id,
            'status' => 'pendente_tratamento',
        ]);

        $resposta = $this->actingAs($this->balcao)
            ->withSession([
                'success' => 'Documento registrado com sucesso.',
                'etiqueta_para_imprimir' => $doc->id,
            ])
            ->get(route('documentos-entradas.index'));

        $resposta->assertStatus(200);
        $resposta->assertSee('<iframe', false);
        $resposta->assertSee(
            route('documentos-entradas.protocolo.etiqueta', [$doc->id, 'auto_print' => 1]),
            false,
        );
        // A saída manual, para quando a impressão não acontecer.
        $resposta->assertSee('Imprimir novamente', false);
    }

    public function test_listagem_sem_o_flash_nao_injeta_iframe(): void
    {
        $resposta = $this->actingAs($this->balcao)->get(route('documentos-entradas.index'));

        $resposta->assertStatus(200);
        $resposta->assertDontSee('Impressão da etiqueta do protocolo', false);
    }

    /** A rota da etiqueta continua guardada pela ability 'view' (P2). */
    public function test_etiqueta_continua_a_exigir_autorizacao(): void
    {
        $papel = Role::where('name', 'user')->firstOrFail();
        $gabB = Gabinete::create(['nome' => 'Gabinete B']);
        $depB = Departamento::create(['nome' => 'Departamento B', 'gabinete_id' => $gabB->id]);
        $estranho = User::factory()->create(['role_id' => $papel->id, 'departamento_id' => $depB->id]);
        $estranho->assignRole($papel);

        $doc = DocumentoEntrada::create([
            'numero_sequencial' => 8,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => 'De outro gabinete',
            'departamento_id' => $this->dep->id,
            'user_id' => $this->balcao->id,
            'status' => 'registrado',
        ]);

        $this->actingAs($estranho)
            ->get(route('documentos-entradas.protocolo.etiqueta', [$doc, 'auto_print' => 1]))
            ->assertStatus(403);

        $this->actingAs($this->balcao)
            ->get(route('documentos-entradas.protocolo.etiqueta', [$doc, 'auto_print' => 1]))
            ->assertStatus(200);
    }

    /** O carimbo de impressão precisa do token — a etiqueta não o tinha. */
    public function test_etiqueta_em_auto_print_traz_o_csrf_para_o_carimbo(): void
    {
        $doc = DocumentoEntrada::create([
            'numero_sequencial' => 9,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => 'Com carimbo',
            'departamento_id' => $this->dep->id,
            'user_id' => $this->balcao->id,
            'status' => 'registrado',
        ]);

        $this->actingAs($this->balcao)
            ->get(route('documentos-entradas.protocolo.etiqueta', [$doc, 'auto_print' => 1]))
            ->assertStatus(200)
            ->assertSee('name="csrf-token"', false)
            ->assertSee('afterprint', false);
    }
}
