<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Duas correções ao registo do percurso do documento de entrada.
 *
 * 1. `encaminhamento_data` era DATE: a hora do encaminhamento perdia-se. A
 *    linha filha, documento_encaminhamentos.encaminhado_em, é DATETIME e guarda
 *    a hora — a cópia desnormalizada no pai era menos precisa do que a fonte, e
 *    dois encaminhamentos do mesmo dia ficavam indistinguíveis na ficha. Em
 *    documentação administrativa a ordem dos atos tem valor probatório.
 *
 * 2. `status_pre_arquivo` passa a guardar o estado imediatamente anterior ao
 *    arquivamento. O desarquivamento repunha 'registrado' — o valor legado do
 *    estado de nascença — fosse qual fosse o percurso já feito, com um
 *    comentário do próprio autor a admitir que não sabia o que repor. Um
 *    documento tratado, ao ser desarquivado, voltava a aparecer na fila de quem
 *    espera despacho.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_entradas', function (Blueprint $table) {
            $table->dateTime('encaminhamento_data')->nullable()->change();

            $table->string('status_pre_arquivo', 40)->nullable()->after('status');
        });

        // Os documentos já arquivados não têm memória do que eram antes. A
        // melhor reconstituição possível a partir dos factos, para que um
        // desarquivamento não os atire para o início do percurso:
        //   - houve conclusão de tarefas e nenhuma pendente  -> tratado
        //   - houve recebimento                              -> recebido
        //   - houve encaminhamento                           -> encaminhado
        //   - nada disso                                     -> pendente_tratamento
        DB::table('documentos_entradas')
            ->where('arquivado', true)
            ->whereNull('status_pre_arquivo')
            ->orderBy('id')
            ->chunkById(500, function ($documentos) {
                foreach ($documentos as $doc) {
                    DB::table('documentos_entradas')
                        ->where('id', $doc->id)
                        ->update(['status_pre_arquivo' => $this->estadoReconstituido((int) $doc->id)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('documentos_entradas', function (Blueprint $table) {
            $table->dropColumn('status_pre_arquivo');

            // A hora volta a perder-se: é o que a coluna DATE comporta.
            $table->date('encaminhamento_data')->nullable()->change();
        });
    }

    private function estadoReconstituido(int $documentoId): string
    {
        $temTarefas = DB::table('documento_tarefas')
            ->where('documento_entrada_id', $documentoId)
            ->exists();

        $temTarefaPendente = DB::table('documento_tarefas')
            ->where('documento_entrada_id', $documentoId)
            ->where('status', 'pendente')
            ->exists();

        if ($temTarefas && ! $temTarefaPendente) {
            return 'tratado';
        }

        $temRecebimento = DB::table('documento_encaminhamentos')
            ->where('documento_entrada_id', $documentoId)
            ->whereNotNull('recebido_em')
            ->exists();

        if ($temRecebimento) {
            return 'recebido';
        }

        $temEncaminhamento = DB::table('documento_encaminhamentos')
            ->where('documento_entrada_id', $documentoId)
            ->exists();

        return $temEncaminhamento ? 'encaminhado' : 'pendente_tratamento';
    }
};
