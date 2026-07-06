<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Enums\PastaTipo;
use App\Jobs\ArchiveDocumentJob;
use App\Models\AuditLog;
use App\Models\Departamento;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\Pasta;
use App\Models\User;
use App\Services\ArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Cobre o fluxo de arquivamento em lote por arrastar-e-soltar:
 *  - autorização SEMPRE no servidor (Policy 'archive'), por documento;
 *  - pré-validação síncrona da pasta de destino (acessível + compatível);
 *  - despacho assíncrono via ArchiveDocumentJob;
 *  - regra canónica no ArchiveService (estado + pasta + auditoria);
 *  - endpoint de destinos (regressão do bug do FormRequest em GET).
 */
class ArchiveDragDropTest extends TestCase
{
    use RefreshDatabase;

    protected Gabinete $gabinete;

    protected Departamento $departamento;

    protected Departamento $outroDepartamento;

    protected User $user;

    protected User $estranho;

    protected DocumentoEspecie $especie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gabinete = Gabinete::create(['nome' => 'Gabinete A', 'sigla' => 'GA']);
        $this->departamento = Departamento::create(['nome' => 'Dep A', 'sigla' => 'DA', 'gabinete_id' => $this->gabinete->id]);
        $this->outroDepartamento = Departamento::create(['nome' => 'Dep B', 'sigla' => 'DB', 'gabinete_id' => $this->gabinete->id]);

        $this->user = User::factory()->create(['departamento_id' => $this->departamento->id]);
        $this->estranho = User::factory()->create(['departamento_id' => $this->outroDepartamento->id]);

