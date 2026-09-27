<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contador de gravações no editor clássico. A sessão colaborativa guarda o valor com que
 * abriu; se entretanto houve uma gravação clássica, o seu HTML está desactualizado e o
 * servidor recusa-o. Não usa versao_atual porque os checkpoints colaborativos também a
 * incrementam e invalidariam os restantes participantes da mesma sessão.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('documento_internos', 'revisao_classica')) {
            Schema::table('documento_internos', function (Blueprint $table) {
                $table->unsignedInteger('revisao_classica')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('documento_internos', 'revisao_classica')) {
            Schema::table('documento_internos', function (Blueprint $table) {
                $table->dropColumn('revisao_classica');
            });
        }
    }
};
