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
}
