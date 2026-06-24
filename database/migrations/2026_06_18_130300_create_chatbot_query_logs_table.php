<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_query_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->text('pergunta');
            $table->string('escopo', 20)->default('global');
            $table->nullableMorphs('documentable');
            $table->string('modelo_llm', 100)->nullable();
            $table->string('modelo_embedding', 100)->nullable();
            $table->unsignedInteger('latencia_ms')->nullable();
            $table->unsignedInteger('chunks_usados')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_query_logs');
    }
};
