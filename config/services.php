<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ocr' => [
        'path' => env('OCR_BINARY_PATH'),
    ],

    'anthropic' => [
        // Driver do provedor de IA: 'anthropic' (produção) ou 'fake' (demonstração local, sem custos/rede).
        'driver' => env('ASSISTENTE_DRIVER', 'anthropic'),
        'key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        // Modelo padrão (respostas fundamentadas) e modelo rápido/barato.
        // Confirmar IDs/preços atuais na documentação da Anthropic antes de produção.
        'model' => env('ASSISTENTE_MODEL', 'claude-sonnet-4-6'),
        'model_fast' => env('ASSISTENTE_MODEL_FAST', 'claude-haiku-4-5-20251001'),
        'max_tokens' => (int) env('ASSISTENTE_MAX_TOKENS', 1024),
        // Orçamento de caracteres do contexto enviado ao modelo (proxy de tokens).
        'context_char_budget' => (int) env('ASSISTENTE_CONTEXT_CHARS', 24000),
        'timeout' => (int) env('ASSISTENTE_TIMEOUT', 60),
    ],

];
