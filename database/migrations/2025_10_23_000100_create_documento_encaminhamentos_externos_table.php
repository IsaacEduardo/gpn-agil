<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_encaminhamentos_externos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_entrada_id')->constrained('documentos_entradas')->onDelete('cascade');
            $table->foreignId('origem_gabinete_id')->nullable()->constrained('gabinetes');
            $table->foreignId('destino_gabinete_id')->constrained('gabinetes');
            $table->foreignId('usuario_id')->nullable()->constrained('users');
            $table->string('oficio_numero', 100)->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->string('observacao', 255)->nullable();
            $table->string('status', 40)->default('enviado');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_encaminhamentos_externos');
    }
};
