<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DadosInstituicao;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\ModeloDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Livros do gabinete (Ofício, OS, Nota, Informação/Parecer) só numerados pelo sistema a
 * partir de 01/01/2027: até lá saem com o número em branco, para preencher à mão.
 */
class NumeracaoLivrosGabineteInicioTest extends TestCase
{
    use RefreshDatabase;

    private Gabinete $sg;

    private Departamento $dlp;

    private User $secretario;

    private User $tecnico;

    private User $admin;

    private DadosInstituicao $instituicao;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-05 10:00:00', 'UTC'));

        $this->secretario = User::factory()->create(['departamento_id' => null]);
        $this->sg = Gabinete::create([
            'nome' => 'Secretaria Geral', 'sigla' => 'SG', 'codigo_oficios' => 'SEC.GOV.PROV.HLA',
            'responsavel_id' => $this->secretario->id,
        ]);
        $this->dlp = Departamento::create(['nome' => 'Departamento de Logística e Património', 'sigla' => 'DLP', 'gabinete_id' => $this->sg->id]);
        $this->tecnico = User::factory()->create(['departamento_id' => $this->dlp->id]);

        $this->admin = User::factory()->create(['departamento_id' => $this->dlp->id]);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin->assignRole($role);
        $this->admin->update(['role_id' => $role->id]);

