<?php

namespace App\Chatbot\Providers;

use App\Chatbot\Console\FlushCommand;
use App\Chatbot\Console\IndexAllCommand;
use App\Chatbot\Contracts\EmbeddingClient;
use App\Chatbot\Contracts\VectorStore;
use App\Chatbot\Embeddings\FakeEmbeddingClient;
use App\Chatbot\Embeddings\OllamaEmbeddingClient;
use App\Chatbot\Embeddings\OpenAiEmbeddingClient;
use App\Chatbot\Observers\AnexoObserver;
use App\Chatbot\Observers\DocumentoEntradaObserver;
use App\Chatbot\Observers\DocumentoInternoObserver;
use App\Chatbot\VectorStore\MysqlVectorStore;
use App\Models\Anexo;
use App\Models\DocumentoEntrada;
use App\Models\DocumentoInterno;
use Illuminate\Support\ServiceProvider;

class ChatbotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmbeddingClient::class, function () {
            $cfg = config('chatbot.embeddings', []);

            return match ($cfg['driver'] ?? 'ollama') {
                'fake' => new FakeEmbeddingClient((int) ($cfg['dimensions'] ?? 64)),
                'openai' => new OpenAiEmbeddingClient($cfg),
                default => new OllamaEmbeddingClient($cfg),
            };
        });

        $this->app->singleton(VectorStore::class, function () {
            // Apenas 'mysql' por agora; a interface permite acrescentar Qdrant/pgvector.
            return new MysqlVectorStore(config('chatbot.vector_store', []));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([IndexAllCommand::class, FlushCommand::class]);
        }

        // Ingestão automática por eventos (apenas quando o chatbot está habilitado).
        if (config('chatbot.enabled')) {
            DocumentoEntrada::observe(DocumentoEntradaObserver::class);
            DocumentoInterno::observe(DocumentoInternoObserver::class);
            Anexo::observe(AnexoObserver::class);
        }
    }
}
