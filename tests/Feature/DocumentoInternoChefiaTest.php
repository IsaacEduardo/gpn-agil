<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\Role;
use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Edição, aprovação e devolução de documentos internos pela chefia (2026-10-01).
 *
 * Chefia do documento = chefe designado do departamento emissor ou responsável do
 * gabinete emissor. Rascunho: autor + chefia editam; em análise: só a chefia;
 * aprovado: ninguém edita, chefia ou signatário devolvem; assinado: imutável.
 */
class DocumentoInternoChefiaTest extends TestCase
{
    use RefreshDatabase;

    private Gabinete $gab;

    private Departamento $dep;

    private User $tecnico;

    private User $chefeDesignado;

    private User $chefeSecundario;

    private User $responsavelGabinete;

    private User $responsavelOutroGabinete;

    private User $admin;

    private DocumentoEspecie $memorando;

    protected function setUp(): void
    {
        parent::setUp();

        $roleUser = Role::firstOrCreate(['name' => 'user'], ['description' => 'Utilizador']);
        $roleChefe = Role::firstOrCreate(['name' => 'chefe-departamento'], ['description' => 'Chefe']);
        $roleAdmin = Role::firstOrCreate(['name' => 'admin'], ['description' => 'Admin']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'chefe-departamento', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->responsavelGabinete = User::factory()->create(['name' => 'Responsável do Gabinete', 'role_id' => $roleUser->id]);
        $this->gab = Gabinete::create(['nome' => 'Gabinete Chefia', 'sigla' => 'GCH', 'responsavel_id' => $this->responsavelGabinete->id]);
        $this->dep = Departamento::create(['nome' => 'Departamento Emissor', 'sigla' => 'DEM', 'gabinete_id' => $this->gab->id]);
        $outroDep = Departamento::create(['nome' => 'Outro Departamento', 'sigla' => 'ODP', 'gabinete_id' => $this->gab->id]);

        $this->responsavelOutroGabinete = User::factory()->create(['name' => 'Responsável de Outro Gabinete', 'role_id' => $roleUser->id]);
        Gabinete::create(['nome' => 'Outro Gabinete', 'sigla' => 'OGB', 'responsavel_id' => $this->responsavelOutroGabinete->id]);

        $this->tecnico = User::factory()->create(['name' => 'Técnica Autora', 'departamento_id' => $this->dep->id, 'role_id' => $roleUser->id]);

        // Chefe designado só por responsavel_id, sem o papel de chefe.
        $this->chefeDesignado = User::factory()->create(['name' => 'Chefe Designado', 'departamento_id' => $this->dep->id, 'role_id' => $roleUser->id]);
        $this->dep->update(['responsavel_id' => $this->chefeDesignado->id]);

        // Tem o papel de chefe (do outro departamento) e é membro secundário do emissor.
        $this->chefeSecundario = User::factory()->create(['name' => 'Chefe de Outro Dep', 'departamento_id' => $outroDep->id, 'role_id' => $roleChefe->id]);
        $this->chefeSecundario->assignRole('chefe-departamento');
        $this->chefeSecundario->departamentos()->attach($this->dep->id);

        $this->admin = User::factory()->create(['name' => 'Admin', 'role_id' => $roleAdmin->id]);
        $this->admin->assignRole('admin');

        $this->memorando = DocumentoEspecie::create(['nome' => 'MEMORANDO', 'descricao' => 'Memorando', 'ativo' => true]);
    }

    private function documento(DocumentoStatus $status, array $extra = []): DocumentoInterno
    {
        return DocumentoInterno::create(array_merge([
            'titulo' => 'Memorando de Teste',
            'conteudo_final' => '<p>Texto original</p>',
            'status' => $status,
            'criado_por' => $this->tecnico->id,
            'departamento_id' => $this->dep->id,
            'documento_especie_id' => $this->memorando->id,
            'numero_referencia' => 'MEM/'.uniqid(),
            'bloqueado_edicao' => $status === DocumentoStatus::APROVADO,
        ], $extra));
    }

    private function documentoDoGabinete(DocumentoStatus $status): DocumentoInterno
    {
        return $this->documento($status, ['departamento_id' => null, 'gabinete_id' => $this->gab->id]);
    }

    // --- Técnico autor ---

    public function test_autor_edita_em_rascunho_mas_nao_em_analise_nem_aprova(): void
    {
        $this->assertTrue($this->tecnico->can('update', $this->documento(DocumentoStatus::RASCUNHO)));

        $emAnalise = $this->documento(DocumentoStatus::EM_ANALISE);
        $this->assertFalse($this->tecnico->can('update', $emAnalise));
        $this->assertFalse($this->tecnico->can('approve', $emAnalise));
        $this->assertFalse($this->tecnico->can('reject', $emAnalise));

        $this->actingAs($this->tecnico)->get(route('documentos-internos.edit', $emAnalise))->assertForbidden();
        $this->actingAs($this->tecnico)->post(route('documentos-internos.approve', $emAnalise))->assertForbidden();
    }

    // --- Chefe designado (sem o papel) ---

    public function test_chefe_designado_sem_papel_edita_em_rascunho_e_em_analise_e_aprova(): void
    {
        $this->assertTrue($this->chefeDesignado->can('update', $this->documento(DocumentoStatus::RASCUNHO)));

        $emAnalise = $this->documento(DocumentoStatus::EM_ANALISE);
        $this->assertTrue($this->chefeDesignado->can('update', $emAnalise));
        $this->assertTrue($this->chefeDesignado->can('reject', $emAnalise));

        $this->actingAs($this->chefeDesignado)->get(route('documentos-internos.edit', $emAnalise))->assertOk();
        $this->actingAs($this->chefeDesignado)->post(route('documentos-internos.approve', $emAnalise))->assertSessionHas('success');
        $this->assertSame(DocumentoStatus::APROVADO, $emAnalise->fresh()->status);
    }

    public function test_edicao_da_chefia_em_analise_cria_versao_e_notifica_o_autor(): void
    {
        Notification::fake();
        $doc = $this->documento(DocumentoStatus::EM_ANALISE);

        $this->actingAs($this->chefeDesignado)->put(route('documentos-internos.update', $doc), [
            'titulo' => 'Memorando de Teste',
            'conteudo_final' => '<p>Texto corrigido pela chefia</p>',
        ])->assertRedirect();

        $doc->refresh();
        $this->assertSame(DocumentoStatus::EM_ANALISE, $doc->status, 'Editar não muda o estado.');
        $this->assertStringContainsString('corrigido pela chefia', $doc->conteudo_final);
        $this->assertDatabaseHas('documento_versaos', [
            'documento_interno_id' => $doc->id,
            'criado_por' => $this->chefeDesignado->id,
        ]);

        Notification::assertSentTo($this->tecnico, SimpleBroadcastNotification::class,
            fn ($n) => $n->toArray($this->tecnico)['type'] === 'documento_editado_chefia');
    }

    public function test_auto_save_fora_de_rascunho_e_recusado(): void
    {
        $doc = $this->documento(DocumentoStatus::EM_ANALISE);

        $this->actingAs($this->chefeDesignado)
            ->postJson(route('documentos-internos.auto-save', $doc), ['conteudo_final' => '<p>Sem versão</p>'])
            ->assertForbidden();
        $this->assertSame('<p>Texto original</p>', $doc->fresh()->conteudo_final);
    }

    // --- Papel de chefe sem chefiar o departamento ---

    public function test_papel_de_chefe_como_membro_secundario_nao_edita_nem_aprova(): void
    {
        $this->assertFalse($this->chefeSecundario->can('update', $this->documento(DocumentoStatus::RASCUNHO)));

        $emAnalise = $this->documento(DocumentoStatus::EM_ANALISE);
        $this->assertFalse($this->chefeSecundario->can('update', $emAnalise));
        $this->assertFalse($this->chefeSecundario->can('approve', $emAnalise));
        $this->assertFalse($this->chefeSecundario->can('reject', $emAnalise));
    }

    // --- Responsável do gabinete ---

    public function test_responsavel_do_gabinete_edita_e_aprova_documentos_dos_seus_departamentos(): void
    {
        $this->assertTrue($this->responsavelGabinete->can('update', $this->documento(DocumentoStatus::RASCUNHO)));

        $emAnalise = $this->documento(DocumentoStatus::EM_ANALISE);
        $this->assertTrue($this->responsavelGabinete->can('update', $emAnalise));
        $this->assertTrue($this->responsavelGabinete->can('approve', $emAnalise));
    }

    public function test_responsavel_do_gabinete_edita_e_aprova_documentos_emitidos_pelo_gabinete(): void
    {
        $rascunho = $this->documentoDoGabinete(DocumentoStatus::RASCUNHO);
        $emAnalise = $this->documentoDoGabinete(DocumentoStatus::EM_ANALISE);

        $this->assertTrue($this->responsavelGabinete->can('update', $rascunho));
        $this->assertTrue($this->responsavelGabinete->can('update', $emAnalise));
        $this->assertTrue($this->responsavelGabinete->can('approve', $emAnalise));
        $this->assertFalse($this->chefeDesignado->can('approve', $emAnalise), 'Documento do gabinete não é do departamento.');
    }

    public function test_responsavel_de_outro_gabinete_nao_edita_nem_aprova(): void
    {
        $emAnalise = $this->documento(DocumentoStatus::EM_ANALISE);

        $this->assertFalse($this->responsavelOutroGabinete->can('update', $emAnalise));
        $this->assertFalse($this->responsavelOutroGabinete->can('approve', $emAnalise));
        $this->assertFalse($this->responsavelOutroGabinete->can('update', $this->documentoDoGabinete(DocumentoStatus::RASCUNHO)));
    }

    public function test_envio_para_analise_de_documento_do_gabinete_notifica_o_responsavel(): void
    {
        Notification::fake();
        $doc = $this->documentoDoGabinete(DocumentoStatus::RASCUNHO);

        $this->actingAs($this->tecnico)->post(route('documentos-internos.submit', $doc))->assertSessionHas('success');

        Notification::assertSentTo($this->responsavelGabinete, SimpleBroadcastNotification::class,
            fn ($n) => $n->toArray($this->responsavelGabinete)['type'] === 'documento_enviado_analise');
    }

    public function test_envio_para_analise_notifica_o_chefe_designado_sem_papel(): void
    {
        Notification::fake();
        $doc = $this->documento(DocumentoStatus::RASCUNHO);

        $this->actingAs($this->tecnico)->post(route('documentos-internos.submit', $doc))->assertSessionHas('success');

        Notification::assertSentTo($this->chefeDesignado, SimpleBroadcastNotification::class);
    }

    // --- Aprovado ---

    public function test_aprovado_ninguem_edita_e_a_chefia_devolve_com_motivo_auditado(): void
    {
        $doc = $this->documento(DocumentoStatus::APROVADO);

        foreach ([$this->tecnico, $this->chefeDesignado, $this->responsavelGabinete] as $user) {
            $this->assertFalse($user->can('update', $doc), "{$user->name} não deve editar um aprovado.");
        }
        $this->assertFalse($this->tecnico->can('reject', $doc));
        $this->assertNaoAlteraPorHttp($this->admin, $doc);

        $this->actingAs($this->chefeDesignado)
            ->post(route('documentos-internos.reject', $doc), ['motivo' => 'Data do evento errada.'])
            ->assertSessionHas('success');

        $doc->refresh();
        $this->assertSame(DocumentoStatus::RASCUNHO, $doc->status);
        $this->assertFalse($doc->bloqueado_edicao);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => DocumentoInterno::class,
            'auditable_id' => $doc->id,
            'action' => 'devolucao',
            'motivo' => 'Data do evento errada.',
        ]);
    }

    public function test_signatario_que_nao_e_chefia_devolve_um_aprovado(): void
    {
        // PARECER: assina o autor (SignatureService::canSignDocumentoInterno).
        $parecer = DocumentoEspecie::create(['nome' => 'PARECER', 'descricao' => 'Parecer', 'ativo' => true]);
        $doc = $this->documento(DocumentoStatus::APROVADO, ['documento_especie_id' => $parecer->id]);

        $this->assertTrue($this->tecnico->can('reject', $doc));
        $this->assertFalse($this->tecnico->can('update', $doc));
    }

    // --- Assinado ---

    public function test_assinado_nao_se_edita_nem_devolve_nem_pelo_admin(): void
    {
        $doc = $this->documento(DocumentoStatus::ASSINADO, ['bloqueado_edicao' => true, 'assinado_em' => now()]);

        foreach ([$this->tecnico, $this->chefeDesignado, $this->responsavelGabinete] as $user) {
            $this->assertFalse($user->can('update', $doc), "{$user->name} não deve editar um assinado.");
            $this->assertFalse($user->can('reject', $doc), "{$user->name} não deve devolver um assinado.");
        }

        // O admin passa no Gate; a guarda está no controller e no serviço.
        $this->assertNaoAlteraPorHttp($this->admin, $doc);
        $this->actingAs($this->admin)
            ->post(route('documentos-internos.reject', $doc), ['motivo' => 'Tentativa'])
            ->assertSessionHas('error');
        $this->assertSame(DocumentoStatus::ASSINADO, $doc->fresh()->status);
    }

    private function assertNaoAlteraPorHttp(User $user, DocumentoInterno $doc): void
    {
        $this->actingAs($user)->get(route('documentos-internos.edit', $doc))->assertSessionHas('error');
        $this->actingAs($user)->put(route('documentos-internos.update', $doc), [
            'titulo' => 'Alterado',
            'conteudo_final' => '<p>Alterado</p>',
        ])->assertSessionHas('error');

        $this->assertSame('<p>Texto original</p>', $doc->fresh()->conteudo_final);
    }

    // --- Ecrã ---

    public function test_ficha_so_mostra_aprovar_e_editar_a_chefia(): void
    {
        $doc = $this->documento(DocumentoStatus::EM_ANALISE);
        $aprovar = route('documentos-internos.approve', $doc);
        $editar = route('documentos-internos.edit', $doc);

        $this->actingAs($this->tecnico)->get(route('documentos-internos.show', $doc))
            ->assertOk()
            ->assertDontSee($aprovar, false)
            ->assertDontSee($editar, false);

        $this->actingAs($this->chefeDesignado)->get(route('documentos-internos.show', $doc))
            ->assertOk()
            ->assertSee($aprovar, false)
            ->assertSee($editar, false);
    }
}
