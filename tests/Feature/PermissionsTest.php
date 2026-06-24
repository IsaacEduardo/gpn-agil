<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permissions
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
    }

    /** @test */
    public function user_without_permission_cannot_access_viaturas()
    {
        $user = User::factory()->create();

        // No permission given

        $response = $this->actingAs($user)->get(route('viaturas.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function user_with_permission_can_access_viaturas()
    {
        $user = User::factory()->create();

        // Give permission
        $user->givePermissionTo('viaturas.listar');

        $response = $this->actingAs($user)->get(route('viaturas.index'));

        $response->assertStatus(200);
    }

    /** @test */
    public function user_without_permission_cannot_create_viatura()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('viaturas.create'));
        $response->assertStatus(403);

        $response = $this->actingAs($user)->post(route('viaturas.store'), [
            'identificacao' => 'TEST-01',
            'modelo' => 'Test Model',
        ]);
        $response->assertStatus(403);
    }

    /** @test */
    public function user_with_permission_can_create_viatura()
    {
        $user = User::factory()->create();
        $user->givePermissionTo('viaturas.gerir');

        $response = $this->actingAs($user)->get(route('viaturas.create'));
        $response->assertStatus(200);
    }

    /** @test */
    public function admin_can_access_everything()
    {
        $user = User::factory()->create();
        $role = Role::findByName('admin');
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('viaturas.index'));
        $response->assertStatus(200);
    }
}
