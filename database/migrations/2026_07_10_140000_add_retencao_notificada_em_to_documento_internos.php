<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca quando o alerta de temporalidade (retenção) já foi notificado, para
     * tornar o CheckRetentionPolicy idempotente e evitar re-notificar diariamente.
     */
    public function up(): void
    {
        Schema::table('documento_internos', function (Blueprint $table) {
            $table->timestamp('retencao_notificada_em')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('documento_internos', function (Blueprint $table) {
            $table->dropColumn('retencao_notificada_em');
        });
    }
};
