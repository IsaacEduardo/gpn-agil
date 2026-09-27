<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\ModeloDocumento;
use App\Models\User;
use App\Services\NumeracaoDocumentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fase 2: todas as séries (ofícios, ordens de serviço, restantes espécies, livro de entrada)
 * na mesma tabela, com reinício anual na hora de Luanda.
 */
class SeriesNumeracaoTest extends TestCase
{
    use RefreshDatabase;

    private Gabinete $sg;

    private User $secretarioGeral;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secretarioGeral = User::factory()->create(['departamento_id' => null]);
        $this->sg = Gabinete::create([
            'nome' => 'Secretaria Geral', 'sigla' => 'SG', 'codigo_oficios' => 'SEC.GOV.PROV.HLA',
            'responsavel_id' => $this->secretarioGeral->id,
        ]);
        Departamento::create(['nome' => 'Departamento de Logística e Património', 'sigla' => 'DLP', 'gabinete_id' => $this->sg->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function criar(string $especie, string $conteudo = '<p>Texto</p>')
    {
        return $this->actingAs($this->secretarioGeral)->post(route('documentos-internos.store'), [
            'titulo' => $especie,
            'documento_especie_id' => DocumentoEspecie::firstOrCreate(['nome' => $especie], ['ativo' => true])->id,
            'conteudo_final' => $conteudo,
        ])->assertRedirect();
    }

    public function test_ordens_de_servico_tem_serie_propria_sem_colidir_com_os_oficios(): void
    {
        $ano = NumeracaoDocumentoService::anoCorrente();

        $this->criar('Ordem de Serviço');
        $this->criar('Ordem de Serviço');
        $this->criar('Ofício');

        $this->assertSame(
            ["OS 01/SEC.GOV.PROV.HLA/{$ano}", "OS 02/SEC.GOV.PROV.HLA/{$ano}", "1/SEC.GOV.PROV.HLA/{$ano}"],
            DocumentoInterno::orderBy('id')->pluck('numero_referencia')->all()
        );
    }

    public function test_numero_da_ordem_de_servico_e_reservado_e_aplicado_ao_titulo(): void
    {
        $modelo = ModeloDocumento::where('codigo', 'ORDEM_DE_SERVICO_SEC_GERAL')->firstOrFail();

        $preview = $this->actingAs($this->secretarioGeral)->postJson(route('documentos-internos.preview'), [
            'modelo_id' => $modelo->id,
            'numero_ordem' => '99', // já não é editável: ignorado
        ])->assertOk()->json('content');

        $this->assertStringContainsString('class="ref-numero-ordem">__</span>', $preview);
        $this->assertStringContainsString('/SEC.GOV.PROV.HLA/', $preview);
        $this->assertStringNotContainsString('SEC.GER.GOV.PROV.HLA', $preview);

        $this->criar('Ordem de Serviço', $preview);

        $doc = DocumentoInterno::latest('id')->first();
        $this->assertStringContainsString('class="ref-numero-ordem">01</span>', $doc->conteudo_final);
        $this->assertStringNotContainsString('>99<', $doc->conteudo_final);
    }

    public function test_livro_de_entrada_numera_por_ano_sem_reutilizar_apagados(): void
    {
        $numeracao = app(NumeracaoDocumentoService::class);
        $ano = NumeracaoDocumentoService::anoCorrente();

        $this->assertSame(1, $numeracao->reservarEntrada($ano));
        $this->assertSame(2, $numeracao->reservarEntrada($ano));

        // Outro ano: série própria, recomeça em 1.
        $this->assertSame(1, $numeracao->reservarEntrada($ano + 1));
    }

    public function test_serie_nova_de_entradas_arranca_depois_do_maior_existente_incluindo_apagados(): void
    {
        $ano = NumeracaoDocumentoService::anoCorrente();
        $doc = DocumentoEntrada::factory()->create(['ano_referencia' => $ano, 'numero_sequencial' => 41]);
        $doc->delete();

        $this->assertSame(42, app(NumeracaoDocumentoService::class)->reservarEntrada($ano));
    }

    public function test_passagem_de_ano_segue_a_hora_de_luanda(): void
    {
        // 31/12 22:30 UTC = 23:30 em Luanda: ainda 2026.
        Carbon::setTestNow(Carbon::parse('2026-12-31 22:30:00', 'UTC'));
        $this->criar('Ofício');
        $this->assertSame('1/SEC.GOV.PROV.HLA/2026', DocumentoInterno::latest('id')->value('numero_referencia'));

        // 31/12 23:30 UTC = 00:30 de 01/01 em Luanda: já 2027, recomeça em 1.
        Carbon::setTestNow(Carbon::parse('2026-12-31 23:30:00', 'UTC'));
        $this->criar('Ofício');
        $this->assertSame('1/SEC.GOV.PROV.HLA/2027', DocumentoInterno::latest('id')->value('numero_referencia'));
    }

    public function test_reserva_salta_numeros_ja_usados_e_nunca_duplica(): void
    {
        $ano = NumeracaoDocumentoService::anoCorrente();
        $this->criar('Ofício'); // cria a série (último = 1)

        // Referência seguinte já ocupada (ex.: registo importado): a reserva salta-a.
        DocumentoInterno::create([
            'titulo' => 'Importado', 'conteudo_final' => '<p>x</p>', 'status' => DocumentoStatus::RASCUNHO,
            'criado_por' => $this->secretarioGeral->id, 'gabinete_id' => $this->sg->id,
            'documento_especie_id' => DocumentoEspecie::where('nome', 'Ofício')->value('id'),
            'numero_referencia' => "2/SEC.GOV.PROV.HLA/{$ano}",
        ]);

        $this->criar('Ofício');
        $this->assertSame("3/SEC.GOV.PROV.HLA/{$ano}", DocumentoInterno::latest('id')->value('numero_referencia'));
        $this->assertSame(
            DocumentoInterno::count(),
            DocumentoInterno::distinct()->count('numero_referencia')
        );
    }
}
