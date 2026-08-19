<?php

namespace Tests\Feature;

use App\Enums\StatusRequisicao;
use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\Requisicao;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RequisicaoAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): array
    {
        $admin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrador']);
        $user = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
        $chefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['description' => 'Chefe de departamento']);

        return compact('admin', 'user', 'chefe');
    }

    /**
     * Concede uma permissão Spatie ao papel (a linha de roles é partilhada
     * entre o model legado e o Spatie).
     */
    private function grantToRole(Role $role, string $permission): void
    {
        $perm = Permission::findOrCreate($permission, 'web');
        \Spatie\Permission\Models\Role::findByName($role->name, 'web')->givePermissionTo($perm);
    }

    private function requisicao(User $owner): Requisicao
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

    public function test_admin_pode_aprovar(): void
    {
        $roles = $this->seedRoles();
        $gab = Gabinete::create(['nome' => 'Gab A']);
        $dep = Departamento::create(['nome' => 'A', 'gabinete_id' => $gab->id]);
        $admin = User::factory()->create(['role_id' => $roles['admin']->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $dep->id]);
        $req = $this->requisicao($owner);

        $this->actingAs($admin)
            ->patch(route('requisicoes.aprovar', $req))
            ->assertRedirect(route('requisicoes.show', $req->id));

        $req->refresh();
        $this->assertEquals(StatusRequisicao::APROVADO, $req->status);
    }

    public function test_usuario_com_permissao_pode_aprovar(): void
    {
        $roles = $this->seedRoles();
        $gab = Gabinete::create(['nome' => 'Gab A']);
        $dep = Departamento::create(['nome' => 'A', 'gabinete_id' => $gab->id]);
        $this->grantToRole($roles['user'], 'requisicoes.aprovar');
        $user = User::factory()->create(['role_id' => $roles['user']->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $dep->id]);
        $req = $this->requisicao($owner);

        $this->actingAs($user)
            ->patch(route('requisicoes.aprovar', $req))
            ->assertRedirect(route('requisicoes.show', $req->id));
    }

    public function test_chefe_mesmo_departamento_pode_aprovar(): void
    {
        $roles = $this->seedRoles();
        $gab = Gabinete::create(['nome' => 'Gab A']);
        $dep = Departamento::create(['nome' => 'A', 'gabinete_id' => $gab->id]);
        $chefe = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $dep->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $dep->id]);
        $req = $this->requisicao($owner);

        $this->actingAs($chefe)
            ->patch(route('requisicoes.aprovar', $req))
            ->assertRedirect(route('requisicoes.show', $req->id));
    }

    public function test_chefe_outro_departamento_nao_pode_aprovar(): void
    {
        $roles = $this->seedRoles();
        $gabA = Gabinete::create(['nome' => 'Gab A']);
        $gabB = Gabinete::create(['nome' => 'Gab B']);
        $depA = Departamento::create(['nome' => 'A', 'gabinete_id' => $gabA->id]);
        $depB = Departamento::create(['nome' => 'B', 'gabinete_id' => $gabB->id]);
        $chefeB = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depB->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $req = $this->requisicao($owner);

        $this->actingAs($chefeB)
            ->patch(route('requisicoes.aprovar', $req))
            ->assertRedirect(route('requisicoes.show', $req->id));
    }

    public function test_visto_chefe_mesmo_departamento_pode(): void
    {
        $roles = $this->seedRoles();
        $this->grantToRole($roles['chefe'], 'visto_departamento_requisicoes');
        $gab = Gabinete::create(['nome' => 'Gab A']);
        $dep = Departamento::create(['nome' => 'A', 'gabinete_id' => $gab->id]);
        $chefe = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $dep->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $dep->id]);
        $req = $this->requisicao($owner);

        $this->actingAs($chefe)
            ->patch(route('requisicoes.visto.aprovar', $req))
            ->assertRedirect(route('requisicoes.show', $req->id));
    }

    public function test_visto_chefe_outro_departamento_forbidden(): void
    {
        $roles = $this->seedRoles();
        $this->grantToRole($roles['chefe'], 'visto_departamento_requisicoes');
        $gabA = Gabinete::create(['nome' => 'Gab A']);
        $gabB = Gabinete::create(['nome' => 'Gab B']);
        $depA = Departamento::create(['nome' => 'A', 'gabinete_id' => $gabA->id]);
        $depB = Departamento::create(['nome' => 'B', 'gabinete_id' => $gabB->id]);
        $chefeB = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depB->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $req = $this->requisicao($owner);

        $this->actingAs($chefeB)
            ->patch(route('requisicoes.visto.aprovar', $req))
            ->assertStatus(403);
    }
}
