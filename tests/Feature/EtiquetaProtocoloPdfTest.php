<?php

namespace Tests\Feature;

use App\Models\DadosInstituicao;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Support\FormatoEtiqueta;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A etiqueta do protocolo sai em PDF com o tamanho exacto do rolo configurado,
 * para ser impressa pelo driver de qualquer marca de térmica. A página da
 * etiqueta continua a abrir o diálogo de impressão sozinha (?auto_print=1),
 * agora sobre o PDF.
 */
class EtiquetaProtocoloPdfTest extends TestCase
{
    use RefreshDatabase;

    private User $balcao;

    private Departamento $dep;

    private DocumentoEntrada $doc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);

        $papel = Role::where('name', 'user')->firstOrFail();
        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $this->dep = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);

        $this->balcao = User::factory()->create(['role_id' => $papel->id, 'departamento_id' => $this->dep->id]);
        $this->balcao->assignRole($papel);

        $this->doc = DocumentoEntrada::create([
            'numero_sequencial' => 12,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'assunto' => 'Pedido de parecer',
            'procedencia' => str_repeat('Procedência muito comprida ', 10),
            'departamento_id' => $this->dep->id,
            'user_id' => $this->balcao->id,
            'status' => 'registrado',
        ]);
    }

    /** Os dados da instituição são partilhados no arranque; o teste tem de os repor. */
    private function instituicaoCom(string $formato): void
    {
        $dados = DadosInstituicao::create([
            'nome_oficial' => 'Governo Provincial da Huíla',
            'sigla' => 'GPH',
            'cidade' => 'Lubango',
            'etiqueta_formato' => $formato,
        ]);

        View::share('dadosInstituicao', $dados);
    }

    private function pdf(): string
    {
        $resposta = $this->actingAs($this->balcao)
            ->get(route('documentos-entradas.protocolo.etiqueta.pdf', $this->doc));

        $resposta->assertOk();
        $resposta->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', $resposta->headers->get('Content-Disposition'));

        return $resposta->getContent();
    }

    /** @return array{0: float, 1: float} largura e altura em pontos */
    private function mediaBox(string $pdf): array
    {
        $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf), 'A etiqueta tem de caber numa só página.');
        $this->assertSame(1, preg_match('#/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+([\d.]+)\s+([\d.]+)\s*\]#', $pdf, $m));

        return [(float) $m[1], (float) $m[2]];
    }

    public static function formatos(): array
    {
        return [
            '100x50' => ['100x50', 100, 50],
            '100x60' => ['100x60', 100, 60],
            '60x40' => ['60x40', 60, 40],
        ];
    }

    #[DataProvider('formatos')]
    public function test_pdf_tem_o_tamanho_exacto_do_formato_e_uma_pagina(string $formato, int $largura, int $altura): void
    {
        $this->instituicaoCom($formato);

        [$w, $h] = $this->mediaBox($this->pdf());

        $this->assertEqualsWithDelta($largura * 72 / 25.4, $w, 1.0);
        $this->assertEqualsWithDelta($altura * 72 / 25.4, $h, 1.0);
    }

    public function test_formato_invalido_cai_para_100x50(): void
    {
        $this->instituicaoCom('30x20');

        [$w, $h] = $this->mediaBox($this->pdf());

        $this->assertEqualsWithDelta(100 * 72 / 25.4, $w, 1.0);
        $this->assertEqualsWithDelta(50 * 72 / 25.4, $h, 1.0);
    }

    public function test_pdf_exige_autorizacao(): void
    {
        $papel = Role::where('name', 'user')->firstOrFail();
        $gabB = Gabinete::create(['nome' => 'Gabinete B']);
        $depB = Departamento::create(['nome' => 'Departamento B', 'gabinete_id' => $gabB->id]);
        $estranho = User::factory()->create(['role_id' => $papel->id, 'departamento_id' => $depB->id]);
        $estranho->assignRole($papel);

        $this->actingAs($estranho)
            ->get(route('documentos-entradas.protocolo.etiqueta.pdf', $this->doc))
            ->assertStatus(403);
    }

    public function test_pagina_em_auto_print_imprime_o_pdf(): void
    {
        $this->instituicaoCom('100x50');

        $this->actingAs($this->balcao)
            ->get(route('documentos-entradas.protocolo.etiqueta', [$this->doc, 'auto_print' => 1]))
            ->assertOk()
            ->assertSee(route('documentos-entradas.protocolo.etiqueta.pdf', $this->doc), false)
            ->assertSee('var autoPrint = true', false)
            ->assertSee('contentWindow.print()', false)
            ->assertSee('Abrir a etiqueta em PDF', false)
            ->assertSee('100 × 50 mm', false);
    }

    public function test_pagina_sem_auto_print_nao_dispara_o_dialogo(): void
    {
        $this->actingAs($this->balcao)
            ->get(route('documentos-entradas.protocolo.etiqueta', $this->doc))
            ->assertOk()
            ->assertSee('var autoPrint = false', false);
    }

    public function test_admin_escolhe_o_formato_e_valor_fora_da_lista_e_recusado(): void
    {
        $roleAdmin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Administrador']);
        $admin = User::factory()->create(['role_id' => $roleAdmin->id]);
        $base = ['nome_oficial' => 'Governo Provincial da Huíla', 'sigla' => 'GPH', 'cidade' => 'Lubango'];

        $this->actingAs($admin)
            ->put(route('admin.instituicao.update'), $base + ['etiqueta_formato' => '60x40'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('dados_instituicao', ['etiqueta_formato' => '60x40']);

        $this->actingAs($admin)
            ->get(route('admin.instituicao.edit'))
            ->assertOk()
            ->assertSee('<option value="60x40" selected', false);

        $this->actingAs($admin)
            ->put(route('admin.instituicao.update'), $base + ['etiqueta_formato' => '30x20'])
            ->assertSessionHasErrors('etiqueta_formato');
        $this->assertDatabaseHas('dados_instituicao', ['etiqueta_formato' => '60x40']);
    }
}
