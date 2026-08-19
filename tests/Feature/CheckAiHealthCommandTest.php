<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckAiHealthCommandTest extends TestCase
{
    public function test_health_check_command_runs_successfully()
    {
        Http::fake([
            'https://api.moonshot.ai/v1/models' => Http::response(['data' => []], 200),
            'http://127.0.0.1:3000/health' => Http::response(['status' => 'up'], 200),
        ]);

        $this->artisan('ai:check-health')
            ->assertExitCode(0);
    }

    public function test_health_check_command_returns_json_structured_output()
    {
        Http::fake([
            'https://api.moonshot.ai/v1/models' => Http::response(['data' => []], 200),
        ]);

        $this->artisan('ai:check-health --json')
            ->expectsOutputToContain('"ai_status": "ok"')
            ->assertExitCode(0);
    }
}
