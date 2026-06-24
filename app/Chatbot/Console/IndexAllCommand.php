<?php

namespace App\Chatbot\Console;

use App\Chatbot\Jobs\IndexDocumentJob;
use App\Chatbot\Services\DocumentIndexer;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoInterno;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class IndexAllCommand extends Command
{
    protected $signature = 'chatbot:index-all
        {--sync : Indexa de forma síncrona (sem enfileirar)}
        {--tipo= : Limita a interno|externo}';

    protected $description = 'Indexa todos os documentos (internos e externos) no vector store do chatbot.';

    public function handle(DocumentIndexer $indexer): int
    {
        $tipo = $this->option('tipo');
        $sync = (bool) $this->option('sync');

        if (! $tipo || $tipo === 'externo') {
            $this->processar('Documentos de entrada', DocumentoEntrada::query(), DocumentoEntrada::class, $sync, $indexer);
        }

        if (! $tipo || $tipo === 'interno') {
            $this->processar('Documentos internos', DocumentoInterno::query(), DocumentoInterno::class, $sync, $indexer);
        }

        $this->info($sync ? 'Indexação concluída.' : 'Jobs de indexação enfileirados.');

        return self::SUCCESS;
    }

    private function processar(string $label, Builder $query, string $class, bool $sync, DocumentIndexer $indexer): void
    {
        $total = (clone $query)->count();
        $this->line("→ {$label}: {$total}");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(50, function ($docs) use ($sync, $indexer, $bar, $class) {
            foreach ($docs as $doc) {
                if ($sync) {
                    try {
                        $indexer->index($doc);
                    } catch (\Throwable $e) {
                        $this->warn(" Falha {$class}#{$doc->id}: ".$e->getMessage());
                    }
                } else {
                    IndexDocumentJob::dispatch($class, $doc->id);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }
}
