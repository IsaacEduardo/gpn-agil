<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            Schema::dropIfExists('requisicoes');
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } else {
            Schema::dropIfExists('requisicoes');
        }

        // Create new table with proper structure
        Schema::create('requisicoes', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20); // Using string instead of enum
            $table->string('codigo_sequencial')->unique();
            $table->date('data_requisicao');
            $table->unsignedBigInteger('usuario_id');
            $table->string('status', 20)->default('pendente');
            $table->string('empresa_destinataria');
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('users');
            $table->index(['tipo', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requisicoes');
    }
};
