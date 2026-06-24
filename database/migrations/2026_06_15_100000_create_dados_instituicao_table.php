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
        Schema::create('dados_instituicao', function (Blueprint $table) {
            $table->id();
            $table->string('nome_oficial');
            $table->string('sigla', 10);
            $table->string('cidade', 50)->default('Moçâmedes');
            $table->string('nif', 20)->nullable();
            $table->string('telefone', 30)->nullable();
            $table->string('email', 100)->nullable();
            $table->text('endereco')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cabecalho_linha1')->nullable();
            $table->string('cabecalho_linha2')->nullable();
            $table->string('cabecalho_linha3')->nullable();
            $table->text('rodape_texto')->nullable();
            $table->string('rodape_img_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dados_instituicao');
    }
};
