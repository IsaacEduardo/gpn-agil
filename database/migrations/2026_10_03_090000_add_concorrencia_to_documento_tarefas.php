<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delegação em concorrência: a mesma tarefa é oferecida a vários técnicos e o
 * primeiro a assumi-la fica com ela.
 *
 * - modo_grupo: 'todos' (cada técnico executa a sua, o comportamento de sempre)
 *   ou 'concorrencia'. Nulo nas tarefas individuais e nas anteriores a isto.
 * - assumida_em: quando o técnico assumiu a tarefa. Serve também ao desempenho
 *   (tempo até assumir, e resolução contada a partir daqui).
 *
 * O índice em grupo_tarefa_uuid serve o bloqueio das linhas do grupo ao assumir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documento_tarefas', function (Blueprint $table) {
            if (! Schema::hasColumn('documento_tarefas', 'modo_grupo')) {
                $table->string('modo_grupo', 20)->nullable()->after('grupo_tarefa_uuid');
            }
            if (! Schema::hasColumn('documento_tarefas', 'assumida_em')) {
                $table->timestamp('assumida_em')->nullable()->after('modo_grupo');
            }
            $table->index('grupo_tarefa_uuid', 'documento_tarefas_grupo_idx');
        });
    }

    public function down(): void
    {
        Schema::table('documento_tarefas', function (Blueprint $table) {
            $table->dropIndex('documento_tarefas_grupo_idx');
            foreach (['assumida_em', 'modo_grupo'] as $coluna) {
                if (Schema::hasColumn('documento_tarefas', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
