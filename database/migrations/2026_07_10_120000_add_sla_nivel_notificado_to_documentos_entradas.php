<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda o último nível de SLA já notificado ao chefe (warning/critical),
     * para tornar `docs:check-sla` idempotente e evitar re-notificar o mesmo
     * documento em todas as execuções diárias.
     */
    public function up(): void
    {
        Schema::table('documentos_entradas', function (Blueprint $table) {
            $table->string('sla_nivel_notificado')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_entradas', function (Blueprint $table) {
            $table->dropColumn('sla_nivel_notificado');
        });
    }
};
