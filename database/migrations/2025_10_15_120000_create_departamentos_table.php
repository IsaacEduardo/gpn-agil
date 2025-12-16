<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departamentos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('sigla')->nullable();
            $table->timestamps();
            $table->index('nome');
            $table->index('sigla');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departamentos');
    }
};
