<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\DocumentoEntradaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * O cartão "Novas Entradas no Setor" do painel do chefe de departamento tem de
 * contar o mesmo que a lista para onde aponta. Antes contava só por
 * departamento_id (que muda apenas no recebimento), e os documentos
 * encaminhados ao sector ficavam de fora.
 */
class PainelChefeDepartamentoContagemTest extends TestCase
{
    use RefreshDatabase;

    private Departamento $dept;

    private Departamento $origem;

    private User $chefe;

    private int $numero = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'chefe-departamento'], ['guard_name' => 'web']);
        $gabinete = Gabinete::create(['nome' => 'Secretaria Geral', 'sigla' => 'SG']);
        $this->dept = Departamento::create(['nome' => 'Logística', 'sigla' => 'DLP', 'gabinete_id' => $gabinete->id]);
        $this->origem = Departamento::create(['nome' => 'Contratação', 'sigla' => 'DCP', 'gabinete_id' => $gabinete->id]);

        $this->chefe = User::factory()->create(['departamento_id' => $this->dept->id, 'role_id' => $role->id]);
        $this->chefe->assignRole('chefe-departamento');
        $this->dept->update(['responsavel_id' => $this->chefe->id]);
    }

    private function entrada(string $status, int $departamentoId, bool $arquivado = false): DocumentoEntrada
    {
        return DocumentoEntrada::create([
            'numero_sequencial' => ++$this->numero,
            'ano_referencia' => 2026,
            'assunto' => "Entrada {$this->numero}",
            'procedencia' => 'Externa',
            'data_entrada' => now(),
            'departamento_id' => $departamentoId,
            'user_id' => $this->chefe->id,
            'status' => $status,
            'arquivado' => $arquivado,
        ]);
    }

    private function kpi(string $id): int
    {
        $dados = app(DashboardService::class)->obterDadosDashboard($this->chefe, true);

        return (int) collect($dados['kpis'])->firstWhere('id', $id)['valor'];
    }

    private function separador(string $chave): int
    {
        $tabs = app(DocumentoEntradaService::class)->getRoleWorkflowTabs($this->chefe, Request::create('/', 'GET', ['tab' => $chave]));

        return (int) collect($tabs)->firstWhere('key', $chave)['count'];
    }

    public function test_conta_encaminhados_por_receber_e_coincide_com_a_lista(): void
    {
        // Encaminhado ao DLP e ainda por receber: departamento_id continua na origem.
        foreach ([1, 2] as $_) {
            $porReceber = $this->entrada('encaminhado', $this->origem->id);
            DocumentoEncaminhamento::create([
                'documento_entrada_id' => $porReceber->id,
                'origem_departamento_id' => $this->origem->id,
                'destino_departamento_id' => $this->dept->id,
                'usuario_id' => $this->chefe->id,
                'encaminhado_em' => now(),
            ]);
        }

        $this->entrada('recebido', $this->dept->id);
        $this->entrada('recebido', $this->dept->id, arquivado: true);
        $this->entrada('recebido', $this->origem->id); // de outro sector, sem ligação ao DLP

        $delegado = $this->entrada('recebido', $this->dept->id);
        DocumentoTarefa::create([
            'documento_entrada_id' => $delegado->id,
            'titulo' => 'Analisar',
            'assigned_by_id' => $this->chefe->id,
            'assigned_to_departamento_id' => $this->dept->id,
            'status' => 'pendente',
        ]);

        $this->assertSame(3, $this->kpi('novas_entradas'));
        $this->assertSame($this->separador('novos_departamento'), $this->kpi('novas_entradas'));

        $this->assertSame(1, $this->kpi('tarefas_delegadas'));
        $this->assertSame($this->separador('delegados'), $this->kpi('tarefas_delegadas'));
    }
}
