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
            $table->foreignId('pasta_id')->nullable()->constrained('pastas')->onDelete('set null');
            $table->boolean('arquivado')->default(false);
            $table->dateTime('arquivado_em')->nullable();
            $table->foreignId('arquivado_por')->nullable()->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documento_internos', function (Blueprint $table) {
            $table->dropForeign(['pasta_id']);
            $table->dropForeign(['arquivado_por']);
            $table->dropColumn(['pasta_id', 'arquivado', 'arquivado_em', 'arquivado_por']);
        });
    }
};
