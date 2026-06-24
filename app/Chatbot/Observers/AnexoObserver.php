<?php

namespace App\Chatbot\Observers;

use App\Chatbot\Jobs\IndexDocumentJob;
use App\Models\Anexo;
use App\Models\DocumentoEntrada;

/**
 * Reindexa o documento de entrada quando um anexo muda (ex.: o OCR preenche
 * texto_extraido de forma assíncrona) ou é removido.
 */
class AnexoObserver
{
    public function saved(Anexo $anexo): void
    {
        $this->reindexarPai($anexo);
    }

    public function deleted(Anexo $anexo): void
    {
        $this->reindexarPai($anexo);
    }

    private function reindexarPai(Anexo $anexo): void
    {
        if ($anexo->anexavel_type === DocumentoEntrada::class && $anexo->anexavel_id) {
            IndexDocumentJob::dispatch(DocumentoEntrada::class, (int) $anexo->anexavel_id);
        }
    }
}
