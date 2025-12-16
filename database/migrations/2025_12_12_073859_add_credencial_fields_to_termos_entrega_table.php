<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('termos_entrega', function (Blueprint $table) {
            $table->date('beneficiario_documento_emitido_em')->nullable()->after('beneficiario_documento');
            $table->string('beneficiario_documento_emitido_local')->nullable()->after('beneficiario_documento_emitido_em');
        });

        // Atualizar ENUM para incluir 'credencial'
        try {
            DB::statement("ALTER TABLE termos_entrega MODIFY tipo ENUM('definitiva','devolutivo','viatura','credencial') DEFAULT 'definitiva'");
        } catch (\Throwable $e) {
            // Ignorar se não suportado (ex: sqlite)
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('termos_entrega', function (Blueprint $table) {
            $table->dropColumn(['beneficiario_documento_emitido_em', 'beneficiario_documento_emitido_local']);
        });
    }
};
