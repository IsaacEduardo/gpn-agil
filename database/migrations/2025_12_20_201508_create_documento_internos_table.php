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
        Schema::create('documento_internos', function (Blueprint $table) {
            $table->id();
            $table->string('numero_referencia')->unique()->nullable();
            $table->string('titulo');
            $table->longText('conteudo_final')->nullable();
            $table->foreignId('documento_especie_id')->constrained('documento_especies');
            $table->foreignId('modelo_documento_id')->nullable()->constrained('modelo_documentos')->nullOnDelete();
            $table->foreignId('documento_entrada_id')->nullable()->constrained('documentos_entradas')->nullOnDelete();
            $table->foreignId('criado_por')->constrained('users');
            $table->foreignId('departamento_id')->constrained('departamentos');
            $table->string('status')->default('rascunho');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento_internos');
    }
};
