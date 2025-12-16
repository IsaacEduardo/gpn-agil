<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }
        // Tornar empresa_destinataria opcional para compatibilizar com empresa_id
        DB::statement('ALTER TABLE requisicoes MODIFY empresa_destinataria VARCHAR(255) NULL');
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }
        // Reverter para NOT NULL (sem default)
        DB::statement('ALTER TABLE requisicoes MODIFY empresa_destinataria VARCHAR(255) NOT NULL');
    }
};
