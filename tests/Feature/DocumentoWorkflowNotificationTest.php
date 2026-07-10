<?php

namespace Tests\Feature;

use App\Enums\DocumentoStatus;
use App\Models\Departamento;
use App\Models\DocumentoEspecie;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use App\Services\DocumentoWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DocumentoWorkflowNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Gabinete $gabinete;

    protected Departamento $dep;

    protected DocumentoEspecie $especie;

    protected User $autor;

    protected User $chefe;

    protected DocumentoWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gabinete = Gabinete::create(['nome' => 'Gabinete X']);
        $this->dep = Departamento::create(['nome' => 'Dep X', 'sigla' => 'DX', 'gabinete_id' => $this->gabinete->id]);

        $this->autor = User::factory()->create(['name' => 'Autora', 'departamento_id' => $this->dep->id]);
        $this->chefe = User::factory()->create(['name' => 'Chefe', 'departamento_id' => $this->dep->id]);
        $this->dep->responsavel_id = $this->chefe->id;
        $this->dep->save();

        $this->especie = DocumentoEspecie::create(['nome' => 'OFICIO', 'descricao' => 'Ofício', 'ativo' => true]);

        $this->service = app(DocumentoWorkflowService::class);
    }

    private function novoDoc(DocumentoStatus $status): DocumentoInterno
    {
        return DocumentoInterno::create([
            'titulo' => 'Relatório Anual',
            'conteudo_final' => '<p>Conteúdo</p>',
            'status' => $status,
            'criado_por' => $this->autor->id,
            'departamento_id' => $this->dep->id,
            'documento_especie_id' => $this->especie->id,
            'numero_referencia' => 'OFI/010',
        ]);
    }

    public function test_submit_for_review_notifica_o_chefe(): void
    {
        Notification::fake();
        $doc = $this->novoDoc(DocumentoStatus::RASCUNHO);

        $this->service->submitForReview($doc, $this->autor);

        Notification::assertSentTo(
            $this->chefe,
            SimpleBroadcastNotification::class,
            function ($n) use ($doc) {
                $data = $n->toArray($this->chefe);

                return $data['type'] === 'documento_enviado_analise'
                    && str_contains($data['title'], 'Relatório Anual')
                    && $data['priority'] === 'high';
            }
        );
        Notification::assertNotSentTo($this->autor, SimpleBroadcastNotification::class);
    }

    public function test_approve_notifica_o_autor(): void
    {
        Notification::fake();
        $doc = $this->novoDoc(DocumentoStatus::EM_ANALISE);

        $this->service->approve($doc, $this->chefe);

        Notification::assertSentTo(
            $this->autor,
            SimpleBroadcastNotification::class,
            function ($n) {
                $data = $n->toArray($this->autor);

                return $data['type'] === 'documento_aprovado'
                    && str_contains($data['title'], 'aprovado');
            }
        );
    }

    public function test_reject_notifica_o_autor_com_motivo(): void
    {
        Notification::fake();
        $doc = $this->novoDoc(DocumentoStatus::EM_ANALISE);

        $this->service->reject($doc, $this->chefe, 'Falta a fundamentação legal.');

        Notification::assertSentTo(
            $this->autor,
            SimpleBroadcastNotification::class,
            function ($n) {
                $data = $n->toArray($this->autor);

                return $data['type'] === 'documento_devolvido'
                    && str_contains($data['body'], 'Falta a fundamentação legal.')
                    && $data['priority'] === 'high';
            }
        );
    }
}
