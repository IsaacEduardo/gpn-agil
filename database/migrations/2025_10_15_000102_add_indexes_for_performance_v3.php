<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIndexesForPerformanceV3 extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }
        $this->ensureIndex('viaturas', 'identificacao', 'viaturas_identificacao_index');
        $this->ensureIndex('viaturas', 'placa', 'viaturas_placa_index');
        $this->ensureIndex('viaturas', 'modelo', 'viaturas_modelo_index');
        $this->ensureIndex('viaturas', 'marca', 'viaturas_marca_index');
        $this->ensureIndex('viaturas', 'ano', 'viaturas_ano_index');
        $this->ensureIndex('viaturas', 'status_operacional', 'viaturas_status_operacional_index');
        $this->ensureIndex('viaturas', 'tipo', 'viaturas_tipo_index');
        $this->ensureIndex('viaturas', 'created_at', 'viaturas_created_at_index');

        $this->ensureIndex('requisicoes', 'tipo', 'requisicoes_tipo_index');
        $this->ensureIndex('requisicoes', 'status', 'requisicoes_status_index');
        $this->ensureIndex('requisicoes', 'data_requisicao', 'requisicoes_data_requisicao_index');
        if (Schema::hasColumn('requisicoes', 'empresa_id')) {
            $this->ensureIndex('requisicoes', 'empresa_id', 'requisicoes_empresa_id_index');
        }
        if (Schema::hasColumn('requisicoes', 'usuario_id')) {
            $this->ensureIndex('requisicoes', 'usuario_id', 'requisicoes_usuario_id_index');
        }
        $this->ensureIndex('requisicoes', 'codigo_sequencial', 'requisicoes_codigo_sequencial_index');
        $this->ensureIndex('requisicoes', 'created_at', 'requisicoes_created_at_index');

        $this->ensureIndex('empresas', 'nome', 'empresas_nome_index');
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }
        $this->dropIndexIfExists('viaturas', 'viaturas_identificacao_index');
        $this->dropIndexIfExists('viaturas', 'viaturas_placa_index');
        $this->dropIndexIfExists('viaturas', 'viaturas_modelo_index');
        $this->dropIndexIfExists('viaturas', 'viaturas_marca_index');
        $this->dropIndexIfExists('viaturas', 'viaturas_ano_index');
        $this->dropIndexIfExists('viaturas', 'viaturas_status_operacional_index');
        $this->dropIndexIfExists('viaturas', 'viaturas_tipo_index');
        $this->dropIndexIfExists('viaturas', 'viaturas_created_at_index');

        $this->dropIndexIfExists('requisicoes', 'requisicoes_tipo_index');
        $this->dropIndexIfExists('requisicoes', 'requisicoes_status_index');
        $this->dropIndexIfExists('requisicoes', 'requisicoes_data_requisicao_index');
        $this->dropIndexIfExists('requisicoes', 'requisicoes_empresa_id_index');
        $this->dropIndexIfExists('requisicoes', 'requisicoes_usuario_id_index');
        $this->dropIndexIfExists('requisicoes', 'requisicoes_codigo_sequencial_index');
        $this->dropIndexIfExists('requisicoes', 'requisicoes_created_at_index');

        $this->dropIndexIfExists('empresas', 'empresas_nome_index');
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
                // ignora duplicatas
            }
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }
        Schema::table($table, function (Blueprint $t) use ($indexName) {
            try {
                $t->dropIndex($indexName);
            } catch (\Throwable $e) {
                // ignora erros
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $rows = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

            return count($rows) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
