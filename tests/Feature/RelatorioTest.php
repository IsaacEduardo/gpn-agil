<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\User;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Módulo de Relatórios & BI: controlo de acesso, escopo por perfil, cálculo
 * dos indicadores de tempo e prazos, filtros temporais e exportações.
 */
class RelatorioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $tecnico;

    private Departamento $departamento;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('relatorios.view', 'web');
        Permission::findOrCreate('relatorios.export', 'web');

        $papelAdmin = Role::findOrCreate('admin', 'web');
        $papelAdmin->givePermissionTo(['relatorios.view', 'relatorios.export']);

        $this->departamento = Departamento::factory()->create();

        $this->admin = User::factory()->create(['departamento_id' => $this->departamento->id]);
        $this->admin->assignRole($papelAdmin);

        // Sem papel nem permissões: não deve passar da porta.
        $this->tecnico = User::factory()->create(['departamento_id' => $this->departamento->id]);
    }

    // -----------------------------------------------------------------
    // Acesso
    // -----------------------------------------------------------------

    public function test_admin_acede_ao_painel(): void
    {
        $this->actingAs($this->admin)
            ->get(route('relatorios.index'))
            ->assertOk()
            ->assertSee('Módulo de Relatórios e Business Intelligence');
    }

    public function test_utilizador_sem_permissao_nao_acede(): void
    {
        $this->actingAs($this->tecnico)
            ->get(route('relatorios.index'))
            ->assertForbidden();
    }

    public function test_permissao_view_basta_para_consultar(): void
    {
        $utilizador = User::factory()->create(['departamento_id' => $this->departamento->id]);
        $utilizador->givePermissionTo('relatorios.view');

        $this->actingAs($utilizador)
            ->get(route('relatorios.index'))
            ->assertOk();
    }

    public function test_consultar_nao_da_direito_a_exportar(): void
    {
        $utilizador = User::factory()->create(['departamento_id' => $this->departamento->id]);
        $utilizador->givePermissionTo('relatorios.view');

        $this->actingAs($utilizador)
            ->get(route('relatorios.export.excel'))
            ->assertForbidden();
    }

    public function test_chefe_de_departamento_ve_apenas_o_seu_departamento(): void
    {
        $outro = Departamento::factory()->create();

        $this->entrada(['departamento_id' => $this->departamento->id, 'data_entrada' => now()]);
        $this->entrada(['departamento_id' => $outro->id, 'data_entrada' => now()]);
        $this->entrada(['departamento_id' => $outro->id, 'data_entrada' => now()]);

        $chefe = User::factory()->create(['departamento_id' => $this->departamento->id]);
        $chefe->assignRole(Role::findOrCreate('chefe-departamento', 'web'));

        // Mesmo pedindo explicitamente o outro departamento, o escopo prevalece.
        $dados = $this->relatorio([
            'granularity' => 'custom',
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'departamento_id' => $outro->id,
        ], $chefe);

        $this->assertSame($this->departamento->id, $dados['filters']['departamento_id']);
        $this->assertFalse($dados['filters']['escopo_livre']);
        $this->assertSame(1, $dados['kpis']['total_entradas']);
    }

    // -----------------------------------------------------------------
    // Indicadores
    // -----------------------------------------------------------------

    public function test_media_de_tempo_de_resposta_conta_apenas_documentos_resolvidos(): void
    {
        // Resolvido em 4 dias (entrada -> arquivo).
        $this->entrada([
            'data_entrada' => now()->subDays(10)->startOfDay(),
            'arquivado' => true,
            'arquivado_em' => now()->subDays(6)->startOfDay(),
            'status' => 'arquivado',
        ]);

        // Resolvido em 2 dias (entrada -> despacho).
        $this->entrada([
            'data_entrada' => now()->subDays(8)->startOfDay(),
            'data_despacho' => now()->subDays(6)->startOfDay(),
            'status' => 'tratado',
        ]);

        // Ainda pendente: não entra na média.
        $this->entrada(['data_entrada' => now()->subDays(30)->startOfDay()]);

        $dados = $this->relatorioDoPeriodo(now()->subDays(40), now());

        $this->assertSame(3.0, $dados['kpis']['avg_resposta_entradas_dias']);
        $this->assertSame(3, $dados['kpis']['total_entradas']);
    }

    public function test_media_de_homologacao_usa_a_data_de_assinatura(): void
    {
        $this->interno([
            'created_at' => now()->subDays(10)->startOfDay(),
            'assinado_em' => now()->subDays(4)->startOfDay(),
            'status' => 'assinado',
        ]);
        $this->interno(['created_at' => now()->subDays(3)->startOfDay()]);

        $dados = $this->relatorioDoPeriodo(now()->subDays(40), now());

        $this->assertSame(6.0, $dados['kpis']['avg_assinatura_internos_dias']);
        $this->assertSame(1, $dados['kpis']['total_assinados']);
        $this->assertSame(2, $dados['kpis']['total_internos']);
    }

    public function test_taxa_de_cumprimento_de_prazos(): void
    {
        // Prazo global de 5 dias (config documentos.prazo_tratamento_dias).
        config(['documentos.prazo_tratamento_dias' => 5]);

        // Dentro do prazo: resolvido em 2 dias.
        $this->entrada([
            'data_entrada' => now()->subDays(10)->startOfDay(),
            'data_despacho' => now()->subDays(8)->startOfDay(),
            'status' => 'tratado',
        ]);

        // Fora do prazo: resolvido ao fim de 9 dias.
        $this->entrada([
            'data_entrada' => now()->subDays(20)->startOfDay(),
            'data_despacho' => now()->subDays(11)->startOfDay(),
            'status' => 'tratado',
        ]);

        // Fora do prazo: pendente há 30 dias.
        $this->entrada(['data_entrada' => now()->subDays(30)->startOfDay()]);

        $dados = $this->relatorioDoPeriodo(now()->subDays(40), now());

        $this->assertSame(3, $dados['charts']['sla']['avaliados']);
        $this->assertSame(1, $dados['charts']['sla']['no_prazo']);
        $this->assertSame(2, $dados['charts']['sla']['atrasados']);
        $this->assertSame(33.3, $dados['kpis']['sla_compliance_percent']);
        $this->assertSame(1, $dados['kpis']['sla_pendentes']);
    }

    // -----------------------------------------------------------------
    // Filtros temporais
    // -----------------------------------------------------------------

    public function test_filtro_por_ano(): void
    {
        $this->entrada(['data_entrada' => Carbon::create(2024, 5, 10)]);
        $this->entrada(['data_entrada' => Carbon::create(2024, 11, 2)]);
        $this->entrada(['data_entrada' => Carbon::create(2025, 3, 1)]);

        $dados = $this->relatorio(['granularity' => 'ano', 'year' => 2024]);

        $this->assertSame(2, $dados['kpis']['total_entradas']);
        // O eixo de um ano tem doze meses.
        $this->assertCount(12, $dados['charts']['tendencia_temporal']['labels']);
    }

    public function test_filtro_por_mes(): void
    {
        $this->entrada(['data_entrada' => Carbon::create(2025, 3, 4)]);
        $this->entrada(['data_entrada' => Carbon::create(2025, 3, 28)]);
        $this->entrada(['data_entrada' => Carbon::create(2025, 4, 1)]);

        $dados = $this->relatorio(['granularity' => 'mes', 'year' => 2025, 'month' => 3]);

        $this->assertSame(2, $dados['kpis']['total_entradas']);
        $this->assertCount(31, $dados['charts']['tendencia_temporal']['labels']);
        $this->assertSame(1, $dados['charts']['tendencia_temporal']['entradas'][3]);
    }

    public function test_filtro_por_dia(): void
    {
        $dia = Carbon::create(2025, 6, 17);

        $this->entrada(['data_entrada' => $dia, 'created_at' => $dia->copy()->setTime(9, 30)]);
        $this->entrada(['data_entrada' => $dia, 'created_at' => $dia->copy()->setTime(9, 45)]);
        $this->entrada(['data_entrada' => $dia->copy()->addDay()]);

        $dados = $this->relatorio([
            'granularity' => 'dia',
            'date_from' => $dia->toDateString(),
        ]);

        $this->assertSame(2, $dados['kpis']['total_entradas']);
        // Eixo horário: 24 colunas, ambas as entradas às 09h.
        $this->assertCount(24, $dados['charts']['tendencia_temporal']['labels']);
        $this->assertSame(2, $dados['charts']['tendencia_temporal']['entradas'][9]);
    }

    public function test_granularidade_invalida_cai_no_padrao_mensal(): void
    {
        $this->actingAs($this->admin)
            ->get(route('relatorios.index', ['granularity' => 'decada']))
            ->assertSessionHasErrors('granularity');
    }

    // -----------------------------------------------------------------
    // Exportações
    // -----------------------------------------------------------------

    public function test_exportacao_pdf(): void
    {
        $this->entrada(['data_entrada' => now()]);

        $resposta = $this->actingAs($this->admin)->get(route('relatorios.export.pdf'));

        $resposta->assertOk();
        $resposta->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment;', $resposta->headers->get('content-disposition'));
    }

    public function test_exportacao_excel(): void
    {
        $this->entrada(['data_entrada' => now()]);

        $resposta = $this->actingAs($this->admin)->get(route('relatorios.export.excel'));

        $resposta->assertOk();
        $this->assertStringContainsString('.xlsx', $resposta->headers->get('content-disposition'));
    }

    public function test_exportacao_csv(): void
    {
        $resposta = $this->actingAs($this->admin)->get(route('relatorios.export.csv'));

        $resposta->assertOk();
        $this->assertStringContainsString('.csv', $resposta->headers->get('content-disposition'));
    }

    public function test_exportacao_acima_do_limite_e_recusada(): void
    {
        // Um relatório oficial truncado sem aviso seria pior do que nenhum.
        config(['documentos.limite_exportacao' => 2]);

        for ($i = 0; $i < 3; $i++) {
            $this->entrada(['data_entrada' => now()]);
        }

        $this->actingAs($this->admin)
            ->from(route('relatorios.index'))
            ->get(route('relatorios.export.csv'))
            ->assertRedirect(route('relatorios.index'))
            ->assertSessionHas('error');
    }

    public function test_exportacao_xml_inclui_metadados(): void
    {
        $entrada = $this->entrada(['data_entrada' => now(), 'assunto' => 'Pedido de parecer técnico']);

        $resposta = $this->actingAs($this->admin)->get(route('relatorios.export.xml'));

        $resposta->assertOk();
        $resposta->assertHeader('content-type', 'application/xml');

        $xml = simplexml_load_string($resposta->getContent());

        $this->assertNotFalse($xml);
        $this->assertSame('GPN-AGIL', (string) $xml['sistema']);
        $this->assertSame('Pedido de parecer técnico', (string) $xml->documentos_entrada->documento->assunto);
        $this->assertSame(
            $entrada->numero_sequencial.'/'.$entrada->ano_referencia,
            (string) $xml->documentos_entrada->documento->numero
        );
    }

    // -----------------------------------------------------------------
    // Apoio
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function entrada(array $atributos = []): DocumentoEntrada
    {
        return DocumentoEntrada::factory()->create($atributos + [
            'departamento_id' => $this->departamento->id,
            'user_id' => $this->admin->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function interno(array $atributos = []): DocumentoInterno
    {
        return DocumentoInterno::factory()->create($atributos + [
            'departamento_id' => $this->departamento->id,
            'criado_por' => $this->admin->id,
            'documento_especie_id' => DocumentoEspecie::factory(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function relatorio(array $filtros, ?User $user = null): array
    {
        return app(ReportService::class)->getReportData($filtros, $user ?? $this->admin);
    }

    /**
     * @return array<string, mixed>
     */
    private function relatorioDoPeriodo(Carbon $de, Carbon $ate): array
    {
        return $this->relatorio([
            'granularity' => 'custom',
            'date_from' => $de->toDateString(),
            'date_to' => $ate->toDateString(),
        ]);
    }
}
