<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca quando um documento crítico foi escalado para o responsável do gabinete,
     * para escalar apenas uma vez.
     */
    public function up(): void
    {
        Schema::table('documentos_entradas', function (Blueprint $table) {
            $table->timestamp('sla_escalado_em')->nullable()->after('sla_nivel_notificado');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_entradas', function (Blueprint $table) {
            $table->dropColumn('sla_escalado_em');
        });
    }
};
