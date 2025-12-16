<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_especies', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100)->unique();
            $table->boolean('ativo')->default(true);
            $table->unsignedSmallInteger('ordem')->nullable();
            $table->timestamps();

            $table->index('ativo', 'docesp_ativo_idx');
            $table->index('ordem', 'docesp_ordem_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_especies');
    }
};