        $this->instituicao = DadosInstituicao::create([
            'nome_oficial' => 'Governo Provincial da Huíla', 'sigla' => 'GPH', 'cidade' => 'Lubango',
            'numeracao_gabinete_desde' => '2027-01-01',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function especie(string $nome): DocumentoEspecie
    {
        return DocumentoEspecie::firstOrCreate(['nome' => $nome], ['ativo' => true]);
    }

    /** Pré-visualiza o modelo e grava com esse conteúdo (o caminho real do formulário). */
    private function criarPeloModelo(User $autor, string $codigoModelo, string $especie, array $extra = []): DocumentoInterno
    {
        $modelo = ModeloDocumento::where('codigo', $codigoModelo)->firstOrFail();
        $html = $this->actingAs($autor)->postJson(route('documentos-internos.preview'), $extra + [
            'modelo_id' => $modelo->id,
        ])->assertOk()->json('content');

        $this->actingAs($autor)->post(route('documentos-internos.store'), $extra + [
            'titulo' => $especie,
            'documento_especie_id' => $this->especie($especie)->id,
            'modelo_documento_id' => $modelo->id,
            'conteudo_final' => $html,
        ])->assertRedirect()->assertSessionHasNoErrors();

        return DocumentoInterno::latest('id')->firstOrFail();
    }

    public function test_antes_da_data_a_nota_fica_sem_numero_e_com_a_referencia_em_branco(): void
    {
        $primeira = $this->criarPeloModelo($this->tecnico, 'NOTA_SEC_GERAL', 'Nota');
        $segunda = $this->criarPeloModelo($this->tecnico, 'NOTA_SEC_GERAL', 'Nota');

        $this->assertNull($primeira->numero_referencia);
        $this->assertNull($segunda->numero_referencia); // vários sem número não colidem no unique
        $this->assertStringContainsString('NOTA _____/SEC.GOV.PROV.HLA.DLP/2026', $primeira->conteudo_final);
        $this->assertDatabaseMissing('sequencias_documentos', ['chave' => 'NOTA:SEC.GOV.PROV.HLA']);
    }

    public function test_antes_da_data_oficio_e_informacao_ficam_em_branco(): void
    {
        $oficio = $this->criarPeloModelo($this->tecnico, 'OFICIO_SEC_GERAL', 'Ofício');
        $this->assertNull($oficio->numero_referencia);
        $this->assertStringContainsString('_____/SEC.GOV.PROV.HLA.DLP/2026', $oficio->conteudo_final);

        $informacao = $this->criarPeloModelo($this->tecnico, 'INFORMACAO_PARECER_SEC_GERAL', 'Informação/Parecer');
        $this->assertNull($informacao->numero_referencia);
        $this->assertStringContainsString('class="ref-referencia-titulo">_____/SEC.GOV.PROV.HLA.DLP/2026</span>', $informacao->conteudo_final);
        $this->assertStringContainsString('class="ref-numero-ordem">_____</span>', $informacao->conteudo_final);
    }

    public function test_antes_da_data_a_ordem_de_servico_fica_com_o_numero_em_branco(): void
    {
        $this->actingAs($this->secretario)->post(route('documentos-internos.store'), [
            'titulo' => 'Ordem de Serviço',
            'documento_especie_id' => $this->especie('Ordem de Serviço')->id,
            'emissor' => "gab:{$this->sg->id}",
            'conteudo_final' => '<p>ORDEM DE SERVIÇO Nº <span class="ref-numero-ordem">_____</span></p>',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $doc = DocumentoInterno::latest('id')->first();
        $this->assertNull($doc->numero_referencia);
        $this->assertStringContainsString('class="ref-numero-ordem">_____</span>', $doc->conteudo_final);
    }

    public function test_as_restantes_series_continuam_numeradas(): void
    {
        $this->actingAs($this->tecnico)->post(route('documentos-internos.store'), [
            'titulo' => 'Memorando',
            'documento_especie_id' => $this->especie('Memorando')->id,
            'conteudo_final' => '<p>Texto</p>',
        ])->assertRedirect();

        $this->assertSame('DLP/MEMO/001/2026', DocumentoInterno::latest('id')->value('numero_referencia'));
    }

    public function test_a_partir_de_1_de_janeiro_de_luanda_numera_desde_1(): void
    {
        // 31/12/2026 23:30 UTC = 01/01/2027 00:30 em Luanda.
        Carbon::setTestNow(Carbon::parse('2026-12-31 23:30:00', 'UTC'));

        $nota = $this->criarPeloModelo($this->tecnico, 'NOTA_SEC_GERAL', 'Nota');

        $this->assertSame('NOTA 1/SEC.GOV.PROV.HLA.DLP/2027', $nota->numero_referencia);
        $this->assertStringContainsString('NOTA 1/SEC.GOV.PROV.HLA.DLP/2027', $nota->conteudo_final);
    }

    public function test_sem_data_de_inicio_numera_sempre(): void
    {
        $this->instituicao->update(['numeracao_gabinete_desde' => null]);

        $nota = $this->criarPeloModelo($this->tecnico, 'NOTA_SEC_GERAL', 'Nota');

        $this->assertSame('NOTA 1/SEC.GOV.PROV.HLA.DLP/2026', $nota->numero_referencia);
    }

    public function test_admin_altera_a_data_no_ecra_numeracao_com_motivo_auditado(): void
    {
        $this->actingAs($this->admin)->get(route('admin.numeracao.index'))
            ->assertOk()->assertSee('Numeração em papel')->assertSee('01/01/2027');

        $this->actingAs($this->admin)->post(route('admin.numeracao.inicio-gabinete'), [
            'numeracao_gabinete_desde' => '2026-11-01',
        ])->assertSessionHasErrors('motivo');

        $this->actingAs($this->tecnico)->post(route('admin.numeracao.inicio-gabinete'), [
            'numeracao_gabinete_desde' => '2026-11-01', 'motivo' => 'Tentativa sem permissão',
        ])->assertForbidden();

        $this->actingAs($this->admin)->post(route('admin.numeracao.inicio-gabinete'), [
            'numeracao_gabinete_desde' => '2026-11-01', 'motivo' => 'Decisão do Secretário Geral',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-11-01', $this->instituicao->fresh()->numeracao_gabinete_desde->toDateString());
        $this->assertTrue(AuditLog::where('action', 'numeracao.inicio_gabinete')->where('motivo', 'Decisão do Secretário Geral')->exists());
    }

    public function test_comando_retira_o_numero_aos_documentos_ja_numerados(): void
    {
        // Numerados antes de haver data de início (como os de hoje em produção).
        $this->instituicao->update(['numeracao_gabinete_desde' => null]);
        $nota = $this->criarPeloModelo($this->tecnico, 'NOTA_SEC_GERAL', 'Nota');
        $assinada = $this->criarPeloModelo($this->tecnico, 'NOTA_SEC_GERAL', 'Nota');
        $assinada->update(['assinado_em' => now(), 'assinatura_hash' => str_repeat('a', 64)]);
        $this->actingAs($this->tecnico)->post(route('documentos-internos.store'), [
            'titulo' => 'Memorando', 'documento_especie_id' => $this->especie('Memorando')->id, 'conteudo_final' => '<p>Texto</p>',
        ]);
        $this->instituicao->update(['numeracao_gabinete_desde' => '2027-01-01']);

        // Simulação: nada muda.
        $this->artisan('numeracao:retirar-numeros-gabinete')->assertSuccessful();
        $this->assertSame('NOTA 1/SEC.GOV.PROV.HLA.DLP/2026', $nota->fresh()->numero_referencia);

        $this->artisan('numeracao:retirar-numeros-gabinete', ['--executar' => true])->assertSuccessful();

        $nota->refresh();
        $this->assertNull($nota->numero_referencia);
        $this->assertStringContainsString('NOTA _____/SEC.GOV.PROV.HLA.DLP/2026', $nota->conteudo_final);
        $this->assertStringNotContainsString('NOTA 1/', $nota->conteudo_final);
        $this->assertTrue($nota->versoes()->where('conteudo_final', 'like', '%NOTA 1/SEC.GOV.PROV.HLA.DLP/2026%')->exists());

        // Assinada fica intacta; as outras séries também.
        $this->assertSame('NOTA 2/SEC.GOV.PROV.HLA.DLP/2026', $assinada->fresh()->numero_referencia);
        $this->assertSame('DLP/MEMO/001/2026', DocumentoInterno::where('titulo', 'Memorando')->value('numero_referencia'));

        // A série volta ao maior número que resta (a nota assinada).
        $this->assertDatabaseHas('sequencias_documentos', ['chave' => 'NOTA:SEC.GOV.PROV.HLA', 'ano' => 2026, 'ultimo_numero' => 2]);
    }
}
