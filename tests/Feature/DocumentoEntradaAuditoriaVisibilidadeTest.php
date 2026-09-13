<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A aba de auditoria mostra o endereço IP e o antes/depois de cada alteração.
 * Ver o documento — regra deliberadamente larga — não pode bastar para ver
 * isso: expõe os IPs dos colegas a qualquer pessoa que tenha tido o documento
 * a passar pelo seu departamento.
 */
class DocumentoEntradaAuditoriaVisibilidadeTest extends TestCase
{
    use RefreshDatabase;

    private Gabinete $gab;

    private Departamento $depA;

    private Departamento $depB;

    private DocumentoEntrada $doc;

    private User $dono;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionsSeeder::class);

        $papelUser = Role::where('name', 'user')->firstOrFail();

        $this->gab = Gabinete::create(['nome' => 'Gabinete A']);
        $this->depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $this->gab->id]);
        $this->depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $this->gab->id]);

        $this->dono = User::factory()->create(['role_id' => $papelUser->id, 'departamento_id' => $this->depA->id]);
        $this->dono->assignRole($papelUser);

        $this->doc = DocumentoEntrada::create([
            'numero_sequencial' => 1,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => 'Documento tramitado',
            'departamento_id' => $this->depA->id,
            'user_id' => $this->dono->id,
            'status' => 'recebido',
        ]);
    }

    private function utilizador(string $papel, Departamento $dep): User
    {
        $role = Role::where('name', $papel)->firstOrFail();
        $u = User::factory()->create(['role_id' => $role->id, 'departamento_id' => $dep->id]);
        $u->assignRole($role);

        return $u;
    }

    public function test_quem_so_ve_por_historico_nao_ve_a_auditoria(): void
    {
        $externo = $this->utilizador('user', $this->depB);

        // Visibilidade apenas por histórico de tramitação.
        DocumentoEncaminhamento::create([
            'documento_entrada_id' => $this->doc->id,
            'origem_departamento_id' => $this->depB->id,
            'destino_departamento_id' => $this->depA->id,
            'usuario_id' => $this->dono->id,
            'encaminhado_em' => now()->subDay(),
            'recebido_em' => now(),
            'recebido_por_id' => $this->dono->id,
        ]);

        $resposta = $this->actingAs($externo)->get(route('documentos-entradas.show', $this->doc));

        $resposta->assertStatus(200);
        $this->assertFalse($resposta->viewData('canVerAuditoria'));
        $resposta->assertDontSee('Endereço IP', false);
    }

    public function test_chefia_do_departamento_ve_a_auditoria(): void
    {
        $chefe = $this->utilizador('chefe-departamento', $this->depA);
        $this->depA->update(['responsavel_id' => $chefe->id]);

        $resposta = $this->actingAs($chefe)->get(route('documentos-entradas.show', $this->doc));

        $resposta->assertStatus(200);
        $this->assertTrue($resposta->viewData('canVerAuditoria'));
        $resposta->assertSee('Endereço IP', false);
    }

    public function test_responsavel_do_gabinete_ve_a_auditoria(): void
    {
        $resp = $this->utilizador('user', $this->depA);
        $this->gab->update(['responsavel_id' => $resp->id]);

        $resposta = $this->actingAs($resp)->get(route('documentos-entradas.show', $this->doc));

        $this->assertTrue($resposta->viewData('canVerAuditoria'));
    }

    public function test_administrador_ve_a_auditoria(): void
    {
        $admin = $this->utilizador('admin', $this->depB);

        $resposta = $this->actingAs($admin)->get(route('documentos-entradas.show', $this->doc));

        $this->assertTrue($resposta->viewData('canVerAuditoria'));
    }

    /** O total tem de ser visível quando a listagem é truncada. */
    public function test_indica_o_total_quando_trunca_a_auditoria(): void
    {
        $chefe = $this->utilizador('chefe-departamento', $this->depA);
        $this->depA->update(['responsavel_id' => $chefe->id]);

        // O trait Auditable grava um registo por gravação.
        for ($i = 0; $i < 55; $i++) {
            $this->doc->update(['observacoes' => 'alteração '.$i]);
        }

        $resposta = $this->actingAs($chefe)->get(route('documentos-entradas.show', $this->doc));

        $this->assertSame(50, $resposta->viewData('audits')->count());
        $this->assertGreaterThan(50, $resposta->viewData('auditsTotal'));
    }
}
