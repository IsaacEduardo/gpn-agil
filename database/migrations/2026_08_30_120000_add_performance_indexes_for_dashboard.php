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
        // 1. Índices para documentos_entradas
        Schema::table('documentos_entradas', function (Blueprint $table) {
            $table->index(['departamento_id', 'status'], 'idx_docent_dept_status');
            $table->index(['ano_referencia', 'status'], 'idx_docent_ano_status');
            $table->index(['status', 'created_at'], 'idx_docent_status_created');
        });

        // 2. Índices para documento_tarefas
        Schema::table('documento_tarefas', function (Blueprint $table) {
            $table->index(['assigned_to_user_id', 'status'], 'idx_doctar_user_status');
            $table->index(['assigned_to_departamento_id', 'status'], 'idx_doctar_dept_status');
            $table->index(['status', 'prazo_at'], 'idx_doctar_status_prazo');
        });

        // 3. Índices para documento_internos
        Schema::table('documento_internos', function (Blueprint $table) {
            $table->index(['departamento_id', 'status'], 'idx_docint_dept_status');
            $table->index(['criado_por', 'status'], 'idx_docint_autor_status');
            $table->index(['status', 'created_at'], 'idx_docint_status_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentos_entradas', function (Blueprint $table) {
            $table->dropIndex('idx_docent_dept_status');
            $table->dropIndex('idx_docent_ano_status');
            $table->dropIndex('idx_docent_status_created');
        });

        Schema::table('documento_tarefas', function (Blueprint $table) {
            $table->dropIndex('idx_doctar_user_status');
            $table->dropIndex('idx_doctar_dept_status');
            $table->dropIndex('idx_doctar_status_prazo');
        });

        Schema::table('documento_internos', function (Blueprint $table) {
            $table->dropIndex('idx_docint_dept_status');
            $table->dropIndex('idx_docint_autor_status');
            $table->dropIndex('idx_docint_status_created');
        });
    }
};
