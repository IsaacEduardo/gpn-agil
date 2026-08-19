<?php

namespace Tests\Unit\Support;

use App\Support\DashboardCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardCacheTest extends TestCase
{
    public function test_pode_armazenar_e_limpar_cache_do_gabinete(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn([
                'total_docs' => 10,
                'em_analise' => 4,
                'assinados_hoje' => 2,
                'status_distrib' => [],
            ]);

        $stats = DashboardCache::getGabineteStats(gabineteId: 1);

        $this->assertEquals(10, $stats['total_docs']);
        $this->assertEquals(4, $stats['em_analise']);
    }

    public function test_pode_esquecer_cache_do_gabinete(): void
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with('dashboard_gabinete_stats_5');

        DashboardCache::forgetGabinete(gabineteId: 5);
    }
}
