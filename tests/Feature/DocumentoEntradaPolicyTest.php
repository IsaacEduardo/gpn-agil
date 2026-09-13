<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Policies\DocumentoEntradaPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentoEntradaPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): array
    {
        $admin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrador']);
        $user = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
        $chefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['description' => 'Chefe de departamento']);

        return compact('admin', 'user', 'chefe');
    }

    private function makeDoc(User $owner, Departamento $dep): DocumentoEntrada
    {
        return DocumentoEntrada::create([
            'numero_sequencial' => 1,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => 'Teste',
            'departamento_id' => $dep->id,
            'user_id' => $owner->id,
            'status' => 'registrado',
        ]);
    }

    public function test_visto_policies(): void
    {
        $roles = $this->seedRoles();

        $gabA = Gabinete::create(['nome' => 'Gab A']);
        $gabB = Gabinete::create(['nome' => 'Gab B']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gabA->id]);
        $depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gabB->id]);

        $admin = User::factory()->create(['role_id' => $roles['admin']->id]);
        $chefeA = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depA->id]);
        $chefeB = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depB->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $doc = $this->makeDoc($owner, $depA);

        $policy = new DocumentoEntradaPolicy;

        $this->assertTrue($policy->vistoAprovar($chefeA, $doc));
        $this->assertTrue($policy->vistoRejeitar($chefeA, $doc));

        $this->assertFalse($policy->vistoAprovar($chefeB, $doc));
        $this->assertFalse($policy->vistoRejeitar($chefeB, $doc));

        $this->assertFalse($policy->vistoAprovar($admin, $doc));
    }

    public function test_encaminhar_policy(): void
    {
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gab']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);
        $depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gab->id]);

        $admin = User::factory()->create(['role_id' => $roles['admin']->id]);
        $memberA = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $memberB = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depB->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $doc = $this->makeDoc($owner, $depA);

        $policy = new DocumentoEntradaPolicy;

        $this->assertTrue($policy->encaminhar($admin, $doc));
        $this->assertTrue($policy->encaminhar($memberA, $doc));
        $this->assertFalse($policy->encaminhar($memberB, $doc));
    }

    public function test_receber_policy(): void
    {
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gab']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);
        $depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gab->id]);

        $admin = User::factory()->create(['role_id' => $roles['admin']->id]);
        $memberB = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depB->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $doc = $this->makeDoc($owner, $depA);

        $policy = new DocumentoEntradaPolicy;

        $this->assertTrue($policy->receber($admin, $doc, $depB->id));
        $this->assertTrue($policy->receber($memberB, $doc, $depB->id));
        $this->assertFalse($policy->receber($owner, $doc, $depB->id));
    }

    public function test_saida_gabinete_policy(): void
    {
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gab']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);
        $admin = User::factory()->create(['role_id' => $roles['admin']->id]);
        $chefeGab = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depA->id]);
        $gab->responsavel_id = $chefeGab->id;
        $gab->save();

        $member = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $doc = $this->makeDoc($owner, $depA);

        $policy = new DocumentoEntradaPolicy;

        $this->assertTrue($policy->saidaGabinete($admin, $doc));
        $this->assertTrue($policy->saidaGabinete($chefeGab, $doc));
        $this->assertFalse($policy->saidaGabinete($member, $doc));
    }

    /**
     * P6 — a policy só bloqueava a edição de documentos arquivados. Qualquer
     * membro do departamento atual podia alterar assunto, procedência e até
     * departamento_id de um documento já despachado e encaminhado.
     */
    public function test_update_e_bloqueado_depois_do_despacho(): void
    {
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gab']);
        $dep = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);
        $membro = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $dep->id]);
        $admin = User::factory()->create(['role_id' => $roles['admin']->id, 'departamento_id' => $dep->id]);

        $doc = $this->makeDoc($membro, $dep);
        $policy = new DocumentoEntradaPolicy;

        // Antes do despacho o registo ainda é corrigível.
        $this->assertTrue($policy->update($membro, $doc)->allowed());

        $doc->data_despacho = now();
        $doc->texto_despacho = 'Para o departamento tratar';
        $doc->save();

        $this->assertFalse($policy->update($membro, $doc->fresh())->allowed());

        // O admin continua a ser a via de correção. A isenção vem do before()
        // da policy, pelo que tem de ser verificada através do Gate — chamar o
        // método da policy diretamente salta-o.
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($admin)->allows('update', $doc->fresh()));
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($membro)->allows('update', $doc->fresh()));
    }

    public function test_update_e_bloqueado_depois_de_encaminhado(): void
    {
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gab']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);
        $depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gab->id]);
        $membro = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);

        $doc = $this->makeDoc($membro, $depA);
        $policy = new DocumentoEntradaPolicy;

        $this->assertTrue($policy->update($membro, $doc)->allowed());

        \App\Models\DocumentoEncaminhamento::create([
            'documento_entrada_id' => $doc->id,
            'origem_departamento_id' => $depA->id,
            'destino_departamento_id' => $depB->id,
            'usuario_id' => $membro->id,
            'encaminhado_em' => now(),
        ]);

        $this->assertFalse($policy->update($membro, $doc->fresh())->allowed());
    }

    /**
     * Vincular documentos relacionados é trabalho normal durante a tramitação e
     * não pode ser apanhado pelo congelamento da edição.
     */
    public function test_relacionar_continua_permitido_depois_do_despacho(): void
    {
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gab']);
        $dep = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);
        $membro = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $dep->id]);

        $doc = $this->makeDoc($membro, $dep);
        $doc->data_despacho = now();
        $doc->save();

        $policy = new DocumentoEntradaPolicy;

        $this->assertTrue($policy->relacionar($membro, $doc->fresh()));

        // Mas não depois de arquivado.
        $doc->arquivado = true;
        $doc->save();
        $this->assertFalse($policy->relacionar($membro, $doc->fresh()));
    }

    /**
     * F9 — só o autor podia cancelar um encaminhamento. Se estivesse ausente, o
     * documento ficava preso: com pendência em aberto não se encaminha de novo
     * nem se dá saída de gabinete.
     */
    public function test_cancelar_encaminhamento_nao_depende_so_do_autor(): void
    {
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gab']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);
        $depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gab->id]);

        $autor = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $chefeOrigem = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depA->id]);
        $respGab = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $gab->update(['responsavel_id' => $respGab->id]);
        $estranho = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depB->id]);

        $doc = $this->makeDoc($autor, $depA);

        $enc = \App\Models\DocumentoEncaminhamento::create([
            'documento_entrada_id' => $doc->id,
            'origem_departamento_id' => $depA->id,
            'destino_departamento_id' => $depB->id,
            'usuario_id' => $autor->id,
            'encaminhado_em' => now(),
        ]);

        $policy = new DocumentoEntradaPolicy;

        $this->assertTrue($policy->cancelarEncaminhamento($autor, $doc, $enc));
        $this->assertTrue($policy->cancelarEncaminhamento($chefeOrigem, $doc, $enc));
        $this->assertTrue($policy->cancelarEncaminhamento($respGab, $doc, $enc));
        $this->assertFalse($policy->cancelarEncaminhamento($estranho, $doc, $enc));
    }

    public function test_endpoint_de_cancelamento_aceita_a_chefia_da_origem(): void
    {
        $roles = $this->seedRoles();

        $gab = Gabinete::create(['nome' => 'Gab']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gab->id]);
        $depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gab->id]);

        $autor = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $chefeOrigem = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depA->id]);
        $estranho = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depB->id]);

        $doc = $this->makeDoc($autor, $depA);
        $enc = \App\Models\DocumentoEncaminhamento::create([
            'documento_entrada_id' => $doc->id,
            'origem_departamento_id' => $depA->id,
            'destino_departamento_id' => $depB->id,
            'usuario_id' => $autor->id,
            'encaminhado_em' => now(),
        ]);

        $this->actingAs($estranho)
            ->delete(route('documentos-entradas.encaminhamentos.cancelar', [$doc, $enc]))
            ->assertStatus(403);

        $this->actingAs($chefeOrigem)
            ->delete(route('documentos-entradas.encaminhamentos.cancelar', [$doc, $enc]))
            ->assertRedirect();

        $this->assertDatabaseMissing('documento_encaminhamentos', ['id' => $enc->id]);
    }
}
