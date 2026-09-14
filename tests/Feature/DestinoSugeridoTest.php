<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\Procedencia;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentoEntradaService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sugerir o departamento de destino a partir do histórico da procedência.
 *
 * Com pouco histórico isto é adivinhação, não sugestão: uma sugestão errada a
 * cada registo treina o balcão a ignorá-la. Daí o limiar de confiança — a
 * funcionalidade fica silenciosa até haver base para falar.
 */
class DestinoSugeridoTest extends TestCase
{
    use RefreshDatabase;

    private Procedencia $proc;

    private Departamento $depA;

    private Departamento $depB;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);

        $papel = Role::where('name', 'user')->firstOrFail();
        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $this->depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);
        $this->depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gab->id]);
        $this->user = User::factory()->create(['role_id' => $papel->id, 'departamento_id' => $this->depA->id]);
        $this->proc = Procedencia::create(['nome' => 'Ministério das Finanças', 'ativo' => true]);
    }

    private function historico(Departamento $dep, int $quantos, int $desde = 1): void
    {
        for ($i = 0; $i < $quantos; $i++) {
            DocumentoEntrada::create([
                'numero_sequencial' => $desde + $i,
                'ano_referencia' => (int) date('Y'),
                'data_entrada' => now()->subDays($i),
                'assunto' => 'Histórico '.($desde + $i),
                'procedencia_id' => $this->proc->id,
                'departamento_id' => $dep->id,
                'user_id' => $this->user->id,
                'status' => 'registrado',
            ]);
        }
    }

    private function sugestao(): ?int
    {
        return app(DocumentoEntradaService::class)->departamentoSugeridoPara($this->proc->id);
    }

    /** Sem histórico não há sugestão. */
    public function test_nao_sugere_sem_historico(): void
    {
        $this->assertNull($this->sugestao());
    }

    /** Um caso só não é histórico — é uma coincidência. */
    public function test_nao_sugere_com_um_unico_documento(): void
    {
        $this->historico($this->depA, 1);

        $this->assertNull($this->sugestao());
    }

    public function test_sugere_quando_o_padrao_e_consistente(): void
    {
        $this->historico($this->depA, 4);

        $this->assertSame($this->depA->id, $this->sugestao());
    }

    /** Procedência dividida entre setores não gera sugestão fiável. */
    public function test_nao_sugere_quando_o_historico_esta_dividido(): void
    {
        $this->historico($this->depA, 3, 1);
        $this->historico($this->depB, 3, 10);

        $this->assertNull($this->sugestao());
    }

    public function test_sugere_o_dominante_quando_ha_maioria_clara(): void
    {
        $this->historico($this->depA, 5, 1);
        $this->historico($this->depB, 1, 20);

        $this->assertSame($this->depA->id, $this->sugestao());
    }

    /** Documentos arquivados continuam a contar: o padrão histórico é o mesmo. */
    public function test_o_formulario_de_registo_expoe_a_sugestao(): void
    {
        $this->historico($this->depA, 4);
        $this->user->assignRole(Role::where('name', 'user')->firstOrFail());

        $this->actingAs($this->user)
            ->get(route('documentos-entradas.create'))
            ->assertStatus(200)
            ->assertSee('data-sugestao-destino', false);
    }
}
