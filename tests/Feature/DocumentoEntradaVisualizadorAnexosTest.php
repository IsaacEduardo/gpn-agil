<?php

namespace Tests\Feature;

use App\Models\Anexo;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Documento com vários ficheiros: o visualizador abre no principal e troca para
 * qualquer anexo, e remover um anexo não pode destruir o original digitalizado
 * nem o ficheiro de que outro anexo depende.
 */
class DocumentoEntradaVisualizadorAnexosTest extends TestCase
{
    use RefreshDatabase;

    private User $autor;

    private Departamento $dep;

    private string $disco;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        Notification::fake();

        // O disco dos documentos é `private` por omissão e o phpunit.xml não o
        // altera; o `public` é o do legado, que o controller também limpa.
        $this->disco = config('filesystems.docs_disk');
        Storage::fake($this->disco);
        Storage::fake('public');

        $roleUser = Role::where('name', 'user')->firstOrFail();
        $gab = Gabinete::create(['nome' => 'Gabinete A', 'sigla' => 'GABA']);
        $this->dep = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);
        $this->autor = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->dep->id]);
        $this->autor->assignRole($roleUser);
    }

    private function documento(?string $arquivoCaminho = null): DocumentoEntrada
    {
        return DocumentoEntrada::create([
            'numero_sequencial' => 1,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now()->subDay(),
            'classificacao_especie' => 'Ofício',
            'assunto' => 'Pedido de parecer',
            'departamento_id' => $this->dep->id,
            'user_id' => $this->autor->id,
            'status' => 'registrado',
            'arquivo_caminho' => $arquivoCaminho,
        ]);
    }

    private function ficheiro(string $caminho): string
    {
        Storage::disk($this->disco)->put($caminho, '%PDF-1.4 conteúdo de teste');

        return $caminho;
    }

    private function anexo(DocumentoEntrada $doc, string $caminho, string $nome, string $mime, int $ordem): Anexo
    {
        return $doc->anexos()->create([
            'nome_original' => $nome,
            'caminho_arquivo' => $caminho,
            'mime_type' => $mime,
            'tamanho_bytes' => 1024,
            'ordem' => $ordem,
            'user_id' => $this->autor->id,
            'ocr_status' => 'NAO_APLICAVEL',
        ]);
    }

    /**
     * Como o registo grava: o caminho do 1.º anexo é copiado para arquivo_caminho.
     *
     * @return array{0: DocumentoEntrada, 1: Anexo, 2: Anexo, 3: Anexo}
     */
    private function documentoRegistadoComTresAnexos(): array
    {
        $doc = $this->documento($this->ficheiro('docs/001/anexos/oficio.pdf'));
        $a1 = $this->anexo($doc, 'docs/001/anexos/oficio.pdf', 'oficio.pdf', 'application/pdf', 1);
        $a2 = $this->anexo($doc, $this->ficheiro('docs/001/anexos/foto.jpg'), 'foto.jpg', 'image/jpeg', 2);
        $a3 = $this->anexo($doc, $this->ficheiro('docs/001/anexos/anexo-b.pdf'), 'anexo-b.pdf', 'application/pdf', 3);

        return [$doc, $a1, $a2, $a3];
    }

    /** A sequência que o show entrega ao script do visualizador. */
    private function ficheirosDoVisualizador(string $html): array
    {
        $this->assertSame(1, preg_match('/ficheiros: (\[.*?\]),\s*\}\);/s', $html, $m), 'Configuração do visualizador não encontrada.');

        return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_show_entrega_todos_os_ficheiros_por_ordem_e_o_principal_uma_so_vez(): void
    {
        [$doc, $a1, $a2, $a3] = $this->documentoRegistadoComTresAnexos();

        $html = $this->actingAs($this->autor)->get(route('documentos-entradas.show', $doc))
            ->assertOk()
            ->getContent();

        $ficheiros = $this->ficheirosDoVisualizador($html);

        $this->assertSame([
            route('documentos-entradas.arquivo.download', $doc),
            route('documentos-entradas.anexos.download', [$doc, $a2]),
            route('documentos-entradas.anexos.download', [$doc, $a3]),
        ], array_column($ficheiros, 'url'));
        $this->assertSame(['pdf', 'imagem', 'pdf'], array_column($ficheiros, 'tipo'));
        $this->assertSame('oficio.pdf', $ficheiros[0]['nome']);

        // Cada anexo da lista tem a sua ação "Visualizar"; o 1.º é o principal.
        foreach ([0, 1, 2] as $indice) {
            $this->assertStringContainsString('data-doc-viewer-ver="'.$indice.'"', $html);
        }
        $this->assertSame(1, substr_count($html, '>Principal</span>'));
        $this->assertStringContainsString('_fichSeguinte', $html);
    }

    public function test_principal_substituido_na_edicao_aparece_na_lista_mesmo_sem_ser_anexo(): void
    {
        $doc = $this->documento($this->ficheiro('docs/001/novo-principal.pdf'));
        $this->anexo($doc, $this->ficheiro('docs/001/anexos/antigo.pdf'), 'antigo.pdf', 'application/pdf', 1);

        $html = $this->actingAs($this->autor)->get(route('documentos-entradas.show', $doc))
            ->assertOk()
            ->getContent();

        $ficheiros = $this->ficheirosDoVisualizador($html);

        $this->assertCount(2, $ficheiros);
        $this->assertSame('Ficheiro principal', $ficheiros[0]['nome']);
        $this->assertSame(route('documentos-entradas.arquivo.download', $doc), $ficheiros[0]['url']);
        $this->assertSame(1, substr_count($html, '>Principal</span>'));
        // O contador e a lista falam do mesmo número de ficheiros.
        $this->assertSame(2, $doc->fresh()->totalDeFicheiros());
        $this->assertSame(2, substr_count($html, 'data-doc-viewer-item="'));
    }

    public function test_documento_sem_principal_mas_com_anexos_abre_o_primeiro_anexo(): void
    {
        $doc = $this->documento();
        $an = $this->anexo($doc, $this->ficheiro('docs/001/anexos/so-anexo.pdf'), 'so-anexo.pdf', 'application/pdf', 1);

        $html = $this->actingAs($this->autor)->get(route('documentos-entradas.show', $doc))
            ->assertOk()
            ->assertDontSee('Sem documento digitalizado')
            ->getContent();

        $ficheiros = $this->ficheirosDoVisualizador($html);
        $this->assertSame([route('documentos-entradas.anexos.download', [$doc, $an])], array_column($ficheiros, 'url'));
    }

    public function test_documento_com_um_so_ficheiro_nao_mostra_anterior_e_seguinte(): void
    {
        $doc = $this->documento($this->ficheiro('docs/001/anexos/unico.pdf'));
        $this->anexo($doc, 'docs/001/anexos/unico.pdf', 'unico.pdf', 'application/pdf', 1);

        $this->actingAs($this->autor)->get(route('documentos-entradas.show', $doc))
            ->assertOk()
            ->assertDontSee('_fichSeguinte', false)
            ->assertDontSee('_fichAnterior', false);
    }

    public function test_remover_o_anexo_que_e_o_principal_e_recusado(): void
    {
        [$doc, $a1] = $this->documentoRegistadoComTresAnexos();

        $this->actingAs($this->autor)
            ->delete(route('documentos-entradas.anexos.destroy', [$doc, $a1]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($a1);
        Storage::disk($this->disco)->assertExists('docs/001/anexos/oficio.pdf');
        $this->assertSame('docs/001/anexos/oficio.pdf', $doc->fresh()->arquivo_caminho);
        $this->actingAs($this->autor)
            ->get(route('documentos-entradas.arquivo.download', $doc))
            ->assertOk();
    }

    public function test_remover_um_anexo_que_nao_e_o_principal_apaga_o_registo_e_o_ficheiro(): void
    {
        [$doc, , $a2] = $this->documentoRegistadoComTresAnexos();

        $this->actingAs($this->autor)
            ->delete(route('documentos-entradas.anexos.destroy', [$doc, $a2]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted($a2);
        Storage::disk($this->disco)->assertMissing('docs/001/anexos/foto.jpg');
        $this->assertSame('docs/001/anexos/oficio.pdf', $doc->fresh()->arquivo_caminho);
    }

    public function test_remover_um_anexo_com_ficheiro_partilhado_mantem_o_ficheiro_do_outro(): void
    {
        // O que guardarArquivoSubstituido deixa: dois anexos sobre o mesmo ficheiro.
        $doc = $this->documento($this->ficheiro('docs/001/novo-principal.pdf'));
        $caminho = $this->ficheiro('docs/001/anexos/oficio.pdf');
        $original = $this->anexo($doc, $caminho, 'oficio.pdf', 'application/pdf', 1);
        $copia = $this->anexo($doc, $caminho, 'oficio.pdf', 'application/pdf', 2);

        $this->actingAs($this->autor)
            ->delete(route('documentos-entradas.anexos.destroy', [$doc, $copia]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSoftDeleted($copia);
        Storage::disk($this->disco)->assertExists($caminho);
        $this->actingAs($this->autor)
            ->get(route('documentos-entradas.anexos.download', [$doc, $original]))
            ->assertOk();
    }

    public function test_edicao_nao_tem_formularios_dentro_do_formulario_de_atualizacao(): void
    {
        [$doc, $a1, $a2] = $this->documentoRegistadoComTresAnexos();

        $html = $this->actingAs($this->autor)->get(route('documentos-entradas.edit', $doc))
            ->assertOk()
            ->getContent();

        // Um <form> aninhado é ignorado pelo browser: o caixote do 1.º anexo e o
        // "Guardar" passavam a enviar _method=DELETE para o próprio documento.
        $abertura = strpos($html, 'action="'.route('documentos-entradas.update', $doc).'"');
        $this->assertNotFalse($abertura);
        $fecho = strpos($html, '</form>', $abertura);
        $this->assertNotFalse($fecho);
        $this->assertStringNotContainsString('<form', substr($html, $abertura, $fecho - $abertura));

        // Os anexos removíveis ligam-se ao seu formulário pelo atributo `form`;
        // o principal não tem como ser removido daqui.
        $this->assertStringContainsString('form="remover-anexo-'.$a2->id.'"', $html);
        $this->assertStringContainsString('id="remover-anexo-'.$a2->id.'"', $html);
        $this->assertStringNotContainsString('form="remover-anexo-'.$a1->id.'"', $html);
    }

    public function test_expandir_e_um_botao_de_ecra_inteiro_com_estado(): void
    {
        [$doc] = $this->documentoRegistadoComTresAnexos();

        $html = $this->actingAs($this->autor)->get(route('documentos-entradas.show', $doc))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<button[^>]*id="docViewer_'.$doc->id.'_expandir"[^>]*aria-pressed="false"[^>]*>.*?Expandir/s',
            $html
        );
        // O handler genérico antigo só mexia na altura e competia com este.
        $this->assertStringNotContainsString('doc-viewer-zoom', $html);
    }

    public function test_area_do_documento_nao_tem_limites_de_altura_escritos_no_html(): void
    {
        [$doc] = $this->documentoRegistadoComTresAnexos();

        $html = $this->actingAs($this->autor)->get(route('documentos-entradas.show', $doc))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, preg_match('/<div[^>]*id="docViewer_'.$doc->id.'_frame"[^>]*>/', $html, $m));
        // Inline, sobrepunham-se ao CSS: a altura ficava entre 550 e 750px,
        // expandida ou não.
        $this->assertStringNotContainsString('max-height', $m[0]);
        $this->assertStringNotContainsString('min-height', $m[0]);
    }
}
