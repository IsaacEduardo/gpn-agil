<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\User;
use App\Services\DocumentoEntradaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Separador "Do Meu Setor" do técnico: os documentos em curso no seu
 * departamento. Só mostrava tarefas atribuídas ao departamento inteiro e sem
 * dono — em produção, 1 em 71 tarefas —, pelo que ficava sempre a 0 e o técnico
 * não via nada do seu setor.
 *
 * Decisões de 2026-09-30: inclui os documentos do próprio técnico (sobrepõe-se a
 * "Atribuídos a Mim" de propósito); "Concluídos" do técnico continua pessoal e
 * passa a chamar-se "Concluídos por Mim".
 */
class SeparadorDoMeuSetorTest extends TestCase
{
    use RefreshDatabase;

    private Departamento $depA;

    private Departamento $depB;

    private User $chefeA;

    private User $tecA;

    private User $colegaA;

    private User $tecB;

    protected function setUp(): void
    {
        parent::setUp();

        $gab = Gabinete::factory()->create();
        $this->depA = Departamento::factory()->create(['gabinete_id' => $gab->id, 'is_area_expediente' => false]);
        $this->depB = Departamento::factory()->create(['gabinete_id' => $gab->id, 'is_area_expediente' => false]);

        $this->chefeA = User::factory()->create(['departamento_id' => $this->depA->id]);
        $this->chefeA->assignRole(Role::findOrCreate('chefe-departamento', 'web'));
        $this->depA->update(['responsavel_id' => $this->chefeA->id]);

        $this->tecA = User::factory()->create(['departamento_id' => $this->depA->id]);
        $this->colegaA = User::factory()->create(['departamento_id' => $this->depA->id]);
        $this->tecB = User::factory()->create(['departamento_id' => $this->depB->id]);
    }

    private function documento(Departamento $dep, string $status = 'recebido', array $extra = []): DocumentoEntrada
    {
        return DocumentoEntrada::factory()->create(array_merge([
            'departamento_id' => $dep->id,
            'user_id' => $this->chefeA->id,
            'status' => $status,
            'arquivado' => false,
        ], $extra));
    }

    private function tarefa(DocumentoEntrada $doc, array $destino): DocumentoTarefa
    {
        return DocumentoTarefa::create(array_merge([
            'documento_entrada_id' => $doc->id,
            'titulo' => 'Emitir parecer',
            'assigned_by_id' => $this->chefeA->id,
            'prazo_at' => now()->addDays(3),
            'status' => 'pendente',
        ], $destino));
    }

    private function encaminhamentoPendente(DocumentoEntrada $doc, Departamento $origem, Departamento $destino): void
    {
        DocumentoEncaminhamento::create([
            'documento_entrada_id' => $doc->id,
            'origem_departamento_id' => $origem->id,
            'destino_departamento_id' => $destino->id,
            'usuario_id' => $this->chefeA->id,
            'encaminhado_em' => now(),
            'recebido_em' => null,
        ]);
    }

    /** @return int[] */
    private function separador(string $chave, User $tecnico): array
    {
        $query = DocumentoEntrada::query()->where('arquivado', false);
        app(DocumentoEntradaService::class)->applyRoleTabFilter($query, $chave, $tecnico, 'tecnico');

        return $query->orderBy('id')->pluck('id')->all();
    }

    public function test_documento_do_departamento_a_aguardar_delegacao_aparece_so_no_seu_setor(): void
    {
        $doc = $this->documento($this->depA);

        $this->assertContains($doc->id, $this->separador('em_execucao', $this->tecA));
        $this->assertNotContains($doc->id, $this->separador('em_execucao', $this->tecB));
    }

    public function test_documento_com_tarefa_de_um_colega_aparece(): void
    {
        $doc = $this->documento($this->depA);
        $this->tarefa($doc, ['assigned_to_user_id' => $this->colegaA->id]);

        $this->assertContains($doc->id, $this->separador('em_execucao', $this->tecA));
        $this->assertNotContains($doc->id, $this->separador('atribuidos_mim', $this->tecA));
    }

