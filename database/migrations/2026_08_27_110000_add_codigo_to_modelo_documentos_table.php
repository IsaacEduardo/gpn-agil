<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('modelo_documentos', 'codigo')) {
            Schema::table('modelo_documentos', function (Blueprint $table) {
                $table->string('codigo')->nullable()->unique()->after('nome');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('modelo_documentos', 'codigo')) {
            Schema::table('modelo_documentos', function (Blueprint $table) {
                $table->dropColumn('codigo');
            });
        }
    }
};
