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
        Schema::table('documento_tarefas', function (Blueprint $table) {
            $table->uuid('grupo_tarefa_uuid')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documento_tarefas', function (Blueprint $table) {
            $table->dropColumn('grupo_tarefa_uuid');
        });
    }
};
