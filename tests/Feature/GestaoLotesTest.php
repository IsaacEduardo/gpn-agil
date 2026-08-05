<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Requerente;
use App\Models\SolicitacaoAtribuicao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestaoLotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Carregar a seed de permissões e roles
        $this->artisan('migrate');

        $g = \App\Models\Gabinete::create(['nome' => 'Gabinete Teste', 'sigla' => 'GT']);
        \App\Models\Departamento::create(['nome' => 'Dep Teste', 'sigla' => 'DT', 'gabinete_id' => $g->id]);
    }

    /**
     * Cria um utilizador com as permissões indicadas do módulo territorial.
     * O módulo é protegido por policies, pelo que um utilizador sem permissões
     * recebe 403 — ver test_utilizador_sem_permissoes_nao_acede_ao_modulo().
     */
    private function userComPermissoes(array $permissoes): User
    {
        $user = User::factory()->create();

        foreach ($permissoes as $nome) {
            $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate($nome, 'web'));
        }

        return $user->fresh();
    }

    public function test_pode_criar_requerente()
    {
        $user = $this->userComPermissoes(['requerentes.create']);

        $response = $this->actingAs($user)->post(route('requerentes.store'), [
            'tipo_pessoa' => 'FISICA',
            'nome_razao_social' => 'João Manuel Silva',
            'nif_bi' => '123456789LA042',
            'email' => 'joao@example.com',
            'telefone' => '923000111',
            'municipio' => 'Namibe',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('requerentes', [
            'nif_bi' => '123456789LA042',
            'nome_razao_social' => 'João Manuel Silva',
        ]);
    }

    public function test_pode_cadastrar_lote_e_obter_geojson()
    {
        $user = $this->userComPermissoes(['lotes.create', 'lotes.view']);

        $response = $this->actingAs($user)->post(route('lotes.store'), [
            'codigo_lote' => 'LOTE-TEST-001',
            'municipio' => 'Namibe',
            'bairro_distrito' => 'Saco Mar',
            'area_m2' => 750.50,
            'zoneamento' => 'HABITACIONAL',
            'status' => 'DISPONIVEL',
            'latitude_centro' => -15.1961,
            'longitude_centro' => 12.1522,
            'geojson_geometria' => '{"type":"Polygon","coordinates":[[[12.15,-15.19],[12.16,-15.19],[12.16,-15.20],[12.15,-15.20],[12.15,-15.19]]]}',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lotes', [
            'codigo_lote' => 'LOTE-TEST-001',
            'status' => 'DISPONIVEL',
        ]);

        $geoResponse = $this->actingAs($user)->get(route('lotes.geojson'));
        $geoResponse->assertStatus(200);
        $geoResponse->assertJsonFragment(['codigo_lote' => 'LOTE-TEST-001']);
    }

    public function test_workflow_de_solicitacao_e_mudanca_de_status_lote()
    {
        $user = $this->userComPermissoes([
            'solicitacoes_lotes.create',
            'solicitacoes_lotes.analisar',
            'solicitacoes_lotes.homologar',
        ]);

        $requerente = Requerente::create([
            'tipo_pessoa' => 'FISICA',
            'nome_razao_social' => 'Maria Antónia',
            'nif_bi' => '987654321LA011',
        ]);

        $lote = Lote::create([
            'codigo_lote' => 'LOTE-TEST-002',
            'municipio' => 'Namibe',
            'area_m2' => 500,
            'zoneamento' => 'COMERCIAL',
            'status' => 'DISPONIVEL',
        ]);

        // 1. Criar Solicitação
        $resSol = $this->actingAs($user)->post(route('solicitacoes.store'), [
            'requerente_id' => $requerente->id,
            'lote_id' => $lote->id,
            'finalidade_uso' => 'COMERCIAL',
            'modalidade_atribuicao' => 'CDRU',
        ]);

        $resSol->assertRedirect();
        $this->assertDatabaseHas('solicitacoes_atribuicao', [
            'requerente_id' => $requerente->id,
            'lote_id' => $lote->id,
            'status' => 'SUBMETIDO',
        ]);

        // Verificar que o lote foi reservado
        $this->assertEquals('RESERVADO', $lote->fresh()->status);

        $solicitacao = SolicitacaoAtribuicao::first();

        // 2. Transicionar para APROVADO
        $resTrans = $this->actingAs($user)->patch(route('solicitacoes.transicionar', $solicitacao), [
            'novo_status' => 'APROVADO',
        ]);

        $resTrans->assertRedirect();
        $this->assertEquals('APROVADO', $solicitacao->fresh()->status);

        // Verificar que o lote mudou para ATRIBUIDO
        $this->assertEquals('ATRIBUIDO', $lote->fresh()->status);

        // 3. Emitir Termo de Atribuição Oficial
        $resTermo = $this->actingAs($user)->post(route('solicitacoes.emitir-termo', $solicitacao));
        $resTermo->assertRedirect();
        $this->assertNotNull($solicitacao->fresh()->documento_interno_termo_id);
    }

    public function test_pode_editar_lote()
    {
        $user = $this->userComPermissoes(['lotes.view', 'lotes.edit']);

        $lote = Lote::create([
            'codigo_lote' => 'LOTE-TEST-010',
            'municipio' => 'Namibe',
            'area_m2' => 400,
            'zoneamento' => 'HABITACIONAL',
            'status' => 'DISPONIVEL',
        ]);

        $this->actingAs($user)->get(route('lotes.edit', $lote))
            ->assertOk()
            ->assertSee('LOTE-TEST-010');

        $this->actingAs($user)->put(route('lotes.update', $lote), [
            'codigo_lote' => 'LOTE-TEST-010',
            'municipio' => 'Tômbwa',
            'area_m2' => 850.25,
            'zoneamento' => 'COMERCIAL',
            'status' => 'INDISPONIVEL',
        ])->assertRedirect();

        $this->assertDatabaseHas('lotes', [
            'id' => $lote->id,
            'municipio' => 'Tômbwa',
            'zoneamento' => 'COMERCIAL',
            'status' => 'INDISPONIVEL',
        ]);
    }

    public function test_pode_editar_requerente()
    {
        $user = $this->userComPermissoes(['requerentes.view', 'requerentes.edit']);

        $requerente = Requerente::create([
            'tipo_pessoa' => 'FISICA',
            'nome_razao_social' => 'Ana Paula',
            'nif_bi' => '111222333LA044',
        ]);

        $this->actingAs($user)->get(route('requerentes.edit', $requerente))
            ->assertOk()
            ->assertSee('Ana Paula');

        $this->actingAs($user)->put(route('requerentes.update', $requerente), [
            'tipo_pessoa' => 'FISICA',
            'nome_razao_social' => 'Ana Paula Ferreira',
            'nif_bi' => '111222333LA044',
            'municipio' => 'Namibe',
        ])->assertRedirect();

        $this->assertDatabaseHas('requerentes', [
            'id' => $requerente->id,
            'nome_razao_social' => 'Ana Paula Ferreira',
        ]);
    }

    public function test_quem_so_consulta_nao_edita()
    {
        $user = $this->userComPermissoes(['lotes.view']);

        $lote = Lote::create([
            'codigo_lote' => 'LOTE-TEST-011',
            'municipio' => 'Namibe',
            'area_m2' => 200,
            'zoneamento' => 'HABITACIONAL',
            'status' => 'DISPONIVEL',
        ]);

        $this->actingAs($user)->get(route('lotes.show', $lote))->assertOk();
        $this->actingAs($user)->get(route('lotes.edit', $lote))->assertForbidden();
        $this->actingAs($user)->delete(route('lotes.destroy', $lote))->assertForbidden();

        $this->assertDatabaseHas('lotes', ['id' => $lote->id]);
    }

    public function test_utilizador_sem_permissoes_nao_acede_ao_modulo()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('lotes.index'))->assertForbidden();
        $this->actingAs($user)->get(route('lotes.geojson'))->assertForbidden();
        $this->actingAs($user)->get(route('requerentes.index'))->assertForbidden();
        $this->actingAs($user)->get(route('solicitacoes.index'))->assertForbidden();
    }

    /**
     * O menu principal renderizado é layouts.partials.gov-nav (via gov-header),
     * e não o sidebar-nav. Este teste fixa a presença do módulo nesse menu.
     */
    public function test_menu_principal_mostra_territorio_a_quem_tem_permissao()
    {
        $comAcesso = $this->userComPermissoes(['lotes.view']);

        $this->actingAs($comAcesso)->get(route('home'))
            ->assertOk()
            ->assertSee('Território')
            ->assertSee(route('lotes.index'));

        $semAcesso = User::factory()->create();

        $this->actingAs($semAcesso)->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('lotes.index'));
    }

    public function test_instrutor_sem_homologacao_nao_aprova_nem_emite_termo()
    {
        $user = $this->userComPermissoes([
            'solicitacoes_lotes.view',
            'solicitacoes_lotes.analisar',
        ]);

        $requerente = Requerente::create([
            'tipo_pessoa' => 'FISICA',
            'nome_razao_social' => 'Carlos Bento',
            'nif_bi' => '555444333LA099',
        ]);

        $lote = Lote::create([
            'codigo_lote' => 'LOTE-TEST-003',
            'municipio' => 'Namibe',
            'area_m2' => 300,
            'zoneamento' => 'HABITACIONAL',
            'status' => 'DISPONIVEL',
        ]);

        $solicitacao = SolicitacaoAtribuicao::create([
            'requerente_id' => $requerente->id,
            'lote_id' => $lote->id,
            'finalidade_uso' => 'RESIDENCIAL',
            'modalidade_atribuicao' => 'CDRU',
            'status' => 'SUBMETIDO',
            'created_by_user_id' => $user->id,
        ]);

        // Transições de instrução são permitidas...
        $this->actingAs($user)
            ->patch(route('solicitacoes.transicionar', $solicitacao), ['novo_status' => 'EM_VISTORIA'])
            ->assertRedirect();
        $this->assertEquals('EM_VISTORIA', $solicitacao->fresh()->status);

        // ...mas a decisão de mérito exige 'solicitacoes_lotes.homologar'.
        $this->actingAs($user)
            ->patch(route('solicitacoes.transicionar', $solicitacao), ['novo_status' => 'APROVADO'])
            ->assertForbidden();
        $this->assertEquals('EM_VISTORIA', $solicitacao->fresh()->status);

        $this->actingAs($user)
            ->post(route('solicitacoes.emitir-termo', $solicitacao))
            ->assertForbidden();
    }
}
