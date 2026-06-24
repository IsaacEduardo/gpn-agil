<?php

namespace App\Chatbot\Observers;

use App\Chatbot\Jobs\IndexDocumentJob;
use App\Chatbot\Jobs\RemoveDocumentIndexJob;
use App\Models\DocumentoInterno;

class DocumentoInternoObserver
{
    public function saved(DocumentoInterno $doc): void
    {
        IndexDocumentJob::dispatch(DocumentoInterno::class, $doc->id);
    }

    public function deleted(DocumentoInterno $doc): void
    {
        RemoveDocumentIndexJob::dispatch(DocumentoInterno::class, $doc->id);
    }
}
