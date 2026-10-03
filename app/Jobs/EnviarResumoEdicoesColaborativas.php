<?php

namespace App\Jobs;

use App\Models\DocumentoInterno;
use App\Notifications\EdicaoColaborativaNotification;
use App\Services\DocumentoCollaborationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Fecha a janela de resumo de um documento: lê quem editou (gravado em cache por
 * DocumentoCollaborationService::registarEdicaoParaResumo) e notifica o autor uma vez.
 */
class EnviarResumoEdicoesColaborativas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $documentoId) {}

    public function handle(): void
    {
        $chave = DocumentoCollaborationService::chaveResumo($this->documentoId);
        // As marcas por pessoa expiram com a janela: quem editar depois abre uma nova.
        $editores = Cache::pull($chave, []);

        $documento = DocumentoInterno::with('autor')->find($this->documentoId);
        if (! $documento || ! $documento->autor || $editores === []) {
            return;
        }

        $documento->autor->notify(new EdicaoColaborativaNotification($documento, array_values($editores)));
    }
}
