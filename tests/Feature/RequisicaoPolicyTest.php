<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\Permission;
use App\Models\Requisicao;
use App\Models\Role;
use App\Models\User;
use App\Policies\RequisicaoPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisicaoPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): array
    {
        $admin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrador']);
        $user = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
        $userNoPermRole = Role::firstOrCreate(['name' => 'user-noperm'], ['description' => 'Usuário sem permissão']);
        $chefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['description' => 'Chefe de departamento']);

        return compact('admin', 'user', 'userNoPermRole', 'chefe');
    }

    private function seedPermissions(): array
    {
        $aprovar = Permission::firstOrCreate(['name' => 'aprovar_requisicoes'], ['description' => 'Aprovar requisicoes']);
        $visto = Permission::firstOrCreate(['name' => 'visto_departamento_requisicoes'], ['description' => 'Visto departamento requisicoes']);

        return compact('aprovar', 'visto');
    }

    private function makeReq(User $owner): Requisicao
    {
        return Requisicao::create([
            'tipo' => 'produto',
            'codigo_sequencial' => 'PRD-'.date('m/Y').'-001',
            'data_requisicao' => now(),
            'usuario_id' => $owner->id,
            'status' => 'pendente',
            'empresa_destinataria' => 'Empresa X',
        ]);
    }

    public function test_approve_reject_policy(): void
    {
        $roles = $this->seedRoles();
        $perms = $this->seedPermissions();

        $gabA = Gabinete::create(['nome' => 'Gab A']);
        $gabB = Gabinete::create(['nome' => 'Gab B']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gabA->id]);
        $depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gabB->id]);

        $admin = User::factory()->create(['role_id' => $roles['admin']->id]);
        $chefeA = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depA->id]);
        $chefeB = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depB->id]);
        $userWithPerm = User::factory()->create(['role_id' => $roles['user']->id]);
        $userNoPerm = User::factory()->create(['role_id' => $roles['userNoPermRole']->id]);

        // Conceder permissão aprovar para role do usuário
        $roles['user']->permissions()->syncWithoutDetaching([$perms['aprovar']->id]);
        $roles['user']->refresh();

        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $req = $this->makeReq($owner);

        $policy = new RequisicaoPolicy;

        $this->assertTrue($policy->approve($admin, $req));
        $this->assertTrue($policy->reject($admin, $req));

        $this->assertTrue($policy->approve($userWithPerm, $req));
        $this->assertTrue($policy->reject($userWithPerm, $req));

        $this->assertTrue($policy->approve($chefeA, $req));
        $this->assertTrue($policy->reject($chefeA, $req));

        $this->assertTrue($policy->approve($chefeB, $req));
        $this->assertTrue($policy->reject($chefeB, $req));

        $this->assertFalse($policy->approve($userNoPerm, $req));
    }

    public function test_visto_policies(): void
    {
        $roles = $this->seedRoles();
        $perms = $this->seedPermissions();

        $gabA = Gabinete::create(['nome' => 'Gab A']);
        $gabB = Gabinete::create(['nome' => 'Gab B']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gabA->id]);
        $depB = Departamento::create(['nome' => 'Dept B', 'gabinete_id' => $gabB->id]);

        $chefeA = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depA->id]);
        $chefeB = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depB->id]);
        $userWithPerm = User::factory()->create(['role_id' => $roles['user']->id]);
        $userNoPerm = User::factory()->create(['role_id' => $roles['userNoPermRole']->id]);

        // Conceder permissão de visto ao role 'user'
        $roles['user']->permissions()->syncWithoutDetaching([$perms['visto']->id]);
        $roles['user']->refresh();

        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $req = $this->makeReq($owner);

        $policy = new RequisicaoPolicy;

        $this->assertTrue($policy->vistoAprovar($userWithPerm, $req));
        $this->assertTrue($policy->vistoRejeitar($userWithPerm, $req));

        $this->assertTrue($policy->vistoAprovar($chefeA, $req));
        $this->assertTrue($policy->vistoRejeitar($chefeA, $req));

        $this->assertFalse($policy->vistoAprovar($chefeB, $req));
        $this->assertFalse($policy->vistoRejeitar($chefeB, $req));

        $this->assertFalse($policy->vistoAprovar($userNoPerm, $req));
    }
}
