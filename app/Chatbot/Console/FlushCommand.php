<?php

namespace App\Chatbot\Console;

use App\Chatbot\Contracts\VectorStore;
use Illuminate\Console\Command;

class FlushCommand extends Command
{
    protected $signature = 'chatbot:flush {--force : Não pedir confirmação}';

    protected $description = 'Apaga todos os chunks/vetores indexados pelo chatbot.';

    public function handle(VectorStore $store): int
    {
        if (! $this->option('force') && ! $this->confirm('Apagar TODO o índice do chatbot?')) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        $store->flush();
        $this->info('Índice do chatbot limpo.');

        return self::SUCCESS;
    }
}
