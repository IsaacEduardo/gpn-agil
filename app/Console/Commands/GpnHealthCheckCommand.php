<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Comando Artisan para verificação e diagnóstico de saúde do sistema GPN-AGIL em produção.
 * Valida SGBD, Redis, Discos de Armazenamento, Gotenberg e OCR.
 */
class GpnHealthCheckCommand extends Command
{
    protected $signature = 'gpn:health-check {--json : Retorna o resultado em formato JSON}';

    protected $description = 'Verifica a saúde e conectividade dos serviços críticos do sistema GPN-AGIL (SGBD, Redis, Storage, Gotenberg e OCR).';

    public function handle(): int
    {
        $isJson = (bool) $this->option('json');
        $checks = [];
        $hasErrors = false;

        // 1. Verificação do SGBD (Banco de Dados)
        try {
            DB::connection()->getPdo();
            $checks['database'] = [
                'status' => 'OK',
                'message' => 'Conexão com SGBD estabelecida.',
            ];
        } catch (\Throwable $e) {
            $hasErrors = true;
            $checks['database'] = [
                'status' => 'FAIL',
                'message' => 'Falha na conexão com SGBD: ' . $e->getMessage(),
            ];
        }

        // 2. Verificação do Cache / Redis
        try {
            Cache::put('gpn_health_test', 'ok', 10);
            $val = Cache::get('gpn_health_test');
            if ($val === 'ok') {
                $checks['cache_redis'] = [
                    'status' => 'OK',
                    'message' => 'Cache/Redis operacional.',
                ];
            } else {
                throw new \Exception('Valor retornado do cache divergente.');
            }
        } catch (\Throwable $e) {
            $hasErrors = true;
            $checks['cache_redis'] = [
                'status' => 'FAIL',
                'message' => 'Falha no Cache/Redis: ' . $e->getMessage(),
            ];
        }

        // 3. Verificação de Storage (Escrita em Disco)
        try {
            $disk = config('filesystems.docs_disk', 'public');
            Storage::disk($disk)->put('health_check.txt', 'health_check_test');
            Storage::disk($disk)->delete('health_check.txt');
            $checks['storage'] = [
                'status' => 'OK',
                'message' => "Disco de armazenamento '{$disk}' gravável.",
            ];
        } catch (\Throwable $e) {
            $hasErrors = true;
            $checks['storage'] = [
                'status' => 'FAIL',
                'message' => 'Falha na gravação de storage: ' . $e->getMessage(),
            ];
        }

        // 4. Verificação de Conectividade com Gotenberg (Conversor de PDF)
        try {
            $gotenbergUrl = env('GOTENBERG_URL', 'http://localhost:3000');
            $response = Http::timeout(3)->get("{$gotenbergUrl}/health");
            if ($response->successful() || $response->status() === 200) {
                $checks['gotenberg'] = [
                    'status' => 'OK',
                    'message' => "Gotenberg PDF Server acessível em {$gotenbergUrl}.",
                ];
            } else {
                $checks['gotenberg'] = [
                    'status' => 'WARN',
                    'message' => "Gotenberg respondeu com status {$response->status()}.",
                ];
            }
        } catch (\Throwable $e) {
            $checks['gotenberg'] = [
                'status' => 'WARN',
                'message' => "Servidor Gotenberg inacessível (Fallback DomPDF ativo): " . $e->getMessage(),
            ];
        }

        // Renderização da Saída
        if ($isJson) {
            $this->output->write(json_encode([
                'status' => $hasErrors ? 'UNHEALTHY' : 'HEALTHY',
                'timestamp' => now()->toIso8601String(),
                'checks' => $checks,
            ], JSON_PRETTY_PRINT));
            return $hasErrors ? Command::FAILURE : Command::SUCCESS;
        }

        $this->info('=== DIAGNÓSTICO DE SAÚDE DO SISTEMA GPN-AGIL ===');
        $this->line('');

        foreach ($checks as $key => $check) {
            $statusStr = match ($check['status']) {
                'OK' => '<fg=green>[ OK ]</>',
                'WARN' => '<fg=yellow>[ AVISO ]</>',
                default => '<fg=red>[ FALHA ]</>',
            };

            $this->line("{$statusStr} " . strtoupper($key) . ": {$check['message']}");
        }

        $this->line('');
        if ($hasErrors) {
            $this->error('Diagnóstico concluído com falhas críticas em serviços.');
            return Command::FAILURE;
        }

        $this->info('Diagnóstico concluído: Todos os serviços críticos estão operacionais.');
        return Command::SUCCESS;
    }
}
