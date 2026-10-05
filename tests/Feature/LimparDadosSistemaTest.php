<?php

namespace Tests\Feature;

use App\Models\DadosInstituicao;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\ModeloDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Entrega do sistema limpo: fica a configuração da instituição e um administrador.
 */
class LimparDadosSistemaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $outroAdmin;

    private User $chefe;

    private Gabinete $sg;

    private Departamento $dlp;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['private', 'local', 'public'] as $disco) {
            Storage::fake($disco);
        }

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'admin@gpn.gov.ao']);
        $this->admin->assignRole($role);
        $this->admin->update(['role_id' => $role->id]);
        $this->outroAdmin = User::factory()->create(['email' => 'outro.admin@teste.ao']);
        $this->outroAdmin->assignRole($role);

        $this->chefe = User::factory()->create();
        $this->sg = Gabinete::create(['nome' => 'Secretaria Geral', 'sigla' => 'SG', 'codigo_oficios' => 'SEC.GOV.PROV.HLA', 'responsavel_id' => $this->chefe->id]);
        $this->dlp = Departamento::create(['nome' => 'Logística', 'sigla' => 'DLP', 'gabinete_id' => $this->sg->id, 'responsavel_id' => $this->chefe->id]);
        $this->chefe->update(['departamento_id' => $this->dlp->id]);

        DadosInstituicao::create(['nome_oficial' => 'Governo Provincial da Huíla', 'sigla' => 'GPH', 'cidade' => 'Lubango', 'rodape_img_path' => 'rodapes/rodape.png']);
        Storage::disk('public')->put('rodapes/rodape.png', 'img');
        Storage::disk('public')->put('fotos/viatura.jpg', 'img');
        Storage::disk('private')->put('documentos_entradas/2026/anexo.pdf', 'pdf');

        DocumentoInterno::create([
            'titulo' => 'Nota', 'conteudo_final' => '<p>x</p>', 'criado_por' => $this->chefe->id,
            'documento_especie_id' => DocumentoEspecie::firstOrCreate(['nome' => 'Nota'], ['ativo' => true])->id,
            'departamento_id' => $this->dlp->id, 'status' => 'rascunho', 'numero_referencia' => 'NOTA 1/SEC.GOV.PROV.HLA.DLP/2026',
        ]);
        DB::table('sequencias_documentos')->insert(['chave' => 'ENT', 'ano' => 2026, 'ultimo_numero' => 73, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('procedencias')->insert(['nome' => 'Ministério X', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_sem_executar_nao_altera_nada(): void
    {
        $antes = User::count();
        $this->artisan('sistema:limpar-dados', ['--manter-admin' => 'admin@gpn.gov.ao'])->assertSuccessful();

        $this->assertSame($antes, User::count());
        $this->assertSame(1, DocumentoInterno::count());
        Storage::disk('private')->assertExists('documentos_entradas/2026/anexo.pdf');
    }

    public function test_recusa_sem_admin_valido(): void
    {
        $antes = User::count();
        $this->artisan('sistema:limpar-dados', ['--manter-admin' => $this->chefe->email, '--executar' => true])->assertFailed();
        $this->artisan('sistema:limpar-dados', ['--executar' => true])->assertFailed();

        $this->assertSame($antes, User::count());
    }

    public function test_limpa_tudo_e_mantem_configuracao_e_o_admin(): void
    {
        $modelos = ModeloDocumento::count();
        $especies = DocumentoEspecie::count();

        $this->artisan('sistema:limpar-dados', ['--manter-admin' => 'admin@gpn.gov.ao', '--executar' => true])->assertSuccessful();

        // Só o admin pedido, com o seu papel.
        $this->assertSame([$this->admin->id], User::pluck('id')->all());
        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
        $this->assertSame(1, DB::table('model_has_roles')->count());

        // Configuração intacta; chefias de utilizadores apagados ficam vazias.
        $this->assertSame(1, DadosInstituicao::count());
        $this->assertSame($modelos, ModeloDocumento::count());
        $this->assertSame($especies, DocumentoEspecie::count());
        $this->assertNull($this->sg->fresh()->responsavel_id);
        $this->assertNull($this->dlp->fresh()->responsavel_id);
        $this->assertSame('SEC.GOV.PROV.HLA', $this->sg->fresh()->codigo_oficios);

        // Dados de utilização apagados; o livro de entrada recomeça.
        $this->assertSame(0, DocumentoInterno::count());
        $this->assertSame(0, DB::table('sequencias_documentos')->count());
        $this->assertSame(0, DB::table('procedencias')->count());
        $this->assertSame(1, DB::table('audit_logs')->count()); // só o registo da limpeza
        $this->assertSame('sistema.limpeza', DB::table('audit_logs')->value('action'));

        // Ficheiros: só fica o rodapé da instituição.
        Storage::disk('private')->assertMissing('documentos_entradas/2026/anexo.pdf');
        Storage::disk('public')->assertMissing('fotos/viatura.jpg');
        Storage::disk('public')->assertExists('rodapes/rodape.png');
    }
}
