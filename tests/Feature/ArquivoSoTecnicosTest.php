<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEncaminhamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Decisão do cliente (2026-09-29): arquivar fica a cargo dos técnicos do
 * departamento com a guarda do documento. Chefe de departamento, chefe de
 * gabinete e quem registou deixam de arquivar; o admin mantém.
 */
class ArquivoSoTecnicosTest extends TestCase
{
    use RefreshDatabase;

    private Gabinete $gabinete;

    private Departamento $dept;

    private Departamento $outro;

    private User $tecnico;

    private User $tecnicoOutro;

    private User $chefeDept;

    private User $chefeGabinete;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $roleChefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['guard_name' => 'web']);
        $roleAdmin = Role::firstOrCreate(['name' => 'admin'], ['guard_name' => 'web']);

        $this->gabinete = Gabinete::create(['nome' => 'Secretaria Geral', 'sigla' => 'SG']);
        $this->dept = Departamento::create(['nome' => 'Logística', 'sigla' => 'DLP', 'gabinete_id' => $this->gabinete->id]);
        $this->outro = Departamento::create(['nome' => 'Expediente', 'sigla' => 'AEX', 'gabinete_id' => $this->gabinete->id]);

        $this->tecnico = User::factory()->create(['departamento_id' => $this->dept->id]);
        $this->tecnicoOutro = User::factory()->create(['departamento_id' => $this->outro->id]);

        $this->chefeDept = User::factory()->create(['departamento_id' => $this->dept->id, 'role_id' => $roleChefe->id]);
        $this->chefeDept->assignRole('chefe-departamento');
        $this->dept->update(['responsavel_id' => $this->chefeDept->id]);

        $this->chefeGabinete = User::factory()->create(['departamento_id' => $this->outro->id]);
        $this->gabinete->update(['responsavel_id' => $this->chefeGabinete->id]);

        $this->admin = User::factory()->create(['departamento_id' => $this->outro->id, 'role_id' => $roleAdmin->id]);
        $this->admin->assignRole('admin');
    }

    private function entrada(int $departamentoId, User $registou, string $status = 'recebido'): DocumentoEntrada
    {
        return DocumentoEntrada::create([
            'assunto' => 'Ofício',
            'numero_sequencial' => random_int(1, 99999),
            'ano_referencia' => 2026,
            'data_entrada' => now(),
            'procedencia' => 'Externa',
            'departamento_id' => $departamentoId,
            'user_id' => $registou->id,
            'status' => $status,
            'arquivado' => false,
        ]);
    }

    private function interno(): DocumentoInterno
    {
        return DocumentoInterno::create([
            'titulo' => 'Informação',
            'conteudo_final' => '<p>x</p>',
            'departamento_id' => $this->dept->id,
            'criado_por' => $this->tecnico->id,
            'status' => DocumentoStatus::ASSINADO,
            'numero_referencia' => 'REF/'.uniqid(),
            'versao_atual' => 1,
            'documento_especie_id' => DocumentoEspecie::firstOrCreate(['nome' => 'Informação'], ['ativo' => true])->id,
            'arquivado' => false,
        ]);
    }

    public function test_tecnico_com_guarda_e_admin_podem_arquivar(): void
    {
        $doc = $this->entrada($this->dept->id, $this->tecnicoOutro);

        $this->assertTrue(Gate::forUser($this->tecnico)->allows('archive', $doc));
        $this->assertTrue(Gate::forUser($this->admin)->allows('archive', $doc));
    }

    public function test_tecnico_do_destino_pode_arquivar_encaminhado_por_receber(): void
    {
        $doc = $this->entrada($this->outro->id, $this->tecnicoOutro, 'encaminhado');
        DocumentoEncaminhamento::create([
            'documento_entrada_id' => $doc->id,
            'origem_departamento_id' => $this->outro->id,
            'destino_departamento_id' => $this->dept->id,
            'usuario_id' => $this->tecnicoOutro->id,
            'encaminhado_em' => now(),
        ]);

        $this->assertTrue(Gate::forUser($this->tecnico)->allows('archive', $doc));
    }

    public function test_chefias_quem_registou_e_outro_sector_nao_arquivam(): void
    {
        // Registado pelo técnico do expediente, à guarda do DLP.
        $doc = $this->entrada($this->dept->id, $this->tecnicoOutro);

        foreach ([$this->chefeDept, $this->chefeGabinete, $this->tecnicoOutro] as $user) {
            $this->assertFalse(Gate::forUser($user)->allows('archive', $doc), "user {$user->id}");

            $this->actingAs($user)
                ->post(route('pastas.arquivar', $doc->id), ['pasta_id' => 'auto', 'tipo' => 'entrada'])
                ->assertForbidden();
        }

        $this->assertFalse($doc->fresh()->arquivado);
    }

    public function test_arquivo_em_lote_recusa_o_chefe_de_departamento(): void
    {
        Bus::fake();
        $doc = $this->entrada($this->dept->id, $this->tecnicoOutro);

        $this->actingAs($this->chefeDept)
            ->postJson(route('documents.archive.store'), [
                'document_type' => 'entrada',
                'document_ids' => [$doc->id],
                'destination_type' => 'status',
            ])
            ->assertStatus(422)
            ->assertJsonPath('dispatched', []);

        $this->actingAs($this->tecnico)
            ->postJson(route('documents.archive.store'), [
                'document_type' => 'entrada',
                'document_ids' => [$doc->id],
                'destination_type' => 'status',
            ])
            ->assertOk()
            ->assertJsonPath('dispatched', [$doc->id]);
    }

    public function test_interno_so_os_tecnicos_do_departamento(): void
    {
        $doc = $this->interno();

        $this->assertTrue(Gate::forUser($this->tecnico)->allows('archive', $doc));
        $this->assertFalse(Gate::forUser($this->chefeDept)->allows('archive', $doc));
        $this->assertFalse(Gate::forUser($this->chefeGabinete)->allows('archive', $doc));
        $this->assertFalse(Gate::forUser($this->tecnicoOutro)->allows('archive', $doc));
    }

    public function test_menu_arquivar_so_aparece_a_quem_pode(): void
    {
        $doc = $this->entrada($this->dept->id, $this->tecnicoOutro);

        $this->actingAs($this->tecnico)->get(route('documentos-entradas.show', $doc))
            ->assertOk()->assertSee('data-bs-target="#modalArquivarDocumento"', false);

        $this->actingAs($this->chefeDept)->get(route('documentos-entradas.show', $doc))
            ->assertOk()->assertDontSee('data-bs-target="#modalArquivarDocumento"', false);
    }

    public function test_desarquivar_so_tecnicos_com_guarda_e_admin(): void
    {
        $doc = $this->entrada($this->dept->id, $this->tecnicoOutro);
        $doc->update(['arquivado' => true, 'arquivado_em' => now(), 'status_pre_arquivo' => 'recebido', 'status' => 'arquivado']);

        foreach ([$this->chefeDept, $this->chefeGabinete, $this->tecnicoOutro] as $user) {
            $this->actingAs($user)
                ->post(route('documentos-entradas.desarquivar', $doc->id))
                ->assertForbidden();
        }
        $this->assertTrue($doc->fresh()->arquivado);

        $this->actingAs($this->tecnico)
            ->post(route('documentos-entradas.desarquivar', $doc->id))
            ->assertRedirect();
        $this->assertFalse($doc->fresh()->arquivado);
        $this->assertSame('recebido', $doc->fresh()->status->value ?? $doc->fresh()->status);

        $this->assertTrue(Gate::forUser($this->admin)->allows('unarchive', $doc->fresh()));
    }
}
