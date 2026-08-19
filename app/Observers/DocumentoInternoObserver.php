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

            if ($documento->departamento && $documento->departamento->gabinete_id) {
                DashboardCache::forgetGabinete($documento->departamento->gabinete_id);
            }
        }
    }
}
