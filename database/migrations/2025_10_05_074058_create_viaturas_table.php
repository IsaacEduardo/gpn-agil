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
        Schema::create('viaturas', function (Blueprint $table) {
            $table->id();
            $table->string('identificacao')->unique();
            $table->string('placa')->unique();
            $table->string('modelo');
            $table->string('marca');
            $table->integer('ano');
            $table->enum('status_operacional', ['Disponível', 'Em manutenção', 'Inoperante'])->default('Disponível');
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viaturas');
    }
};
