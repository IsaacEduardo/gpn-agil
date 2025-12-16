<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_entradas', function (Blueprint $table) {
            $table->unique(['ano_referencia', 'numero_sequencial'], 'docent_year_seq_unique');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_entradas', function (Blueprint $table) {
            $table->dropUnique('docent_year_seq_unique');
        });
    }
};
