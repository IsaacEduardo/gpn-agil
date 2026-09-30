<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\Empresa;
use App\Models\Gabinete;
use App\Models\Requisicao;
use App\Models\RequisicaoPassagem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Editar, gravar, apagar e gerar o PDF de uma requisição exigia só sessão:
 * qualquer autenticado alterava pedidos de outros departamentos pelo ID
 * (auditoria de 2026-09-30, A4). Passa a valer a regra de visibilidade da
 * RequisicaoPolicy::view; editar e apagar ficam só para o requerente
 * (decisão de 2026-09-30).
 */
class RequisicaoEdicaoAutorizacaoTest extends TestCase
{
    use RefreshDatabase;

    private User $dono;

    private User $intruso;

    private User $colega;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionsSeeder::class);

        $role = Role::firstOrCreate(['name' => 'user'], ['description' => 'Usuário']);
        $depA = Departamento::create(['nome' => 'Dep A', 'gabinete_id' => Gabinete::create(['nome' => 'Gab A'])->id]);
        $depB = Departamento::create(['nome' => 'Dep B', 'gabinete_id' => Gabinete::create(['nome' => 'Gab B'])->id]);

        $this->dono = User::factory()->create(['role_id' => $role->id, 'departamento_id' => $depA->id]);
        $this->intruso = User::factory()->create(['role_id' => $role->id, 'departamento_id' => $depB->id]);
        $this->colega = User::factory()->create(['role_id' => $role->id, 'departamento_id' => $depA->id]);
    }

    public static function tipos(): array
    {
        return [
            'produto' => ['produto', 'requisicoes.produtos.'],
            'oficina' => ['oficina', 'requisicoes.oficina.'],
            'servico' => ['servico', 'requisicoes.servico.'],
            'passagem' => ['passagem', 'requisicoes.passagem.'],
        ];
    }

    #[DataProvider('tipos')]
    public function test_intruso_nao_edita_nem_ve_pdf_de_requisicao_alheia(string $tipo, string $rota): void
    {
        $req = $this->requisicao($tipo);

        $this->actingAs($this->intruso)->get(route($rota.'edit', $req->id))->assertForbidden();
        $this->actingAs($this->intruso)->put(route($rota.'update', $req->id), $this->dadosUpdate())->assertForbidden();
        $this->actingAs($this->intruso)->get(route($rota.'pdf', $req->id))->assertForbidden();

        $this->assertDatabaseHas('requisicoes', ['id' => $req->id, 'observacoes' => 'original']);
    }

    #[DataProvider('tipos')]
    public function test_pdf_sem_id_nao_devolve_requisicao_alheia(string $tipo, string $rota): void
    {
        if (! \Illuminate\Support\Facades\Route::has($rota.'pdf.noid')) {
            $this->markTestSkipped("{$tipo} não tem PDF sem ID.");
        }

        $req = $this->requisicao($tipo);

        $resposta = $this->actingAs($this->intruso)->get(route($rota.'pdf.noid'));

        $this->assertStringNotContainsString($req->codigo_sequencial, (string) $resposta->headers->get('content-disposition'));
    }

    #[DataProvider('tipos')]
    public function test_colega_do_departamento_ve_mas_nao_edita(string $tipo, string $rota): void
    {
        $req = $this->requisicao($tipo);

        $this->actingAs($this->colega)->get(route('requisicoes.show', $req->id))->assertOk()
            ->assertDontSee('<i class="fas fa-edit"></i> Editar', false);
        $this->actingAs($this->dono)->get(route('requisicoes.show', $req->id))->assertOk()
            ->assertSee('<i class="fas fa-edit"></i> Editar', false);
        $this->actingAs($this->colega)->get(route($rota.'pdf', $req->id))->assertOk();
        $this->actingAs($this->colega)->get(route($rota.'edit', $req->id))->assertForbidden();
        $this->actingAs($this->colega)->put(route($rota.'update', $req->id), $this->dadosUpdate())->assertForbidden();

        $this->assertDatabaseHas('requisicoes', ['id' => $req->id, 'observacoes' => 'original']);
    }

    public function test_colega_do_departamento_nao_apaga(): void
    {
        $req = $this->requisicao('produto');

        $this->actingAs($this->colega)->delete(route('requisicoes.destroy', $req->id))->assertForbidden();
        $this->assertDatabaseHas('requisicoes', ['id' => $req->id]);
    }

    #[DataProvider('tipos')]
    public function test_dono_continua_a_abrir_a_edicao(string $tipo, string $rota): void
    {
        $req = $this->requisicao($tipo);

        $this->actingAs($this->dono)->get(route($rota.'edit', $req->id))->assertOk();
    }

    public function test_intruso_nao_edita_nem_apaga_pela_rota_generica(): void
    {
        $req = $this->requisicao('produto');

        $this->actingAs($this->intruso)->get(route('requisicoes.edit', $req->id))->assertForbidden();
        $this->actingAs($this->intruso)->put(route('requisicoes.update', $req->id), [
            'empresa_destinataria' => 'Alterada',
        ])->assertForbidden();
        $this->actingAs($this->intruso)->delete(route('requisicoes.destroy', $req->id))->assertForbidden();

        $this->assertDatabaseHas('requisicoes', ['id' => $req->id, 'empresa_destinataria' => 'Fornecedor']);
    }

    private function requisicao(string $tipo): Requisicao
    {
        $req = Requisicao::create([
            'tipo' => $tipo,
            'codigo_sequencial' => 'SEG-'.strtoupper($tipo).'-777',
            'data_requisicao' => now(),
            'usuario_id' => $this->dono->id,
            'status' => 'pendente',
            'empresa_destinataria' => 'Fornecedor',
            'observacoes' => 'original',
        ]);

        if ($tipo === 'passagem') {
            RequisicaoPassagem::create([
                'requisicao_id' => $req->id,
                'beneficiario_nome' => 'Beneficiário',
                'destino' => 'Luanda',
                'data_partida' => now()->toDateString(),
            ]);
        }

        return $req;
    }

    private function dadosUpdate(): array
    {
        return [
            'empresa_id' => Empresa::create(['nome' => 'Outra'])->id,
            'observacoes' => 'adulterada',
            'beneficiario_nome' => 'X',
            'destino' => 'Y',
            'data_partida' => now()->toDateString(),
            'tipo_servico' => 'X',
            'descricao_servico' => 'Y',
            'local_execucao' => 'Z',
            'data_prevista' => now()->toDateString(),
            'prioridade' => 'baixa',
        ];
    }
}
