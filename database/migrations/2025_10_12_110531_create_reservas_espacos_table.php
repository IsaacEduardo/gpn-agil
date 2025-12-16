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
        Schema::create('reservas_espacos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_reserva')->unique();
            $table->enum('tipo_espaco', ['salao_nobre', 'anfiteatro']);
            $table->string('solicitante_nome');
            $table->string('solicitante_email');
            $table->string('solicitante_telefone')->nullable();
            $table->string('evento_titulo');
            $table->text('evento_descricao')->nullable();
            $table->date('data_evento');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->integer('numero_participantes')->nullable();
            $table->enum('status', ['pendente', 'aprovada', 'rejeitada', 'cancelada'])->default('pendente');
            $table->text('observacoes')->nullable();
            $table->text('motivo_rejeicao')->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('aprovado_por')->nullable();
            $table->timestamp('data_aprovacao')->nullable();
            $table->timestamps();

            // Índices para otimização de consultas
            $table->index(['data_evento', 'tipo_espaco']);
            $table->index(['status']);
            $table->index(['usuario_id']);

            // Chaves estrangeiras
            $table->foreign('usuario_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('aprovado_por')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservas_espacos');
    }
};
