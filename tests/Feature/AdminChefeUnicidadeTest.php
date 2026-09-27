<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminChefeUnicidadeTest extends TestCase
{
    use RefreshDatabase;

    protected function seedRoles()
    {
        $admin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrador']);
        $user = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário padrão']);
        $chefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['description' => 'Chefe de departamento']);

        return compact('admin', 'user', 'chefe');
    }

    public function test_require_departamento_when_role_is_chefe_on_create(): void
    {
        $roles = $this->seedRoles();
        $adminUser = User::factory()->create([
            'role_id' => $roles['admin']->id,
        ]);

        $this->actingAs($adminUser);

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Chefe Sem Dep',
            'email' => 'chefe.semdep@example.com',
            'password' => 'password123',
            'role_id' => $roles['chefe']->id,
            // 'departamento_id' => missing on purpose
        ]);

        $response->assertSessionHasErrors(['departamento_id']);
    }

    /**
     * Antes, promover um segundo chefe despromovia o anterior em silêncio (só role_id — o papel
     * Spatie ficava, e ele podia continuar a assinar). Agora a designação é recusada até a
     * anterior ser retirada (decisão: um departamento tem no máximo um Chefe de Departamento).
     */
    public function test_promover_segundo_chefe_e_recusado_ate_retirar_o_anterior(): void
    {
        $roles = $this->seedRoles();
        $adminUser = User::factory()->create([
            'role_id' => $roles['admin']->id,
        ]);
        $this->actingAs($adminUser);

        $gab = Gabinete::create(['nome' => 'Gab Logística']);
        $dep = Departamento::create(['nome' => 'Logística', 'sigla' => 'DL', 'gabinete_id' => $gab->id]);

        // Chefe atual
        $chefeAtual = User::factory()->create([
            'name' => 'Chefe Atual',
            'email' => 'chefe.atual@example.com',
            'role_id' => $roles['chefe']->id,
            'departamento_id' => $dep->id,
        ]);

        // Usuário a ser promovido
        $usuario = User::factory()->create([
            'name' => 'Novo Chefe',
            'email' => 'novo.chefe@example.com',
            'role_id' => $roles['user']->id,
            'departamento_id' => $dep->id,
        ]);

        $promover = fn () => $this->put(route('admin.users.update', $usuario), [
            'name' => 'Novo Chefe',
            'email' => 'novo.chefe@example.com',
            'role_id' => $roles['chefe']->id,
            'departamento_id' => $dep->id,
            'departamentos' => [],
        ]);

        $promover()->assertSessionHasErrors([
            'role_id' => 'O departamento DL já tem Chefe de Departamento (Chefe Atual). Retire primeiro essa designação.',
        ]);
        $this->assertEquals($roles['chefe']->id, $chefeAtual->fresh()->role_id, 'Chefe atual mantém-se');
        $this->assertEquals($roles['user']->id, $usuario->fresh()->role_id, 'Promoção não aplicada');

        // Retirada a designação anterior, a promoção passa e o espelho fica certo.
        $this->put(route('admin.users.update', $chefeAtual), [
            'name' => 'Chefe Atual',
            'email' => 'chefe.atual@example.com',
            'role_id' => $roles['user']->id,
            'departamento_id' => $dep->id,
            'departamentos' => [],
        ])->assertSessionHasNoErrors();

        $promover()->assertRedirect(route('admin.users.index'));
        $this->assertEquals($roles['chefe']->id, $usuario->fresh()->role_id);
        $this->assertEquals($usuario->id, $dep->fresh()->responsavel_id);
    }
}
