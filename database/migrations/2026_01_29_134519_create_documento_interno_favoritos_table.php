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
        Schema::create('documento_interno_favoritos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('documento_interno_id')->constrained('documento_internos')->onDelete('cascade');
            $table->timestamps();

            // Ensure unique pair
            $table->unique(['user_id', 'documento_interno_id'], 'user_doc_fav_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento_interno_favoritos');
    }
};
