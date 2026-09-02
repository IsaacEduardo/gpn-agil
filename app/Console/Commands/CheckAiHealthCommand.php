<?php

namespace App\Console\Commands;

use App\Services\Ai\LlmClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckAiHealthCommand extends Command
{
    /**
     * O nome e assinatura do comando.
     *
     * @var string
     */
    protected $signature = 'ai:check-health {--json : Retorna o status em formato JSON estruturado para monitorização}';

    /**
     * A descrição do comando.
     *
     * @var string
     */
    protected $description = 'Verifica a saúde, conectividade e saldo das APIs do Assistente de IA (DeepSeek / OpenAI / Kimi) e do Gotenberg PDF Engine';

    /**
     * Executa o comando.
     */
    public function handle(LlmClient $llmClient): int
    {
        $driver = env('ASSISTENTE_DRIVER', config('services.deepseek.driver', 'deepseek'));
        $asJson = $this->option('json');

        $status = [
            'timestamp' => now()->toIso8601String(),
            'ai_driver' => $driver,
            'ai_status' => 'unknown',
            'ai_message' => '',
            'gotenberg_status' => 'unknown',
            'gotenberg_message' => '',
        ];

        // 1. Checar serviço de IA
        if (in_array(strtolower($driver), ['deepseek'])) {
            $key = config('services.deepseek.key');
            $baseUrl = rtrim(config('services.deepseek.base_url', 'https://api.deepseek.com'), '/');

            if (empty($key)) {
                $status['ai_status'] = 'error';
                $status['ai_message'] = 'DEEPSEEK_API_KEY ausente ou não configurada no .env';
            } else {
                try {
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . trim($key),
                    ])->timeout(5)->post($baseUrl . '/chat/completions', [
                        'model' => config('services.deepseek.model', 'deepseek-chat'),
                        'messages' => [['role' => 'user', 'content' => 'ping']],
                        'max_tokens' => 1,
                    ]);

                    if ($response->successful()) {
                        $status['ai_status'] = 'ok';
                        $status['ai_message'] = 'DeepSeek API ativa, conectada e com saldo disponível.';
                    } elseif ($response->status() === 402 || str_contains($response->body(), 'Insufficient Balance') || str_contains($response->body(), 'insufficient_quota')) {
                        $status['ai_status'] = 'critical_quota';
                        $status['ai_message'] = 'ALERTA: Conta DeepSeek sem saldo/crédito disponível. Recarga necessária em https://platform.deepseek.com/top_up.';
                        Log::emergency('ALERTA SRE: Conta DeepSeek sem saldo/crédito. Recarga necessária em https://platform.deepseek.com/top_up');
                    } elseif ($response->status() === 401) {
                        $status['ai_status'] = 'error';
                        $status['ai_message'] = 'DEEPSEEK_API_KEY inválida (HTTP 401 Unauthorized).';
                        Log::error('ALERTA SRE: DEEPSEEK_API_KEY inválida no .env');
                    } else {
                        $status['ai_status'] = 'warning';
                        $status['ai_message'] = 'API DeepSeek devolveu status inesperado: ' . $response->status();
                    }
                } catch (\Throwable $e) {
                    $status['ai_status'] = 'offline';
                    $status['ai_message'] = 'Falha de conexão com a API DeepSeek: ' . $e->getMessage();
                }
            }
        } elseif (in_array(strtolower($driver), ['openai', 'chatgpt'])) {
            $key = config('services.openai.key');
            $baseUrl = rtrim(config('services.openai.base_url', 'https://api.openai.com/v1'), '/');

            if (empty($key)) {
                $status['ai_status'] = 'error';
                $status['ai_message'] = 'OPENAI_API_KEY ausente ou não configurada no .env';
            } else {
                try {
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . trim($key),
                    ])->timeout(5)->post($baseUrl . '/chat/completions', [
                        'model' => config('services.openai.model', 'gpt-4o-mini'),
                        'messages' => [['role' => 'user', 'content' => 'ping']],
                        'max_tokens' => 1,
                    ]);

                    if ($response->successful()) {
                        $status['ai_status'] = 'ok';
                        $status['ai_message'] = 'OpenAI API (ChatGPT) ativa, conectada e com saldo disponível.';
                    } elseif ($response->status() === 429 || str_contains($response->body(), 'insufficient_quota') || str_contains($response->body(), 'credit_balance_exhausted')) {
                        $status['ai_status'] = 'critical_quota';
                        $status['ai_message'] = 'ALERTA: Conta OpenAI sem saldo/crédito disponível (credit_balance_exhausted). Adicione créditos em https://platform.openai.com/settings/organization/billing/.';
                        Log::emergency('ALERTA SRE: Conta OpenAI sem saldo/crédito. Recarga necessária em https://platform.openai.com/settings/organization/billing/');
                    } elseif ($response->status() === 401) {
                        $status['ai_status'] = 'error';
                        $status['ai_message'] = 'OPENAI_API_KEY inválida (HTTP 401 Unauthorized).';
                        Log::error('ALERTA SRE: OPENAI_API_KEY inválida no .env');
                    } else {
                        $status['ai_status'] = 'warning';
                        $status['ai_message'] = 'API OpenAI devolveu status inesperado: ' . $response->status();
                    }
                } catch (\Throwable $e) {
                    $status['ai_status'] = 'offline';
                    $status['ai_message'] = 'Falha de conexão com a API OpenAI: ' . $e->getMessage();
                }
            }
        } elseif (in_array(strtolower($driver), ['kimi', 'moonshot'])) {
            $key = config('services.kimi.key');
            $baseUrl = rtrim(config('services.kimi.base_url', 'https://api.moonshot.ai/v1'), '/');

            if (empty($key)) {
                $status['ai_status'] = 'error';
                $status['ai_message'] = 'KIMI_API_KEY ausente ou não configurada no .env';
            } else {
                try {
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . trim($key),
                    ])->timeout(5)->get($baseUrl . '/models');

                    if ($response->successful()) {
                        $status['ai_status'] = 'ok';
                        $status['ai_message'] = 'Kimi AI API ativa e com saldo disponível.';
                    } elseif ($response->status() === 429 || str_contains($response->body(), 'insufficient balance')) {
                        $status['ai_status'] = 'critical_quota';
                        $status['ai_message'] = 'ALERTA SRE: Conta Kimi IA suspensa por falta de saldo (insufficient balance).';
                        Log::emergency('ALERTA SRE: Conta Kimi IA suspensa por falta de saldo. Recarga necessária em https://platform.moonshot.ai');
                    } elseif ($response->status() === 401) {
                        $status['ai_status'] = 'error';
                        $status['ai_message'] = 'KIMI_API_KEY inválida (HTTP 401 Unauthorized).';
                        Log::error('ALERTA SRE: KIMI_API_KEY inválida no .env');
                    } else {
                        $status['ai_status'] = 'warning';
                        $status['ai_message'] = 'API Kimi devolveu status inesperado: ' . $response->status();
                    }
                } catch (\Throwable $e) {
                    $status['ai_status'] = 'offline';
                    $status['ai_message'] = 'Falha de conexão com a API Kimi: ' . $e->getMessage();
                }
            }
        } else {
            $status['ai_status'] = 'ok';
            $status['ai_message'] = "Provedor de IA em modo '{$driver}'";
        }

        // 2. Checar Gotenberg PDF Engine
        $gotenbergUrl = env('GOTENBERG_URL');
        if (! empty($gotenbergUrl)) {
            try {
                $res = Http::timeout(3)->get(rtrim($gotenbergUrl, '/') . '/health');
                if ($res->successful()) {
                    $status['gotenberg_status'] = 'ok';
                    $status['gotenberg_message'] = 'Gotenberg PDF Engine ativo (Chromium Ready).';
                } else {
                    $status['gotenberg_status'] = 'fallback';
                    $status['gotenberg_message'] = 'Gotenberg devolveu HTTP ' . $res->status() . '. Fallback Dompdf ativo.';
                }
            } catch (\Throwable $e) {
                $status['gotenberg_status'] = 'fallback';
                $status['gotenberg_message'] = 'Gotenberg offline. Fallback Dompdf ativo.';
            }
        } else {
            $status['gotenberg_status'] = 'fallback';
            $status['gotenberg_message'] = 'GOTENBERG_URL não definida. Fallback Dompdf ativo por padrão.';
        }

        if ($asJson) {
            $this->output->writeln(json_encode($status, JSON_PRETTY_PRINT));
            return $status['ai_status'] === 'critical_quota' ? 2 : 0;
        }

        $this->info("=== DIAGNÓSTICO DE INFRAESTRUTURA & IA (GPN-AGIL) ===");
        $this->line("Provedor de IA: <comment>{$driver}</comment>");
        
        if ($status['ai_status'] === 'ok') {
            $this->info("✔ IA Status: " . $status['ai_message']);
        } elseif ($status['ai_status'] === 'critical_quota') {
            $this->error("✖ IA Status: " . $status['ai_message']);
        } else {
            $this->warn("⚠ IA Status: " . $status['ai_message']);
        }

        if ($status['gotenberg_status'] === 'ok') {
            $this->info("✔ PDF Engine: " . $status['gotenberg_message']);
        } else {
            $this->warn("⚠ PDF Engine: " . $status['gotenberg_message']);
        }

        return $status['ai_status'] === 'critical_quota' ? 2 : 0;
    }
}
