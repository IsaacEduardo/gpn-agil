<?php

namespace Tests\Feature;

use App\Jobs\ProcessarOcrAnexo;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Digitalização direta por scanner — percurso do PDF do agente até ao arquivo.
 *
 * A fixture `digitalizacao-3-paginas.pdf` é produzida pelo próprio agente
 * (agent/webscan_bridge/pdf.py) a partir do adaptador mock. Se o montador de PDF
 * regredir, estes testes apanham-no antes de chegar a um posto de trabalho.
 */
class DocumentoEntradaDigitalizacaoTest extends TestCase
{
    use RefreshDatabase;

    private User $balcao;

    private Departamento $dep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
        Storage::fake('public');
        Notification::fake();

        $roleUser = Role::where('name', 'user')->firstOrFail();
        $gab = Gabinete::create(['nome' => 'Gabinete A', 'sigla' => 'GABA']);
        $this->dep = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);
        $this->balcao = User::factory()->create(['role_id' => $roleUser->id, 'departamento_id' => $this->dep->id]);
        $this->balcao->assignRole($roleUser);

        DocumentoEspecie::firstOrCreate(['nome' => 'Ofício'], ['ativo' => true, 'ordem' => 1]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'classificacao_especie' => 'Ofício',
            'assunto' => 'Correspondência digitalizada no balcão',
            'departamento_id' => $this->dep->id,
        ], $overrides);
    }

    private function fixturePath(): string
    {
        return base_path('tests/fixtures/digitalizacao-3-paginas.pdf');
    }

    /** Cópia da fixture, porque o upload consome (move) o ficheiro de origem. */
    private function pdfDigitalizado(string $nome = 'digitalizacao_20260916_101500.pdf'): UploadedFile
    {
        $copia = tempnam(sys_get_temp_dir(), 'scan-').'.pdf';
        copy($this->fixturePath(), $copia);

        return new UploadedFile($copia, $nome, 'application/pdf', null, true);
    }

    public function test_a_fixture_e_um_pdf_multipagina_verdadeiro(): void
    {
        $caminho = $this->fixturePath();

        $this->assertFileExists($caminho, 'Gere a fixture com o agente antes de correr a suite.');
        $this->assertSame('application/pdf', (new \finfo(FILEINFO_MIME_TYPE))->file($caminho));

        $paginas = (new \Smalot\PdfParser\Parser())->parseFile($caminho)->getPages();
        $this->assertCount(3, $paginas, 'O PDF do agente tem de manter as três páginas.');
    }

    public function test_pdf_digitalizado_e_aceite_e_guardado_como_anexo(): void
    {
        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload([
                'anexos' => [$this->pdfDigitalizado()],
            ]))
            ->assertSessionHasNoErrors();

        $documento = DocumentoEntrada::firstOrFail();
        $anexos = $documento->anexos()->get();

        $this->assertCount(1, $anexos);
        $this->assertSame('application/pdf', $anexos->first()->mime_type);
        $this->assertSame('digitalizacao_20260916_101500.pdf', $anexos->first()->nome_original);
        Storage::disk(config('filesystems.docs_disk'))->assertExists($anexos->first()->caminho_arquivo);
    }

    /** O ficheiro guardado tem de continuar a ser um PDF legível, não um JPEG renomeado. */
    public function test_o_ficheiro_guardado_continua_a_ser_um_pdf_valido(): void
    {
        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload([
                'anexos' => [$this->pdfDigitalizado()],
            ]))
            ->assertSessionHasNoErrors();

        $anexo = DocumentoEntrada::firstOrFail()->anexos()->firstOrFail();
        $conteudo = Storage::disk(config('filesystems.docs_disk'))->get($anexo->caminho_arquivo);

        $this->assertStringStartsWith('%PDF-', $conteudo);
        $this->assertStringEndsWith('.pdf', $anexo->caminho_arquivo);
    }

    /** O upload manual e a digitalização convivem no mesmo input `anexos[]`. */
    public function test_preserva_anexos_manuais_enviados_com_a_digitalizacao(): void
    {
        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload([
                'anexos' => [
                    UploadedFile::fake()->image('capa-recebida-no-balcao.jpg', 600, 800),
                    $this->pdfDigitalizado('digitalizacao_do_scanner.pdf'),
                ],
            ]))
            ->assertSessionHasNoErrors();

        $anexos = DocumentoEntrada::firstOrFail()->anexos()->orderBy('ordem')->get();

        $this->assertCount(2, $anexos, 'Os dois anexos têm de sobreviver ao registo.');
        $this->assertSame('capa-recebida-no-balcao.jpg', $anexos[0]->nome_original);
        $this->assertSame('digitalizacao_do_scanner.pdf', $anexos[1]->nome_original);
        $this->assertSame('application/pdf', $anexos[1]->mime_type);
    }

    /** O PDF digitalizado é imagem pura: sem OCR não fica pesquisável. */
    public function test_pdf_digitalizado_entra_na_fila_de_ocr(): void
    {
        Bus::fake();

        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload([
                'anexos' => [$this->pdfDigitalizado()],
            ]))
            ->assertSessionHasNoErrors();

        $anexo = DocumentoEntrada::firstOrFail()->anexos()->firstOrFail();

        $this->assertSame('PENDENTE', $anexo->ocr_status);
        Bus::assertDispatched(ProcessarOcrAnexo::class);
    }

    public function test_recusa_pdf_acima_do_limite_de_dez_megabytes(): void
    {
        $grande = UploadedFile::fake()->create(
            'digitalizacao_enorme.pdf',
            \App\Http\Requests\StoreDocumentoEntradaRequest::LIMITE_FICHEIRO_KB + 512,
            'application/pdf'
        );

        $this->actingAs($this->balcao)
            ->post(route('documentos-entradas.store'), $this->payload(['anexos' => [$grande]]))
            ->assertSessionHasErrors('anexos.0');

        $this->assertDatabaseCount('documentos_entradas', 0);
    }
}
