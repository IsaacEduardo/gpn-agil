<?php

namespace App\Observers;

use App\Models\DocumentoInterno;
use App\Support\DashboardCache;

/**
 * Observer Reativo para Invalidação de Caches do Dashboard quando Documentos Internos são alterados.
 */
class DocumentoInternoObserver
{
    public function saved(DocumentoInterno $documento): void
    {
        $this->invalidateCache($documento);
    }

    public function deleted(DocumentoInterno $documento): void
    {
        $this->invalidateCache($documento);
    }

    private function invalidateCache(DocumentoInterno $documento): void
    {
        if ($documento->departamento_id) {
            DashboardCache::forgetDepartamento($documento->departamento_id);
        }

        // Inclui documentos emitidos pelo próprio gabinete (sem departamento).
        if ($gabineteId = $documento->gabineteEmissorId()) {
            DashboardCache::forgetGabinete($gabineteId);
        }
    }
}
