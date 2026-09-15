<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repõe documentos dados por tratados antes de o trabalho estar feito.
 *
 * A marca TRATADO era aposta no momento em que a chefia DELEGAVA a tarefa, e
 * não quando ela era concluída: o documento aparecia como tratado pelo
 * departamento com o técnico ainda a trabalhar nele. A regra passou para a
 * conclusão da última tarefa (ver DocumentoEntradaService::completeTask);
 * esta migração alinha os documentos que ficaram marcados por engano.
 */
return new class extends Migration
{
    public function up(): void
    {
        $afetados = DB::table('documentos_entradas as d')
            ->whereNull('d.deleted_at')
            ->where('d.arquivado', false)
            ->where('d.status', 'tratado')
            ->whereExists(function ($q) {
                $q->selectRaw(1)
                    ->from('documento_tarefas as t')
                    ->whereColumn('t.documento_entrada_id', 'd.id')
                    ->where('t.status', 'pendente');
            })
            ->pluck('d.id');

        if ($afetados->isNotEmpty()) {
            DB::table('documentos_entradas')
                ->whereIn('id', $afetados)
                ->update(['status' => 'recebido']);
        }
    }

    public function down(): void
    {
        // Sem reversão: voltar a marcar estes documentos como tratados repunha
        // exatamente a informação falsa que esta migração corrige — dá-los por
        // concluídos com trabalho ainda por fazer.
    }
};
