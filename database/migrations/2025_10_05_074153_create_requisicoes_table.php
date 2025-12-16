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
        Schema::create('requisicoes', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['produto', 'oficina', 'servico']);
            $table->string('codigo_sequencial')->unique();
            $table->date('data_requisicao');
            $table->foreignId('usuario_id')->constrained('users');
            $table->enum('status', ['pendente', 'aprovada', 'rejeitada', 'concluida'])->default('pendente');
            $table->string('empresa_destinataria')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('aprovado_por')->nullable()->constrained('users');
            $table->dateTime('data_aprovacao')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisicoes');
    }
};
