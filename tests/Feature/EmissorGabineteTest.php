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
use Tests\TestCase;

/**
 * Fase 1: documentos emitidos pelo próprio gabinete (Chefe de Gabinete / Secretário Geral).
 */
class EmissorGabineteTest extends TestCase
{
    use RefreshDatabase;

    private Gabinete $sg;

    private Departamento $dlp;

    private User $secretarioGeral;

    private User $tecnicoDlp;

    private DocumentoEspecie $oficio;

    protected function setUp(): void
    {
        parent::setUp();

        // Como na instituição: o Secretário Geral não pertence a nenhum departamento.
        $this->secretarioGeral = User::factory()->create(['name' => 'Secretário Geral', 'departamento_id' => null]);
        $this->sg = Gabinete::create([
            'nome' => 'Secretaria Geral', 'sigla' => 'SG', 'codigo_oficios' => 'SEC.GOV.PROV.HLA',
            'responsavel_id' => $this->secretarioGeral->id,
        ]);
        $this->dlp = Departamento::create(['nome' => 'Departamento de Logística e Património', 'sigla' => 'DLP', 'gabinete_id' => $this->sg->id]);
        $this->tecnicoDlp = User::factory()->create(['departamento_id' => $this->dlp->id]);
        $this->oficio = DocumentoEspecie::firstOrCreate(['nome' => 'Ofício'], ['ativo' => true]);
    }

    private function criar(User $autor, array $extra = [])
    {
        return $this->actingAs($autor)->post(route('documentos-internos.store'), $extra + [
            'titulo' => 'Ofício',
            'documento_especie_id' => $this->oficio->id,
            'conteudo_final' => '<p>Texto</p>',
        ]);
    }

    public function test_secretario_geral_sem_departamento_ve_o_modelo_do_oficio(): void
    {
        $modelos = app(DocumentoInternoService::class)->getTemplatesForUser($this->secretarioGeral);

        $this->assertTrue($modelos->contains('codigo', 'OFICIO_SEC_GERAL'));
    }

    public function test_oficio_emitido_pelo_gabinete_nao_tem_sufixo_e_partilha_o_livro_com_o_dlp(): void
    {
        $ano = now('Africa/Luanda')->year;

        $this->criar($this->tecnicoDlp)->assertRedirect();
        $this->criar($this->secretarioGeral, ['emissor' => "gab:{$this->sg->id}"])->assertRedirect();
        $this->criar($this->tecnicoDlp)->assertRedirect();

        $refs = DocumentoInterno::orderBy('id')->pluck('numero_referencia')->all();
        $this->assertSame([
            "1/SEC.GOV.PROV.HLA.DLP/{$ano}",
            "2/SEC.GOV.PROV.HLA/{$ano}",
            "3/SEC.GOV.PROV.HLA.DLP/{$ano}",
        ], $refs);

        $doGabinete = DocumentoInterno::where('numero_referencia', "2/SEC.GOV.PROV.HLA/{$ano}")->first();
        $this->assertNull($doGabinete->departamento_id);
        $this->assertSame($this->sg->id, $doGabinete->gabinete_id);
        $this->assertTrue($doGabinete->emitidoPeloGabinete());
    }

    public function test_por_omissao_o_chefe_de_gabinete_emite_pelo_gabinete(): void
    {
        $this->criar($this->secretarioGeral)->assertRedirect();

        $this->assertTrue(DocumentoInterno::latest('id')->first()->emitidoPeloGabinete());
    }

    public function test_secretario_geral_assina_o_que_emite_e_ve_o_na_listagem(): void
    {
        $this->criar($this->secretarioGeral)->assertRedirect();
        $doc = DocumentoInterno::latest('id')->first();

        $this->assertTrue(app(SignatureService::class)->canSign($doc, $this->secretarioGeral));
        $this->assertTrue(DocumentoInterno::accessibleBy($this->secretarioGeral)->whereKey($doc->id)->exists());
        $this->actingAs($this->secretarioGeral)->get(route('documentos-internos.show', $doc))->assertOk();
    }

    public function test_cabecalho_e_referencia_provisoria_do_gabinete(): void
    {
        $this->criar($this->secretarioGeral)->assertRedirect();
        $doc = DocumentoInterno::latest('id')->first();

        $pdf = view('documentos_internos.pdf', ['documentoInterno' => $doc])->render();
        // O cabeçalho mostra o gabinete emissor (em maiúsculas).
        $this->assertMatchesRegularExpression('/doc-header__gabinete[^>]*>\s*SECRETARIA GERAL\s*</', $pdf);

        $html = app(DocumentoInternoService::class)
            ->processarTemplate('{{NOSSA_REFERENCIA}}|{{DEPARTAMENTO_SIGLA}}', null, $this->secretarioGeral, [], $this->sg);
        $this->assertStringContainsString('___/SEC.GOV.PROV.HLA/'.now('Africa/Luanda')->year, $html);
        $this->assertStringContainsString('|SG', $html);
    }

    public function test_tecnico_nao_emite_pelo_gabinete_nem_por_departamento_de_outro_gabinete(): void
    {
        $outroGab = Gabinete::create(['nome' => 'Outro Gabinete']);
        $outroDep = Departamento::create(['nome' => 'Outro Dep', 'sigla' => 'OD', 'gabinete_id' => $outroGab->id]);

        $this->criar($this->tecnicoDlp, ['emissor' => "gab:{$this->sg->id}"])->assertSessionHasErrors('emissor');
        $this->criar($this->tecnicoDlp, ['emissor' => "dep:{$outroDep->id}"])->assertSessionHasErrors('emissor');
        $this->criar($this->tecnicoDlp, ['departamento_id' => $outroDep->id])->assertSessionHasErrors('emissor');
        $this->assertDatabaseCount('documento_internos', 0);
    }

    public function test_chefe_de_gabinete_so_emite_pelos_departamentos_do_seu_gabinete(): void
    {
        $outroGab = Gabinete::create(['nome' => 'Outro Gabinete']);
        $outroDep = Departamento::create(['nome' => 'Outro Dep', 'sigla' => 'OD', 'gabinete_id' => $outroGab->id]);

        $this->criar($this->secretarioGeral, ['emissor' => "dep:{$this->dlp->id}"])->assertRedirect();
        $this->criar($this->secretarioGeral, ['emissor' => "dep:{$outroDep->id}"])->assertSessionHasErrors('emissor');
        $this->criar($this->secretarioGeral, ['emissor' => "gab:{$outroGab->id}"])->assertSessionHasErrors('emissor');
    }

    public function test_utilizador_sem_departamento_nem_gabinete_recebe_erro_e_nao_um_departamento_qualquer(): void
    {
        $semNada = User::factory()->create(['departamento_id' => null]);

        $this->criar($semNada)->assertSessionHasErrors('emissor');
        $this->assertDatabaseCount('documento_internos', 0);
    }

    public function test_formulario_do_chefe_lista_o_gabinete_pre_seleccionado_e_so_os_seus_departamentos(): void
    {
        $outroGab = Gabinete::create(['nome' => 'Gabinete Alheio']);
        Departamento::create(['nome' => 'Dep Alheio', 'sigla' => 'DAL', 'gabinete_id' => $outroGab->id]);

        $this->actingAs($this->secretarioGeral)->get(route('documentos-internos.create'))
            ->assertOk()
            ->assertSee('value="gab:'.$this->sg->id.'" selected', false)
            ->assertSee('DLP — Departamento de Logística e Património')
            ->assertDontSee('Dep Alheio');
    }
}
