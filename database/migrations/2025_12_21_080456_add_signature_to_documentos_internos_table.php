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
        Schema::table('documento_internos', function (Blueprint $table) {
            $table->timestamp('assinado_em')->nullable();
            $table->foreignId('assinado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assinatura_hash')->nullable(); // SHA-256 hash
            $table->boolean('bloqueado_edicao')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documento_internos', function (Blueprint $table) {
            $table->dropForeign(['assinado_por_user_id']);
            $table->dropColumn(['assinado_em', 'assinado_por_user_id', 'assinatura_hash', 'bloqueado_edicao']);
        });
    }
};
