<?php

namespace Tests\Unit\Services;

use App\Chatbot\Jobs\IndexDocumentJob;

use App\Models\DocumentoInterno;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IndexDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_observer_dispatches_index_job_to_queue()
    {
        Queue::fake();

        $user = User::factory()->create();
        $especie = \App\Models\DocumentoEspecie::create(['nome' => 'Ofício Teste', 'sigla' => 'OFI']);
        $gab = \App\Models\Gabinete::factory()->create();
        $dep = \App\Models\Departamento::factory()->create(['gabinete_id' => $gab->id]);

        $doc = DocumentoInterno::create([
            'titulo' => 'Documento para Teste RAG',
            'conteudo_final' => '<p>Conteúdo de teste para vetorização assíncrona.</p>',
            'criado_por' => $user->id,
            'documento_especie_id' => $especie->id,
            'departamento_id' => $dep->id,
            'status' => 'rascunho',
            'numero_referencia' => 'RAG/001/2026',
            'versao_atual' => 1,
        ]);

        Queue::assertPushed(IndexDocumentJob::class, function ($job) use ($doc) {
            return $job->documentableId === $doc->id && $job->documentableType === DocumentoInterno::class;
        });
    }
}
