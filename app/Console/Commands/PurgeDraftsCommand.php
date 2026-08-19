<?php

namespace App\Console\Commands;

use App\Chatbot\Jobs\RemoveDocumentIndexJob;
use App\Models\DocumentoInterno;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeDraftsCommand extends Command
{
    protected $signature = 'documents:purge-drafts {--days=30 : Número de dias de inatividade para expurgar rascunhos}';

    protected $description = 'Expurga rascunhos obsoletos e não modificados além do período de retenção configurado.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $draftsToPurge = DocumentoInterno::whereIn('status', ['rascunho', \App\Enums\DocumentoStatus::RASCUNHO->value, \App\Enums\DocumentoStatus::RASCUNHO])
            ->where('updated_at', '<', $cutoffDate)
            ->get();

        $count = $draftsToPurge->count();

        if ($count === 0) {
            $this->info("Nenhum rascunho obsoleto com mais de {$days} dias encontrado.");
            return Command::SUCCESS;
        }

        $purgedCount = 0;
        foreach ($draftsToPurge as $draft) {
            try {
                // Remover índice de vetores RAG
                RemoveDocumentIndexJob::dispatch(DocumentoInterno::class, $draft->id);

                Log::info("Expurgando rascunho obsoleto ID {$draft->id} (Criado por: {$draft->criado_por}, Última atualização: {$draft->updated_at})");
                $draft->delete();
                $purgedCount++;
            } catch (\Exception $e) {
                Log::error("Erro ao expurgar rascunho ID {$draft->id}: {$e->getMessage()}");
            }
        }

        $this->info("{$purgedCount} de {$count} rascunhos obsoletos expurgados com sucesso.");
        return Command::SUCCESS;
    }
}
