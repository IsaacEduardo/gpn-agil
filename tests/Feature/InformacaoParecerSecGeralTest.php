<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\ModeloDocumento;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentoInternoService;
use App\Services\NumeracaoDocumentoService;
use App\Services\SignatureService;
use App\Support\Sanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * Informação/Parecer (Secretaria Geral): livro próprio, emitida pelos departamentos e pelo
 * gabinete, assinada pelo Chefe de Gabinete; quadro Parecer/Despacho em branco.
 */
class InformacaoParecerSecGeralTest extends TestCase
{
    use RefreshDatabase;

    private Gabinete $sg;

    private Departamento $dlp;

    private Departamento $drpp;

    private User $secretarioGeral;

    private User $tecnicoDlp;

    private User $admin;

    private ModeloDocumento $modelo;

    private int $ano;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ano = NumeracaoDocumentoService::anoCorrente();

        $this->secretarioGeral = User::factory()->create(['name' => 'Anselmo Cristiano José Vasco', 'departamento_id' => null]);
        $this->sg = Gabinete::create([
            'nome' => 'Secretaria Geral', 'sigla' => 'SG', 'codigo_oficios' => 'SEC.GOV.PROV.HLA',
            'responsavel_id' => $this->secretarioGeral->id,
        ]);
        $this->dlp = Departamento::create(['nome' => 'Departamento de Logística e Património', 'sigla' => 'DLP', 'gabinete_id' => $this->sg->id]);
        $this->drpp = Departamento::create(['nome' => 'Departamento de Relações Públicas e Protocolo', 'sigla' => 'DRPP', 'gabinete_id' => $this->sg->id]);
        $this->tecnicoDlp = User::factory()->create(['departamento_id' => $this->dlp->id]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['departamento_id' => $this->dlp->id, 'role_id' => $adminRole->id]);
        $this->admin->assignRole('admin');

