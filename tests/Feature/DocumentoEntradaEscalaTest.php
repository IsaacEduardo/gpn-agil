<?php

namespace Tests\Feature;

use App\Models\Anexo;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 5 — escala: pesquisa sobre o texto de OCR e limites nas operações que
 * carregam o resultado inteiro em memória.
 */
class DocumentoEntradaEscalaTest extends TestCase
{
    use RefreshDatabase;

    private User $ator;

    private Departamento $dep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionsSeeder::class);

        $role = Role::where('name', 'admin')->firstOrFail();
        $gab = Gabinete::create(['nome' => 'Gabinete A', 'sigla' => 'GABA']);
        $this->dep = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);
        $this->ator = User::factory()->create(['role_id' => $role->id, 'departamento_id' => $this->dep->id]);
        $this->ator->assignRole($role);
    }

    private function doc(int $seq, string $assunto = 'Assunto'): DocumentoEntrada
    {
        return DocumentoEntrada::create([
            'numero_sequencial' => $seq,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => $assunto,
            'departamento_id' => $this->dep->id,
            'user_id' => $this->ator->id,
            'status' => 'registrado',
        ]);
    }

    /**
     * F7 — a pesquisa sobre anexos.texto_extraido tem de continuar a funcionar
     * depois de passar a MATCH...AGAINST em MySQL, incluindo em sqlite (onde a
     * suite corre e o fallback LIKE se mantém).
     */
    public function test_pesquisa_encontra_documento_pelo_texto_de_ocr(): void
    {
        $doc = $this->doc(1, 'Documento com anexo digitalizado');

        Anexo::create([
            'anexavel_type' => DocumentoEntrada::class,
            'anexavel_id' => $doc->id,
            'nome_original' => 'digitalizacao.pdf',
            'caminho_arquivo' => 'fake/digitalizacao.pdf',
            'mime_type' => 'application/pdf',
            'tamanho_bytes' => 1024,
            'ordem' => 1,
            'user_id' => $this->ator->id,
            'texto_extraido' => 'Requerimento de licenciamento comercial da empresa Panguila',
            'ocr_status' => 'CONCLUIDO',
        ]);

        $this->doc(2, 'Documento sem relação');

        $resposta = $this->actingAs($this->ator)
            ->get(route('documentos-entradas.index', ['search' => 'licenciamento', 'tab' => 'todos']));

        $resposta->assertStatus(200);
        $resposta->assertSee('Documento com anexo digitalizado', false);
        $resposta->assertDontSee('Documento sem relação', false);
    }

    public function test_pesquisa_json_encontra_pelo_texto_de_ocr(): void
    {
        $doc = $this->doc(3, 'Ofício digitalizado');

        Anexo::create([
            'anexavel_type' => DocumentoEntrada::class,
            'anexavel_id' => $doc->id,
            'nome_original' => 'oficio.pdf',
            'caminho_arquivo' => 'fake/oficio.pdf',
            'mime_type' => 'application/pdf',
            'tamanho_bytes' => 1024,
            'ordem' => 1,
            'user_id' => $this->ator->id,
            'texto_extraido' => 'Assunto: transferencia de verbas orcamentais',
            'ocr_status' => 'CONCLUIDO',
        ]);

        $this->actingAs($this->ator)
            ->getJson(route('documentos-entradas.search.json', ['q' => 'orcamentais']))
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $doc->id]);
    }

    /** Termos curtos continuam a funcionar (abaixo do tamanho mínimo de token). */
    public function test_pesquisa_com_termo_curto_continua_a_funcionar(): void
    {
        $doc = $this->doc(4, 'Documento com sigla');

        Anexo::create([
            'anexavel_type' => DocumentoEntrada::class,
            'anexavel_id' => $doc->id,
            'nome_original' => 'nota.pdf',
            'caminho_arquivo' => 'fake/nota.pdf',
            'mime_type' => 'application/pdf',
            'tamanho_bytes' => 1024,
            'ordem' => 1,
            'user_id' => $this->ator->id,
            'texto_extraido' => 'Parecer da DN sobre o processo',
            'ocr_status' => 'CONCLUIDO',
        ]);

        $this->actingAs($this->ator)
            ->get(route('documentos-entradas.index', ['search' => 'DN', 'tab' => 'todos']))
            ->assertStatus(200)
            ->assertSee('Documento com sigla', false);
    }

    /** F8 — exportPDF/exportExcel faziam ->get() do resultado filtrado inteiro. */
    public function test_exportacao_acima_do_limite_e_recusada_com_aviso(): void
    {
        config()->set('documentos.limite_exportacao', 2);

        $this->doc(10);
        $this->doc(11);
        $this->doc(12);

        $this->actingAs($this->ator)
            ->get(route('documentos-entradas.export.pdf'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($this->ator)
            ->get(route('documentos-entradas.export.excel'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_exportacao_dentro_do_limite_prossegue(): void
    {
        config()->set('documentos.limite_exportacao', 10);

        $this->doc(20);
        $this->doc(21);

        $this->actingAs($this->ator)
            ->get(route('documentos-entradas.export.excel'))
            ->assertStatus(200);
    }

    /** F8 — 'ids' era JSON sem limite de cardinalidade. */
    public function test_lote_acima_do_limite_e_recusado(): void
    {
        config()->set('documentos.limite_lote', 3);

        $ids = range(1, 50);

        $this->actingAs($this->ator)
            ->postJson(route('documentos-entradas.batch.receber'), ['ids' => json_encode($ids)])
            ->assertStatus(422);

        $this->actingAs($this->ator)
            ->postJson(route('documentos-entradas.batch.encaminhar'), [
                'ids' => json_encode($ids),
                'destino_departamento_id' => $this->dep->id,
            ])
            ->assertStatus(422);
    }
}
