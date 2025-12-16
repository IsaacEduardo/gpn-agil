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
        Schema::create('requisicao_produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicao_id')->constrained('requisicoes')->onDelete('cascade');
            $table->string('nome_produto');
            $table->integer('quantidade');
            $table->string('unidade_medida');
            $table->enum('prioridade', ['Baixa', 'Média', 'Alta', 'Urgente'])->default('Média');
            $table->text('finalidade')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisicao_produtos');
    }
};
