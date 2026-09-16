<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoTarefa;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use App\Services\DocumentoEntradaService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Correção do registo de um documento de entrada.
 *
 * O caso de uso é a gralha de digitação apanhada logo a seguir ao registo. O que
 * se exige: que a correção chegue mesmo a gravar, que alcance todos os campos do
 * registo — incluindo a data de entrada, que conta para o prazo — e que não sirva
 * de atalho para atos de tramitação que têm regra própria.
 */
class DocumentoEntradaEdicaoTest extends TestCase
{
    use RefreshDatabase;

    private User $autor;

    private Departamento $dep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        Storage::fake('public');
        Notification::fake();

        $roleUser = Role::where('name', 'user')->firstOrFail();
        $gab = Gabinete::create(['nome' => 'Gabinete A', 'sigla' => 'GABA']);
        $this->dep = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);
        $this->autor = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->dep->id]);
        $this->autor->assignRole($roleUser);

        DocumentoEspecie::firstOrCreate(['nome' => 'Ofício'], ['ativo' => true, 'ordem' => 1]);
    }

    private function documento(array $overrides = []): DocumentoEntrada
    {
        return DocumentoEntrada::create(array_merge([
            'numero_sequencial' => 1,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now()->subDay(),
            'classificacao_especie' => 'Ofício',
            'assunto' => 'Pedido de parecer',
            'departamento_id' => $this->dep->id,
            'user_id' => $this->autor->id,
            'status' => 'registrado',
        ], $overrides));
    }

    private function payload(DocumentoEntrada $doc, array $overrides = []): array
    {
        return array_merge([
            'classificacao_especie' => $doc->classificacao_especie,
            'assunto' => $doc->assunto,
        ], $overrides);
    }

    /**
     * O campo existia no formulário mas nem era validado nem sincronizado: o
     * utilizador corrigia a palavra-chave, recebia "atualizado com sucesso" e
     * nada mudava.
     */
    public function test_tags_sao_gravadas_na_edicao(): void
    {
        $doc = $this->documento();

        $this->actingAs($this->autor)
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'tags' => 'urgente, financeiro',
            ]))
            ->assertRedirect();

        $this->assertEqualsCanonicalizing(
            ['urgente', 'financeiro'],
            $doc->fresh()->tags->pluck('nome')->all()
        );
    }

    /** Corrigir uma tag mal escrita passa por conseguir tirá-la. */
    public function test_tags_podem_ser_limpas_na_edicao(): void
    {
        $doc = $this->documento();
        $tag = Tag::create(['nome' => 'urgemte', 'slug' => 'urgemte']);
        $doc->tags()->sync([$tag->id]);

        $this->actingAs($this->autor)
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, ['tags' => '']))
            ->assertRedirect();

        $this->assertCount(0, $doc->fresh()->tags);
    }

    /**
     * A data de entrada alimenta o SLA e era o único campo do registo sem
     * qualquer via de correção — não estava no formulário nem na validação.
     */
    public function test_data_de_entrada_e_corrigivel(): void
    {
        $doc = $this->documento(['data_entrada' => now()->subDays(10)]);
        $correta = now()->subDays(2)->startOfDay();

        $this->actingAs($this->autor)
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'data_entrada' => $correta->toDateString(),
            ]))
            ->assertRedirect();

        $this->assertSame($correta->toDateString(), $doc->fresh()->data_entrada->toDateString());
    }

    /** Mesma regra do registo: uma entrada no futuro contaminaria o prazo. */
    public function test_data_de_entrada_no_futuro_e_recusada(): void
    {
        $doc = $this->documento();

        $this->actingAs($this->autor)
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'data_entrada' => now()->addDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('data_entrada');
    }

    /**
     * A saída de gabinete tem policy própria (só o responsável do gabinete),
     * cria o encaminhamento externo, muda o status e notifica o destino. Pelo
     * formulário de edição não acontecia nada disso — e a saída verdadeira ficava
     * depois barrada por "documento já possui saída registada".
     */
    public function test_edicao_nao_regista_saida_de_gabinete(): void
    {
        $doc = $this->documento();

        $this->actingAs($this->autor)
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'saida_gabinete_data' => now()->toDateString(),
                'encaminhamento_orgao' => 'Gabinete Inventado',
                'encaminhamento_oficio_numero' => 'OF/999',
            ]))
            ->assertRedirect();

        $doc = $doc->fresh();

        $this->assertNull($doc->saida_gabinete_data);
        $this->assertNull($doc->encaminhamento_orgao);
        $this->assertNull($doc->encaminhamento_oficio_numero);
        $this->assertDatabaseCount('documento_encaminhamentos_externos', 0);
    }

    /** A correção normal do registo continua a funcionar. */
    public function test_assunto_e_corrigido(): void
    {
        $doc = $this->documento(['assunto' => 'Pedido de pareser']);

        $this->actingAs($this->autor)
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'assunto' => 'Pedido de parecer',
            ]))
            ->assertRedirect();

        $this->assertSame('Pedido de parecer', $doc->fresh()->assunto);
    }

    // ── A janela fecha-se ao primeiro tratamento de chefia ──────────────────
    //
    // O despacho, o encaminhamento e o arquivamento já congelavam o registo
    // (ver DocumentoEntradaPolicyTest). Faltavam estes quatro: um documento já
    // visto, com tarefa delegada ou já saído do gabinete continuava a poder ver
    // o assunto reescrito por baixo de quem o tratou.

    public function test_visto_do_chefe_de_departamento_congela_o_registo(): void
    {
        $doc = $this->documento([
            'visto_departamento_status' => 'aprovado',
            'visto_departamento_data' => now(),
        ]);

        $this->assertSame('Pedido de parecer', $this->tentarCorrigir($doc)->assunto);
    }

    public function test_visto_do_gabinete_congela_o_registo(): void
    {
        $doc = $this->documento([
            'visto_gabinete_status' => 'aprovado',
            'visto_gabinete_data' => now(),
        ]);

        $this->assertSame('Pedido de parecer', $this->tentarCorrigir($doc)->assunto);
    }

    public function test_saida_de_gabinete_congela_o_registo(): void
    {
        $doc = $this->documento(['saida_gabinete_data' => now()->subDay()]);

        $this->assertSame('Pedido de parecer', $this->tentarCorrigir($doc)->assunto);
    }

    public function test_tarefa_delegada_congela_o_registo(): void
    {
        $doc = $this->documento();

        DocumentoTarefa::create([
            'documento_entrada_id' => $doc->id,
            'assigned_by_id' => $this->autor->id,
            'assigned_to_user_id' => $this->autor->id,
            'titulo' => 'Analisar',
            'descricao' => 'Analisar e responder',
            'prazo_at' => now()->addWeek(),
            'status' => 'pendente',
        ]);

        $this->assertSame('Pedido de parecer', $this->tentarCorrigir($doc)->assunto);
    }

    /**
     * O status de partida do visto não é tratamento nenhum — é a coluna por
     * preencher. Congelar por aí trancava o registo à nascença.
     */
    public function test_visto_por_registar_nao_congela_o_registo(): void
    {
        $doc = $this->documento(['visto_departamento_status' => 'pendente']);

        $this->assertSame('Assunto corrigido', $this->tentarCorrigir($doc)->assunto);
    }

    /** O admin continua a ser a via de correção depois do congelamento. */
    public function test_admin_corrige_registo_congelado(): void
    {
        $doc = $this->documento(['visto_departamento_data' => now()]);

        $this->actingAs($this->admin())
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'assunto' => 'Assunto corrigido',
                'motivo' => 'Assunto trocado no ato do registo.',
            ]))
            ->assertRedirect();

        $this->assertSame('Assunto corrigido', $doc->fresh()->assunto);
    }

    /**
     * Alterar por cima do trabalho de uma chefia tem de dizer porquê. Enquanto a
     * janela está aberta a correção é trivial e não se exige justificação.
     */
    public function test_correcao_de_registo_congelado_exige_motivo(): void
    {
        $doc = $this->documento(['visto_departamento_data' => now()]);

        $this->actingAs($this->admin())
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'assunto' => 'Assunto corrigido',
            ]))
            ->assertSessionHasErrors('motivo');

        $this->assertSame('Pedido de parecer', $doc->fresh()->assunto);
    }

    public function test_correcao_com_janela_aberta_nao_exige_motivo(): void
    {
        $doc = $this->documento();

        $this->actingAs($this->autor)
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'assunto' => 'Assunto corrigido',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_motivo_fica_na_auditoria(): void
    {
        $doc = $this->documento(['visto_departamento_data' => now()]);

        $this->actingAs($this->admin())
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'assunto' => 'Assunto corrigido',
                'motivo' => 'Assunto trocado no ato do registo.',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => DocumentoEntrada::class,
            'auditable_id' => $doc->id,
            'action' => 'update',
            'motivo' => 'Assunto trocado no ato do registo.',
        ]);
    }

    /**
     * A correção ficava só no audit log — visível à chefia e só na aba de
     * auditoria. Quem lê o percurso do documento não via que o assunto tinha
     * sido reescrito depois de o documento seguir caminho.
     */
    public function test_correcao_aparece_no_percurso_do_documento(): void
    {
        $doc = $this->documento(['visto_departamento_data' => now()]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'assunto' => 'Assunto corrigido',
                'motivo' => 'Assunto trocado no ato do registo.',
            ]))
            ->assertRedirect();

        $percurso = $this->actingAs($admin)
            ->get(route('documentos-entradas.show', $doc))
            ->assertOk()
            ->viewData('timelineEvents');

        $correcao = collect($percurso)->firstWhere('tipo', 'correcao_registo');

        $this->assertNotNull($correcao, 'A correção não apareceu no percurso.');
        $this->assertStringContainsString('Assunto', $correcao['descricao']);
        $this->assertStringContainsString('Assunto trocado no ato do registo.', $correcao['descricao']);
        $this->assertSame($admin->name, $correcao['autor']);
    }

    /**
     * O audit log grava um 'update' por cada save — vistos, despacho, status.
     * Só as alterações aos campos do registo são correções.
     */
    public function test_visto_nao_conta_como_correcao_no_percurso(): void
    {
        $doc = $this->documento();
        $admin = $this->admin();

        app(DocumentoEntradaService::class)->registerVisto($doc, 'departamento', 'aprovado', $admin);

        $percurso = $this->actingAs($admin)
            ->get(route('documentos-entradas.show', $doc))
            ->assertOk()
            ->viewData('timelineEvents');

        $this->assertNull(collect($percurso)->firstWhere('tipo', 'correcao_registo'));
    }

    private function admin(): User
    {
        $admin = User::factory()->create([
            'role_id' => Role::where('name', 'admin')->firstOrFail()->id,
            'departamento_id' => $this->dep->id,
        ]);
        $admin->assignRole(Role::where('name', 'admin')->firstOrFail());

        return $admin;
    }

    /**
     * Corrigir a gralha é trabalho de quem registou. Qualquer membro do
     * departamento podia reescrever o registo de um colega.
     */
    public function test_colega_do_mesmo_departamento_nao_corrige_registo_alheio(): void
    {
        $doc = $this->documento();
        $colega = User::factory()->create([
            'role_id' => Role::where('name', 'user')->firstOrFail()->id,
            'departamento_id' => $this->dep->id,
        ]);

        $this->actingAs($colega)
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'assunto' => 'Assunto corrigido',
            ]))
            ->assertStatus(403);

        $this->assertSame('Pedido de parecer', $doc->fresh()->assunto);
    }

    /**
     * O botão da listagem e a policy têm de concordar.
     *
     * O @can('update') da listagem decide sobre o modelo tal como a query da
     * listagem o traz, e essa query não selecionava data_despacho nem user_id.
     * Sem strict mode o Eloquent devolve null em silêncio, pelo que o botão
     * "Editar" aparecia em documentos despachados e desaparecia para o próprio
     * autor. Por isso o teste avalia o Gate sobre o modelo vindo dessa query, e
     * não sobre um modelo carregado de fresco — é aí que estava a diferença.
     */
    public function test_gate_da_listagem_permite_ao_autor_corrigir(): void
    {
        $doc = $this->documento();

        $this->assertTrue(
            Gate::forUser($this->autor)->allows('update', $this->documentoComoNaListagem($doc))
        );
    }

    public function test_gate_da_listagem_recusa_documento_despachado(): void
    {
        $doc = $this->documento(['data_despacho' => now(), 'texto_despacho' => 'Ao departamento']);

        $this->assertFalse(
            Gate::forUser($this->autor)->allows('update', $this->documentoComoNaListagem($doc))
        );
    }

    public function test_gate_da_listagem_recusa_documento_com_visto(): void
    {
        $doc = $this->documento(['visto_departamento_data' => now()]);

        $this->assertFalse(
            Gate::forUser($this->autor)->allows('update', $this->documentoComoNaListagem($doc))
        );
    }

    /** Devolve o documento tal como a listagem o carrega (colunas e counts). */
    private function documentoComoNaListagem(DocumentoEntrada $doc): DocumentoEntrada
    {
        $this->actingAs($this->autor);

        $listados = app(DocumentoEntradaService::class)
            ->getFilteredDocuments(Request::create('/documentos-entradas?tab=todos'), $this->autor);

        return $listados->firstWhere('id', $doc->id)
            ?? $this->fail('O documento não apareceu na listagem.');
    }

    /**
     * Tenta corrigir o assunto como autor e devolve o documento recarregado,
     * para que cada teste diga apenas o que espera encontrar lá.
     */
    private function tentarCorrigir(DocumentoEntrada $doc): DocumentoEntrada
    {
        $this->actingAs($this->autor)
            ->put(route('documentos-entradas.update', $doc), $this->payload($doc, [
                'assunto' => 'Assunto corrigido',
            ]));

        return $doc->fresh();
    }
}