        $this->modelo = ModeloDocumento::where('codigo', 'INFORMACAO_PARECER_SEC_GERAL')->firstOrFail();
    }

    private function preview(User $autor, array $extra = []): string
    {
        return $this->actingAs($autor)->postJson(route('documentos-internos.preview'), $extra + [
            'modelo_id' => $this->modelo->id,
            'destinatario_nome' => 'Sua Excelência Senhor',
            'destinatario_cargo' => 'Governador Provincial da Huíla',
            'destinatario_local' => 'Lubango',
        ])->assertOk()->json('content');
    }

    private function criar(User $autor, string $especie = 'Informação/Parecer', array $extra = [], ?string $conteudo = null)
    {
        return $this->actingAs($autor)->post(route('documentos-internos.store'), $extra + [
            'titulo' => 'Desaparecimento de prateleiras',
            'documento_especie_id' => DocumentoEspecie::firstOrCreate(['nome' => $especie], ['ativo' => true])->id,
            'conteudo_final' => $conteudo ?? '<p>Texto</p>',
        ])->assertRedirect();
    }

    public function test_livro_proprio_emitido_por_departamentos_e_pelo_gabinete_sem_colidir_com_oficios(): void
    {
        $tecnicoDrpp = User::factory()->create(['departamento_id' => $this->drpp->id]);

        $this->criar($this->tecnicoDlp);
        $this->criar($this->secretarioGeral); // por omissão, pelo gabinete
        $this->criar($this->tecnicoDlp, 'Ofício');
        $this->criar($tecnicoDrpp);

        $this->assertSame([
            "INFORMAÇÃO 1/SEC.GOV.PROV.HLA.DLP/{$this->ano}",
            "INFORMAÇÃO 2/SEC.GOV.PROV.HLA/{$this->ano}",
            "1/SEC.GOV.PROV.HLA.DLP/{$this->ano}",
            "INFORMAÇÃO 3/SEC.GOV.PROV.HLA.DRPP/{$this->ano}",
        ], DocumentoInterno::orderBy('id')->pluck('numero_referencia')->all());
    }

    public function test_numero_inicial_no_ecra_numeracao(): void
    {
        $this->actingAs($this->admin)->post(route('admin.numeracao.definir'), [
            'ano' => $this->ano, 'tipo' => 'internos', 'emissor' => "dep:{$this->dlp->id}",
            'documento_especie_id' => $this->modelo->documento_especie_id,
            'ultimo_numero' => 11, 'motivo' => 'Última informação em papel: 11',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sequencias_documentos', ['chave' => 'INF:SEC.GOV.PROV.HLA', 'ultimo_numero' => 11]);
        $this->criar($this->tecnicoDlp);
        $this->assertSame("INFORMAÇÃO 12/SEC.GOV.PROV.HLA.DLP/{$this->ano}", DocumentoInterno::latest('id')->value('numero_referencia'));
    }

    public function test_gravado_titulo_sem_identificacao_repetida_quadro_com_numero_data_e_proc(): void
    {
        $html = $this->preview($this->tecnicoDlp, ['numero_processo' => '45/2026']);
        $this->assertStringContainsString("INFORMAÇÃO Nº <span class=\"ref-referencia-titulo\">_____/SEC.GOV.PROV.HLA.DLP/{$this->ano}</span>", $html);

        $this->criar($this->tecnicoDlp, conteudo: $html);
        $doc = DocumentoInterno::latest('id')->first();

        $this->assertStringContainsString("INFORMAÇÃO Nº <span class=\"ref-referencia-titulo\">1/SEC.GOV.PROV.HLA.DLP/{$this->ano}</span>", $doc->conteudo_final);
        $this->assertStringNotContainsString('INFORMAÇÃO Nº <span class="ref-referencia-titulo">INFORMAÇÃO', $doc->conteudo_final);
        $this->assertStringContainsString('class="ref-numero-ordem">1</span>', $doc->conteudo_final);
        $this->assertStringContainsString('Proc. 45/2026', $doc->conteudo_final);
        $this->assertStringContainsString('Data: '.now()->format('d/m/Y'), $doc->conteudo_final);
        $this->assertStringContainsString('Submetemos à consideração superior.', $doc->conteudo_final);
    }

    public function test_proc_vazio_fica_linha_para_preencher_a_mao(): void
    {
        $this->assertStringContainsString('Proc. ________', $this->preview($this->tecnicoDlp));
    }

    public function test_ordens_de_servico_mantem_dois_digitos(): void
    {
        $this->criar($this->secretarioGeral, 'Ordem de Serviço', conteudo: '<p>Nº <span class="ref-numero-ordem">__</span></p>');

        $doc = DocumentoInterno::latest('id')->first();
        $this->assertSame("OS 01/SEC.GOV.PROV.HLA/{$this->ano}", $doc->numero_referencia);
        $this->assertStringContainsString('class="ref-numero-ordem">01</span>', $doc->conteudo_final);
    }

    public function test_assina_o_chefe_de_gabinete_e_a_regra_do_parecer_mantem_se(): void
    {
        $chefeDlp = User::factory()->create(['departamento_id' => $this->dlp->id]);
        $this->dlp->update(['responsavel_id' => $chefeDlp->id]);

        $this->criar($this->tecnicoDlp);
        $doc = DocumentoInterno::latest('id')->first();
        $assinaturas = app(SignatureService::class);

        $this->assertTrue($assinaturas->canSign($doc, $this->secretarioGeral));
        $this->assertFalse($assinaturas->canSign($doc, $chefeDlp));
        $this->assertFalse($assinaturas->canSign($doc, $this->tecnicoDlp)); // o autor não

        // "Parecer" (jurídico) continua: só o autor.
        $this->criar($this->tecnicoDlp, 'Parecer');
        $parecer = DocumentoInterno::latest('id')->first();
        $this->assertTrue($assinaturas->canSign($parecer, $this->tecnicoDlp));
        $this->assertFalse($assinaturas->canSign($parecer, $this->secretarioGeral));
    }

    public function test_modelo_exclusivo_e_quadro_sobrevive_ao_sanitizer(): void
    {
        $service = app(DocumentoInternoService::class);
        $this->assertTrue($service->getTemplatesForUser($this->tecnicoDlp)->contains('id', $this->modelo->id));

        $outroGab = Gabinete::create(['nome' => 'Outro Gabinete']);
        $outro = User::factory()->create(['departamento_id' => Departamento::create(['nome' => 'Dep X', 'sigla' => 'DX', 'gabinete_id' => $outroGab->id])->id]);
        $this->assertFalse($service->getTemplatesForUser($outro)->contains('id', $this->modelo->id));

        $limpo = Sanitizer::clean($this->preview($this->tecnicoDlp));
        $this->assertStringContainsString('padding: 95px 12px 115px 0', $limpo);
        $this->assertStringContainsString('border-right: 1.5px solid #111111', $limpo);
        $this->assertStringContainsString('ref-referencia-titulo', $limpo);
    }
}
