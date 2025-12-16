<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gabinetes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('sigla')->nullable();
            $table->foreignId('responsavel_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->index('nome');
            $table->index('sigla');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gabinetes');
    }
};
