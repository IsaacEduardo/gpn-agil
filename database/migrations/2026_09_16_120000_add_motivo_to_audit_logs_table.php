<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A correção de um registo já congelado exige justificação de quem a faz.
 *
 * O audit log já guardava o antes/depois e o autor, mas não o porquê — e é o
 * porquê que distingue a correção de uma gralha de uma alteração indevida a um
 * documento que uma chefia já tratou.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('audit_logs', 'motivo')) {
                $table->text('motivo')->nullable()->after('new_values');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('audit_logs', 'motivo')) {
                $table->dropColumn('motivo');
            }
        });
    }
};
