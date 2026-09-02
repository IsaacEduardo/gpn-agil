<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Criar tabela de catálogo de procedências
        if (! Schema::hasTable('procedencias')) {
            Schema::create('procedencias', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 255)->unique();
                $table->boolean('ativo')->default(true);
                $table->timestamps();
            });
        }

        // 2. Extrair os valores distintos da coluna procedencia atual e inseri-los na tabela procedencias
        if (Schema::hasTable('documentos_entradas')) {
            $procedenciasDistintas = DB::table('documentos_entradas')
                ->whereNotNull('procedencia')
                ->where('procedencia', '!=', '')
                ->select(DB::raw('TRIM(procedencia) as nome'))
                ->distinct()
                ->pluck('nome');

            foreach ($procedenciasDistintas as $nome) {
                $nome = trim($nome);
                if (! empty($nome)) {
                    DB::table('procedencias')->updateOrInsert(
                        ['nome' => $nome],
                        [
                            'ativo' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }

        // 3. Adicionar coluna procedencia_id em documentos_entradas
        if (Schema::hasTable('documentos_entradas') && ! Schema::hasColumn('documentos_entradas', 'procedencia_id')) {
            Schema::table('documentos_entradas', function (Blueprint $table) {
                $table->foreignId('procedencia_id')
                    ->nullable()
                    ->after('procedencia')
                    ->constrained('procedencias')
                    ->nullOnDelete();
            });

            // 4. Mapear procedencia_id para os registros existentes
            $catalogo = DB::table('procedencias')->pluck('id', 'nome');
            foreach ($catalogo as $nome => $id) {
                DB::table('documentos_entradas')
                    ->whereRaw('TRIM(procedencia) = ?', [$nome])
                    ->update(['procedencia_id' => $id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('documentos_entradas') && Schema::hasColumn('documentos_entradas', 'procedencia_id')) {
            Schema::table('documentos_entradas', function (Blueprint $table) {
                $table->dropForeign(['procedencia_id']);
                $table->dropColumn('procedencia_id');
            });
        }

        Schema::dropIfExists('procedencias');
    }
};
