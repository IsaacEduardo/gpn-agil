<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SimpleBroadcastNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class QueueDiagnoseCommand extends Command
{
    protected $signature = 'queue:diagnose {--queue=notifications} {--driver=} {--run}';

    protected $description = 'Diagnostica a fila ativa, enfileira teste e opcionalmente consome os itens disponíveis';

    public function handle(): int
    {
        $queue = (string) $this->option('queue');
        $driver = (string) ($this->option('driver') ?: config('queue.default'));
        $this->info("Driver: {$driver}");
        $this->info("Queue: {$queue}");

        $countBefore = $this->countQueue($driver, $queue);
        $this->line("Itens pendentes: {$countBefore}");

        $user = User::first();
        if (! $user) {
            $this->error('Nenhum usuário disponível para teste.');

            return self::INVALID;
        }
        $user->notify(new SimpleBroadcastNotification('Diagnóstico de Fila', 'Teste de enfileiramento', url('/notifications')));
        $this->info('Notificação de teste enfileirada.');

        $countAfter = $this->countQueue($driver, $queue);
        $this->line("Itens pendentes após enfileirar: {$countAfter}");

        if ($this->option('run')) {
            $this->info('Processando itens disponíveis...');
            Artisan::call('queue:work', [
                '--queue' => $queue,
                '--sleep' => 1,
                '--tries' => 1,
                '--stop-when-empty' => true,
            ]);
            $this->line(Artisan::output());
            $countFinal = $this->countQueue($driver, $queue);
            $this->line("Itens pendentes após processamento: {$countFinal}");
        }

        return self::SUCCESS;
    }

    protected function countQueue(string $driver, string $queue): int
    {
        if ($driver === 'database') {
            $table = config('queue.connections.database.table', 'jobs');

            return (int) DB::table($table)->where('queue', $queue)->count();
        }
        if ($driver === 'redis') {
            $conn = config('queue.connections.redis.connection', 'default');
            try {
                return (int) Redis::connection($conn)->llen('queues:'.$queue);
            } catch (\Throwable $e) {
                return 0;
            }
        }

        return 0;
    }
}
