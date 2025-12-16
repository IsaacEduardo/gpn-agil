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
        Schema::create('documento_encaminhamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_entrada_id')->constrained('documentos_entradas')->cascadeOnDelete();
            $table->foreignId('origem_departamento_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->foreignId('destino_departamento_id')->constrained('departamentos')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recebido_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('encaminhado_em')->nullable();
            $table->timestamp('recebido_em')->nullable();
            $table->string('status', 30)->default('encaminhado');
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['documento_entrada_id']);
            $table->index(['destino_departamento_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento_encaminhamentos');
    }
};
