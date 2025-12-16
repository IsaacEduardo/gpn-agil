<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_tarefas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_entrada_id')->constrained('documentos_entradas')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->foreignId('assigned_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_departamento_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->timestamp('prazo_at')->nullable();
            $table->string('status')->default('pendente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_tarefas');
    }
};
