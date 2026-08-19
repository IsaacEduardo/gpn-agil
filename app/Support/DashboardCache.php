<?php

namespace App\Support;

use App\Enums\DocumentoStatus;
use App\Models\DocumentoInterno;
use App\Models\Gabinete;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Helper de Caching Estratégico em Redis para os Dashboards de Gabinete e Departamento.
 * Evita múltiplas queries agregadoras pesadas a cada carregamento de página.
 */
class DashboardCache
{
    private const TTL_SECONDS = 120; // 2 minutos de cache por default

    /**
     * Obtém as estatísticas do Gabinete via Cache Redis com invalidação reativa.
     */
    public static function getGabineteStats(int $gabineteId): array
    {
        return Cache::remember("dashboard_gabinete_stats_{$gabineteId}", self::TTL_SECONDS, function () use ($gabineteId) {
            $statusDistrib = DocumentoInterno::whereHas('departamento', fn ($q) => $q->where('gabinete_id', $gabineteId))
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->mapWithKeys(function ($item) {
                    $statusKey = $item->status instanceof DocumentoStatus ? $item->status->value : $item->status;
                    return [$statusKey => $item->count];
                });

            $assinadosHoje = DocumentoInterno::whereHas('departamento', fn ($q) => $q->where('gabinete_id', $gabineteId))
                ->where('status', DocumentoStatus::ASSINADO)
                ->whereDate('assinado_em', today())
                ->count();

            return [
                'total_docs' => $statusDistrib->sum(),
                'em_analise' => (int) $statusDistrib->get(DocumentoStatus::EM_ANALISE->value, 0),
                'assinados_hoje' => $assinadosHoje,
                'status_distrib' => $statusDistrib->toArray(),
            ];
        });
    }

    /**
     * Limpa o cache do Dashboard de um Gabinete.
     */
    public static function forgetGabinete(int $gabineteId): void
    {
        Cache::forget("dashboard_gabinete_stats_{$gabineteId}");
    }

    /**
     * Limpa o cache do Dashboard de um Departamento.
     */
    public static function forgetDepartamento(int $departamentoId): void
    {
        Cache::forget("dashboard_dept_stats_{$departamentoId}");
    }
}
