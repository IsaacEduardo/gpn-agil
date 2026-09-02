<?php

namespace App\Console\Commands;

use App\Jobs\ProcessarOcrAnexo;
use App\Models\Anexo;
use Illuminate\Console\Command;

class OcrProcessCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ocr:process 
                            {anexoId? : ID do anexo específico a processar}
                            {--all : Processar todos os anexos elegíveis (PDF/Imagens)}
                            {--failed : Reprocessar anexos que falharam (ocr_status = FALHA)}
                            {--pending : Processar anexos pendentes (ocr_status = PENDENTE)}
                            {--sync : Executar sincronamente em vez de enfileirar}
                            {--force : Forçar reprocessamento mesmo que já concluído}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispara o pipeline de extração e indexação OCR para anexos';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $anexoId = $this->argument('anexoId');
        $all = $this->option('all');
        $failed = $this->option('failed');
        $pending = $this->option('pending');
        $sync = $this->option('sync');
        $force = $this->option('force');

        if ($anexoId) {
            $anexo = Anexo::find((int) $anexoId);
            if (! $anexo) {
                $this->error("Anexo com ID {$anexoId} não encontrado.");

                return Command::FAILURE;
            }

            $this->info("Processando Anexo ID: {$anexo->id} ({$anexo->nome_original})...");
            if ($sync) {
                (new ProcessarOcrAnexo($anexo->id))->handle(app(\App\Services\Ocr\OcrService::class));
                $anexo->refresh();
                $this->info("Status final: {$anexo->ocr_status} | Método: {$anexo->ocr_metodo} | Palavras: {$anexo->ocr_palavras_count}");
            } else {
                ProcessarOcrAnexo::dispatch($anexo->id);
                $this->info("Job enfileirado com sucesso para o Anexo ID {$anexo->id}.");
            }

            return Command::SUCCESS;
        }

        $query = Anexo::query()->where(function ($q) {
            $q->where('mime_type', 'application/pdf')
                ->orWhere('mime_type', 'like', 'image/%');
        });

        if ($failed) {
            $query->where('ocr_status', 'FALHA');
        } elseif ($pending) {
            $query->where('ocr_status', 'PENDENTE');
        } elseif (! $all && ! $force) {
            $query->where(function ($q) {
                $q->whereNull('ocr_status')
                    ->orWhere('ocr_status', 'PENDENTE')
                    ->orWhere('ocr_status', 'FALHA');
            });
        }

        $anexos = $query->get();
        $total = $anexos->count();

        if ($total === 0) {
            $this->info('Nenhum anexo encontrado para processamento com os filtros fornecidos.');

            return Command::SUCCESS;
        }

        $this->info("Encontrados {$total} anexo(s) para processar.");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($anexos as $anexo) {
            if ($sync) {
                try {
                    (new ProcessarOcrAnexo($anexo->id))->handle(app(\App\Services\Ocr\OcrService::class));
                } catch (\Throwable $e) {
                    $this->error("Erro no Anexo {$anexo->id}: ".$e->getMessage());
                }
            } else {
                ProcessarOcrAnexo::dispatch($anexo->id);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info($sync ? 'Processamento síncrono concluído.' : 'Todos os jobs foram despachados para a fila.');

        return Command::SUCCESS;
    }
}
