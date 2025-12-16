<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            // Remover a FK atual (provavelmente "departamentos_gabinete_id_foreign")
            $table->dropForeign(['gabinete_id']);

            // Recriar FK com restrição de exclusão para evitar departamentos órfãos
            $table->foreign('gabinete_id')
                ->references('id')
                ->on('gabinetes')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->dropForeign(['gabinete_id']);

            // Retornar para comportamento anterior (set null) se necessário
            $table->foreign('gabinete_id')
                ->references('id')
                ->on('gabinetes')
                ->onDelete('set null');
        });
    }
};
