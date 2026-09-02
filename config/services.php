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
        'tessdata_path' => env('OCR_TESSDATA_PATH', storage_path('app/tessdata')),
        'languages' => env('OCR_LANGUAGES', 'por+eng'),
        'min_native_words' => (int) env('OCR_MIN_NATIVE_WORDS', 50),
        'pdftoppm_path' => env('OCR_PDFTOPPM_PATH'),
        'gs_path' => env('OCR_GS_PATH'),
        'magick_path' => env('OCR_MAGICK_PATH'),
        'dpi' => (int) env('OCR_DPI', 300),
    ],

    // Webhook de bounces/reclamações de e-mail. Defina um token partilhado com o
    // provedor de e-mail (SES/Mailgun/Postmark) para autenticar as chamadas.
    'mail_webhook' => [
        'token' => env('MAIL_WEBHOOK_TOKEN'),
    ],

    'deepseek' => [
        'driver' => 'deepseek',
        'key' => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'model' => env('ASSISTENTE_MODEL', 'deepseek-chat'),
        'max_tokens' => (int) env('ASSISTENTE_MAX_TOKENS', 1024),
        'timeout' => (int) env('ASSISTENTE_TIMEOUT', 60),
    ],

    'openai' => [
        'driver' => 'openai',
        'key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('ASSISTENTE_MODEL', 'gpt-4o-mini'),
        'max_tokens' => (int) env('ASSISTENTE_MAX_TOKENS', 1024),
        'timeout' => (int) env('ASSISTENTE_TIMEOUT', 60),
    ],

    'anthropic' => [
        // Driver do provedor de IA: 'deepseek' (DeepSeek), 'openai' (OpenAI / ChatGPT), 'anthropic' (produção), 'kimi' (Kimi AI / Moonshot) ou 'fake'.
        'driver' => env('ASSISTENTE_DRIVER', 'deepseek'),
        'key' => env('ANTHROPIC_API_KEY'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        'model' => env('ASSISTENTE_MODEL', 'claude-sonnet-4-6'),
        'model_fast' => env('ASSISTENTE_MODEL_FAST', 'claude-haiku-4-5-20251001'),
        'max_tokens' => (int) env('ASSISTENTE_MAX_TOKENS', 1024),
        'context_char_budget' => (int) env('ASSISTENTE_CONTEXT_CHARS', 24000),
        'timeout' => (int) env('ASSISTENTE_TIMEOUT', 60),
    ],

    'kimi' => [
        'driver' => 'kimi',
        'key' => env('KIMI_API_KEY', env('MOONSHOT_API_KEY')),
        'base_url' => env('KIMI_BASE_URL', 'https://api.moonshot.ai/v1'),
        'model' => env('ASSISTENTE_MODEL', 'kimi-k2.6'),
        'max_tokens' => (int) env('ASSISTENTE_MAX_TOKENS', 1024),
        'timeout' => (int) env('ASSISTENTE_TIMEOUT', 60),
    ],

];
