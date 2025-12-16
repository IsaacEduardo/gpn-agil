<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anexos', function (Blueprint $table) {
            $table->id();
            $table->string('anexavel_type');
            $table->unsignedBigInteger('anexavel_id');

            $table->string('nome_original', 255);
            $table->string('caminho_arquivo', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('tamanho_bytes')->nullable();
            $table->string('descricao', 255)->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users');

            $table->softDeletes();
            $table->timestamps();

            $table->index(['anexavel_type', 'anexavel_id'], 'anexos_anexavel_idx');
            $table->index('ordem');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anexos');
    }
};
