<?php

namespace Tests\Feature;

use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ecrã de administração das espécies de documento — é por aqui que os prazos de
 * tratamento passam a ser definidos, sem SQL nem nova migration.
 */
class DocumentoEspecieAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $comum;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);

        $papelAdmin = Role::where('name', 'admin')->firstOrFail();
        $papelUser = Role::where('name', 'user')->firstOrFail();

        $gab = Gabinete::create(['nome' => 'Gabinete A']);
        $dep = Departamento::create(['nome' => 'Departamento A', 'gabinete_id' => $gab->id]);

        $this->admin = User::factory()->create(['role_id' => $papelAdmin->id, 'departamento_id' => $dep->id]);
        $this->admin->assignRole($papelAdmin);

        $this->comum = User::factory()->create(['role_id' => $papelUser->id, 'departamento_id' => $dep->id]);
        $this->comum->assignRole($papelUser);
    }

    public function test_apenas_administradores_acedem(): void
    {
        $this->actingAs($this->comum)
            ->get(route('admin.documento-especies.index'))
            ->assertStatus(403);

        $this->actingAs($this->admin)
            ->get(route('admin.documento-especies.index'))
            ->assertStatus(200);
    }

    public function test_define_prazo_de_tratamento(): void
    {
        $especie = DocumentoEspecie::firstOrCreate(['nome' => 'Requerimento'], ['ativo' => true, 'ordem' => 1]);

        $this->actingAs($this->admin)
            ->put(route('admin.documento-especies.update', $especie), [
                'nome' => 'Requerimento',
                'prazo_tratamento_dias' => 15,
                'ordem' => 3,
                'ativo' => '1',
            ])
            ->assertRedirect();

        $especie->refresh();
        $this->assertSame(15, $especie->prazo_tratamento_dias);
        $this->assertSame(3, $especie->ordem);
        $this->assertTrue($especie->ativo);
    }

    /** O prazo em branco significa "usar o prazo global", não zero. */
    public function test_prazo_em_branco_volta_ao_prazo_global(): void
    {
        $especie = DocumentoEspecie::firstOrCreate(
            ['nome' => 'Circular'],
            ['ativo' => true, 'ordem' => 1, 'prazo_tratamento_dias' => 9],
        );

        $this->actingAs($this->admin)
            ->put(route('admin.documento-especies.update', $especie), [
                'nome' => 'Circular',
                'prazo_tratamento_dias' => '',
                'ativo' => '1',
            ])
            ->assertRedirect();

        $this->assertNull($especie->refresh()->prazo_tratamento_dias);
    }

    public function test_alterar_prazo_reflete_se_de_imediato_no_sla(): void
    {
        $especie = DocumentoEspecie::firstOrCreate(['nome' => 'Memorando'], ['ativo' => true, 'ordem' => 1]);

        $gab = Gabinete::first();
        $dep = Departamento::first();

        $doc = DocumentoEntrada::create([
            'numero_sequencial' => 1,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now()->subDays(8),
            'classificacao_especie' => 'Memorando',
            'assunto' => 'Teste',
            'departamento_id' => $dep->id,
            'user_id' => $this->admin->id,
            'status' => 'registrado',
        ]);

        // Prazo global (5): 8 dias já é crítico.
        $this->assertSame('critical', $doc->sla_status);

        $this->actingAs($this->admin)->put(route('admin.documento-especies.update', $especie), [
            'nome' => 'Memorando',
            'prazo_tratamento_dias' => 30,
            'ativo' => '1',
        ]);

        // A cache do mapa tem de ter sido invalidada pela gravação: com prazo de
        // 30 dias, 8 dias decorridos deixam de ser sequer aviso (que só dispara
        // aos 12 = 40% de 30).
        $this->assertSame('normal', $doc->fresh()->sla_status);
    }

    public function test_cria_nova_especie(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.documento-especies.store'), [
                'nome' => 'Notificação Judicial',
                'prazo_tratamento_dias' => 3,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('documento_especies', [
            'nome' => 'Notificação Judicial',
            'prazo_tratamento_dias' => 3,
        ]);
    }

    public function test_recusa_especie_duplicada(): void
    {
        DocumentoEspecie::firstOrCreate(['nome' => 'Parecer'], ['ativo' => true, 'ordem' => 1]);

        $this->actingAs($this->admin)
            ->post(route('admin.documento-especies.store'), ['nome' => '  parecer  '])
            ->assertSessionHasErrors('nome');
    }

    /** Não se apaga uma espécie que já classifica documentos: perder-se-ia a retenção. */
    public function test_nao_apaga_especie_em_uso(): void
    {
        $especie = DocumentoEspecie::firstOrCreate(['nome' => 'Acta'], ['ativo' => true, 'ordem' => 1]);
        $dep = Departamento::first();

        DocumentoEntrada::create([
            'numero_sequencial' => 2,
            'ano_referencia' => (int) date('Y'),
            'data_entrada' => now(),
            'classificacao_especie' => 'Acta',
            'assunto' => 'Em uso',
            'departamento_id' => $dep->id,
            'user_id' => $this->admin->id,
            'status' => 'registrado',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.documento-especies.destroy', $especie))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('documento_especies', ['id' => $especie->id]);
    }

    public function test_apaga_especie_sem_uso(): void
    {
        $especie = DocumentoEspecie::create(['nome' => 'Espécie Órfã', 'ativo' => true, 'ordem' => 99]);

        $this->actingAs($this->admin)
            ->delete(route('admin.documento-especies.destroy', $especie))
            ->assertRedirect();

        $this->assertDatabaseMissing('documento_especies', ['id' => $especie->id]);
    }
}
