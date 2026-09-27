<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Emissor "Gabinete": um documento pode ser emitido pelo próprio gabinete (Chefe de Gabinete /
 * Secretário Geral), sem departamento. gabinete_id é o snapshot do gabinete emissor e fica
 * preenchido em todos os documentos; departamento_id passa a ser opcional.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('documento_internos', 'gabinete_id')) {
            Schema::table('documento_internos', function (Blueprint $table) {
                $table->foreignId('gabinete_id')->nullable()->after('departamento_id')
                    ->constrained('gabinetes')->nullOnDelete();
            });
        }

        Schema::table('documento_internos', function (Blueprint $table) {
            $table->unsignedBigInteger('departamento_id')->nullable()->change();
        });

        // Snapshot para os documentos existentes: o gabinete actual do seu departamento.
        DB::statement('UPDATE documento_internos SET gabinete_id = ('
            .'SELECT departamentos.gabinete_id FROM departamentos WHERE departamentos.id = documento_internos.departamento_id'
            .') WHERE gabinete_id IS NULL AND departamento_id IS NOT NULL');
    }

    public function down(): void
    {
        // Documentos emitidos pelo gabinete não têm departamento: não é possível voltar a
        // tornar a coluna obrigatória sem os perder, por isso só se remove o gabinete_id.
        if (Schema::hasColumn('documento_internos', 'gabinete_id')) {
            Schema::table('documento_internos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('gabinete_id');
            });
        }
    }
};