    public function test_documento_do_proprio_aparece_nos_dois_separadores(): void
    {
        $doc = $this->documento($this->depA);
        $this->tarefa($doc, ['assigned_to_user_id' => $this->tecA->id]);

        $this->assertContains($doc->id, $this->separador('em_execucao', $this->tecA));
        $this->assertContains($doc->id, $this->separador('atribuidos_mim', $this->tecA));
    }

    public function test_documento_por_receber_no_departamento_aparece(): void
    {
        $doc = $this->documento($this->depB, 'encaminhado');
        $this->encaminhamentoPendente($doc, $this->depB, $this->depA);

        $this->assertContains($doc->id, $this->separador('em_execucao', $this->tecA));
    }

    public function test_documento_encaminhado_para_fora_e_por_receber_la_sai_do_setor_de_origem(): void
    {
        // Encaminhado de A para B; departamento_id continua A até B receber.
        $doc = $this->documento($this->depA, 'encaminhado');
        $this->encaminhamentoPendente($doc, $this->depA, $this->depB);

        $this->assertNotContains($doc->id, $this->separador('em_execucao', $this->tecA));
        $this->assertContains($doc->id, $this->separador('em_execucao', $this->tecB));
    }

    public function test_documentos_tratados_ou_arquivados_nao_aparecem(): void
    {
        $tratado = $this->documento($this->depA, 'tratado');
        $arquivado = $this->documento($this->depA, 'recebido', ['arquivado' => true]);
        $externo = $this->documento($this->depA, 'encaminhado_externo');

        $query = DocumentoEntrada::query(); // sem o filtro de arquivados da base
        app(DocumentoEntradaService::class)->applyRoleTabFilter($query, 'em_execucao', $this->tecA, 'tecnico');
        $ids = $query->pluck('id')->all();

        $this->assertNotContains($tratado->id, $ids);
        $this->assertNotContains($arquivado->id, $ids);
        $this->assertNotContains($externo->id, $ids);
    }

    public function test_tarefa_ao_departamento_sem_dono_continua_a_aparecer(): void
    {
        // Documento de B, delegado ao departamento A inteiro (p. ex. pelo chefe de gabinete).
        $doc = $this->documento($this->depB);
        $this->tarefa($doc, ['assigned_to_departamento_id' => $this->depA->id]);

        $this->assertContains($doc->id, $this->separador('em_execucao', $this->tecA));
    }

    public function test_contador_bate_com_a_listagem(): void
    {
        $this->documento($this->depA);
        $this->tarefa($this->documento($this->depA), ['assigned_to_user_id' => $this->tecA->id]);
        $this->documento($this->depA, 'tratado');
        $this->documento($this->depB);

        $servico = app(DocumentoEntradaService::class);
        $pedido = Request::create('/documentos-entradas', 'GET', ['tab' => 'em_execucao']);

        $tabs = collect($servico->getRoleWorkflowTabs($this->tecA, $pedido));
        $contador = $tabs->firstWhere('key', 'em_execucao')['count'];
        $linhas = $servico->getFilteredDocumentsQuery($pedido, $this->tecA)->count();

        $this->assertSame(2, $linhas);
        $this->assertSame($linhas, $contador);
    }

    public function test_concluidos_do_tecnico_chama_se_concluidos_por_mim_e_o_do_chefe_nao_muda(): void
    {
        $servico = app(DocumentoEntradaService::class);
        $pedido = Request::create('/documentos-entradas', 'GET');

        $doTecnico = collect($servico->getRoleWorkflowTabs($this->tecA, $pedido))->firstWhere('key', 'concluidos');
        $doChefe = collect($servico->getRoleWorkflowTabs($this->chefeA, $pedido))->firstWhere('key', 'concluidos');

        $this->assertSame('Concluídos por Mim', $doTecnico['label']);
        $this->assertSame('Concluídos', $doChefe['label']);
    }

    public function test_concluidos_por_mim_continua_pessoal(): void
    {
        $meu = $this->documento($this->depA);
        $this->tarefa($meu, ['assigned_to_user_id' => $this->tecA->id, 'status' => 'concluida']);
        $doColega = $this->documento($this->depA);
        $this->tarefa($doColega, ['assigned_to_user_id' => $this->colegaA->id, 'status' => 'concluida']);

        $this->assertSame([$meu->id], $this->separador('concluidos', $this->tecA));
    }
}
