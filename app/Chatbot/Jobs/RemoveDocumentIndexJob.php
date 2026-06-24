<?php

namespace App\Chatbot\Jobs;

use App\Chatbot\Services\DocumentIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Remove os chunks de um documento do vector store (ao apagar o documento).
 */
class RemoveDocumentIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  class-string  $documentableType
     */
    public function __construct(public string $documentableType, public int $documentableId)
    {
        $this->onQueue(config('chatbot.queue', 'default'));
    }

    public function handle(DocumentIndexer $indexer): void
    {
        $indexer->remove($this->documentableType, $this->documentableId);
    }
}
