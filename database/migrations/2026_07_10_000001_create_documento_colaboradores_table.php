<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colaboradores de edição em tempo real por Documento Interno.
 * Migração não-destrutiva: apenas cria uma tabela nova (nenhuma coluna existente é alterada).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_colaboradores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_interno_id')->constrained('documento_internos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // visualizar | comentar | editar | administrar (App\Enums\NivelColaboracao)
            $table->string('nivel')->default('editar');
            $table->foreignId('convidado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['documento_interno_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_colaboradores');
    }
};
