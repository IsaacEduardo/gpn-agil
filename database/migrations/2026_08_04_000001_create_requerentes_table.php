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
        Schema::create('requerentes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('tipo_pessoa', ['FISICA', 'JURIDICA'])->default('FISICA');
            $table->string('nome_razao_social');
            $table->string('nif_bi')->unique();
            $table->string('email')->nullable();
            $table->string('telefone')->nullable();
            $table->string('telemovel_alternativo')->nullable();
            $table->string('representante_nome')->nullable();
            $table->string('representante_nif_bi')->nullable();
            $table->text('endereco_completo')->nullable();
            $table->string('municipio')->default('Namibe');
            $table->string('comuna')->nullable();
            $table->string('bairro')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requerentes');
    }
};
