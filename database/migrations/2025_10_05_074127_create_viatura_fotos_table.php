<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('viatura_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viatura_id')->constrained()->onDelete('cascade');
            $table->string('caminho_arquivo');
            $table->enum('tipo', ['foto', 'documento'])->default('foto');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viatura_fotos');
    }
};
