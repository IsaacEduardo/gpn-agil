<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices de apoio ao módulo de Relatórios & BI.
 *
 * As agregações do ReportService filtram por período e agrupam por estado,
 * departamento e datas de fecho. Parte destes índices já existe (ver
 * create_documentos_entradas_table e add_performance_indexes_for_dashboard);
 * por isso cada índice só é criado se ainda não houver um com o mesmo nome
 * nem um que já o cubra — um índice sobre (a, b) serve as consultas por (a),
 * pelo que criar (a) ao lado dele seria peso morto na escrita. A migração
 * pode assim correr sobre bases em estados diferentes sem rebentar.
 */
return new class extends Migration
{
    /**
     * Índices pretendidos: tabela => [nome => colunas].
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private array $indices = [
        'documentos_entradas' => [
            'idx_docent_data_dept' => ['data_entrada', 'departamento_id'],
            'idx_docent_data_status' => ['data_entrada', 'status'],
            'idx_docent_arquivado_em' => ['arquivado_em'],
            'idx_docent_created_at' => ['created_at'],
        ],
        'documento_internos' => [
            'idx_docint_created_dept' => ['created_at', 'departamento_id'],
            'idx_docint_assinado_em' => ['assinado_em'],
            'idx_docint_criado_por' => ['criado_por'],
        ],
        'documento_encaminhamentos' => [
            'idx_docenc_encaminhado_em' => ['encaminhado_em'],
            'idx_docenc_recebido_em' => ['recebido_em'],
            'idx_docenc_status' => ['status'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indices as $tabela => $indices) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }

            $existentes = $this->indicesExistentes($tabela);

            foreach ($indices as $nome => $colunas) {
                if (! $this->colunasExistem($tabela, $colunas)) {
                    continue;
                }

                if (isset($existentes['nomes'][$nome]) || $this->jaCoberto($existentes['colunas'], $colunas)) {
                    continue;
                }

                Schema::table($tabela, function (Blueprint $table) use ($colunas, $nome) {
                    $table->index($colunas, $nome);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indices as $tabela => $indices) {
            if (! Schema::hasTable($tabela)) {
                continue;
            }

            $existentes = $this->indicesExistentes($tabela);

            foreach (array_keys($indices) as $nome) {
                if (! isset($existentes['nomes'][$nome])) {
                    continue;
                }

                Schema::table($tabela, function (Blueprint $table) use ($nome) {
                    $table->dropIndex($nome);
                });
            }
        }
    }

    /**
     * Índices já presentes na tabela, por nome e por conjunto de colunas.
     *
     * @return array{nomes: array<string, true>, colunas: array<int, array<int, string>>}
     */
    private function indicesExistentes(string $tabela): array
    {
        $nomes = [];
        $colunas = [];

        foreach (Schema::getIndexes($tabela) as $indice) {
            $nomes[$indice['name']] = true;
            $colunas[] = array_map('strtolower', $indice['columns']);
        }

        return ['nomes' => $nomes, 'colunas' => $colunas];
    }

    /**
     * Um índice já existente cobre as colunas pedidas quando estas são um
     * prefixo da sua lista de colunas.
     *
     * @param  array<int, array<int, string>>  $existentes
     * @param  array<int, string>  $colunas
     */
    private function jaCoberto(array $existentes, array $colunas): bool
    {
        $pretendido = array_map('strtolower', $colunas);

        foreach ($existentes as $indice) {
            if (array_slice($indice, 0, count($pretendido)) === $pretendido) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $colunas
     */
    private function colunasExistem(string $tabela, array $colunas): bool
    {
        foreach ($colunas as $coluna) {
            if (! Schema::hasColumn($tabela, $coluna)) {
                return false;
            }
        }

        return true;
    }
};
