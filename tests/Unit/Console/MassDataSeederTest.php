<?php

namespace Tests\Unit\Console;

use Database\Seeders\MassDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MassDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_mass_data_seeder_insere_registos_corretamente(): void
    {
        $this->seed(MassDataSeeder::class);

        $this->assertEquals(2000, DB::table('documentos_entradas')->count());
        $this->assertEquals(1500, DB::table('documento_internos')->count());
        $this->assertEquals(1000, DB::table('lotes')->count());
        $this->assertEquals(1000, DB::table('requisicoes')->count());
    }
}
