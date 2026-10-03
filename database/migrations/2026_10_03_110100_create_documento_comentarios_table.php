<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comentários da edição colaborativa (nível Comentar). Ancorados ao trecho citado
 * do texto, e não a uma marca dentro do documento: o HTML oficial (PDF, assinatura)
 * fica sem rasto dos comentários.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_interno_id')->constrained('documento_internos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('documento_comentarios')->cascadeOnDelete();
            $table->text('trecho')->nullable();
            $table->text('texto');
            $table->timestamp('resolvido_em')->nullable();
            $table->foreignId('resolvido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['documento_interno_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_comentarios');
    }
};
