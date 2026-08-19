<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\ReservaEspaco;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReservaAuthorizationTest extends TestCase
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

    private function reserva(User $owner): ReservaEspaco
    {
        return ReservaEspaco::create([
            'codigo_reserva' => 'RSV-'.time(),
            'tipo_espaco' => ReservaEspaco::TIPO_SALAO_NOBRE,
            'solicitante_nome' => 'Teste',
            'solicitante_email' => 'teste@example.com',
            'evento_titulo' => 'Evento',
            'data_evento' => now()->addDay()->toDateString(),
            'hora_inicio' => '10:00',
            'hora_fim' => '11:00',
            'status' => ReservaEspaco::STATUS_PENDENTE,
            'usuario_id' => $owner->id,
        ]);
    }

    public function test_admin_pode_aprovar(): void
    {
        $roles = $this->seedRoles();
        $gab = Gabinete::create(['nome' => 'Gab A']);
        $dep = Departamento::create(['nome' => 'A', 'gabinete_id' => $gab->id]);
        $admin = User::factory()->create(['role_id' => $roles['admin']->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $dep->id]);
        $reserva = $this->reserva($owner);

        $this->actingAs($admin)
            ->patch(route('reservas.aprovar', $reserva))
            ->assertRedirect(route('reservas.show', $reserva->id));

        $reserva->refresh();
        $this->assertEquals(ReservaEspaco::STATUS_APROVADA, $reserva->status);
    }

    public function test_usuario_com_permissao_pode_aprovar(): void
    {
        $roles = $this->seedRoles();
        $gab = Gabinete::create(['nome' => 'Gab A']);
        $dep = Departamento::create(['nome' => 'A', 'gabinete_id' => $gab->id]);
        $this->grantToRole($roles['user'], 'reservas.aprovar');
        $user = User::factory()->create(['role_id' => $roles['user']->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $dep->id]);
        $reserva = $this->reserva($owner);

        $this->actingAs($user)
            ->patch(route('reservas.aprovar', $reserva))
            ->assertRedirect(route('reservas.show', $reserva->id));
    }

    public function test_chefe_nao_pode_aprovar(): void
    {
        $roles = $this->seedRoles();
        $gab = Gabinete::create(['nome' => 'Gab A']);
        $dep = Departamento::create(['nome' => 'A', 'gabinete_id' => $gab->id]);
        $chefe = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $dep->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $dep->id]);
        $reserva = $this->reserva($owner);

        $this->actingAs($chefe)
            ->patch(route('reservas.aprovar', $reserva))
            ->assertStatus(403);
    }

    public function test_visto_usuario_com_permissao_pode(): void
    {
        $roles = $this->seedRoles();
        $this->grantToRole($roles['user'], 'visto_departamento_reservas');
        $gab = Gabinete::create(['nome' => 'Gab A']);
        $dep = Departamento::create(['nome' => 'A', 'gabinete_id' => $gab->id]);
        $user = User::factory()->create(['role_id' => $roles['user']->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $dep->id]);
        $reserva = $this->reserva($owner);

        $this->actingAs($user)
            ->patch(route('reservas.visto.aprovar', $reserva))
            ->assertRedirect(route('reservas.show', $reserva->id));
    }

    public function test_visto_chefe_mesmo_dep_com_permissao_pode(): void
    {
        $roles = $this->seedRoles();
        $this->grantToRole($roles['chefe'], 'visto_departamento_reservas');
        $gab = Gabinete::create(['nome' => 'Gab A']);
        $dep = Departamento::create(['nome' => 'A', 'gabinete_id' => $gab->id]);
        $chefe = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $dep->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $dep->id]);
        $reserva = $this->reserva($owner);

        $this->actingAs($chefe)
            ->patch(route('reservas.visto.aprovar', $reserva))
            ->assertRedirect(route('reservas.show', $reserva->id));
    }

    public function test_visto_chefe_outro_dep_forbidden(): void
    {
        $roles = $this->seedRoles();
        $this->grantToRole($roles['chefe'], 'visto_departamento_reservas');
        $gabA = Gabinete::create(['nome' => 'Gab A']);
        $gabB = Gabinete::create(['nome' => 'Gab B']);
        $depA = Departamento::create(['nome' => 'A', 'gabinete_id' => $gabA->id]);
        $depB = Departamento::create(['nome' => 'B', 'gabinete_id' => $gabB->id]);
        $chefeB = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depB->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $reserva = $this->reserva($owner);

        $this->actingAs($chefeB)
            ->patch(route('reservas.visto.aprovar', $reserva))
            ->assertStatus(403);
    }

    public function test_rejeitar_requer_motivo_e_permissao(): void
    {
        $roles = $this->seedRoles();
        $gab = Gabinete::create(['nome' => 'Gab A']);
        $dep = Departamento::create(['nome' => 'A', 'gabinete_id' => $gab->id]);
        $this->grantToRole($roles['user'], 'reservas.aprovar');
        $user = User::factory()->create(['role_id' => $roles['user']->id]);
        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $dep->id]);
        $reserva = $this->reserva($owner);

        $this->actingAs($user)
            ->patch(route('reservas.rejeitar', $reserva), ['motivo_rejeicao' => 'Teste'])
            ->assertRedirect(route('reservas.show', $reserva->id));
    }
}
