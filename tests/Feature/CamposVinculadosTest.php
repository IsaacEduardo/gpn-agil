<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\DadosInstituicao;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\ModeloDocumento;
use App\Models\User;
use App\Services\DocumentoInternoService;
use App\Support\CamposVinculados;
use App\Support\Sanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CamposVinculadosTest extends TestCase
{
    use RefreshDatabase;

    private User $autor;

    private ModeloDocumento $oficio;

    private DocumentoEspecie $especie;

    protected function setUp(): void
    {
        parent::setUp();

        DadosInstituicao::query()->delete();
        DadosInstituicao::create(['nome_oficial' => 'Governo Provincial da Huíla', 'sigla' => 'GPH', 'cidade' => 'Lubango']);

        $gab = Gabinete::create(['nome' => 'Secretaria Geral', 'sigla' => 'SG', 'codigo_oficios' => 'SEC.GOV.PROV.HLA']);
        $dep = Departamento::create(['nome' => 'Departamento de Logística e Património', 'sigla' => 'DLP', 'gabinete_id' => $gab->id]);
        $this->autor = User::factory()->create(['departamento_id' => $dep->id]);

        $this->oficio = ModeloDocumento::where('codigo', 'OFICIO_SEC_GERAL')->firstOrFail();
        $this->especie = DocumentoEspecie::findOrFail($this->oficio->documento_especie_id);
    }

    private function processar(string $template, array $extras = []): string
    {
        return app(DocumentoInternoService::class)->processarTemplate($template, null, $this->autor, $extras);
    }

    private function gravar(array $campos, string $conteudo)
    {
        return $this->actingAs($this->autor)->post(route('documentos-internos.store'), $campos + [
            'titulo' => 'Remessa de guias de marcha',
            'documento_especie_id' => $this->especie->id,
            'modelo_documento_id' => $this->oficio->id,
            'conteudo_final' => $conteudo,
        ]);
    }

    public function test_valores_preenchidos_saem_em_marcadores(): void
    {
        $html = $this->processar('{{ASSUNTO}}|{{DESTINATARIO_NOME}}', [
            'titulo' => 'Remessa de guias de marcha',
            'destinatario_nome' => 'RODRIGUES JAMBA',
        ]);

        $this->assertStringContainsString('class="campo-vinculado campo-assunto">Remessa de guias de marcha<', $html);
        $this->assertStringContainsString('class="campo-vinculado campo-nome">RODRIGUES JAMBA<', $html);
    }

    public function test_campo_vazio_tem_texto_guia_e_nunca_fica_vazio(): void
    {
        $html = $this->processar('{{DESTINATARIO_CARGO}}', ['destinatario_cargo' => '']);

        $this->assertStringContainsString('class="campo-vinculado campo-cargo campo-vazio">[Cargo]</span>', $html);
        $this->assertStringNotContainsString('></span>', $html);
    }

    public function test_valores_sao_escapados(): void
    {
        $html = $this->processar('{{DESTINATARIO_ORGAO}}', ['destinatario_orgao' => '<script>x</script> & Cª']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;x&lt;/script&gt; &amp; Cª', $html);
    }

    public function test_gravar_resincroniza_com_os_valores_submetidos_sem_javascript(): void
    {
        // Modelo carregado com os campos ainda vazios; o utilizador preenche-os depois.
        $carregado = $this->processar($this->oficio->conteudo);

        $this->gravar([
            'titulo' => 'Remessa de guias de marcha',
            'destinatario_nome' => 'RODRIGUES JAMBA',
            'destinatario_cargo' => 'Director do Gabinete de Recursos Humanos',
            'destinatario_orgao' => 'Gabinete Provincial de Recursos Humanos/GPH',
            'destinatario_local' => 'Lubango',
        ], $carregado)->assertRedirect();

        $html = DocumentoInterno::latest('id')->value('conteudo_final');
        $this->assertStringContainsString('campo-assunto">Remessa de guias de marcha<', $html);
        $this->assertStringContainsString('campo-nome">RODRIGUES JAMBA<', $html);
        $this->assertStringContainsString('campo-cargo">Director do Gabinete de Recursos Humanos<', $html);
        $this->assertStringContainsString('campo-orgao">Gabinete Provincial de Recursos Humanos/GPH<', $html);
        $this->assertStringContainsString('campo-local">Lubango<', $html);
        $this->assertStringNotContainsString('campo-vazio', $html);
    }

    public function test_vazios_ficam_gravados_mas_a_apresentacao_retira_os_e_o_br_seguinte(): void
    {
        $carregado = $this->processar($this->oficio->conteudo);

        $this->gravar([
            'destinatario_nome' => 'RODRIGUES JAMBA',
            'destinatario_orgao' => 'Gabinete Provincial de Recursos Humanos/GPH',
        ], $carregado);

        $doc = DocumentoInterno::latest('id')->first();
        // Gravado: o marcador vazio fica, para o campo poder ser preenchido mais tarde.
        $this->assertStringContainsString('campo-cargo campo-vazio">[Cargo]</span>', $doc->conteudo_final);

        // Apresentado: sem "[Cargo]" nem linha em branco.
        $html = $doc->conteudoParaApresentacao();
        $this->assertStringNotContainsString('[Cargo]', $html);
        $this->assertStringNotContainsString('campo-cargo', $html);
        $this->assertMatchesRegularExpression('/RODRIGUES JAMBA<\/span><br>\s*<span class="campo-vinculado campo-orgao">/', $html);
    }

    public function test_local_vazio_usa_a_cidade_da_instituicao(): void
    {
        $html = $this->processar('{{DESTINATARIO_LOCAL}}', ['destinatario_local' => '']);
        $this->assertStringContainsString('campo-local">Lubango<', $html);

        $this->gravar(['destinatario_local' => ''], $html);
        $gravado = DocumentoInterno::latest('id')->value('conteudo_final');
        $this->assertStringContainsString('campo-local">Lubango<', $gravado);
        $this->assertStringNotContainsString('Moçâmedes', $gravado);

        $this->actingAs($this->autor)->get(route('documentos-internos.create'))
            ->assertOk()
            ->assertSee('name="destinatario_local" value="Lubango"', false)
            ->assertDontSee('value="Moçâmedes"', false);
    }

    public function test_marcadores_sobrevivem_ao_sanitizer(): void
    {
        $html = Sanitizer::clean($this->processar('<p>{{DESTINATARIO_NOME}}</p>', ['destinatario_nome' => 'RODRIGUES JAMBA']));

        $this->assertStringContainsString('class="campo-vinculado campo-nome"', $html);
    }

    public function test_valor_do_campo_prevalece_sobre_edicao_manual_do_marcador(): void
    {
        $alterado = '<p><span class="campo-vinculado campo-nome">Texto escrito à mão</span></p>';

        $this->gravar(['destinatario_nome' => 'RODRIGUES JAMBA'], $alterado);

        $html = DocumentoInterno::latest('id')->value('conteudo_final');
        $this->assertStringContainsString('campo-nome">RODRIGUES JAMBA<', $html);
        $this->assertStringNotContainsString('Texto escrito à mão', $html);
    }

    public function test_edicao_grava_o_destinatario_e_sincroniza_o_corpo(): void
    {
        $doc = DocumentoInterno::factory()->create([
            'criado_por' => $this->autor->id,
            'departamento_id' => $this->autor->departamento_id,
            'documento_especie_id' => $this->especie->id,
            'status' => DocumentoStatus::RASCUNHO,
            'conteudo_final' => '<p><span class="campo-vinculado campo-cargo">Chefe</span><br>Corpo</p>',
        ]);

        $this->actingAs($this->autor)->put(route('documentos-internos.update', $doc), [
            'titulo' => 'Novo assunto',
            'conteudo_final' => $doc->conteudo_final,
            'destinatario_cargo' => 'Director Provincial',
        ])->assertRedirect();

        $doc->refresh();
        $this->assertSame('Director Provincial', $doc->destinatario_cargo);
        $this->assertStringContainsString('campo-cargo">Director Provincial<', $doc->conteudo_final);
        $this->assertStringContainsString('Corpo', $doc->conteudo_final);
    }

    public function test_classes_do_js_sao_as_mesmas_do_servidor(): void
    {
        $js = CamposVinculados::paraJs('Lubango');

        $this->assertSame('campo-vazio', $js['classeVazio']);
        $this->assertSame('campo-assunto', $js['campos']['titulo']['classe']);
        $this->assertSame('Lubango', $js['campos']['destinatario_local']['padrao']);
    }
}
