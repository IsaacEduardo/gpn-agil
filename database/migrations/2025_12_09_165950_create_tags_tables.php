<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('documento_entrada_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_entrada_id')->constrained('documentos_entradas')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['documento_entrada_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_entrada_tag');
        Schema::dropIfExists('tags');
    }
};
