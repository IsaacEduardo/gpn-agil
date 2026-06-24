<?php

namespace App\Chatbot\Jobs;

use App\Chatbot\Services\DocumentIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Indexa (ou reindexa) um documento no vector store, de forma desacoplada.
 */
class IndexDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /**
     * @param  class-string  $documentableType
     */
    public function __construct(public string $documentableType, public int $documentableId)
    {
        $this->onQueue(config('chatbot.queue', 'default'));
    }

    public function handle(DocumentIndexer $indexer): void
    {
        $model = $this->documentableType::find($this->documentableId);
        if ($model) {
            $indexer->index($model);
        } else {
            // Documento removido entretanto — garante limpeza do índice.
            $indexer->remove($this->documentableType, $this->documentableId);
        }
    }
}
