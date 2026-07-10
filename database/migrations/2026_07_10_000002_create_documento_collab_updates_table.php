<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log durável de atualizações Yjs (CRDT) por Documento Interno.
 *
 * Durante uma sessão colaborativa os deltas são trocados entre pares por WebSocket (baixa latência);
 * este log garante durabilidade/recuperação e o restabelecimento do estado inicial. As linhas com
 * is_snapshot=true representam um estado completo compactado (Y.encodeStateAsUpdate) que substitui
 * o histórico anterior no checkpoint, limitando o crescimento da tabela.
 *
 * Migração não-destrutiva: apenas cria uma tabela nova.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_collab_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_interno_id')->constrained('documento_internos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Update Yjs binário codificado em base64.
            $table->longText('update');
            $table->boolean('is_snapshot')->default(false);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_collab_updates');
    }
};
