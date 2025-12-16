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
        Schema::create('pastas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->foreignId('departamento_id')->nullable()->constrained('departamentos')->onDelete('cascade');
            $table->foreignId('gabinete_id')->nullable()->constrained('gabinetes')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users'); // Assuming users table
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pastas');
    }
};
