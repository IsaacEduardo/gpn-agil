<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Viatura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Registo de viatura: até 2026-09-29 todos os cadastros davam erro 500 (a
 * camada de domínio inseria sem marca nem ano), os anexos partiam por falta
 * do import de Str e a edição perdia o tipo e a afectação.
 */
class ViaturaCadastroTest extends TestCase
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

    private function dados(array $extra = []): array
    {
        return $extra + [
            'identificacao' => 'AUTO',
            'placa' => 'ld-12-34-ab',
            'modelo' => 'Hilux',
            'marca' => 'Toyota',
            'ano' => 2022,
            'tipo' => 'Carro',
            'status_operacional' => 'Operacional',
            'afetacao' => 'Secretaria Geral',
            'motor_numero' => '2GD-4455871',
            'cor' => 'Branca',
        ];
    }

    public function test_cadastro_grava_todas_as_colunas_e_os_anexos(): void
    {
        $this->actingAs($this->admin)->post(route('viaturas.store'), $this->dados([
            'fotos' => [UploadedFile::fake()->image('frente.jpg')],
            'documentos' => [UploadedFile::fake()->create('livrete.pdf', 50, 'application/pdf')],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $viatura = Viatura::firstOrFail();
        $this->assertSame('LD-12-34-AB', $viatura->placa);
        $this->assertSame('Hilux', $viatura->modelo); // antes: "Toyota Hilux"
        $this->assertSame('Toyota', $viatura->marca);
        $this->assertSame(2022, (int) $viatura->ano);
        $this->assertSame('Carro', $viatura->tipo);
        $this->assertSame('Secretaria Geral', $viatura->afetacao);
        $this->assertSame('2GD-4455871', $viatura->motor_numero);
        $this->assertMatchesRegularExpression('/^V-\d{8}-[A-Z0-9]{4}$/', $viatura->identificacao);

        $this->assertCount(2, $viatura->fotos);
        foreach ($viatura->fotos as $anexo) {
            Storage::disk('public')->assertExists($anexo->caminho_arquivo);
        }
    }

    public function test_edicao_grava_tipo_e_afectacao(): void
    {
        $this->actingAs($this->admin)->post(route('viaturas.store'), $this->dados());
        $viatura = Viatura::firstOrFail();

        $this->actingAs($this->admin)->put(route('viaturas.update', $viatura), $this->dados([
            'identificacao' => $viatura->identificacao,
            'placa' => $viatura->placa,
            'tipo' => 'Caminhão',
            'afetacao' => 'Gabinete do Governador',
            'fotos' => [UploadedFile::fake()->image('lateral.jpg')],
        ]))->assertRedirect(route('viaturas.show', $viatura))->assertSessionHasNoErrors();

        $viatura->refresh();
        $this->assertSame('Caminhão', $viatura->tipo);
        $this->assertSame('Gabinete do Governador', $viatura->afetacao);
        $this->assertSame('Hilux', $viatura->modelo);
        $this->assertCount(1, $viatura->fotos);
    }

    public function test_tipo_fora_da_lista_e_matricula_repetida_sao_recusados(): void
    {
        $this->actingAs($this->admin)->post(route('viaturas.store'), $this->dados(['tipo' => 'Avião']))
            ->assertSessionHasErrors('tipo');

        $this->actingAs($this->admin)->post(route('viaturas.store'), $this->dados())->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('viaturas.store'), $this->dados())
            ->assertSessionHasErrors('placa');

        $this->assertSame(1, Viatura::count());
    }

    public function test_formulario_nao_pede_quilometragem(): void
    {
        $this->actingAs($this->admin)->get(route('viaturas.create'))
            ->assertOk()
            ->assertDontSee('name="quilometragem"', false);
    }
}
