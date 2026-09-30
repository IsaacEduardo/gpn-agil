<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Empresas: qualquer autenticado criava, editava e apagava (auditoria de
 * 2026-09-30, A3). Consultar continua aberto — os formulários de requisição
 * dependem da lista e do json.
 */
class EmpresaAutorizacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_utilizador_comum_nao_gere_empresas(): void
    {
        $user = User::factory()->create();
        $empresa = Empresa::create(['nome' => 'Fornecedor X']);

        $this->actingAs($user)->get(route('empresas.create'))->assertForbidden();
        $this->actingAs($user)->post(route('empresas.store'), ['nome' => 'Nova'])->assertForbidden();
        $this->actingAs($user)->get(route('empresas.edit', $empresa))->assertForbidden();
        $this->actingAs($user)->put(route('empresas.update', $empresa), ['nome' => 'Alterada'])->assertForbidden();
        $this->actingAs($user)->delete(route('empresas.destroy', $empresa))->assertForbidden();

        $this->assertDatabaseHas('empresas', ['id' => $empresa->id, 'nome' => 'Fornecedor X']);
        $this->assertDatabaseMissing('empresas', ['nome' => 'Nova']);
    }

    public function test_utilizador_comum_continua_a_consultar(): void
    {
        $user = User::factory()->create();
        $empresa = Empresa::create(['nome' => 'Fornecedor X']);

        $this->actingAs($user)->get(route('empresas.index'))->assertOk();
        $this->actingAs($user)->get(route('empresas.show', $empresa))->assertOk();
        $this->actingAs($user)->get(route('empresas.json', $empresa))->assertOk();
    }

    public function test_quem_tem_a_permissao_gere_empresas(): void
    {
        $gestor = User::factory()->create();
        $gestor->givePermissionTo(Permission::findOrCreate('empresas.gerir', 'web'));
        $empresa = Empresa::create(['nome' => 'Fornecedor X']);

        $this->actingAs($gestor)->post(route('empresas.store'), ['nome' => 'Nova'])->assertRedirect();
        $this->actingAs($gestor)->put(route('empresas.update', $empresa), ['nome' => 'Alterada'])->assertRedirect();

        $this->assertDatabaseHas('empresas', ['nome' => 'Nova']);
        $this->assertDatabaseHas('empresas', ['id' => $empresa->id, 'nome' => 'Alterada']);
    }
}
