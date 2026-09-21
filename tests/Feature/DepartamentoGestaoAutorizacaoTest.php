<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * As rotas /departamentos estavam sem qualquer verificação de autorização.
 *
 * Qualquer sessão autenticada — incluindo um técnico — listava a estrutura
 * orgânica de toda a instituição, abria o formulário de criação, editava
 * departamentos de outros gabinetes e gravava. A permissão 'departamentos.gerir'
 * já existia na matriz de acesso mas nunca era consultada. Foi possível criar um
 * departamento no Gabinete do Governador com uma conta de técnico.
 *
 * Gerir a estrutura orgânica é ato de administração: o admin (ou quem tenha a
 * permissão) sem restrição, o chefe de gabinete apenas dentro do seu.
 */
class DepartamentoGestaoAutorizacaoTest extends TestCase
{
    use RefreshDatabase;

    private Gabinete $gabineteA;

    private Gabinete $gabineteB;

    private Departamento $depA;

    private Departamento $depB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gabineteA = Gabinete::create(['nome' => 'Gabinete A', 'sigla' => 'GA']);
        $this->gabineteB = Gabinete::create(['nome' => 'Gabinete B', 'sigla' => 'GB']);

        $this->depA = Departamento::create(['nome' => 'Dep A', 'gabinete_id' => $this->gabineteA->id]);
        $this->depB = Departamento::create(['nome' => 'Dep B', 'gabinete_id' => $this->gabineteB->id]);
    }

    private function utilizadorCom(string $papel, ?Departamento $dep = null): User
    {
        $role = Role::firstOrCreate(['name' => $papel], ['description' => $papel]);
        SpatieRole::firstOrCreate(['name' => $papel, 'guard_name' => 'web']);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'departamento_id' => $dep?->id,
        ]);
        $user->syncRoles([$papel]);

        return $user->fresh();
    }

    public static function papeisSemGestao(): array
    {
        return [
            'tecnico' => ['tecnico'],
            'utilizador comum' => ['user'],
            'chefe de departamento' => ['chefe-departamento'],
        ];
    }

    #[DataProvider('papeisSemGestao')]
    public function test_papel_sem_gestao_nao_acede_a_estrutura_organica(string $papel): void
    {
        $user = $this->utilizadorCom($papel, $this->depA);

        $this->actingAs($user)->get(route('departamentos.index'))->assertForbidden();
        $this->actingAs($user)->get(route('departamentos.create'))->assertForbidden();
        $this->actingAs($user)->get(route('departamentos.edit', $this->depB))->assertForbidden();
    }

    /**
     * A prova da falha: um técnico criou um departamento no gabinete do
     * Governador e recebeu "Departamento criado com sucesso".
     */
    #[DataProvider('papeisSemGestao')]
    public function test_papel_sem_gestao_nao_cria_departamento(string $papel): void
    {
        $user = $this->utilizadorCom($papel, $this->depA);

        $this->actingAs($user)
            ->post(route('departamentos.store'), [
                'nome' => 'ZZ TESTE PERMISSAO',
                'sigla' => 'ZZT',
                'gabinete_id' => $this->gabineteB->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('departamentos', ['nome' => 'ZZ TESTE PERMISSAO']);
    }

    #[DataProvider('papeisSemGestao')]
    public function test_papel_sem_gestao_nao_altera_nem_apaga_departamento(string $papel): void
    {
        $user = $this->utilizadorCom($papel, $this->depA);

        $this->actingAs($user)
            ->put(route('departamentos.update', $this->depB), [
                'nome' => 'Renomeado Indevidamente',
                'gabinete_id' => $this->gabineteB->id,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('departamentos.destroy', $this->depB))
            ->assertForbidden();

        $this->assertDatabaseHas('departamentos', ['id' => $this->depB->id, 'nome' => 'Dep B']);
    }

    public function test_admin_gere_qualquer_gabinete(): void
    {
        $admin = $this->utilizadorCom('admin');

        $this->actingAs($admin)->get(route('departamentos.index'))->assertOk();
        $this->actingAs($admin)->get(route('departamentos.edit', $this->depB))->assertOk();

        $this->actingAs($admin)
            ->post(route('departamentos.store'), [
                'nome' => 'Dep Novo Admin',
                'gabinete_id' => $this->gabineteB->id,
            ])
            ->assertRedirect(route('departamentos.index'));

        $this->assertDatabaseHas('departamentos', [
            'nome' => 'Dep Novo Admin',
            'gabinete_id' => $this->gabineteB->id,
        ]);
    }

    public function test_chefe_de_gabinete_gere_apenas_o_seu_gabinete(): void
    {
        $chefe = $this->utilizadorCom('chefe-gabinete', $this->depA);
        $this->gabineteA->update(['responsavel_id' => $chefe->id]);
        $chefe = $chefe->fresh();

        // Dentro do seu gabinete: pode.
        $this->actingAs($chefe)->get(route('departamentos.edit', $this->depA))->assertOk();

        // Fora do seu gabinete: não pode ver nem gravar.
        $this->actingAs($chefe)->get(route('departamentos.edit', $this->depB))->assertForbidden();

        $this->actingAs($chefe)
            ->post(route('departamentos.store'), [
                'nome' => 'Dep Intruso',
                'gabinete_id' => $this->gabineteB->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('departamentos', ['nome' => 'Dep Intruso']);
    }

    public function test_listagem_do_chefe_de_gabinete_so_mostra_o_seu_gabinete(): void
    {
        $chefe = $this->utilizadorCom('chefe-gabinete', $this->depA);
        $this->gabineteA->update(['responsavel_id' => $chefe->id]);

        $this->actingAs($chefe->fresh())
            ->get(route('departamentos.index'))
            ->assertOk()
            ->assertSee('Dep A')
            ->assertDontSee('Dep B');
    }

    /**
     * O menu e o backend têm de decidir pela mesma regra: era o menu que já se
     * guardava por 'departamentos.gerir' enquanto o controller não verificava
     * nada. A regra vive agora num só sítio.
     *
     * @see \App\Models\User::podeGerirDepartamentos()
     */
    #[DataProvider('papeisSemGestao')]
    public function test_regra_partilhada_nega_a_quem_nao_gere(string $papel): void
    {
        $user = $this->utilizadorCom($papel, $this->depA);

        $this->assertFalse($user->podeGerirDepartamentos());
        $this->assertFalse($user->podeGerirDepartamentosSemRestricao());
    }

    public function test_regra_partilhada_reconhece_admin_e_chefe_de_gabinete(): void
    {
        $admin = $this->utilizadorCom('admin');

        $this->assertTrue($admin->podeGerirDepartamentos());
        $this->assertTrue($admin->podeGerirDepartamentosSemRestricao());

        $chefe = $this->utilizadorCom('chefe-gabinete', $this->depA);
        $this->gabineteA->update(['responsavel_id' => $chefe->id]);
        $chefe = $chefe->fresh();

        // Gere, mas apenas dentro do seu gabinete.
        $this->assertTrue($chefe->podeGerirDepartamentos());
        $this->assertFalse($chefe->podeGerirDepartamentosSemRestricao());
    }

    /**
     * O chefe de gabinete não pode empurrar um departamento seu para outro
     * gabinete — isso retirá-lo-ia do seu alcance e entregá-lo-ia a terceiros.
     */
    public function test_chefe_de_gabinete_nao_move_departamento_para_outro_gabinete(): void
    {
        $chefe = $this->utilizadorCom('chefe-gabinete', $this->depA);
        $this->gabineteA->update(['responsavel_id' => $chefe->id]);

        $this->actingAs($chefe->fresh())
            ->put(route('departamentos.update', $this->depA), [
                'nome' => 'Dep A',
                'gabinete_id' => $this->gabineteB->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('departamentos', [
            'id' => $this->depA->id,
            'gabinete_id' => $this->gabineteA->id,
        ]);
    }
}
