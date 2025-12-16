<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\Requisicao;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisicaoVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeRole(string $name): Role
    {
        return Role::firstOrCreate(['name' => $name], ['description' => $name]);
    }

    public function test_usuario_sem_departamento_ve_apenas_as_suas_requisicoes(): void
    {
        $roleUser = $this->makeRole('user');

        $depGab = Gabinete::create(['nome' => 'Gab A']);
        $depA = Departamento::create(['nome' => 'Dept A', 'gabinete_id' => $depGab->id]);

        $actor = User::factory()->create(['role_id' => $roleUser->id]);
        $ownerA = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $depA->id]);

        $mine = Requisicao::create([
            'tipo' => 'produto',
            'codigo_sequencial' => 'TEST-MEU-001',
            'data_requisicao' => now(),
            'usuario_id' => $actor->id,
            'status' => 'pendente',
            'empresa_destinataria' => 'Empresa X',
        ]);

        $other = Requisicao::create([
            'tipo' => 'produto',
            'codigo_sequencial' => 'TEST-OUTRO-001',
            'data_requisicao' => now(),
            'usuario_id' => $ownerA->id,
            'status' => 'pendente',
            'empresa_destinataria' => 'Empresa Y',
        ]);

        $this->actingAs($actor)
            ->get(route('requisicoes.index'))
            ->assertStatus(200)
            ->assertSee('TEST-MEU-001')
            ->assertDontSee('TEST-OUTRO-001');
    }

    public function test_chefe_gabinete_ve_requisicoes_de_todos_departamentos_do_seu_gabinete(): void
    {
        $roleChefeGab = $this->makeRole('chefe-gabinete');
        $roleUser = $this->makeRole('user');

        $gab = Gabinete::create(['nome' => 'Gab X']);
        $dep1 = Departamento::create(['nome' => 'Dept X1', 'gabinete_id' => $gab->id]);
        $dep2 = Departamento::create(['nome' => 'Dept X2', 'gabinete_id' => $gab->id]);

        $chefe = User::factory()->create(['role_id' => $roleChefeGab->id, 'departamento_id' => $dep1->id]);
        $gab->responsavel_id = $chefe->id;
        $gab->save();

        $owner1 = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $dep1->id]);
        $owner2 = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $dep2->id]);

        Requisicao::create([
            'tipo' => 'servico',
            'codigo_sequencial' => 'TEST-GAB-001',
            'data_requisicao' => now(),
            'usuario_id' => $owner1->id,
            'status' => 'pendente',
            'empresa_destinataria' => 'Empresa Z',
        ]);
        Requisicao::create([
            'tipo' => 'servico',
            'codigo_sequencial' => 'TEST-GAB-002',
            'data_requisicao' => now(),
            'usuario_id' => $owner2->id,
            'status' => 'pendente',
            'empresa_destinataria' => 'Empresa W',
        ]);

        $this->actingAs($chefe)
            ->get(route('requisicoes.servico.index'))
            ->assertStatus(200)
            ->assertSee('TEST-GAB-001')
            ->assertSee('TEST-GAB-002');
    }
}
