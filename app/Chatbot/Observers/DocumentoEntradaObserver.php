<?php

namespace App\Chatbot\Observers;

use App\Chatbot\Jobs\IndexDocumentJob;
use App\Chatbot\Jobs\RemoveDocumentIndexJob;
use App\Models\DocumentoEntrada;

class DocumentoEntradaObserver
{
    public function saved(DocumentoEntrada $doc): void
    {
        IndexDocumentJob::dispatch(DocumentoEntrada::class, $doc->id);
    }

    public function deleted(DocumentoEntrada $doc): void
    {
        RemoveDocumentIndexJob::dispatch(DocumentoEntrada::class, $doc->id);
    }

    public function restored(DocumentoEntrada $doc): void
    {
        IndexDocumentJob::dispatch(DocumentoEntrada::class, $doc->id);
    }

    public function forceDeleted(DocumentoEntrada $doc): void
    {
        RemoveDocumentIndexJob::dispatch(DocumentoEntrada::class, $doc->id);
    }
}
