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
        Schema::create('requisicao_servicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicao_id')->constrained('requisicoes')->onDelete('cascade');
            $table->string('tipo_servico');
            $table->string('local');
            $table->enum('prioridade', ['Baixa', 'Média', 'Alta', 'Urgente'])->default('Média');
            $table->dateTime('data_hora_desejada')->nullable();
            $table->text('descricao')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisicao_servicos');
    }
};
