<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comando Artisan para expurgo/retenção de logs antigos de auditoria e logins.
 * Previne crescimento descontrolado das tabelas audit_logs e login_histories.
 */
class AuditPruneCommand extends Command
{
    protected $signature = 'audit:prune {--days=365 : Número de dias de retenção}';

    protected $description = 'Expurga registros de auditoria e históricos de login mais antigos do que a quantidade de dias especificada.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Iniciando expurgo de logs anteriores a: {$cutoffDate->toDateTimeString()} ({$days} dias)");

        $auditDeleted = DB::table('audit_logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        $loginDeleted = DB::table('login_histories')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        $this->info("Expurgo concluído: {$auditDeleted} registros de audit_logs e {$loginDeleted} registros de login_histories removidos.");

        return Command::SUCCESS;
    }
}
