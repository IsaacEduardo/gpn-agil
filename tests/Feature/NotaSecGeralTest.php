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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * Nota (Secretaria Geral): livro de notas do gabinete, sempre de um departamento, assinada
 * pelo Chefe de Departamento designado na Gestão de Utilizadores.
 */
class NotaSecGeralTest extends TestCase
{
    use RefreshDatabase;

    private Gabinete $sg;

    private Departamento $dlp;

    private Departamento $drpp;

    private User $secretarioGeral;

    private User $admin;

    private User $chefeDlp;

    private User $chefeDrpp;

    private User $tecnicoDlp;

    private DocumentoEspecie $nota;

    private int $ano;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ano = NumeracaoDocumentoService::anoCorrente();

        $this->secretarioGeral = User::factory()->create(['name' => 'Secretário Geral', 'departamento_id' => null]);
        $this->sg = Gabinete::create([
            'nome' => 'Secretaria Geral', 'sigla' => 'SG', 'codigo_oficios' => 'SEC.GOV.PROV.HLA',
            'responsavel_id' => $this->secretarioGeral->id,
        ]);
        $this->dlp = Departamento::create(['nome' => 'Departamento de Logística e Património', 'sigla' => 'DLP', 'gabinete_id' => $this->sg->id]);
        $this->drpp = Departamento::create(['nome' => 'Departamento de Relações Públicas e Protocolo', 'sigla' => 'DRPP', 'gabinete_id' => $this->sg->id]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        SpatieRole::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'chefe-departamento']);
        SpatieRole::firstOrCreate(['name' => 'chefe-departamento', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user']);
        SpatieRole::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['departamento_id' => $this->dlp->id, 'role_id' => $adminRole->id]);
        $this->admin->assignRole('admin');

        $this->chefeDlp = $this->designar('Gilberto Silva', $this->dlp);
        $this->chefeDrpp = $this->designar('Francisco Miguel', $this->drpp);
        $this->tecnicoDlp = User::factory()->create(['departamento_id' => $this->dlp->id]);
        $this->nota = DocumentoEspecie::firstOrCreate(['nome' => 'Nota'], ['ativo' => true]);
    }

    /** Designa pelo ecrã de Gestão de Utilizadores (o caminho real). */
    private function designar(string $nome, Departamento $dep, ?User $existente = null)
    {
        $roleChefe = Role::where('name', 'chefe-departamento')->value('id');
        $dados = [
            'name' => $nome, 'email' => ($existente?->email ?? str()->slug($nome).'@teste.ao'),
            'password' => 'password123', 'role_id' => $roleChefe, 'departamento_id' => $dep->id,
        ];

        $resposta = $existente
            ? $this->actingAs($this->admin)->put(route('admin.users.update', $existente), $dados)
            : $this->actingAs($this->admin)->post(route('admin.users.store'), $dados);

        return $existente ? $resposta : User::where('email', $dados['email'])->firstOrFail();
    }

    private function criar(User $autor, string $especie = 'Nota', array $extra = [])
    {
        return $this->actingAs($autor)->post(route('documentos-internos.store'), $extra + [
            'titulo' => $especie,
            'documento_especie_id' => DocumentoEspecie::firstOrCreate(['nome' => $especie], ['ativo' => true])->id,
            'conteudo_final' => '<p>Texto</p>',
        ]);
    }

    public function test_livro_de_notas_proprio_por_gabinete_sem_colidir_com_os_oficios(): void
    {
        $tecnicoDrpp = User::factory()->create(['departamento_id' => $this->drpp->id]);

        $this->criar($this->tecnicoDlp)->assertRedirect();
        $this->criar($this->tecnicoDlp, 'Ofício')->assertRedirect();
        $this->criar($tecnicoDrpp)->assertRedirect();

        $this->assertSame([
            "NOTA 1/SEC.GOV.PROV.HLA.DLP/{$this->ano}",
            "1/SEC.GOV.PROV.HLA.DLP/{$this->ano}",
            "NOTA 2/SEC.GOV.PROV.HLA.DRPP/{$this->ano}",
        ], DocumentoInterno::orderBy('id')->pluck('numero_referencia')->all());
    }

    public function test_numero_inicial_das_notas_no_ecra_numeracao(): void
    {
        $this->actingAs($this->admin)->post(route('admin.numeracao.definir'), [
            'ano' => $this->ano, 'tipo' => 'internos', 'emissor' => "dep:{$this->dlp->id}",
            'documento_especie_id' => $this->nota->id, 'ultimo_numero' => 11, 'motivo' => 'Última nota em papel: 11',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sequencias_documentos', ['chave' => 'NOTA:SEC.GOV.PROV.HLA', 'ultimo_numero' => 11]);
        $this->criar($this->tecnicoDlp)->assertRedirect();
        $this->assertSame("NOTA 12/SEC.GOV.PROV.HLA.DLP/{$this->ano}", DocumentoInterno::latest('id')->value('numero_referencia'));
    }

    public function test_nota_emitida_pelo_gabinete_e_recusada(): void
    {
        $this->criar($this->secretarioGeral, 'Nota', ['emissor' => "gab:{$this->sg->id}"])->assertSessionHasErrors('emissor');
        $this->criar($this->secretarioGeral)->assertSessionHasErrors('emissor'); // omissão do chefe = gabinete

        $modelo = ModeloDocumento::where('codigo', 'NOTA_SEC_GERAL')->firstOrFail();
        $this->actingAs($this->secretarioGeral)->postJson(route('documentos-internos.preview'), [
            'modelo_id' => $modelo->id, 'emissor' => "gab:{$this->sg->id}",
        ])->assertStatus(422)->assertJsonValidationErrors('emissor');

        // Escolhendo um departamento do seu gabinete, o Secretário Geral pode elaborá-la.
        $this->criar($this->secretarioGeral, 'Nota', ['emissor' => "dep:{$this->dlp->id}"])->assertRedirect();
        $this->assertDatabaseCount('documento_internos', 1);
    }

    public function test_pre_visualizacao_mostra_formato_da_nota_e_o_chefe_do_departamento(): void
    {
        $modelo = ModeloDocumento::where('codigo', 'NOTA_SEC_GERAL')->firstOrFail();

        $html = $this->actingAs($this->tecnicoDlp)->postJson(route('documentos-internos.preview'), [
            'modelo_id' => $modelo->id,
        ])->assertOk()->json('content');

        $this->assertStringContainsString("NOTA ___/SEC.GOV.PROV.HLA.DLP/{$this->ano}", $html);
        $this->assertStringContainsString('O Chefe de Departamento', $html);
        $this->assertStringContainsString('Gilberto Silva', $html);
        $this->assertStringNotContainsString('Secretário Geral', $html);

        // Datação pelo departamento emissor (o ofício mantém a do gabinete).
        $this->assertStringContainsString('<strong>DEPARTAMENTO DE LOGÍSTICA E PATRIMÓNIO DA SECRETARIA GERAL DO ', $html);
        $oficio = app(DocumentoInternoService::class)->processarTemplate(
            ModeloDocumento::where('codigo', 'OFICIO_SEC_GERAL')->value('conteudo'), null, $this->tecnicoDlp, [], $this->dlp
        );
        $this->assertStringContainsString('<strong>SECRETARIA GERAL DO ', $oficio);
        $this->assertStringNotContainsString('DEPARTAMENTO DE LOGÍSTICA', $oficio);
    }

    public function test_so_o_chefe_designado_do_departamento_assina(): void
    {
        $this->criar($this->tecnicoDlp)->assertRedirect();
        $doc = DocumentoInterno::latest('id')->first();
        $assinaturas = app(SignatureService::class);

        $this->assertTrue($assinaturas->canSign($doc, $this->chefeDlp));
        $this->assertFalse($assinaturas->canSign($doc, $this->secretarioGeral));
        $this->assertFalse($assinaturas->canSign($doc, $this->chefeDrpp));
        $this->assertFalse($assinaturas->canSign($doc, $this->tecnicoDlp));
    }

    public function test_segundo_chefe_no_mesmo_departamento_e_recusado(): void
    {
        $roleChefe = Role::where('name', 'chefe-departamento')->value('id');

        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Outro Chefe', 'email' => 'outro@teste.ao', 'password' => 'password123',
            'role_id' => $roleChefe, 'departamento_id' => $this->dlp->id,
        ])->assertSessionHasErrors(['role_id' => 'O departamento DLP já tem Chefe de Departamento (Gilberto Silva). Retire primeiro essa designação.']);

        $this->assertDatabaseMissing('users', ['email' => 'outro@teste.ao']);
        $this->assertSame($this->chefeDlp->id, $this->dlp->fresh()->responsavel_id);
    }

    public function test_retirar_o_papel_liberta_o_departamento_e_a_nota_fica_sem_assinante(): void
    {
        $this->criar($this->tecnicoDlp)->assertRedirect();
        $doc = DocumentoInterno::latest('id')->first();

        $this->actingAs($this->admin)->put(route('admin.users.update', $this->chefeDlp), [
            'name' => $this->chefeDlp->name, 'email' => $this->chefeDlp->email,
            'role_id' => Role::where('name', 'user')->value('id'), 'departamento_id' => $this->dlp->id,
        ])->assertSessionHasNoErrors();

        $this->assertNull($this->dlp->fresh()->responsavel_id);
        $this->assertFalse(app(SignatureService::class)->canSign($doc->fresh(), $this->chefeDlp->fresh()));

        $html = app(DocumentoInternoService::class)->processarTemplate('{{CHEFE_DEPARTAMENTO_NOME}}', null, $this->tecnicoDlp, [], $this->dlp->fresh());
        $this->assertSame('[Chefe de Departamento não definido]', $html);

        $this->actingAs($this->tecnicoDlp)->get(route('documentos-internos.show', $doc))
            ->assertOk()->assertSee('não tem Chefe de Departamento designado');
    }

    public function test_mudar_o_chefe_de_departamento_move_a_designacao(): void
    {
        $this->designar('Gilberto Silva', $this->drpp, $this->chefeDlp)
            ->assertSessionHasErrors('role_id'); // o DRPP já tem chefe

        $semChefe = Departamento::create(['nome' => 'Departamento de Orçamento', 'sigla' => 'DOGOC', 'gabinete_id' => $this->sg->id]);
        $this->designar('Gilberto Silva', $semChefe, $this->chefeDlp)->assertSessionHasNoErrors();

        $this->assertNull($this->dlp->fresh()->responsavel_id);
        $this->assertSame($this->chefeDlp->id, $semChefe->fresh()->responsavel_id);
    }

    public function test_apagar_o_chefe_liberta_o_departamento(): void
    {
        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->chefeDrpp));

        $this->assertNull($this->drpp->fresh()->responsavel_id);
    }

    public function test_dois_utilizadores_com_o_papel_sem_designacao_nao_da_chefe(): void
    {
        $roleChefe = Role::where('name', 'chefe-departamento')->value('id');
        $dogoc = Departamento::create(['nome' => 'Departamento de Orçamento', 'sigla' => 'DOGOC', 'gabinete_id' => $this->sg->id]);
        // Criados fora da Gestão de Utilizadores (ex.: importação): espelho vazio.
        $a = User::factory()->create(['departamento_id' => $dogoc->id, 'role_id' => $roleChefe]);
        $this->assertSame($a->id, $dogoc->fresh()->chefeDesignado()?->id, 'Um só com o papel: é o chefe');

        User::factory()->create(['departamento_id' => $dogoc->id, 'role_id' => $roleChefe]);
        $this->assertNull($dogoc->fresh()->chefeDesignado(), 'Dois com o papel: nenhum (nunca "um qualquer")');

        $tecnico = User::factory()->create(['departamento_id' => $dogoc->id]);
        $this->criar($tecnico)->assertRedirect();
        $doc = DocumentoInterno::latest('id')->first();
        $this->assertFalse(app(SignatureService::class)->canSign($doc, $a));
        $this->assertTrue(app(SignatureService::class)->canSign($doc, $this->admin));
    }

    public function test_migracao_preenche_so_departamentos_com_um_unico_chefe(): void
    {
        $roleChefe = Role::where('name', 'chefe-departamento')->value('id');
        $um = Departamento::create(['nome' => 'Dep Um', 'sigla' => 'D1', 'gabinete_id' => $this->sg->id]);
        $dois = Departamento::create(['nome' => 'Dep Dois', 'sigla' => 'D2', 'gabinete_id' => $this->sg->id]);
        $chefeUm = User::factory()->create(['departamento_id' => $um->id, 'role_id' => $roleChefe]);
        User::factory()->count(2)->create(['departamento_id' => $dois->id, 'role_id' => $roleChefe]);

        (require database_path('migrations/2026_09_27_160000_sincroniza_responsavel_dos_departamentos.php'))->up();

        $this->assertSame($chefeUm->id, $um->fresh()->responsavel_id);
        $this->assertNull($dois->fresh()->responsavel_id);
    }

    public function test_modelo_exclusivo_da_secretaria_geral_e_copiado_do_oficio(): void
    {
        $service = app(DocumentoInternoService::class);
        $modelo = ModeloDocumento::where('codigo', 'NOTA_SEC_GERAL')->firstOrFail();

        $this->assertTrue($service->getTemplatesForUser($this->tecnicoDlp)->contains('id', $modelo->id));

        $outroGab = Gabinete::create(['nome' => 'Outro Gabinete']);
        $outro = User::factory()->create(['departamento_id' => Departamento::create(['nome' => 'Dep X', 'sigla' => 'DX', 'gabinete_id' => $outroGab->id])->id]);
        $this->assertFalse($service->getTemplatesForUser($outro)->contains('id', $modelo->id));

        foreach (['{{DESTINATARIO_NOME}}', '{{DESTINATARIO_CARGO}}', '{{DESTINATARIO_ORGAO}}', '{{DESTINATARIO_LOCAL}}', '{{NOSSA_REFERENCIA}}', '{{CHEFE_DEPARTAMENTO_NOME}}'] as $marcador) {
            $this->assertStringContainsString($marcador, $modelo->conteudo);
        }
        $this->assertStringNotContainsString('{{ nome_signatario }}', $modelo->conteudo);
    }
}
