<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->string('tipo', 20); // interno | externo
            $table->unsignedBigInteger('anexo_id')->nullable();
            $table->unsignedInteger('pagina')->nullable();
            $table->unsignedInteger('indice')->default(0);
            $table->longText('conteudo');
            $table->string('conteudo_hash', 64);
            // Denormalização para pré-filtro de permissão na busca vetorial.
            $table->unsignedBigInteger('departamento_id')->nullable();
            $table->unsignedBigInteger('gabinete_id')->nullable();
            $table->json('embedding');
            $table->string('modelo_embedding', 100)->nullable();
            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id']);
            $table->index('departamento_id');
            $table->index('gabinete_id');
            $table->index('conteudo_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_chunks');
    }
};
