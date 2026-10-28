<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\Procedencia;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Responsividade: o menu passa a um painel lateral abaixo de 992px, a pesquisa
 * global existe também nesse painel, e o combobox de procedências deixa de
 * escrever o catálogo inteiro no HTML.
 */
class LayoutResponsivoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Departamento $dep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);

        $role = Role::where('name', 'user')->firstOrFail();
        $gab = Gabinete::create(['nome' => 'Gabinete A', 'sigla' => 'GABA']);
        $this->dep = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);
        $this->user = User::factory()->create(['role_id' => $role->id, 'departamento_id' => $this->dep->id]);
        $this->user->assignRole($role);
    }

    public function test_menu_tem_painel_lateral_e_botao_para_o_abrir(): void
    {
        $html = $this->actingAs($this->user)->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('id="govNavMenu"', $html);
        $this->assertStringContainsString('offcanvas-lg', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*class="gov-menu-toggle[^"]*"[^>]*data-bs-target="#govNavMenu"/', $html);
    }

    public function test_pesquisa_global_existe_no_cabecalho_e_no_menu_sem_ids_repetidos(): void
    {
        $html = $this->actingAs($this->user)->get(route('home'))->assertOk()->getContent();

        $this->assertSame(2, preg_match_all('/<input[^>]*name="q"/', $html));
        $this->assertSame(2, preg_match_all('/<div[^>]*class="gov-search__results/', $html));
        $this->assertSame(1, substr_count($html, 'id="searchResults"'));
    }

    public function test_layout_ja_nao_carrega_o_datatables(): void
    {
        $this->actingAs($this->user)->get(route('home'))
            ->assertOk()
            ->assertDontSee('cdn.datatables.net', false);
    }

    public function test_combobox_nao_escreve_o_catalogo_de_procedencias(): void
    {
        foreach (range(1, 30) as $i) {
            Procedencia::create(['nome' => sprintf('Entidade Remota %02d', $i), 'ativo' => true]);
        }

        $this->actingAs($this->user);
        $html = Blade::render('<x-procedencia-combobox name="procedencia_id" />');

        $this->assertStringNotContainsString('Entidade Remota', $html);
        $this->assertStringContainsString('data-url-pesquisa="'.route('api.procedencias.index').'"', $html);
    }

    public function test_combobox_mostra_a_procedencia_ja_escolhida_na_edicao(): void
    {
        $escolhida = Procedencia::create(['nome' => 'Tribunal da Comarca', 'ativo' => true]);
        Procedencia::create(['nome' => 'Outra Entidade Qualquer', 'ativo' => true]);

        $doc = DocumentoEntrada::create([
            'numero_sequencial' => 1,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now()->subDay(),
            'classificacao_especie' => 'Ofício',
            'assunto' => 'Pedido de parecer',
            'departamento_id' => $this->dep->id,
            'user_id' => $this->user->id,
            'status' => 'registrado',
            'procedencia_id' => $escolhida->id,
        ]);

        $html = $this->actingAs($this->user)->get(route('documentos-entradas.edit', $doc))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/name="procedencia_id"[^>]*value="'.$escolhida->id.'"/', $html);
        $this->assertMatchesRegularExpression('/procedencia-combobox-input[^>]*value="Tribunal da Comarca"/s', $html);
        $this->assertStringNotContainsString('Outra Entidade Qualquer', $html);
    }
}
