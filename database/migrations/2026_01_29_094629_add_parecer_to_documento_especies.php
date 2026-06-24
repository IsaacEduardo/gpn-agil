<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('documento_especies')->insertOrIgnore([
            ['nome' => 'Parecer', 'ativo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        DB::table('documento_especies')->where('nome', 'Parecer')->delete();
    }
};
