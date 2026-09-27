<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\DadosInstituicao;
use App\Models\Departamento;
use App\Models\DocumentoCollabUpdate;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\ModeloDocumento;
use App\Models\User;
use App\Services\DocumentoInternoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 3: editor clássico e colaborativo não se sobrepõem, e os campos vazios reaparecem
 * quando são preenchidos mais tarde.
 */
class CoerenciaEditoresTest extends TestCase
{
    use RefreshDatabase;

    private User $autor;

    private DocumentoInterno $doc;

    protected function setUp(): void
    {
        parent::setUp();

        DadosInstituicao::query()->delete();
        DadosInstituicao::create(['nome_oficial' => 'Governo Provincial da Huíla', 'sigla' => 'GPH', 'cidade' => 'Lubango']);

        $gab = Gabinete::create(['nome' => 'Secretaria Geral', 'sigla' => 'SG', 'codigo_oficios' => 'SEC.GOV.PROV.HLA']);
        $dep = Departamento::create(['nome' => 'Departamento de Logística e Património', 'sigla' => 'DLP', 'gabinete_id' => $gab->id]);
        $this->autor = User::factory()->create(['departamento_id' => $dep->id]);

        $this->doc = DocumentoInterno::create([
            'titulo' => 'Doc',
            'conteudo_final' => '<p>Base</p>',
            'status' => DocumentoStatus::RASCUNHO,
            'criado_por' => $this->autor->id,
            'departamento_id' => $dep->id,
            'documento_especie_id' => DocumentoEspecie::firstOrCreate(['nome' => 'Memorando'], ['ativo' => true])->id,
            'numero_referencia' => 'COER/001',
        ]);
    }

    private function gravarClassico(string $conteudo, array $extra = []): void
    {
        $this->actingAs($this->autor)->put(route('documentos-internos.update', $this->doc), $extra + [
            'titulo' => 'Doc',
            'conteudo_final' => $conteudo,
        ])->assertRedirect(route('documentos-internos.index'));
    }

    public function test_gravacao_classica_descarta_o_estado_colaborativo(): void
    {
        DocumentoCollabUpdate::create([
            'documento_interno_id' => $this->doc->id, 'user_id' => $this->autor->id,
            'update' => base64_encode('estado-antigo'), 'is_snapshot' => false, 'created_at' => now(),
        ]);

        $this->gravarClassico('<p>Editado no clássico</p>');

        $this->assertDatabaseMissing('documento_collab_updates', ['documento_interno_id' => $this->doc->id]);
        $this->assertSame(1, $this->doc->fresh()->revisao_classica);
    }

    public function test_sessao_colaborativa_aberta_antes_da_gravacao_classica_e_recusada(): void
    {
        $revisaoDaSessao = $this->actingAs($this->autor)
            ->getJson(route('documentos-internos.collab.state', $this->doc))
            ->assertOk()->json('revisao_classica');

        $this->gravarClassico('<p>Editado no clássico</p>');

        $this->actingAs($this->autor)->postJson(route('documentos-internos.collab.sync', $this->doc), [
            'update' => base64_encode('delta'), 'html' => '<p>Sessão antiga</p>', 'revisao' => $revisaoDaSessao,
        ])->assertStatus(409)->assertJson(['versao_desactualizada' => true]);

        $this->actingAs($this->autor)->postJson(route('documentos-internos.collab.checkpoint', $this->doc), [
            'html' => '<p>Sessão antiga</p>', 'revisao' => $revisaoDaSessao,
        ])->assertStatus(409);

        $this->assertSame('<p>Editado no clássico</p>', $this->doc->fresh()->conteudo_final);
        $this->assertDatabaseMissing('documento_collab_updates', ['documento_interno_id' => $this->doc->id]);
    }

    public function test_sessao_actual_continua_a_gravar(): void
    {
        $this->gravarClassico('<p>Editado no clássico</p>');
        $revisao = $this->actingAs($this->autor)->getJson(route('documentos-internos.collab.state', $this->doc))->json('revisao_classica');

        $this->actingAs($this->autor)->postJson(route('documentos-internos.collab.sync', $this->doc), [
            'update' => base64_encode('delta'), 'html' => '<p>Sessão nova</p>', 'revisao' => $revisao,
        ])->assertOk();

        // O autosave passa pelo Sanitizer, que codifica os acentos em entidades.
        $this->assertStringContainsString('Sess&atilde;o nova', $this->doc->fresh()->conteudo_final);
    }

    public function test_cargo_preenchido_na_edicao_aparece_no_pdf(): void
    {
        $service = app(DocumentoInternoService::class);
        $oficio = ModeloDocumento::where('codigo', 'OFICIO_SEC_GERAL')->firstOrFail();
        $campos = ['titulo' => 'Remessa', 'destinatario_nome' => 'RODRIGUES JAMBA', 'destinatario_orgao' => 'GPH'];
        $html = $service->prepararCamposVinculados(
            $service->processarTemplate($oficio->conteudo, null, $this->autor, $campos),
            $campos
        );
        $this->doc->update(['conteudo_final' => $html]);

        $pdfAntes = view('documentos_internos.pdf', ['documentoInterno' => $this->doc->fresh()])->render();
        $this->assertStringNotContainsString('[Cargo]', $pdfAntes);

        // Criado sem Cargo; editado depois com Cargo.
        $this->gravarClassico($this->doc->fresh()->conteudo_final, $campos + ['destinatario_cargo' => 'Director Provincial']);

        $pdfDepois = view('documentos_internos.pdf', ['documentoInterno' => $this->doc->fresh()])->render();
        $this->assertStringContainsString('Director Provincial', $pdfDepois);
        $this->assertStringNotContainsString('[Cargo]', $pdfDepois);
    }
}
