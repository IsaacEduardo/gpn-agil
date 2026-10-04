<?php

namespace App\Jobs;

use App\Models\DocumentoInterno;
use App\Notifications\EdicaoColaborativaNotification;
use App\Services\ActividadeColaborativaService;
use App\Services\DocumentoCollaborationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Fecha a janela de resumo de um documento: lê quem editou (gravado em cache por
 * DocumentoCollaborationService::registarEdicaoParaResumo) e notifica o autor uma vez.
 */
class EnviarResumoEdicoesColaborativas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $documentoId) {}

    public function handle(ActividadeColaborativaService $actividade): void
    {
        $chave = DocumentoCollaborationService::chaveResumo($this->documentoId);
        // As marcas por pessoa expiram com a janela: quem editar depois abre uma nova.
        $editores = Cache::pull($chave, []);
        $inicio = Cache::pull("{$chave}:inicio");

        $documento = DocumentoInterno::with('autor')->find($this->documentoId);
        if (! $documento || ! $documento->autor || $editores === []) {
            return;
        }

        // O que cada um fez na janela ("Josuelma: 14 alterações ao texto e 1 comentário").
        $detalhes = $actividade->resumoPorPessoa(
            $documento,
            $inicio ? Carbon::parse($inicio) : now()->subMinutes(DocumentoCollaborationService::JANELA_RESUMO_MINUTOS + 5),
            $editores,
        );

        $documento->autor->notify(new EdicaoColaborativaNotification($documento, array_values($editores), $detalhes));
    }
}
