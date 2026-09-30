<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoVinculo;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vínculos entre documentos: criar, desfazer, listar e pesquisar exigiam só
 * sessão, e as listas devolviam o assunto de documentos que o utilizador não
 * pode ver (auditoria de 2026-09-30, A1/A2).
 */
class VinculoAutorizacaoTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private User $userB;

    private DocumentoEntrada $docA;

    private DocumentoEntrada $docA2;

    private DocumentoEntrada $docB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
        $depA = Departamento::create(['nome' => 'Dep A', 'gabinete_id' => Gabinete::create(['nome' => 'Gab A'])->id]);
        $depB = Departamento::create(['nome' => 'Dep B', 'gabinete_id' => Gabinete::create(['nome' => 'Gab B'])->id]);

        $this->userA = User::factory()->create(['role_id' => $role->id, 'departamento_id' => $depA->id]);
        $this->userB = User::factory()->create(['role_id' => $role->id, 'departamento_id' => $depB->id]);

        $this->docA = $this->entrada(701, 'Segredo do departamento A', $depA, $this->userA);
        $this->docA2 = $this->entrada(702, 'Outro de A', $depA, $this->userA);
        $this->docB = $this->entrada(703, 'Documento de B', $depB, $this->userB);
    }

    public function test_nao_lista_vinculos_de_documento_alheio(): void
    {
        $this->actingAs($this->userB)
            ->getJson(route('api.documentos.vinculos.index', ['EXTERNO', $this->docA->id]))
            ->assertNotFound();
    }

    public function test_nao_pesquisa_a_partir_de_documento_alheio(): void
    {
        $this->actingAs($this->userB)
            ->getJson(route('api.documentos.vinculos.pesquisar', ['EXTERNO', $this->docA->id]).'?q=Segredo')
            ->assertNotFound();
    }

    public function test_pesquisa_nao_devolve_documentos_invisiveis(): void
    {
        $resposta = $this->actingAs($this->userB)
            ->getJson(route('api.documentos.vinculos.pesquisar', ['EXTERNO', $this->docB->id]).'?q=Segredo')
            ->assertOk();

        $this->assertStringNotContainsString('Segredo do departamento A', $resposta->getContent());
    }

    public function test_nao_vincula_a_partir_de_documento_alheio(): void
    {
        $this->actingAs($this->userB)
            ->postJson(route('api.documentos.vinculos.store', ['EXTERNO', $this->docA->id]), [
                'destino_tipo' => 'EXTERNO',
                'destino_id' => $this->docA2->id,
                'tipo_relacao' => 'COMPLEMENTAR',
            ])->assertNotFound();

        $this->assertDatabaseCount('documento_vinculos', 0);
    }

    public function test_nao_vincula_a_documento_de_destino_invisivel(): void
    {
        $this->actingAs($this->userB)
            ->postJson(route('api.documentos.vinculos.store', ['EXTERNO', $this->docB->id]), [
                'destino_tipo' => 'EXTERNO',
                'destino_id' => $this->docA->id,
                'tipo_relacao' => 'COMPLEMENTAR',
            ]);

        $this->assertDatabaseCount('documento_vinculos', 0);
    }

    public function test_dono_continua_a_vincular_os_seus_documentos(): void
    {
        $this->actingAs($this->userA)
            ->postJson(route('api.documentos.vinculos.store', ['EXTERNO', $this->docA->id]), [
                'destino_tipo' => 'EXTERNO',
                'destino_id' => $this->docA2->id,
                'tipo_relacao' => 'COMPLEMENTAR',
            ])->assertOk();

        $this->assertDatabaseCount('documento_vinculos', 1);
    }

    public function test_nao_desfaz_vinculo_entre_documentos_alheios(): void
    {
        $vinculo = $this->vinculo($this->docA, $this->docA2);

        $this->actingAs($this->userB)
            ->deleteJson(route('api.documentos.vinculos.destroy', $vinculo->id))
            ->assertNotFound();

        $this->assertDatabaseHas('documento_vinculos', ['id' => $vinculo->id]);
    }

    public function test_lista_oculta_dados_do_documento_vinculado_invisivel(): void
    {
        // B vê o seu documento, vinculado a um de A que não pode ver.
        $this->vinculo($this->docB, $this->docA);

        $resposta = $this->actingAs($this->userB)
            ->getJson(route('api.documentos.vinculos.index', ['EXTERNO', $this->docB->id]))
            ->assertOk()
            ->assertJsonPath('vinculos.0.documento.pode_visualizar', false)
            ->assertJsonPath('vinculos.0.documento.url_show', null);

        $this->assertStringNotContainsString('Segredo do departamento A', $resposta->getContent());
    }

    private function entrada(int $numero, string $assunto, Departamento $dep, User $autor): DocumentoEntrada
    {
        return DocumentoEntrada::create([
            'numero_sequencial' => $numero,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => $assunto,
            'procedencia' => 'Procedência '.$numero,
            'departamento_id' => $dep->id,
            'user_id' => $autor->id,
            'status' => 'registrado',
            'origem' => 'externo',
        ]);
    }

    private function vinculo(DocumentoEntrada $origem, DocumentoEntrada $destino): DocumentoVinculo
    {
        return DocumentoVinculo::create([
            'origem_tipo' => 'EXTERNO',
            'origem_id' => $origem->id,
            'destino_tipo' => 'EXTERNO',
            'destino_id' => $destino->id,
            'tipo_relacao' => 'COMPLEMENTAR',
            'vinculado_por_id' => $origem->user_id,
        ]);
    }
}
