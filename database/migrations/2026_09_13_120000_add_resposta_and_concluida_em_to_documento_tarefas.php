<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O ramo 'tecnico' do quickAction escrevia em `resposta` e `concluida_em` —
 * colunas que nunca existiram. Registar o parecer técnico pelo painel de ação
 * rápida rebentava com "Unknown column", e é o último passo do fluxo do
 * documento externo.
 *
 * `concluida_em` também dá à linha do tempo a data real de conclusão, em vez
 * do `updated_at`, que qualquer alteração posterior à tarefa deslocaria.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documento_tarefas', function (Blueprint $table) {
            if (! Schema::hasColumn('documento_tarefas', 'resposta')) {
                $table->text('resposta')
                    ->nullable()
                    ->after('status')
                    ->comment('Parecer ou resposta técnica registada na conclusão.');
            }

            if (! Schema::hasColumn('documento_tarefas', 'concluida_em')) {
                $table->timestamp('concluida_em')
                    ->nullable()
                    ->after('resposta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documento_tarefas', function (Blueprint $table) {
            foreach (['resposta', 'concluida_em'] as $coluna) {
                if (Schema::hasColumn('documento_tarefas', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
