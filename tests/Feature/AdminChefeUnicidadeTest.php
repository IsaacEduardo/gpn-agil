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

    public function test_promoting_new_chefe_demotes_previous(): void
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

        $response = $this->put(route('admin.users.update', $usuario), [
            'name' => 'Novo Chefe',
            'email' => 'novo.chefe@example.com',
            'role_id' => $roles['chefe']->id,
            'departamento_id' => $dep->id,
            'departamentos' => [],
        ]);
        $response->assertRedirect(route('admin.users.index'));

        $chefeAtual->refresh();
        $usuario->refresh();

        $this->assertEquals($roles['user']->id, $chefeAtual->role_id, 'Chefe anterior deve ser rebaixado a user');
        $this->assertEquals($roles['chefe']->id, $usuario->role_id, 'Usuário promovido deve ser chefe');
    }
}
