<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('documento_protocolos')) {
            // Table already exists (likely created before FK fix). Skip creation.
            return;
        }

        Schema::create('documento_protocolos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_entrada_id')->constrained('documentos_entradas')->onDelete('cascade');
            $table->string('codigo')->unique();
            $table->string('url_consulta')->nullable();
            $table->timestamp('gerado_em');
            $table->timestamp('impresso_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_protocolos');
    }
};
