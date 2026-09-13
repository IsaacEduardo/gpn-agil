<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A pesquisa da listagem e do autocomplete percorre anexos.texto_extraido com
 * LIKE '%termo%' sobre um LONGTEXT. Não existia qualquer índice de texto no
 * projeto, pelo que cada pesquisa faz varrimento completo da tabela de anexos —
 * degrada linearmente com o volume de OCR acumulado.
 *
 * Guardada por driver, como a migration de índices 2025_12_09_151000: a suite
 * corre em sqlite, que não tem FULLTEXT, e mantém o caminho LIKE.
 */
return new class extends Migration
{
    private const INDEX = 'anexos_texto_extraido_fulltext';

    private function indexExists(): bool
    {
        try {
            return count(DB::select('SHOW INDEX FROM `anexos` WHERE Key_name = ?', [self::INDEX])) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        if (! Schema::hasColumn('anexos', 'texto_extraido') || $this->indexExists()) {
            return;
        }

        try {
            DB::statement('ALTER TABLE `anexos` ADD FULLTEXT INDEX `'.self::INDEX.'` (`texto_extraido`)');
        } catch (Throwable $e) {
            // Sem o índice a pesquisa continua a funcionar por LIKE; não vale a
            // pena falhar o deploy por causa disto.
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql' || ! $this->indexExists()) {
            return;
        }

        try {
            DB::statement('ALTER TABLE `anexos` DROP INDEX `'.self::INDEX.'`');
        } catch (Throwable $e) {
            // ignore
        }
    }
};
