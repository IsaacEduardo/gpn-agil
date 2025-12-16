<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documento_tarefas', function (Blueprint $table) {
            $table->foreignId('responsavel_user_id')->nullable()->after('assigned_to_departamento_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documento_tarefas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsavel_user_id');
        });
    }
};
