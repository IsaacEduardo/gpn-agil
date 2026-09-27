<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contadores de numeração dos documentos internos, um por âmbito e ano.
 *
 * A chave é o prefixo da referência (ex.: "DLP/MEMO", "GAB:1/OF") e não o id da
 * espécie: espécies diferentes podem partilhar a mesma abreviatura (DECL), e o que
 * tem de ser único é a referência impressa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sequencias_documentos')) {
            return;
        }

        Schema::create('sequencias_documentos', function (Blueprint $table) {
            $table->id();
            $table->string('chave', 120);
            $table->unsignedSmallInteger('ano');
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->timestamps();

            $table->unique(['chave', 'ano']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequencias_documentos');
    }
};
