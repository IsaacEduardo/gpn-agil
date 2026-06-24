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
        Schema::create('documento_relacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_id')->constrained('documentos_entradas')->cascadeOnDelete();
            $table->foreignId('relacionado_id')->constrained('documentos_entradas')->cascadeOnDelete();
            $table->string('tipo')->default('relacionado'); // relacionado, resposta, anexo, etc.
            $table->timestamps();

            $table->unique(['documento_id', 'relacionado_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento_relacoes');
    }
};
