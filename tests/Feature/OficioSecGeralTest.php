<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\ModeloDocumento;
use App\Models\User;
use App\Services\DocumentoInternoService;
use App\Services\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OficioSecGeralTest extends TestCase
{
    use RefreshDatabase;

    private Gabinete $secGeral;

    private Departamento $dlp;

    private Departamento $drpp;

    private User $secretarioGeral;

    private User $tecnicoDlp;

    private DocumentoEspecie $oficio;

    private ModeloDocumento $modelo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secretarioGeral = User::factory()->create(['name' => 'Anselmo Cristiano José Vasco']);
        $this->secGeral = Gabinete::create([
            'nome' => 'Secretaria Geral',
            'sigla' => 'SG',
            'codigo_oficios' => 'SEC.GOV.PROV.HLA',
            'responsavel_id' => $this->secretarioGeral->id,
        ]);
        $this->dlp = Departamento::create(['nome' => 'Departamento de Logística e Património', 'sigla' => 'DLP', 'gabinete_id' => $this->secGeral->id]);
        $this->drpp = Departamento::create(['nome' => 'Departamento de Relações Públicas e Protocolo', 'sigla' => 'DRPP', 'gabinete_id' => $this->secGeral->id]);
        $this->secretarioGeral->update(['departamento_id' => $this->dlp->id]);

        $this->tecnicoDlp = User::factory()->create(['departamento_id' => $this->dlp->id]);

        $this->oficio = DocumentoEspecie::firstOrCreate(['nome' => 'Ofício'], ['ativo' => true]);
        $this->modelo = ModeloDocumento::where('codigo', 'OFICIO_SEC_GERAL')->firstOrFail();
    }

    private function criarOficio(User $autor, array $extra = [])
    {
        return $this->actingAs($autor)->post(route('documentos-internos.store'), array_merge([
            'titulo' => 'Remessa de guias de marcha',
            'documento_especie_id' => $this->oficio->id,
            'modelo_documento_id' => $this->modelo->id,
            'conteudo_final' => '<p>Ofício</p>',
        ], $extra));
    }

    private function ultimaReferencia(): string
    {
        return DocumentoInterno::latest('id')->value('numero_referencia');
    }

    public function test_referencia_do_oficio_usa_codigo_do_gabinete_e_sigla_do_departamento(): void
    {
        $this->criarOficio($this->tecnicoDlp)->assertRedirect();

        $this->assertSame('1/SEC.GOV.PROV.HLA.DLP/'.now()->year, $this->ultimaReferencia());
    }

    public function test_departamentos_do_mesmo_gabinete_partilham_a_sequencia(): void
    {
        $tecnicoDrpp = User::factory()->create(['departamento_id' => $this->drpp->id]);

        $this->criarOficio($this->tecnicoDlp);
        $this->criarOficio($tecnicoDrpp);

        $ano = now()->year;
        $this->assertDatabaseHas('documento_internos', ['numero_referencia' => "1/SEC.GOV.PROV.HLA.DLP/{$ano}"]);
        $this->assertDatabaseHas('documento_internos', ['numero_referencia' => "2/SEC.GOV.PROV.HLA.DRPP/{$ano}"]);
    }

    public function test_outras_especies_nao_consomem_a_sequencia_dos_oficios(): void
    {
        $memo = DocumentoEspecie::firstOrCreate(['nome' => 'Memorando'], ['ativo' => true]);

        $this->criarOficio($this->tecnicoDlp, ['documento_especie_id' => $memo->id]);
        $this->assertSame('DLP/MEMO/001/'.now()->year, $this->ultimaReferencia());

        $this->criarOficio($this->tecnicoDlp);
        $this->assertSame('1/SEC.GOV.PROV.HLA.DLP/'.now()->year, $this->ultimaReferencia());
    }

    public function test_gabinete_sem_codigo_mantem_o_formato_antigo(): void
    {
        $this->secGeral->update(['codigo_oficios' => null]);

        $this->criarOficio($this->tecnicoDlp);

        $this->assertSame('DLP/OF/001/'.now()->year, $this->ultimaReferencia());
    }

    public function test_sequencia_arranca_do_maior_numero_existente_sem_duplicar(): void
    {
        $ano = now()->year;
        DocumentoInterno::factory()->create([
            'numero_referencia' => "584/SEC.GOV.PROV.HLA.DRPP/{$ano}",
            'departamento_id' => $this->drpp->id,
            'documento_especie_id' => $this->oficio->id,
        ]);

        $this->criarOficio($this->tecnicoDlp);
        $this->criarOficio($this->tecnicoDlp);

        $this->assertDatabaseHas('documento_internos', ['numero_referencia' => "585/SEC.GOV.PROV.HLA.DLP/{$ano}"]);
        $this->assertDatabaseHas('documento_internos', ['numero_referencia' => "586/SEC.GOV.PROV.HLA.DLP/{$ano}"]);
    }

    public function test_sigla_do_departamento_vem_do_documento_e_nao_do_utilizador(): void
    {
        $service = app(DocumentoInternoService::class);

        $html = $service->processarTemplate('{{DEPARTAMENTO_SIGLA}}|{{NOSSA_REFERENCIA}}', null, $this->tecnicoDlp, [], $this->drpp);

        $this->assertStringStartsWith('DRPP|', $html);
        $this->assertStringContainsString('___/SEC.GOV.PROV.HLA.DRPP/'.now()->year, $html);
        $this->assertSame('DLP', trim($service->processarTemplate('{{DEPARTAMENTO_SIGLA}}', null, $this->tecnicoDlp)));
    }

    public function test_referencia_no_corpo_gravado_e_igual_a_numero_referencia(): void
    {
        $preview = $this->actingAs($this->tecnicoDlp)->postJson(route('documentos-internos.preview'), [
            'modelo_id' => $this->modelo->id,
            'titulo' => 'Remessa de guias de marcha',
            'destinatario_orgao' => 'Gabinete Provincial de Recursos Humanos/GPH',
        ])->assertOk()->json('content');

        $this->assertStringContainsString('___/SEC.GOV.PROV.HLA.DLP/', $preview);

        $this->criarOficio($this->tecnicoDlp, ['conteudo_final' => $preview]);

        $doc = DocumentoInterno::latest('id')->first();
        $this->assertStringContainsString('>'.$doc->numero_referencia.'</span>', $doc->conteudo_final);
        $this->assertStringNotContainsString('___/', $doc->conteudo_final);
        $this->assertStringContainsString('ref-nossa-referencia', \App\Support\Sanitizer::clean($doc->conteudo_final));
    }

    public function test_modelo_visivel_apenas_para_a_secretaria_geral(): void
    {
        $service = app(DocumentoInternoService::class);
        $this->assertTrue($service->getTemplatesForUser($this->tecnicoDlp)->contains('id', $this->modelo->id));

        $outroGab = Gabinete::create(['nome' => 'Gabinete de Infraestruturas', 'sigla' => 'DINF']);
        $outroDep = Departamento::create(['nome' => 'Departamento de Obras', 'sigla' => 'DOB', 'gabinete_id' => $outroGab->id]);
        $outro = User::factory()->create(['departamento_id' => $outroDep->id]);

        $modelos = $service->getTemplatesForUser($outro);
        $this->assertFalse($modelos->contains('id', $this->modelo->id));
        $this->assertFalse($modelos->contains('codigo', 'ORDEM_DE_SERVICO_SEC_GERAL'));
    }

    public function test_so_o_responsavel_do_gabinete_pode_assinar(): void
    {
        $this->criarOficio($this->tecnicoDlp);
        $doc = DocumentoInterno::latest('id')->first();

        $chefeDlp = User::factory()->create(['departamento_id' => $this->dlp->id]);
        $chefeDlp->assignRole(Role::firstOrCreate(['name' => 'chefe-departamento', 'guard_name' => 'web']));

        $assinaturas = app(SignatureService::class);
        $this->assertTrue($assinaturas->canSign($doc, $this->secretarioGeral));
        $this->assertFalse($assinaturas->canSign($doc, $chefeDlp));
    }

    public function test_conteudo_processado_tem_uma_unica_linha_de_datacao(): void
    {
        $html = app(DocumentoInternoService::class)
            ->processarTemplate($this->modelo->conteudo, null, $this->secretarioGeral);

        $this->assertSame(1, substr_count($html, ', no Lubango, aos') + substr_count($html, ', no Sede, aos'));
        $this->assertStringNotContainsString(', em ', $html);
        $this->assertStringContainsString('SECRETARIA GERAL DO ', $html);
        $this->assertStringContainsString('O Secretário Geral', $html);
        $this->assertStringContainsString('Anselmo Cristiano José Vasco', $html);
        $this->assertDoesNotMatchRegularExpression('/\{\{.*?\}\}/', $html);
    }

    public function test_validacao_do_codigo_de_oficios_no_gabinete(): void
    {
        $admin = User::factory()->create();
        $roleAdmin = \App\Models\Role::firstOrCreate(['name' => 'admin']);
        $admin->update(['role_id' => $roleAdmin->id]);

        $this->actingAs($admin)->post(route('gabinetes.store'), [
            'nome' => 'Gabinete Válido',
            'codigo_oficios' => 'gab.prov.hla.',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('gabinetes', ['nome' => 'Gabinete Válido', 'codigo_oficios' => 'GAB.PROV.HLA']);

        $this->actingAs($admin)->post(route('gabinetes.store'), [
            'nome' => 'Gabinete Inválido',
            'codigo_oficios' => 'sec gov',
        ])->assertSessionHasErrors('codigo_oficios');

        $this->actingAs($admin)->post(route('gabinetes.store'), [
            'nome' => 'Gabinete Repetido',
            'codigo_oficios' => 'SEC.GOV.PROV.HLA',
        ])->assertSessionHasErrors('codigo_oficios');
    }
}
