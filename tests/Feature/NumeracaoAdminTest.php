<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\User;
use App\Services\NumeracaoDocumentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fase 3: ecrã "Numeração" — continuar a numeração em papel e preparar o ano seguinte.
 */
class NumeracaoAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $tecnico;

    private Gabinete $sg;

    private Departamento $dlp;

    private DocumentoEspecie $oficio;

    private int $ano;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-01 10:00:00', 'UTC'));
        $this->ano = NumeracaoDocumentoService::anoCorrente();

        $secretario = User::factory()->create(['departamento_id' => null]);
        $this->sg = Gabinete::create([
            'nome' => 'Secretaria Geral', 'sigla' => 'SG', 'codigo_oficios' => 'SEC.GOV.PROV.HLA',
            'responsavel_id' => $secretario->id,
        ]);
        $this->dlp = Departamento::create(['nome' => 'Departamento de Logística e Património', 'sigla' => 'DLP', 'gabinete_id' => $this->sg->id]);
        $this->tecnico = User::factory()->create(['departamento_id' => $this->dlp->id]);
        $this->oficio = DocumentoEspecie::firstOrCreate(['nome' => 'Ofício'], ['ativo' => true]);

        $this->admin = User::factory()->create(['departamento_id' => $this->dlp->id]);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin->assignRole($role);
        $this->admin->update(['role_id' => $role->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function definirOficios(int $ultimo, array $extra = [])
    {
        return $this->actingAs($this->admin)->post(route('admin.numeracao.definir'), $extra + [
            'ano' => $this->ano,
            'tipo' => 'internos',
            'emissor' => "dep:{$this->dlp->id}",
            'documento_especie_id' => $this->oficio->id,
            'ultimo_numero' => $ultimo,
            'motivo' => 'Último ofício em papel: nº '.$ultimo,
        ]);
    }

    private function criarOficio(): string
    {
        $this->actingAs($this->tecnico)->post(route('documentos-internos.store'), [
            'titulo' => 'Ofício', 'documento_especie_id' => $this->oficio->id, 'conteudo_final' => '<p>x</p>',
        ])->assertRedirect();

        return DocumentoInterno::latest('id')->value('numero_referencia');
    }

    public function test_so_o_admin_acede(): void
    {
        $this->actingAs($this->tecnico)->get(route('admin.numeracao.index'))->assertForbidden();
        $this->actingAs($this->tecnico)->post(route('admin.numeracao.definir'), [])->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.numeracao.index'))->assertOk()->assertSee('Numeração de documentos');
    }

    public function test_continuar_a_numeracao_em_papel(): void
    {
        $this->definirOficios(584)->assertRedirect(route('admin.numeracao.index', ['ano' => $this->ano]))
            ->assertSessionHas('success');

        // O ofício de um departamento usa o livro único do gabinete.
        $this->assertDatabaseHas('sequencias_documentos', ['chave' => 'OF:SEC.GOV.PROV.HLA', 'ano' => $this->ano, 'ultimo_numero' => 584]);
        $this->assertSame("585/SEC.GOV.PROV.HLA.DLP/{$this->ano}", $this->criarOficio());
    }

    public function test_recusa_valor_abaixo_do_maior_ja_emitido_mas_aceita_correccao_ate_esse_minimo(): void
    {
        $this->definirOficios(584);
        $this->criarOficio(); // 585

        $this->definirOficios(500)->assertSessionHasErrors('ultimo_numero');
        $this->assertDatabaseHas('sequencias_documentos', ['chave' => 'OF:SEC.GOV.PROV.HLA', 'ultimo_numero' => 585]);

        // Engano para cima corrigido para baixo, até ao maior emitido (585).
        $this->definirOficios(900);
        $this->definirOficios(585)->assertSessionHasNoErrors();
        $this->assertSame("586/SEC.GOV.PROV.HLA.DLP/{$this->ano}", $this->criarOficio());
    }

    public function test_motivo_e_obrigatorio(): void
    {
        $this->definirOficios(10, ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->assertDatabaseMissing('sequencias_documentos', ['chave' => 'OF:SEC.GOV.PROV.HLA']);
    }

    public function test_alteracao_fica_na_auditoria_com_valores_e_motivo(): void
    {
        $this->definirOficios(584);
        $this->definirOficios(600, ['motivo' => 'Correcção: último em papel foi o 600']);

        $log = AuditLog::where('action', 'numeracao.definir')->latest('id')->first();
        $this->assertSame(584, $log->old_values['ultimo_numero']);
        $this->assertSame(600, $log->new_values['ultimo_numero']);
        $this->assertSame('Correcção: último em papel foi o 600', $log->motivo);
        $this->assertSame($this->admin->id, $log->user_id);
    }

    public function test_serie_do_ano_seguinte_nao_afecta_o_actual_e_e_usada_a_1_de_janeiro(): void
    {
        $this->definirOficios(10, ['ano' => $this->ano + 1]);

        $this->assertSame("1/SEC.GOV.PROV.HLA.DLP/{$this->ano}", $this->criarOficio());

        Carbon::setTestNow(Carbon::parse(($this->ano + 1).'-01-01 00:30:00', 'Africa/Luanda'));
        $this->assertSame('11/SEC.GOV.PROV.HLA.DLP/'.($this->ano + 1), $this->criarOficio());
    }

    public function test_livro_de_entrada_e_series_de_outras_especies(): void
    {
        $this->actingAs($this->admin)->post(route('admin.numeracao.definir'), [
            'ano' => $this->ano, 'tipo' => 'entradas', 'ultimo_numero' => 120, 'motivo' => 'Livro de entrada em papel até ao 120',
        ])->assertSessionHasNoErrors();
        $this->assertSame(121, app(NumeracaoDocumentoService::class)->reservarEntrada($this->ano));

        $memo = DocumentoEspecie::firstOrCreate(['nome' => 'Memorando'], ['ativo' => true]);
        $this->definirOficios(33, ['documento_especie_id' => $memo->id]);
        $this->assertDatabaseHas('sequencias_documentos', ['chave' => 'DLP/MEMO', 'ultimo_numero' => 33]);
    }

    public function test_ano_fora_do_intervalo_e_recusado(): void
    {
        $this->definirOficios(5, ['ano' => $this->ano - 1])->assertSessionHasErrors('ano');
    }

    public function test_previa_mostra_o_proximo_documento_e_o_minimo(): void
    {
        $this->criarOficio(); // 1

        $this->actingAs($this->admin)->getJson(route('admin.numeracao.previa', [
            'ano' => $this->ano, 'tipo' => 'internos', 'emissor' => "gab:{$this->sg->id}",
            'documento_especie_id' => $this->oficio->id, 'ultimo_numero' => 584,
        ]))->assertOk()->assertJson(['proxima' => "585/SEC.GOV.PROV.HLA/{$this->ano}", 'minimo' => 1]);
    }

    public function test_lista_as_series_do_ano(): void
    {
        $this->definirOficios(584);

        $this->actingAs($this->admin)->get(route('admin.numeracao.index', ['ano' => $this->ano]))
            ->assertOk()
            ->assertSee('Ofícios — Secretaria Geral')
            ->assertSee("585/SEC.GOV.PROV.HLA/{$this->ano}");
    }
}
