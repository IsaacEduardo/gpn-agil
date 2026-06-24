<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_versaos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_interno_id')->constrained('documento_internos')->cascadeOnDelete();
            $table->integer('versao');
            $table->string('titulo');
            $table->longText('conteudo_final');
            $table->foreignId('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_versaos');
    }
};
