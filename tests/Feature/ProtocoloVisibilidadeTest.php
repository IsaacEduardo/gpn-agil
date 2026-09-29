<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Decisão do cliente (2026-09-29): o protocolo de entrada só é visível ao
 * Expediente (departamento com is_area_expediente) e ao Secretário (chefe do
 * gabinete desse departamento — a Secretaria Geral), além do admin.
 */
class ProtocoloVisibilidadeTest extends TestCase
{
    use RefreshDatabase;

    private DocumentoEntrada $doc;

    private User $expediente;

    private User $secretario;

    private User $tecnico;

    private User $chefeDept;

    private User $chefeOutroGabinete;

    protected function setUp(): void
    {
        parent::setUp();

        $roleChefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['guard_name' => 'web']);

        $sg = Gabinete::create(['nome' => 'Secretaria Geral', 'sigla' => 'SG']);
        $outroGab = Gabinete::create(['nome' => 'Gabinete do Governador', 'sigla' => 'GG']);
        $aex = Departamento::create(['nome' => 'Área do Expediente', 'sigla' => 'AEX', 'gabinete_id' => $sg->id, 'is_area_expediente' => true]);
        $dlp = Departamento::create(['nome' => 'Logística', 'sigla' => 'DLP', 'gabinete_id' => $sg->id]);
        $dgg = Departamento::create(['nome' => 'Apoio', 'sigla' => 'DAP', 'gabinete_id' => $outroGab->id]);

        $this->expediente = User::factory()->create(['departamento_id' => $aex->id]);
        $this->tecnico = User::factory()->create(['departamento_id' => $dlp->id]);

        $this->chefeDept = User::factory()->create(['departamento_id' => $dlp->id, 'role_id' => $roleChefe->id]);
        $this->chefeDept->assignRole('chefe-departamento');
        $dlp->update(['responsavel_id' => $this->chefeDept->id]);

        $this->secretario = User::factory()->create(['departamento_id' => $dlp->id]);
        $sg->update(['responsavel_id' => $this->secretario->id]);

        $this->chefeOutroGabinete = User::factory()->create(['departamento_id' => $dgg->id]);
        $outroGab->update(['responsavel_id' => $this->chefeOutroGabinete->id]);

        $this->doc = DocumentoEntrada::create([
            'assunto' => 'Ofício',
            'numero_sequencial' => 7,
            'ano_referencia' => 2026,
            'data_entrada' => now(),
            'procedencia' => 'Externa',
            'departamento_id' => $dlp->id,
            'user_id' => $this->expediente->id,
            'status' => 'recebido',
            'arquivado' => false,
        ]);
    }

    public function test_expediente_e_secretario_veem_o_protocolo(): void
    {
        foreach ([$this->expediente, $this->secretario] as $user) {
            $this->assertTrue(Gate::forUser($user)->allows('verProtocolo', $this->doc), "user {$user->id}");
            $this->actingAs($user)->get(route('documentos-entradas.protocolo', $this->doc))->assertOk();
            $this->actingAs($user)->get(route('documentos-entradas.protocolo.etiqueta', $this->doc))->assertOk();
        }
    }

    public function test_restantes_perfis_recebem_403_e_nao_veem_o_menu(): void
    {
        foreach ([$this->tecnico, $this->chefeDept, $this->chefeOutroGabinete] as $user) {
            $this->assertFalse(Gate::forUser($user)->allows('verProtocolo', $this->doc), "user {$user->id}");

            foreach (['documentos-entradas.protocolo', 'documentos-entradas.protocolo.pdf', 'documentos-entradas.protocolo.etiqueta'] as $rota) {
                $resposta = $this->actingAs($user)->get(route($rota, $this->doc));
                $this->assertContains($resposta->status(), [403], "{$rota} user {$user->id}");
            }
        }

        $this->actingAs($this->tecnico)->get(route('documentos-entradas.show', $this->doc))
            ->assertOk()
            ->assertDontSee(route('documentos-entradas.protocolo', $this->doc));
    }

    public function test_quem_registou_imprime_a_etiqueta_do_seu_registo(): void
    {
        $this->doc->update(['user_id' => $this->tecnico->id]);

        $this->assertTrue(Gate::forUser($this->tecnico)->allows('verProtocolo', $this->doc->fresh()));
    }

    public function test_consulta_publica_por_codigo_nao_e_afectada(): void
    {
        $this->get(route('protocolo.publico', 'CODIGO-INEXISTENTE'))->assertStatus(404);
    }
}
