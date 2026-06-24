<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chatbot RAG — Configuração
    |--------------------------------------------------------------------------
    | Módulo de chatbot sobre documentos (internos e externos) com busca
    | semântica (embeddings + vector store) e geração via LLM, respeitando as
    | permissões de cada utilizador/departamento/gabinete.
    */

    // Liga a ingestão automática (observers), a API e os comandos do chatbot.
    'enabled' => env('CHATBOT_ENABLED', false),

    // Geração de respostas reutiliza o LlmClient (config/services.anthropic).
    'llm' => [
        'model' => env('ASSISTENTE_MODEL', 'claude-sonnet-4-6'),
        'max_tokens' => (int) env('ASSISTENTE_MAX_TOKENS', 1024),
        'temperature' => (float) env('CHATBOT_TEMPERATURE', 0.1),
    ],

    // Provedor de embeddings (on-premise por padrão).
    'embeddings' => [
        'driver' => env('EMBEDDINGS_DRIVER', 'ollama'), // ollama | openai | fake
        'model' => env('EMBEDDINGS_MODEL', 'nomic-embed-text'),
        'dimensions' => (int) env('EMBEDDINGS_DIM', 768),
        'timeout' => (int) env('EMBEDDINGS_TIMEOUT', 30),

        // Ollama (on-premise)
        'ollama_base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),

        // OpenAI (opcional)
        'openai_key' => env('OPENAI_API_KEY'),
        'openai_base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com'),
    ],

    // Vector store. 'mysql' guarda os vetores no banco e calcula a similaridade
    // na aplicação (sem nova infra). A interface permite trocar por Qdrant/pgvector.
    'vector_store' => [
        'driver' => env('CHATBOT_VECTOR_DRIVER', 'mysql'),
        'top_k' => (int) env('CHATBOT_TOPK', 6),
        // Limite de candidatos lidos do banco antes do cálculo de cosseno (proteção de desempenho).
        'candidate_limit' => (int) env('CHATBOT_CANDIDATES', 500),
        'min_score' => (float) env('CHATBOT_MIN_SCORE', 0.05),
    ],

    // Fragmentação do texto dos documentos.
    'chunk' => [
        'size' => (int) env('CHATBOT_CHUNK_SIZE', 1000),
        'overlap' => (int) env('CHATBOT_CHUNK_OVERLAP', 150),
    ],

    // Combina busca vetorial com a busca por palavra-chave existente (melhora recall).
    'hybrid' => (bool) env('CHATBOT_HYBRID', true),

    // Fila usada pelos jobs de indexação.
    'queue' => env('CHATBOT_QUEUE', 'default'),

    // Limite de perguntas por minuto por utilizador.
    'rate_limit' => (int) env('CHATBOT_RATE_LIMIT', 20),
];
