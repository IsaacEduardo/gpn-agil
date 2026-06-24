<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserDelegationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic permissions if needed
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
    }

    /** @test */
    public function it_can_manage_department_hierarchy_and_responsible_chief()
    {
        // Criar Gabinete obrigatório
        $gabinete = Gabinete::create([
            'nome' => 'Gabinete de Apoio',
            'sigla' => 'GAP',
        ]);

        // 1. Criar departamentos em hierarquia
        $direcao = Departamento::create([
            'nome' => 'Direção Geral',
            'sigla' => 'DG',
            'gabinete_id' => $gabinete->id,
        ]);

        $departamento = Departamento::create([
            'nome' => 'Departamento de Recursos Humanos',
            'sigla' => 'DRH',
            'parent_id' => $direcao->id,
            'gabinete_id' => $gabinete->id,
        ]);

        // Verificar relações de parentesco
        $this->assertEquals($direcao->id, $departamento->parent->id);
        $this->assertTrue($direcao->children->contains('id', $departamento->id));

        // 2. Associar responsável direto
        $chefe = User::factory()->create([
            'name' => 'Chefe Manuel',
        ]);

        $departamento->update([
            'responsavel_id' => $chefe->id,
        ]);

        // Verificar relações de chefia
        $this->assertEquals($chefe->id, $departamento->responsavel->id);
        $this->assertEquals($chefe->name, $departamento->chefe->name); // Accessor fallback/override
    }

    /** @test */
    public function delegate_inherits_chief_permissions_only_during_active_period()
    {
        // 1. Criar utilizadores
        $chefe = User::factory()->create([
            'name' => 'Chefe da Secção',
        ]);

        $delegado = User::factory()->create([
            'name' => 'Funcionário Delegado',
        ]);

        // Criar permissão de exemplo e associar ao chefe
        $permissao = Permission::firstOrCreate(['name' => 'requisicoes.aprovar', 'guard_name' => 'web']);
        $chefe->givePermissionTo($permissao);

        // Garantir que o delegado não tem acesso antes da delegação
        $this->assertFalse($delegado->hasPermissionTo('requisicoes.aprovar'));

        // 2. Ativar delegação no chefe para o delegado (Período Ativo)
        $chefe->update([
            'delegado_id' => $delegado->id,
            'delegacao_inicio' => now()->subDay(),
            'delegacao_fim' => now()->addDay(),
        ]);

        // Atualizar as instâncias para recarregar relacionamentos do banco
        $chefe->refresh();
        $delegado->refresh();

        // O delegado agora deve herdar a permissão do chefe
        $this->assertTrue($delegado->hasPermissionTo('requisicoes.aprovar'));

        // 3. Modificar delegação para período futuro (Inativo no momento)
        $chefe->update([
            'delegacao_inicio' => now()->addDays(5),
            'delegacao_fim' => now()->addDays(10),
        ]);

        $chefe->refresh();
        $delegado->refresh();

        // O delegado não deve mais herdar a permissão
        $this->assertFalse($delegado->hasPermissionTo('requisicoes.aprovar'));
    }
}
