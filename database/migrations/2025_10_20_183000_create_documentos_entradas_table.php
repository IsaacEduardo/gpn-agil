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
        Schema::create('documentos_entradas', function (Blueprint $table) {
            $table->id();
            // Número sequencial por ano
            $table->unsignedInteger('numero_sequencial');
            $table->unsignedSmallInteger('ano_referencia');

            // Dados principais
            $table->date('data_entrada');
            $table->string('classificacao_especie', 100)->nullable();
            $table->string('classificacao_ref_numero', 100)->nullable();
            $table->date('data_documento')->nullable();
            $table->string('procedencia', 255)->nullable();
            $table->string('assunto', 500);
            $table->text('observacoes')->nullable();

            // Saída do gabinete e encaminhamento
            $table->date('saida_gabinete_data')->nullable();
            $table->string('encaminhamento_orgao', 255)->nullable();
            $table->string('encaminhamento_oficio_numero', 100)->nullable();
            $table->date('encaminhamento_data')->nullable();

            // Controle
            $table->foreignId('departamento_id')->constrained('departamentos');
            $table->foreignId('user_id')->constrained('users');
            $table->string('status', 30)->default('registrado');
            $table->string('arquivo_caminho', 255)->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Índices
            $table->index(['ano_referencia', 'numero_sequencial'], 'docent_ano_num_idx');
            $table->index('data_entrada', 'docent_data_entrada_idx');
            $table->index('departamento_id', 'docent_departamento_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documentos_entradas');
    }
};
