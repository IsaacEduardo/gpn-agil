<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\SignatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job Assíncrono para Assinatura Digital de Lote em Segundo Plano.
 * Processa a lista de IDs de documentos sem bloquear requisições HTTP.
 */
class ProcessarAssinaturaLoteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        public readonly array $documentoIds,
        public readonly int $userId,
        public readonly string $password,
        public readonly ?string $certificatePassword = null
    ) {
        $this->afterCommit = true;
    }

    public function handle(SignatureService $signatureService): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning("ProcessarAssinaturaLoteJob: Usuário ID {$this->userId} não encontrado.");
            return;
        }

        try {
            $sucessos = $signatureService->signBatch(
                $this->documentoIds,
                $user,
                $this->password,
                $this->certificatePassword
            );

            Log::info("ProcessarAssinaturaLoteJob: Assinatura em lote concluída com sucesso para " . count($sucessos) . " documentos pelo Usuário ID {$this->userId}");
        } catch (\Throwable $e) {
            Log::error("ProcessarAssinaturaLoteJob: Falha no processamento de assinatura em lote para Usuário ID {$this->userId}: " . $e->getMessage());
            throw $e;
        }
    }
}
