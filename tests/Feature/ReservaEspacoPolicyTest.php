<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\Permission;
use App\Models\ReservaEspaco;
use App\Models\Role;
use App\Models\User;
use App\Policies\ReservaEspacoPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservaEspacoPolicyTest extends TestCase
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
        $aprovar = Permission::firstOrCreate(['name' => 'aprovar_reservas'], ['description' => 'Aprovar reservas']);
        $visto = Permission::firstOrCreate(['name' => 'visto_departamento_reservas'], ['description' => 'Visto departamento reservas']);

        return compact('aprovar', 'visto');
    }

    private function makeReserva(User $owner): ReservaEspaco
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

    public function test_approve_reject_policy(): void
    {
        $roles = $this->seedRoles();
        $perms = $this->seedPermissions();

        $gabA = Gabinete::create(['nome' => 'Gab A']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $gabA->id]);
        $admin = User::factory()->create(['role_id' => $roles['admin']->id]);
        $chefe = User::factory()->create(['role_id' => $roles['chefe']->id, 'departamento_id' => $depA->id]);
        $userWithPerm = User::factory()->create(['role_id' => $roles['user']->id]);
        $userNoPerm = User::factory()->create(['role_id' => $roles['userNoPermRole']->id]);

        // Permissão aprovar para role 'user'
        $roles['user']->permissions()->syncWithoutDetaching([$perms['aprovar']->id]);
        $roles['user']->refresh();

        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $reserva = $this->makeReserva($owner);

        $policy = new ReservaEspacoPolicy;

        $this->assertTrue($policy->approve($admin, $reserva));
        $this->assertTrue($policy->reject($admin, $reserva));

        $this->assertTrue($policy->approve($userWithPerm, $reserva));
        $this->assertTrue($policy->reject($userWithPerm, $reserva));

        $this->assertFalse($policy->approve($chefe, $reserva));
        $this->assertFalse($policy->reject($chefe, $reserva));

        $this->assertFalse($policy->approve($userNoPerm, $reserva));
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

        // Permissão visto para role 'user'
        $roles['user']->permissions()->syncWithoutDetaching([$perms['visto']->id]);
        $roles['user']->refresh();

        $owner = User::factory()->create(['role_id' => $roles['user']->id, 'departamento_id' => $depA->id]);
        $reserva = $this->makeReserva($owner);

        $policy = new ReservaEspacoPolicy;

        $this->assertTrue($policy->vistoAprovar($userWithPerm, $reserva));
        $this->assertTrue($policy->vistoRejeitar($userWithPerm, $reserva));

        $this->assertTrue($policy->vistoAprovar($chefeA, $reserva));
        $this->assertTrue($policy->vistoRejeitar($chefeA, $reserva));

        $this->assertFalse($policy->vistoAprovar($chefeB, $reserva));
        $this->assertFalse($policy->vistoRejeitar($chefeB, $reserva));

        $this->assertFalse($policy->vistoAprovar($userNoPerm, $reserva));
    }
}
