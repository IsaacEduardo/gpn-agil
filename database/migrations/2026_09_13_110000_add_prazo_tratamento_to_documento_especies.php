<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O SLA usava limiares fixos de 2 e 5 dias para todo o tipo de documento: um
 * ofício urgente e um relatório anual tinham o mesmo prazo.
 *
 * A coluna fica nula por omissão — nesse caso vale o prazo global
 * (config('documentos.prazo_tratamento_dias')), que reproduz exatamente o
 * comportamento anterior. Só as espécies a que for atribuído um prazo passam a
 * ter tratamento próprio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('documento_especies', 'prazo_tratamento_dias')) {
            return;
        }

        Schema::table('documento_especies', function (Blueprint $table) {
            $table->unsignedSmallInteger('prazo_tratamento_dias')
                ->nullable()
                ->after('ordem')
                ->comment('Dias úteis de corrido até o documento desta espécie ficar em incumprimento. Nulo = prazo global.');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('documento_especies', 'prazo_tratamento_dias')) {
            return;
        }

        Schema::table('documento_especies', function (Blueprint $table) {
            $table->dropColumn('prazo_tratamento_dias');
        });
    }
};
