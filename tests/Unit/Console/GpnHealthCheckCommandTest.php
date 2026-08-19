<?php

namespace Tests\Unit\Console;

use Tests\TestCase;

class GpnHealthCheckCommandTest extends TestCase
{
    public function test_comando_health_check_executa_com_sucesso(): void
    {
        $this->artisan('gpn:health-check')
            ->assertExitCode(0);
    }

    public function test_comando_health_check_retorna_json_estruturado(): void
    {
        $this->artisan('gpn:health-check --json')
            ->expectsOutputToContain('"status": "HEALTHY"')
            ->assertExitCode(0);
    }
}
