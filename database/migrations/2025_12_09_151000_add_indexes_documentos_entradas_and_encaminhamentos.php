<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

            return count($rows) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function ensureIndex(string $table, string $column, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($column, $indexName) {
            try {
                $t->index($column, $indexName);
            } catch (\Throwable $e) {
                // ignore
            }
        });
    }

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        // documentos_entradas filters
        $this->ensureIndex('documentos_entradas', 'departamento_id', 'doc_ent_departamento_id_index');
        $this->ensureIndex('documentos_entradas', 'data_entrada', 'doc_ent_data_entrada_index');
        $this->ensureIndex('documentos_entradas', 'ano_referencia', 'doc_ent_ano_referencia_index');
        $this->ensureIndex('documentos_entradas', 'status', 'doc_ent_status_index');
        $this->ensureIndex('documentos_entradas', 'visto_departamento_status', 'doc_ent_visto_dep_status_index');
        $this->ensureIndex('documentos_entradas', 'visto_gabinete_status', 'doc_ent_visto_gab_status_index');

        // documento_encaminhamentos relations and sorting
        $this->ensureIndex('documento_encaminhamentos', 'documento_entrada_id', 'doc_enc_doc_entrada_id_index');
        $this->ensureIndex('documento_encaminhamentos', 'origem_departamento_id', 'doc_enc_origem_dep_id_index');
        $this->ensureIndex('documento_encaminhamentos', 'destino_departamento_id', 'doc_enc_destino_dep_id_index');
        $this->ensureIndex('documento_encaminhamentos', 'encaminhado_em', 'doc_enc_encaminhado_em_index');
        $this->ensureIndex('documento_encaminhamentos', 'recebido_em', 'doc_enc_recebido_em_index');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }
        $drop = function (string $table, string $indexName) {
            if (! $this->indexExists($table, $indexName)) {
                return;
            }
            Schema::table($table, function (Blueprint $t) use ($indexName) {
                try {
                    $t->dropIndex($indexName);
                } catch (\Throwable $e) {
                    // ignore
                }
            });
        };

        $drop('documentos_entradas', 'doc_ent_departamento_id_index');
        $drop('documentos_entradas', 'doc_ent_data_entrada_index');
        $drop('documentos_entradas', 'doc_ent_ano_referencia_index');
        $drop('documentos_entradas', 'doc_ent_status_index');
        $drop('documentos_entradas', 'doc_ent_visto_dep_status_index');
        $drop('documentos_entradas', 'doc_ent_visto_gab_status_index');

        $drop('documento_encaminhamentos', 'doc_enc_doc_entrada_id_index');
        $drop('documento_encaminhamentos', 'doc_enc_origem_dep_id_index');
        $drop('documento_encaminhamentos', 'doc_enc_destino_dep_id_index');
        $drop('documento_encaminhamentos', 'doc_enc_encaminhado_em_index');
        $drop('documento_encaminhamentos', 'doc_enc_recebido_em_index');
    }
};
