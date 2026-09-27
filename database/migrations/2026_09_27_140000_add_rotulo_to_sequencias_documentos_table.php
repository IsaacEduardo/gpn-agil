<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rótulo legível de cada série (ex.: "Ofícios — Secretaria Geral") para o ecrã "Numeração".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sequencias_documentos', 'rotulo')) {
            Schema::table('sequencias_documentos', function (Blueprint $table) {
                $table->string('rotulo')->nullable()->after('chave');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sequencias_documentos', 'rotulo')) {
            Schema::table('sequencias_documentos', function (Blueprint $table) {
                $table->dropColumn('rotulo');
            });
        }
    }
};
