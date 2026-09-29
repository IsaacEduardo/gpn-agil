<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\TermoEntrega;
use App\Models\User;
use App\Models\Viatura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * O nº do motor (e a cor) passam a ser registados no cadastro da viatura, e a
 * credencial usa esse valor — sem o reescrever nem aceitar outro diferente.
 */
class ViaturaMotorCredencialTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $role = Role::firstOrCreate(['name' => 'admin'], ['guard_name' => 'web']);
        $this->admin = User::factory()->create(['role_id' => $role->id]);
        $this->admin->assignRole('admin');
    }

    /** Criada directamente; o cadastro por HTTP é coberto em ViaturaCadastroTest. */
    private function cadastrarViatura(array $extra = []): Viatura
    {
        return Viatura::create($extra + [
            'identificacao' => 'V-TESTE-1',
            'placa' => 'LD-12-34-AB',
            'modelo' => 'Hilux',
            'marca' => 'Toyota',
            'ano' => 2022,
            'tipo' => 'Carro',
            'status_operacional' => 'Operacional',
        ]);
    }

    private function credencial(Viatura $viatura, array $extra = [])
    {
        return $this->actingAs($this->admin)->post(route('credenciais.store'), $extra + [
            'tipo_credencial' => 'utilizacao_normal',
            'beneficiario_nome' => 'João Manuel',
            'beneficiario_documento' => '000123456HA042',
            'beneficiario_documento_emitido_em' => '2020-01-10',
            'beneficiario_documento_emitido_local' => 'Lubango',
            'viatura_id' => $viatura->id,
        ]);
    }

    public function test_edicao_grava_motor_e_cor_e_mostra_na_ficha(): void
    {
        $viatura = $this->cadastrarViatura();

        $this->actingAs($this->admin)->get(route('viaturas.create'))
            ->assertOk()->assertSee('name="motor_numero"', false)->assertSee('name="cor"', false);

        $this->actingAs($this->admin)->put(route('viaturas.update', $viatura), [
            'identificacao' => $viatura->identificacao,
            'placa' => $viatura->placa,
            'modelo' => 'Hilux',
            'marca' => 'Toyota',
            'ano' => 2022,
            'tipo' => 'Carro',
            'status_operacional' => 'Operacional',
            'motor_numero' => '2GD-4455871',
            'cor' => 'Branca',
        ])->assertRedirect(route('viaturas.show', $viatura))->assertSessionHasNoErrors();

        $viatura->refresh();
        $this->assertSame('2GD-4455871', $viatura->motor_numero);
        $this->assertSame('Branca', $viatura->cor);

        $this->actingAs($this->admin)->get(route('viaturas.edit', $viatura))
            ->assertOk()->assertSee('value="2GD-4455871"', false);
        $this->actingAs($this->admin)->get(route('viaturas.show', $viatura))
            ->assertOk()->assertSee('2GD-4455871')->assertSee('Branca');
    }

    public function test_credencial_usa_o_motor_da_viatura_no_termo(): void
    {
        $viatura = $this->cadastrarViatura(['motor_numero' => '2GD-4455871', 'cor' => 'Branca']);

        $this->credencial($viatura, ['motor_numero' => '2GD-4455871'])->assertRedirect()->assertSessionHasNoErrors();

        $termo = TermoEntrega::where('tipo', 'credencial')->latest('id')->firstOrFail();
        $this->assertSame('2GD-4455871', $termo->motor_numero);
        $this->assertSame('Branca', $termo->cor_viatura);
    }

    public function test_credencial_recusa_motor_diferente_do_registado(): void
    {
        $viatura = $this->cadastrarViatura(['motor_numero' => '2GD-4455871']);

        $this->credencial($viatura, ['motor_numero' => 'OUTRO-000'])->assertSessionHasErrors('motor_numero');

        $this->assertSame('2GD-4455871', $viatura->fresh()->motor_numero);
        $this->assertSame(0, TermoEntrega::where('tipo', 'credencial')->count());
    }

    public function test_viatura_sem_motor_recebe_o_indicado_na_credencial(): void
    {
        $viatura = $this->cadastrarViatura();

        $this->credencial($viatura, ['motor_numero' => '1KD-777'])->assertSessionHasNoErrors();

        $this->assertSame('1KD-777', $viatura->fresh()->motor_numero);
    }

    public function test_so_utilizadores_do_dlp_emitem_credenciais(): void
    {
        $viatura = $this->cadastrarViatura(['motor_numero' => '2GD-4455871']);
        $gab = \App\Models\Gabinete::create(['nome' => 'Secretaria Geral', 'sigla' => 'SG']);
        $dlp = \App\Models\Departamento::create(['nome' => 'Logística e Património', 'sigla' => 'DLP', 'gabinete_id' => $gab->id]);
        $dcp = \App\Models\Departamento::create(['nome' => 'Contratação Pública', 'sigla' => 'DCP', 'gabinete_id' => $gab->id]);

        $doDlp = User::factory()->create(['departamento_id' => $dlp->id]);
        $deOutro = User::factory()->create(['departamento_id' => $dcp->id]);

        $this->actingAs($deOutro)->get(route('credenciais.create'))->assertForbidden();
        $this->admin = $deOutro;
        $this->credencial($viatura)->assertForbidden();
        $this->assertSame(0, TermoEntrega::where('tipo', 'credencial')->count());

        $this->actingAs($doDlp)->get(route('credenciais.create'))->assertOk();
        $this->admin = $doDlp;
        $this->credencial($viatura)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, TermoEntrega::where('tipo', 'credencial')->count());
    }
}
