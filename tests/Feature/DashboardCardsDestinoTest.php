<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cada cartão do painel leva a uma lista com o mesmo número de documentos que
 * o cartão mostra (2026-10-02).
 *
 * Os links usavam ?status=, que a listagem de entradas ignorava com sessão
 * iniciada: "A Carecer de Despacho", "Tratados" e "Total Geral" abriam a mesma
 * lista. Nos internos o estado somava-se ao separador por omissão e dava listas
 * vazias ("Documentos Submetidos" do técnico, "Ver todos" dos atos emitidos).
 */
class DashboardCardsDestinoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $chefeGabinete;

    private User $chefeDepartamento;

    private User $tecnico;

    private Departamento $depA;

    private Departamento $depB;

    private int $numero = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $roleUser = Role::firstOrCreate(['name' => 'user'], ['description' => 'Utilizador']);
        $roleChefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['description' => 'Chefe']);
        $roleAdmin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'chefe-departamento', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $gabA = Gabinete::create(['nome' => 'Gabinete Alfa', 'sigla' => 'GA']);
        $gabB = Gabinete::create(['nome' => 'Gabinete Beta', 'sigla' => 'GB']);
        $this->depA = Departamento::create(['nome' => 'Dep Alfa', 'sigla' => 'DA', 'gabinete_id' => $gabA->id]);
        $this->depB = Departamento::create(['nome' => 'Dep Beta', 'sigla' => 'DB', 'gabinete_id' => $gabB->id]);

        $this->admin = User::factory()->create(['role_id' => $roleAdmin->id]);
        $this->admin->syncRoles(['admin']);

        $this->chefeGabinete = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->depA->id]);
        $gabA->update(['responsavel_id' => $this->chefeGabinete->id]);

        $this->chefeDepartamento = User::factory()->create(['role_id' => $roleChefe->id, 'departamento_id' => $this->depA->id]);
        $this->chefeDepartamento->assignRole('chefe-departamento');
        $this->depA->update(['responsavel_id' => $this->chefeDepartamento->id]);

        $this->tecnico = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->depA->id]);

        $this->semear();
    }

    private function entrada(string $status, Departamento $dep, array $extra = []): DocumentoEntrada
    {
        return DocumentoEntrada::create(array_merge([
            'numero_sequencial' => ++$this->numero,
            'ano_referencia' => (int) date('Y'),
            'assunto' => "Entrada {$this->numero}",
            'procedencia' => 'Externa',
            'data_entrada' => now(),
            'departamento_id' => $dep->id,
            'user_id' => $this->admin->id,
            'status' => $status,
            'arquivado' => false,
        ], $extra));
    }

    private function tarefa(DocumentoEntrada $doc, string $status): void
    {
        DocumentoTarefa::create([
            'documento_entrada_id' => $doc->id,
            'titulo' => 'Analisar',
            'assigned_by_id' => $this->chefeDepartamento->id,
            'assigned_to_user_id' => $this->tecnico->id,
            'status' => $status,
        ]);
    }

    private function interno(string $status, User $autor, Departamento $dep): void
    {
        $especie = DocumentoEspecie::firstOrCreate(['nome' => 'INFORMACAO'], ['descricao' => 'Informação', 'ativo' => true]);

        DocumentoInterno::create([
            'titulo' => "Interno {$status}",
            'conteudo_final' => '<p>Texto</p>',
            'status' => $status,
            'criado_por' => $autor->id,
            'departamento_id' => $dep->id,
            'documento_especie_id' => $especie->id,
            'numero_referencia' => 'INF/'.uniqid(),
        ]);
    }

    private function semear(): void
    {
        // Gabinete Alfa
        $this->entrada('registrado', $this->depA);
        $this->entrada('registrado', $this->depA);
        $this->entrada('pendente_tratamento', $this->depA);
        $this->entrada('tratado', $this->depA);
        $this->entrada('tratado', $this->depA);
        $this->entrada('tratado', $this->depA, ['arquivado' => true]);
        $this->entrada('registrado', $this->depA, ['ano_referencia' => (int) date('Y') - 1]);
        $this->tarefa($this->entrada('recebido', $this->depA), 'pendente');
        $this->tarefa($this->entrada('recebido', $this->depA), 'concluida');

        // Gabinete Beta: fora do âmbito do chefe de gabinete
        $this->entrada('registrado', $this->depB);
        $this->entrada('tratado', $this->depB);

        $this->interno('rascunho', $this->tecnico, $this->depA);
        $this->interno('em_analise', $this->tecnico, $this->depA);
        $this->interno('em_analise', $this->tecnico, $this->depA);
        $this->interno('aprovado', $this->tecnico, $this->depA);
        $this->interno('rascunho', $this->chefeDepartamento, $this->depA);
        $this->interno('em_analise', $this->chefeDepartamento, $this->depB);
    }

    private function painel(User $user): array
    {
        return app(DashboardService::class)->obterDadosDashboard($user->fresh(), true);
    }

    /** Total de documentos da lista para onde o link leva. */
    private function totalDaLista(User $user, string $link): int
    {
        $response = $this->actingAs($user)->get($link);
        $response->assertOk();

        return $response->viewData('documentos')->total();
    }

    private function assertCartoesLevamAoMesmoNumero(User $user): array
    {
        $kpis = collect($this->painel($user)['kpis'])->keyBy('id');

        foreach ($kpis as $id => $kpi) {
            $this->assertSame(
                $kpi['valor'],
                $this->totalDaLista($user, $kpi['link']),
                "Cartão '{$kpi['label']}' ({$id}) mostra {$kpi['valor']} mas a lista para onde leva tem outro número: {$kpi['link']}"
            );
        }

        return $kpis->map(fn ($k) => $k['valor'])->all();
    }

    public function test_admin_cada_cartao_leva_a_lista_com_o_mesmo_numero(): void
    {
        $valores = $this->assertCartoesLevamAoMesmoNumero($this->admin);

        // Registados + pendentes não arquivados de toda a instituição, de qualquer ano.
        $this->assertSame(5, $valores['carecer_despacho']);
        $this->assertSame(3, $valores['tratados_departamentos'], 'Tratados não arquivados.');
        $this->assertSame(3, $valores['documentos_em_analise']);
        $this->assertSame(10, $valores['total_registrado_ano'], 'Entradas do ano, arquivadas incluídas.');
    }

    public function test_chefe_de_gabinete_cada_cartao_leva_a_lista_com_o_mesmo_numero(): void
    {
        $valores = $this->assertCartoesLevamAoMesmoNumero($this->chefeGabinete);

        $this->assertSame(4, $valores['carecer_despacho'], 'Só o seu gabinete.');
        $this->assertSame(2, $valores['tratados_departamentos']);
        $this->assertSame(2, $valores['documentos_em_analise']);
        $this->assertSame(8, $valores['total_registrado_ano']);
    }

    public function test_chefe_de_departamento_cada_cartao_leva_a_lista_com_o_mesmo_numero(): void
    {
        $valores = $this->assertCartoesLevamAoMesmoNumero($this->chefeDepartamento);

        $this->assertSame(1, $valores['tarefas_delegadas']);
        $this->assertSame(2, $valores['minutas_revisao']);
        $this->assertSame(9, $valores['total_acervo'], 'Acervo de entradas do sector, arquivadas incluídas.');
    }

    public function test_tecnico_cada_cartao_leva_a_lista_com_o_mesmo_numero(): void
    {
        $valores = $this->assertCartoesLevamAoMesmoNumero($this->tecnico);

        $this->assertSame(1, $valores['tarefas_pendentes']);
        $this->assertSame(1, $valores['rascunhos_internos']);
        $this->assertSame(2, $valores['documentos_submetidos'], 'Antes a lista vinha sempre vazia.');
        $this->assertSame(1, $valores['concluidos_mes']);
    }

    public function test_links_ver_todos_abrem_listas_com_dados(): void
    {
        foreach ([$this->admin, $this->chefeGabinete, $this->chefeDepartamento, $this->tecnico] as $user) {
            foreach ($this->painel($user)['listas'] as $lado => $lista) {
                if (count($lista['itens']) === 0) {
                    continue;
                }
                $this->assertGreaterThan(0, $this->totalDaLista($user, $lista['url_ver_todos']),
                    "\"Ver todos\" ({$lado}) de {$user->name} abriu uma lista vazia com itens no painel: {$lista['url_ver_todos']}");
            }
        }
    }

    public function test_filtro_de_estado_aplica_se_com_sessao_iniciada(): void
    {
        $total = $this->totalDaLista($this->admin, route('documentos-entradas.index', ['tab' => 'todos', 'status' => 'tratado']));

        $this->assertSame(3, $total, 'O filtro de estado era ignorado e vinham todos os documentos.');
    }

    public function test_formulario_de_filtros_preserva_o_separador(): void
    {
        $this->actingAs($this->admin)
            ->get(route('documentos-entradas.index', ['tab' => 'tratados']))
            ->assertOk()
            ->assertSee('<input type="hidden" name="tab" value="tratados">', false);
    }
}
