<?php

namespace App\Jobs;

use App\Events\DocumentoArquivado;
use App\Models\User;
use App\Services\ArchiveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Arquiva um documento (entrada ou interno) de forma assíncrona, delegando a regra
 * canónica ao ArchiveService. Dispara DocumentoArquivado ao concluir.
 */
class ArchiveDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /**
     * @param  class-string  $documentableType  DocumentoEntrada::class | DocumentoInterno::class
     * @param  string|int|null  $pastaId  'auto' (cronológico) ou id de pasta
     */
    public function __construct(
        public string $documentableType,
        public int $documentId,
        public int $userId,
        public string|int|null $pastaId = 'auto',
    ) {}

    public function handle(ArchiveService $archiveService): void
    {
        $documento = $this->documentableType::find($this->documentId);
        $user = User::find($this->userId);

        if (! $documento || ! $user) {
            return;
        }

        try {
            $pasta = $archiveService->archive($documento, $user, $this->pastaId);
            event(new DocumentoArquivado($this->documentableType, $this->documentId, $this->userId, $pasta->id));
        } catch (\Throwable $e) {
            Log::warning('Arquivamento falhou para '.$this->documentableType.'#'.$this->documentId.': '.$e->getMessage());
            throw $e; // deixa a fila tratar o retry/falha
        }
    }
}