        $this->especie = DocumentoEspecie::firstOrCreate(['nome' => 'Memorando'], ['ativo' => true]);
    }

    /**
     * Cria um documento interno. Por omissão fica em RASCUNHO porque, segundo a
     * regra de perfil existente (DocumentoInternoPolicy::archive), o AUTOR só pode
     * arquivar os seus próprios rascunhos — documentos assinados exigem
     * chefe de departamento / gabinete / admin.
     */
    private function internoDe(User $dono, Departamento $dep, bool $arquivado = false, DocumentoStatus $status = DocumentoStatus::RASCUNHO): DocumentoInterno
    {
        return DocumentoInterno::create([
            'titulo' => 'Doc Interno',
            'conteudo_final' => 'Conteúdo',
            'departamento_id' => $dep->id,
            'criado_por' => $dono->id,
            'status' => $status,
            'numero_referencia' => 'REF/'.uniqid(),
            'versao_atual' => 1,
            'documento_especie_id' => $this->especie->id,
            'arquivado' => $arquivado,
        ]);
    }

    private function entradaDe(User $dono, Departamento $dep): DocumentoEntrada
    {
        return DocumentoEntrada::create([
            'assunto' => 'Ofício de Entrada',
            'numero_sequencial' => random_int(1, 99999),
            'ano_referencia' => 2026,
            'data_entrada' => now(),
            'procedencia' => 'Origem',
            'departamento_id' => $dep->id,
            'user_id' => $dono->id,
            'status' => 'recebido',
            'arquivado' => false,
        ]);
    }

    public function test_endpoint_requires_authentication(): void
    {
        $this->postJson(route('documents.archive.store'), [
            'document_type' => 'interno',
            'document_ids' => [1],
            'destination_type' => 'status',
        ])->assertStatus(401);
    }

    public function test_dispatches_job_for_authorized_document(): void
    {
        Bus::fake();
        $doc = $this->internoDe($this->user, $this->departamento);

        $this->actingAs($this->user)
            ->postJson(route('documents.archive.store'), [
                'document_type' => 'interno',
                'document_ids' => [$doc->id],
                'destination_type' => 'status',
            ])
            ->assertOk()
            ->assertJsonPath('dispatched', [$doc->id]);

        Bus::assertDispatched(ArchiveDocumentJob::class);
    }

    public function test_auto_archive_runs_job_and_persists_state(): void
    {
        // QUEUE_CONNECTION=sync → o Job corre em linha durante o pedido.
        $doc = $this->internoDe($this->user, $this->departamento);

        $this->actingAs($this->user)
            ->postJson(route('documents.archive.store'), [
                'document_type' => 'interno',
                'document_ids' => [$doc->id],
                'destination_type' => 'status',
            ])
            ->assertOk();

        $doc->refresh();
        $this->assertTrue($doc->arquivado);
        $this->assertSame(DocumentoStatus::ARQUIVADO, $doc->status);
        $this->assertNotNull($doc->pasta_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'documento.arquivado',
            'auditable_type' => DocumentoInterno::class,
            'auditable_id' => $doc->id,
        ]);
    }

    public function test_denies_document_of_another_department(): void
    {
        $alheio = $this->internoDe($this->estranho, $this->outroDepartamento);

        $this->actingAs($this->user)
            ->postJson(route('documents.archive.store'), [
                'document_type' => 'interno',
                'document_ids' => [$alheio->id],
                'destination_type' => 'status',
            ])
            ->assertStatus(422)
            ->assertJsonPath('dispatched', [])
            ->assertJsonPath('denied.0.id', $alheio->id);

        $this->assertFalse($alheio->fresh()->arquivado);
    }

    public function test_rejects_already_archived_document(): void
    {
        $doc = $this->internoDe($this->user, $this->departamento, arquivado: true);

        $this->actingAs($this->user)
            ->postJson(route('documents.archive.store'), [
                'document_type' => 'interno',
                'document_ids' => [$doc->id],
                'destination_type' => 'status',
            ])
            ->assertStatus(422)
            ->assertJsonPath('denied.0.reason', 'Documento já arquivado.');
    }

    public function test_pre_validation_rejects_incompatible_folder(): void
    {
        // Pasta exclusiva de internos, documento de entrada → incompatível (422).
        $pastaInterna = Pasta::create([
            'nome' => 'Despachos',
            'departamento_id' => $this->departamento->id,
            'created_by' => $this->user->id,
            'type' => PastaTipo::INTERNO_DESPACHOS->value,
        ]);
        $entrada = $this->entradaDe($this->user, $this->departamento);

        $this->actingAs($this->user)
            ->postJson(route('documents.archive.store'), [
                'document_type' => 'entrada',
                'document_ids' => [$entrada->id],
                'destination_type' => 'folder',
                'destination_id' => $pastaInterna->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A pasta selecionada não é compatível com este tipo de documento.');

        $this->assertFalse($entrada->fresh()->arquivado);
    }

    public function test_pre_validation_rejects_inaccessible_folder(): void
    {
        // Pasta de outro departamento → fora do âmbito do utilizador (403).
        $pastaAlheia = Pasta::create([
            'nome' => 'Arquivo Alheio',
            'departamento_id' => $this->outroDepartamento->id,
            'created_by' => $this->estranho->id,
            'type' => 'custom',
        ]);
        $entrada = $this->entradaDe($this->user, $this->departamento);

        $this->actingAs($this->user)
            ->postJson(route('documents.archive.store'), [
                'document_type' => 'entrada',
                'document_ids' => [$entrada->id],
                'destination_type' => 'folder',
                'destination_id' => $pastaAlheia->id,
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Você não tem permissão para arquivar nesta pasta.');
    }

    public function test_destinations_endpoint_lists_only_accessible_folders(): void
    {
        $minha = Pasta::create([
            'nome' => 'Minha Pasta',
            'departamento_id' => $this->departamento->id,
            'created_by' => $this->user->id,
            'type' => 'custom',
        ]);
        $alheia = Pasta::create([
            'nome' => 'Pasta Alheia',
            'departamento_id' => $this->outroDepartamento->id,
            'created_by' => $this->estranho->id,
            'type' => 'custom',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('documents.archive.destinations'))
            ->assertOk();

        $ids = collect($response->json('folders'))->pluck('id');
        $this->assertTrue($ids->contains($minha->id));
        $this->assertFalse($ids->contains($alheia->id));
    }

    public function test_archive_service_auto_creates_chronological_folder(): void
    {
        $entrada = DocumentoEntrada::create([
            'assunto' => 'Cronológico',
            'numero_sequencial' => 4242,
            'ano_referencia' => 2026,
            'data_entrada' => \Carbon\Carbon::parse('2026-06-15'),
            'procedencia' => 'Origem',
            'departamento_id' => $this->departamento->id,
            'user_id' => $this->user->id,
            'status' => 'recebido',
            'arquivado' => false,
        ]);

        $pasta = app(ArchiveService::class)->archive($entrada, $this->user, 'auto');

        $this->assertSame('06 - Junho', $pasta->nome);
        $this->assertSame(PastaTipo::ENTRADA_MES->value, $pasta->type);

        $entrada->refresh();
        $this->assertTrue($entrada->arquivado);
        $this->assertSame('arquivado', $entrada->status);
        $this->assertSame($pasta->id, $entrada->pasta_id);
        $this->assertSame($this->user->id, $entrada->arquivado_por);
    }
}
